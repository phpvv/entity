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

interface InitManager
{
    /**
     * Initializes the Entity with his ID (string|int) or previously obtained data (array)
     *
     * @param string|int|array $id
     *
     * @return Entity
     */
    public function initEntity(string|int|array $id): Entity;
}
