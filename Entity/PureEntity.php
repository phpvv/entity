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

interface PureEntity extends Entity
{
    /**
     * Saves changed data to DB
     *
     * @param bool|Repo|Transaction|null $transaction true - force save in transaction free connection
     *
     * @return $this
     */
    public function save(Transaction|Repo|bool|null $transaction = null): static;
}
