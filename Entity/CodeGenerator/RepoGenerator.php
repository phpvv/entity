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

class RepoGenerator
{
    public function __construct(protected Generator $generator)
    {
    }

    /**
     * @return string
     */
    public function generateRepo(): string
    {
        $generator = $this->generator;
        $className = $generator->getClassName();
        $entityName = $generator->getEntityName();
        $dbInstanceCode = '\\' . get_class($generator->getDb()) . '::instance()';

        $withMethods = '';
        $imports = new Imports();
        $imports->add('VV\\Db\\Model\\Table');
        $imports->add('VV\\Entity\\DbRepo');

        foreach ($generator->getFieldsInfo() as $fi) {
            if (!$entityNs = $fi->entityNs) {
                continue;
            }

            $repoClassName = "{$fi->entityClassName}Repo";
            $isOld = !class_exists("$entityNs\\$repoClassName") && class_exists("$entityNs\\Repo");

            if ($isOld) {
                $repoClass = $imports->add('Repo', $entityNs, $repoClassName);
            } else {
                $repoClass = $imports->add($repoClassName, $entityNs);
            }

            $withMethods .= <<<CODE

                public function with{$fi->studly}($repoClass \$repo = null): static
                {
                    return \$this->with({$className}Data::$fi->const, \$repo ?: $repoClass::create());
                }

            CODE;
        }

        return <<<CODE
            {$generator->getFileHeader()}
            {$imports->toString()}

            class {$className}Repo extends DbRepo
            {
                public function initEntity(string|int|array \$id): $className
                {
                    return $className::init(\$id, \$this);
                }
            $withMethods
                public function find{$entityName}ById(string|int \$id): ?$className
                {
                    return \$this->findById(\$id);
                }

                protected function initTable(): Table
                {
                    return {$dbInstanceCode}->tables()->{$generator->getTableCamelName()};
                }

                protected function queryColumns(): array
                {
                    return {$className}Data::getFieldNames();
                }

                public static function create(): static
                {
                    return new static();
                }
            }

            CODE;
    }
}
