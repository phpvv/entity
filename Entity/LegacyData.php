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

class LegacyData extends Data
{
    private ?Repo $repo;

    public function getRepo(): ?Repo
    {
        return $this->repo;
    }

    public function setRepo(?Repo $repo): static
    {
        $this->repo = $repo;

        return $this;
    }
}
