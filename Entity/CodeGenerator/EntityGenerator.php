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

use VV\Db\Model\Column;

class EntityGenerator
{
    public function __construct(protected Generator $generator)
    {
    }

    public function generateEntity(): string
    {
        $generator = $this->generator;
        $className = $generator->getClassName();

        $imports = new Imports();
        $imports->add('VV\Entity\OrmAttribute');
        $imports->add('VV\Entity\PureEntityBase');

        $entityGSetters = $this->generateEntityGSetters($imports);

        return <<<CODE
            {$generator->getFileHeader()}
            {$imports->toString()}

            /**
             * Class {$className}
             *
             * @package {$generator->getNamespace()}
             */
            #[OrmAttribute('{$generator->getTable()->getName()}')]
            class {$className} extends PureEntityBase
            {
            {$this->generateEntityTopContent()}
            {$entityGSetters}
            {$this->generateEntityBottomContent()}
            }

            CODE;
    }

    protected function generateEntityTopContent(): string
    {
        $className = $this->generator->getClassName();

        return <<<CODE
            private {$className}Data \$data;
        
            protected function __construct({$className}Repo \$repo = null)
            {
                \$this->data = new {$className}Data(\$repo);
            }
        CODE;
    }

    /**
     * @param Imports $imports
     *
     * @return string
     */
    protected function generateEntityGSetters(Imports $imports): string
    {
        $generator = $this->generator;

        /** @noinspection SpellCheckingInspection */
        $gsetters = '';
        foreach ($generator->getFieldsInfo() as $fi) {
            if ($fi->name == 'id') {
                continue;
            }

            $var = $fi->camel;
            $dataFieldGetter = "{$fi->camel}Field";

            $dbField = $fi->dbField;
            $isLob = $dbField->getType() == Column::T_BLOB || $dbField->getType() == Column::T_TEXT;
            $setMethod = $isLob ? 'setValueForce' : 'setValue';

            $underscored = strtolower(preg_replace('/[A-Z]/', '_\0', $fi->camel));
            $ttl = str_replace('_', ' ', $underscored);

            $type = $fi->type;
            if ($fi->entityClassName) {
                $fqcn = "$fi->entityNs\\$fi->entityClassName";
                $sameClass = $fqcn === $generator->getNamespace() . '\\' . $generator->getClassName();

                if ($sameClass) {
                    $type = 'self';
                } else {
                    $type = $imports->add($fi->entityClassName, $fi->entityNs);
                }
            }

            /** @noinspection SpellCheckingInspection */
            $gsetters .= <<<GS

                /**
                 * Returns $ttl
                 */
                public function get$fi->studly(): ?$type
                {
                    return \$this->data()->$dataFieldGetter()->getValue();
                }

                /**
                 * Sets $ttl
                 */
                public function set$fi->studly(?$type \$$var): static
                {
                    \$this->data()->$dataFieldGetter()->$setMethod(\$$var);

                    return \$this;
                }

            GS;
        }

        return $gsetters;
    }

    protected function generateEntityBottomContent(): string
    {
        $className = $this->generator->getClassName();

        return <<<CODE
            protected function data(): {$className}Data
            {
                return \$this->data;
            }
        
            protected function repo(): ?{$className}Repo
            {
                return \$this->data->getRepo();
            }
        
            public static function init(string|int|array \$id, {$className}Repo \$repo = null): static
            {
                return (new static(\$repo))->initSelf(\$id);
            }
        
            public static function create({$className}Repo \$repo = null): static
            {
                return new static(\$repo);
            }
        CODE;
    }
}
