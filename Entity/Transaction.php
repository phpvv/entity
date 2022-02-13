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

interface Transaction
{
    /**
     * Commits changes
     */
    public function commit(): void;

    /**
     * Rollbacks changes
     */
    public function rollback(): void;

    /**
     * Saves all Entities in one try/catch block
     */
    public function saveAll(Entity|\Closure|iterable ...$entities): void;

    /**
     * Persists all Entities but not calls commit/rollback
     */
    public function persistAll(Entity|\Closure|iterable ...$entities): static;
}
