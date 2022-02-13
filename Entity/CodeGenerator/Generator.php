<?php

/*
 * This file is part of the VV package.
 *
 * (c) Volodymyr Sarnytskyi <v00v4n@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace VV\Entity\CodeGenerator;

use VV\Db;
use VV\Db\Model\Column;
use VV\Db\Model\Table;
use VV\Db\Model\TableList;
use VV\Entity\Entity;
use VV\Entity\OrmAttribute;
use VV\Utils\Fs;

use function VV\camelCase;
use function VV\StudlyCaps;

final class Generator
{
    public const DFLT_ROOT_NS = 'App\Domain';

    private Db $db;
    private string $sourceRoot;
    private string $rootNamespace;

    private ?string $relativeNamespace = null;
    private ?string $namespace = null;
    private ?string $className = null;
    private ?string $entityName = null;

    private ?string $tableCamelName = null;
    private ?Table $table = null;

    private bool $reaskMappedRelations = false;

    private ?string $fileHeader = null;
    /** @var FieldInfo[] */
    private ?array $fieldsInfo = null;
    private bool $interactiveMode = false;
    private array $nextGenerations = [];
    private ?array $tableEntityMap = null;

    /**
     * CodeGenerator constructor.
     *
     * @param Db      $db
     * @param string      $sourceRoot Path to classes root dir
     * @param string|null $rootNamespace
     */
    public function __construct(Db $db, string $sourceRoot, string $rootNamespace = null)
    {
        $this->db = $db;
        $this->sourceRoot = $sourceRoot;
        $this->rootNamespace = $rootNamespace ?? self::DFLT_ROOT_NS;
    }

    /**
     * @return Db
     */
    public function getDb(): Db
    {
        return $this->db;
    }

    /**
     * @return string
     */
    public function getRelativeNamespace(): string
    {
        assert((bool)$this->relativeNamespace);

        return $this->relativeNamespace;
    }

    /**
     * @return string
     */
    public function getNamespace(): string
    {
        assert((bool)$this->namespace);

        return $this->namespace;
    }

    /**
     * @param string $relativeNamespace
     *
     * @return $this
     */
    public function setRelativeNamespace(string $relativeNamespace): self
    {
        if (!preg_match('/^([a-z]\w*\\\\)*([a-z]\w*)$/i', $relativeNamespace, $matches)) {
            throw new \RuntimeException('Invalid namespace');
        }

        $this->relativeNamespace = $relativeNamespace;
        $this->namespace = $this->rootNamespace . '\\' . $relativeNamespace;
        $this->className = $matches[2];
        $this->entityName = $matches[2];
        $this->fieldsInfo = $this->fileHeader = null;

        return $this;
    }

    public function getClassName(): string
    {
        assert((bool)$this->className);

        return $this->className;
    }

    /**
     * @param string $className
     *
     * @return $this
     */
    public function setClassName(string $className): self
    {
        if (!preg_match('/^([a-z]\w*)$/i', $className)) {
            throw new \RuntimeException('Invalid Class Name');
        }
        $this->className = $className;

        return $this;
    }

    public function getEntityName(): string
    {
        assert((bool)$this->entityName);

        return $this->entityName;
    }

    /**
     * @param string $entityName
     *
     * @return $this
     */
    public function setEntityName(string $entityName): self
    {
        if (!preg_match('/^([a-z]\w*)$/i', $entityName)) {
            throw new \RuntimeException('Invalid Entity Name');
        }
        $this->entityName = $entityName;

        return $this;
    }

    /**
     * @return string
     */
    public function getTableCamelName(): string
    {
        assert((bool)$this->tableCamelName);

        return $this->tableCamelName;
    }

    /**
     * @param string $name
     *
     * @return $this
     */
    public function setTableName(string $name): self
    {
        $camelName = camelCase(Table::trimPrefix($name));
        /** @var Table $table */
        $this->table = $this->tables()->getByCamelName($camelName);
        if (!$this->table) {
            throw new \RuntimeException("Table '$name' not foud");
        }

        $this->tableCamelName = $camelName;

        return $this;
    }

    /**
     * @return Table
     */
    public function getTable(): Table
    {
        assert($this->table);

        return $this->table;
    }

    /**
     * @param Table $table
     *
     * @return $this
     */
    public function setTable(Table $table): self
    {
        $this->table = $table;
        $this->tableCamelName = camelCase(Table::trimPrefix($table->getName()));

        return $this;
    }

    /**
     * @param bool $reask
     *
     * @return $this
     */
    public function setReaskMappedRelations(bool $reask = true): self
    {
        $this->reaskMappedRelations = $reask;

        return $this;
    }

    /**
     * @return string
     */
    public function getFileHeader(): string
    {
        if (!$this->fileHeader) {
            $dt = date('Y-m-d H:i');
            $this->fileHeader =
                <<<HDR
                <?php

                /** Created by VV Entity Generator $dt */

                declare(strict_types=1);

                namespace {$this->getNamespace()};
                HDR;
        }

        return $this->fileHeader;
    }

    /**
     * @param string $fileHeader
     *
     * @return $this
     */
    public function setFileHeader(string $fileHeader): self
    {
        $this->fileHeader = $fileHeader;

        return $this;
    }

    /**
     * Runs code generation from console in interactive mode
     *
     * @param array $argv
     */
    public function runInteractConsole(array $argv): void
    {
        $this->interactiveMode = true;

        set_time_limit(600);
        [, $relativeNamespace, $className, $entityName, $tableCamelName] = $argv + array_fill(0, 5, null);

        $prevArgs = [];
        $prevArgsFile = Fs::storeDir() . 'gen-Entity-last-args.json';
        if (file_exists($prevArgsFile)) {
            $prevArgs = json_decode(file_get_contents($prevArgsFile), true);
        }

        // namespace
        $dfltRelativeNamespace = $prevArgs['relativeNamespace'] ?? null;
        $relativeNamespace = $relativeNamespace
            ?: \VV\readline(
                "Entity Namespace relative to `$this->rootNamespace\\`",
                $dfltRelativeNamespace
            );
        $this->setRelativeNamespace($relativeNamespace);
        $nsChanged = $relativeNamespace != $dfltRelativeNamespace;

        // class name
        $dfltClassName = $nsChanged ? $this->getClassName() : ($prevArgs['className'] ?? null);
        $className = $className
            ?: \VV\readline(
                "Entity Class Name relative to `{$this->getNamespace()}\\`",
                $dfltClassName
            );
        $this->setClassName($className);

        // entity name
        $dfltEntityName = $nsChanged ? $this->getEntityName() : ($prevArgs['entityName'] ?? null);
        $entityName = $entityName
            ?: \VV\readline(
                "Entity Name (for methods like create[EntityName]())",
                $dfltEntityName
            );
        $this->setEntityName($entityName);

        // table name
        if (!$tableCamelName) {
            $dfltTableCamelName = $relativeNamespace == $dfltRelativeNamespace
                ? ($prevArgs['tableCamelName'] ?? null)
                : null;
            if (!$dfltTableCamelName) {
                $dfltTableCamelName = $this->calcTableNameFromFqcn($relativeNamespace);
            }

            $tableCamelName = \VV\readline('Table name', $dfltTableCamelName);
            if (!$this->tables()->getByCamelName($tableCamelName)) {
                throw new \RuntimeException("Table $tableCamelName not found");
            }
        }

        file_put_contents(
            $prevArgsFile,
            json_encode([
                'relativeNamespace' => $relativeNamespace,
                'className' => $className,
                'entityName' => $entityName,
                'tableCamelName' => $tableCamelName,
            ])
        );

        $this->setTableName($tableCamelName);

        $this->addTableEntityMapItem(
            $this->getTable()->getName(),
            $this->getRelativeNamespace(),
            $this->getClassName(),
        );

        $this->run();

        while ([$relativeNs, $className, $entityName, $tableName] = array_shift($this->nextGenerations)) {
            echo "\n";

            $res = \VV\readline("Generate entity `$relativeNs\\$className`?", 'y', ['y', 'n']);
            if ($res != 'y') {
                continue;
            }

            $this
                ->setRelativeNamespace($relativeNs)
                ->setClassName($className)
                ->setEntityName($entityName)
                ->setTableName($tableName)
                ->run();
        }
    }

    public function run()
    {
        $className = $this->getClassName();

        $this->runByTemplateMap([
            'entity' => [$className, $this->generateEntity()],
            'data' => [$className . 'Data', $this->generateData()],
            'repo' => [$className . 'Repo', $this->generateRepo()],
        ]);
    }

    /**
     * @return FieldInfo[]
     */
    public function getFieldsInfo(): array
    {
        if ($this->fieldsInfo) {
            return $this->fieldsInfo;
        }

        $tbl = $this->getTable();

        $fieldsInfo = [];
        $tableEntityMap = &$this->getTableEntityMap();

        $headingShown = false;
        foreach ($tbl->getColumns() as $dbField) {
            $oFieldName = $fieldName = $dbField->getName();
            if ($fieldName == $tbl->getPk()) {
                $fieldName = 'id';
            }

            $entityRelativeNs = null;
            $entityNs = null;
            $entityClassName = null;
            $foreignKey = $tbl->getForeignKeys()->getFromColumns([$fieldName]);
            if ($foreignKey) {
                $rootNs = $this->rootNamespace;
                $fieldNameWoId = (string)preg_replace('/_id$/i', '', $fieldName);

                $toTable = $foreignKey->getToTable();
                [$subNsFromEntityMap, $classNameFromEntityMap] = $tableEntityMap[$toTable] ?? null;

                if ($subNsFromEntityMap && !$this->reaskMappedRelations) {
                    $tmpEntityRelativeNs = $subNsFromEntityMap;
                    $entityClassName = $classNameFromEntityMap;
                    $fieldName = $fieldNameWoId;
                } else {
                    if ($this->interactiveMode && !$headingShown) {
                        $headingShown = true;
                        echo "\nSet FQCN for fields:\n";
                        if ($this->reaskMappedRelations) {
                            echo "  ('*' - related entity already exists)\n";
                        }
                        echo "  (type '-' to skip relation mapping for field)\n\n";
                    }

                    $classNameByField = StudlyCaps($fieldNameWoId);
                    $dfltRelativeNs = $subNsFromEntityMap ?: "{$this->getRelativeNamespace()}\\$classNameByField";

                    $outpfx = $subNsFromEntityMap ? '*' : ' ';
                    $heading = "$outpfx {$tbl->getName()}.$oFieldName:\n";
                    if ($this->interactiveMode) {
                        echo $heading;
                    }

                    $tmpEntityRelativeNs = $this->askInteractiveOrDflt("    namespace $rootNs\\", $dfltRelativeNs);
                    if ($tmpEntityRelativeNs == '-') {
                        $tmpEntityRelativeNs = null;
                    } else {
                        $fieldName = $fieldNameWoId;

                        if (!preg_match('/^([a-z]\w*\\\\)*([a-z]\w*)$/i', $tmpEntityRelativeNs, $matches)) {
                            throw new \RuntimeException('Invalid namespace');
                        }

                        $dfltClassName = $dfltEntityName = $matches[2];

                        $dfltClassName = $classNameFromEntityMap ?: $classNameByField;
                        $entityClassName = $this->askInteractiveOrDflt(
                            "    classname $rootNs\\$tmpEntityRelativeNs\\",
                            $dfltClassName
                        );
                        $entityName = $this->askInteractiveOrDflt(
                            "    entityname",
                            $dfltEntityName
                        );

                        if (!$subNsFromEntityMap) {
                            $this->addTableEntityMapItem(
                                $toTable,
                                $tmpEntityRelativeNs,
                                $entityClassName
                            );

                            $this->nextGenerations[] = [
                                $tmpEntityRelativeNs,
                                $entityClassName,
                                $entityName,
                                $toTable
                            ];
                        }
                    }
                }

                if ($tmpEntityRelativeNs) {
                    $entityNs = "$rootNs\\$tmpEntityRelativeNs";

                    $relStart = $this->getRelativeNamespace() . '\\';
                    if (str_starts_with($tmpEntityRelativeNs, $relStart)) {
                        // relative domain root
                        $entityRelativeNs = substr($tmpEntityRelativeNs, strlen($relStart));
                    } else {
                        // absolute entityRelativeNs
                        $entityRelativeNs = "\\$entityNs";
                    }
                }
            }

            $fi = new FieldInfo();
            $fi->dbField = $dbField;
            $fi->name = $fieldName;
            $fi->camel = camelCase($fieldName);
            $fi->studly = StudlyCaps($fieldName);
            $fi->const = 'F_' . strtoupper($fieldName);
            $fi->entityNs = $entityNs;
            $fi->entityRelativeNs = $entityRelativeNs;
            $fi->entityClassName = $entityClassName;

            $fi->type = $this->calcFieldInfoPhpType($fi);

            $fieldsInfo[] = $fi;
        }

        return $this->fieldsInfo = $fieldsInfo;
    }

    protected function generateEntity(): string
    {
        return (new EntityGenerator($this))->generateEntity();
    }

    protected function generateData(): string
    {
        return (new DataGenerator($this))->generateData();
    }

    protected function generateRepo(): string
    {
        return (new RepoGenerator($this))->generateRepo();
    }

    protected function calcTableNameFromFqcn(string $relfqcn): string
    {
        $fqcn2tbl = fn (string $fqcn): string => lcfirst(str_replace('\\', '', $fqcn));
        $dflt = $fqcn2tbl($relfqcn);

        while ($relfqcn) {
            $name = $fqcn2tbl($relfqcn);
            if ($this->tables()->getByCamelName($name)) {
                return $name;
            }
            $relfqcn = preg_replace('/^\w+(\\\\|$)/', '', $relfqcn);
        }

        return $dflt;
    }

    /**
     * @return array
     */
    protected function &getTableEntityMap(): array
    {
        $map = &$this->tableEntityMap;
        if ($map !== null) {
            return $map;
        }

        $map = [];
        $ds = \DIRECTORY_SEPARATOR;
        $domainRootDir = Fs::path($this->sourceRoot . $ds . $this->rootNamespace, true);
        $directory = new \RecursiveDirectoryIterator($domainRootDir);
        $iterator = new \RecursiveIteratorIterator($directory);

        $quotedBase = preg_quote(Fs::path($this->rootNamespace, true) . $ds, '//');
        $rxds = '[\\\\|\/]';
        $rx = "/({$quotedBase}(.*)$rxds(\w+)).php\$/i";
        $regex = new \RegexIterator(
            $iterator,
            $rx,
            \RegexIterator::GET_MATCH
        );
        echo "\nScan existing relations...\n";
        foreach ($regex as $file => [, $fqcn, $entitySubNs, $className]) {
            $entitySubNs = str_replace('/', '\\', $entitySubNs);
            $fqcn = str_replace('/', '\\', $fqcn);
            try {
                $reflectionClass = new \ReflectionClass($fqcn);
            } catch (\ReflectionException) {
                continue;
            }
            if (!$reflectionClass->isSubclassOf(Entity::class)) {
                continue;
            }

            $attributes = $reflectionClass->getAttributes(OrmAttribute::class);
            if (!$cnt = count($attributes)) {
                continue;
            }
            if ($cnt > 1) {
                throw new \RuntimeException('Too many attributes');
            }

            $ormAttr = $attributes[0]->newInstance();
            assert($ormAttr instanceof OrmAttribute);

            $tableName = $ormAttr->tableName;

            echo "  $tableName => $fqcn";
            if (isset($map[$tableName])) {
                echo "\n    warn! already exists";
            } else {
                $map[$tableName] = [$entitySubNs, $className];
            }
            echo "\n";
        }

        return $map;
    }

    protected function addTableEntityMapItem(string $tableName, string $entityRelativeNs, $className): self
    {
        $thisTableEntityMap = &$this->getTableEntityMap();

        $thisTableEntityMap[$tableName] = [$entityRelativeNs, $className];
        echo "\n+ {$tableName} => {$entityRelativeNs}\n\n";

        return $this;
    }

    /**
     * @param array $map
     */
    protected function runByTemplateMap(array $map)
    {
        $ns = $this->getNamespace();
        $origDir = $dir = Fs::relPath($this->sourceRoot . '/' . $ns, PTH_ROOT);
        $i = 1;


        $exists = function () use ($map, &$dir) {
            if (!file_exists(PTH_ROOT . $dir)) {
                return false;
            }
            foreach ($map as [$class]) {
                if (file_exists(PTH_ROOT . "$dir/$class.php")) {
                    return true;
                }
            }

            return false;
        };

        while ($exists()) {
            $dir = $origDir . '-' . ($i++);
        }
        @mkdir(PTH_ROOT . $dir, 0777, true);


        echo "\n";
        if ($dir != $origDir) {
            echo "warn! original dir ($origDir) already exists\n";
            echo "new dir created: {$dir}\n\n";
        }

        foreach ($map as [$class, $content]) {
            $file = "{$dir}/{$class}.php";
            file_put_contents(PTH_ROOT . $file, $content);
            echo "File '$file' created\n";
        }
    }

    protected function calcFieldInfoPhpType(FieldInfo $fi): string
    {
        $field = $fi->dbField;
        $type = $field->getType();
        if ($fi->entityClassName) {
            return "$fi->entityNs\\$fi->entityClassName";
        }

        if ($type === Column::T_NUM) {
            if ($field->getScale()) {
                return 'float';
            }
        }

        if ($type === Column::T_INT) {
            if (($size = $field->getIntSize()) && $size <= PHP_INT_SIZE) {
                return 'int';
            }
            if ($field->getPrecision() <= 9) { // int32
                return 'int';
            }
            if (PHP_INT_SIZE == 8 && $field->getPrecision() <= 18) {
                return 'int';
            }
        }

        if ($type === Column::T_BOOL) {
            return 'bool';
        }

        if ($field->hasDate() || $field->hasTime()) {
            return '\DateTimeInterface';
        }

        return 'string';
    }

    protected function askInteractiveOrDflt(string $prompt, string $dflt = null, array $allowed = null): ?string
    {
        if (!$this->interactiveMode) {
            return $dflt;
        }

        return \VV\readline($prompt, $dflt, $allowed);
    }

    private function tables(): TableList
    {
        return $this->db->tables();
    }
}
