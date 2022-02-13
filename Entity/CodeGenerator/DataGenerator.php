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

class DataGenerator
{
    public function __construct(protected Generator $generator)
    {
    }

    /**
     * @return string
     */
    public function generateData(): string
    {
        $generator = $this->generator;
        $className = $generator->getClassName();

        $imports = new Imports();
        $imports->add('VV\Entity\Data');
        $imports->add('VV\Entity\Field');
        $imports->add('VV\Entity\Repo');

        $bottom = rtrim("{$this->generateDataGetters()}\n{$this->generateDataCasts($imports)}");

        return <<<CODE
            {$generator->getFileHeader()}
            {$imports->toString()}

            class {$className}Data extends Data
            {
                public const {$this->generateFieldConstants()};

                public const FIELD_NAMES = [
                    self::{$this->generateFieldNames()}
                ];

                private ?{$className}Repo \$repo;

                public function getRepo(): ?{$className}Repo
                {
                    return \$this->repo;
                }

                public function setRepo(?Repo \$repo): static
                {
                    \$this->repo = \$repo;
            
                    return \$this;
                }
            $bottom
            }

            CODE;
    }

    /**
     * @return string
     */
    protected function generateFieldConstants(): string
    {
        $fieldConstants = '';
        foreach ($this->generator->getFieldsInfo() as $fi) {
            if ($fieldConstants) {
                $fieldConstants .= "        ";
            }

            $fieldConstants .= "$fi->const = '{$fi->dbField->getName()}',\n";
        }

        return substr($fieldConstants, 0, -2);
    }

    /**
     * @return string
     */
    protected function generateFieldNames(): string
    {
        $constants = [];
        foreach ($this->generator->getFieldsInfo() as $fi) {
            $constants[] = $fi->const;
        }

        return implode(",\n        self::", $constants) . ',';
    }

    /**
     * @return string
     */
    protected function generateDataGetters(): string
    {
        $getters = '';
        foreach ($this->generator->getFieldsInfo() as $fi) {
            if ($fi->name == 'id') {
                continue;
            }

            $getters .=
                <<<FUNC

                    public function {$fi->camel}Field(): Field
                    {
                        return \$this->getField(static::$fi->const);
                    }

                FUNC;
        }

        return $getters;
    }

    /**
     * @param Imports $imports
     *
     * @return string
     */
    protected function generateDataCasts(Imports $imports): string
    {
        $generator = $this->generator;
        $className = $generator->getClassName();

        $castsGroups = [];
        foreach ($generator->getFieldsInfo() as $fi) {
            $type = $fi->type;
            $group = &$castsGroups[$type];

            if ($entityNs = $fi->entityNs) {
                if (!$group) {
                    if ($entityNs == '\\' . $generator->getNamespace()) {
                        $repoClass = $className . 'Repo';
                    } else {
                        $repoClassName = "{$fi->entityClassName}Repo";
                        $isOld = !class_exists("$entityNs\\$repoClassName")
                                 && class_exists("$entityNs\\Repo");

                        if ($isOld) {
                            $repoClass = $imports->add('Repo', $entityNs, $repoClassName);
                        } else {
                            $repoClass = $imports->add($repoClassName, $entityNs);
                        }
                    }

                    $code = "                    return $repoClass::create();";
                    $group = [$code, []];
                }
                $group[1][] = $fi->const;
            }

            if ($type == '\DateTimeInterface') {
                if (!$group) {
                    $code = "                    /** @noinspection PhpUnhandledExceptionInspection */\n" .
                            '                    return new \\DateTimeImmutable($scalar);';
                    $group = [$code, []];
                }
                $group[1][] = $fi->const;
            }

            if ($generator->getDb()->getConnection()->isOracle()) {
                // Oracle always returns strings
                if (in_array($type, ['int', 'float', 'bool'])) {
                    if (!$group) {
                        $code = "                    return ($type)\$scalar;";
                        $group = [$code, []];
                    }
                    $group[1][] = $fi->const;
                }
            }
        }

        $cases = [];
        foreach ($castsGroups as [$code, $constants]) {
            if (!$code) {
                continue;
            }

            $case = '';
            foreach ($constants as $const) {
                $case .= "                case {$className}Data::$const:\n";
            }
            $case .= $code;

            $cases[] = $case;
        }

        $casts = implode("\n", $cases);
        if ($casts) {
            $casts = <<<REL
                public function toValue(string \$fieldName, mixed \$scalar): mixed
                {
                    if (\$scalar !== null) {
                        switch (\$fieldName) {\n{$casts}
                        }
                    }

                    return parent::toValue(\$fieldName, \$scalar);
                }
            REL;
        }

        return $casts;
    }
}
