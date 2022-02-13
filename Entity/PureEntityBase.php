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

abstract class PureEntityBase extends EntityBase implements PureEntity
{
    public function save(Repo|Transaction|bool|null $transaction = null, Repo $repo = null): static
    {
        return parent::save($transaction, $repo);
    }
}
