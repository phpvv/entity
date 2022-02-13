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

class FieldInfo
{
    public Column $dbField;
    public string $name;
    public string $camel;
    public string $studly;
    public string $const;
    public string $type;
    public ?string $entityNs;
    public ?string $entityRelativeNs;
    public ?string $entityClassName;
}
