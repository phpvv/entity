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

/**
 * Class DbTransaction
 *
 * @package VV\Entity
 */
class DbTransaction extends TransactionBase
{
    private \VV\Db\Transaction $dbTransaction;

    public function __construct(\VV\Db\Transaction $dbTransaction)
    {
        $this->dbTransaction = $dbTransaction;
    }

    public function getDbTransaction(): \VV\Db\Transaction
    {
        return $this->dbTransaction;
    }

    public function commit(): void
    {
        $this->dbTransaction->commit();
    }

    public function rollback(): void
    {
        $this->dbTransaction->rollback();
    }
}
