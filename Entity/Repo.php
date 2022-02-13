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

interface Repo
{
    /**
     * Loads data from db
     */
    public function fetchById(string|int $id): ?array;

    /**
     * Finds Entity by ID
     */
    public function findById(string|int $id): ?Entity;

    /**
     * Inserts data to DB and returns inserted record ID
     *
     * @return string|int ID of new Entity
     */
    public function insert(Transaction $transaction, array $data): string|int;

    /**
     * Updates data in DB
     */
    public function update(Transaction $transaction, array $data, string|int $id): bool;

    /**
     * Creates new Transaction
     */
    public function startTransaction(bool $useFreeConnection = false): Transaction;

    /**
     * Checks if Transaction is in same DB as Repo
     */
    public function isSameTransactionDb(Transaction $transaction): bool;

    /**
     * Returns true if repo's DB connection is already in transaction
     */
    public function isDbInTransaction(): bool;

    /**
     * Persists or Saves all Entities
     */
    public function saveAll(?Transaction $transaction, Entity|\Closure|iterable ...$entities): void;
}
