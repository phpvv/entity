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

namespace VV\Entity;

abstract class LegacyDbRepo extends DbRepo
{
    private InitManager $manager;

    public function __construct(InitManager $manager)
    {
        $this->manager = $manager;
    }

    public function initEntity(string|int|array $id): Entity
    {
        return $this->manager->initEntity($id);
    }
}
