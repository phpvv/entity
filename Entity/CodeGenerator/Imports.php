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

class Imports
{
    /** @var array<string, string> */
    private array $imports = [];

    /**
     * Accepts Fully Qualified Class Name or Class Name and Namespace separately,
     * adds it to imports if Class Name does not exist yet
     * and returns Class Name (or his FQCN) to be used in code
     */
    public function add(string $className, string $namespace = null, string $alias = null): string
    {
        if ($namespace === null) {
            $fqcn = ltrim($className, '\\');

            if (!preg_match('!^\\\\?(?:\w+\\\\)*(\w+)$!', $className, $m)) {
                throw new \UnexpectedValueException("Can't parse FQCN: $className");
            }
            $className = $m[1];
        } else {
            $fqcn = "$namespace\\$className";
        }

        if (!$alias) {
            $alias = $className;
        }

        $use = $fqcn;
        if ($alias !== $className) {
            $use .= " as $alias";
        }

        $import = &$this->imports[$alias];
        if (!$import || $import == $use) {
            if (!$import) {
                $import = $use;
            }

            return $alias;
        }

        return "\\$fqcn";
    }

    public function toString()
    {
        sort($this->imports, SORT_STRING);

        return array_reduce($this->imports, fn ($p, $i) => "$p\nuse $i;");
    }
}
