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

use VV\Db\Connection;
use VV\Db\Model\Table;
use VV\Db\Result;
use VV\Db\Sql;
use VV\Db\Sql\Condition;
use VV\Db\Sql\SelectQuery;

/**
 * Class DbRepo
 *
 * @package VV\Entity
 */
abstract class DbRepo implements Repo, InitManager
{
    private ?Table $table = null;
    private ?Connection $connection = null;
    private array $withList = [];

    /**
     * @return SelectQuery
     */
    final public function createQuery(): SelectQuery
    {
        $query = $this->createBaseQuery();

        $tableClause = $query->getTableClause();
        $mta = $tableClause->getMainTableAlias();
        /** @var self $repo */
        foreach ($this->withList as $field => [$repo, $alias, $on, $virtualColumn]) {
            $repoTbl = $repo->getTable();

            if (!$alias) {
                $alias = preg_replace('/_id$/i', '', $field);
            }
            if (!$on) {
                $on = "$mta.$field=$alias.{$repoTbl->getPk()}";
            }

            $query->leftNestedColumns($repo->createQuery(), $on, $field, $alias);
            if ($virtualColumn) {
                $query->addColumns("$alias.$field");
                if (is_string($virtualColumn)) {
                    $query->addColumns("'$virtualColumn' {$field}__vvirt");
                }
            }
        }

        return $query;
    }

    /**
     * @param string|int $id
     *
     * @return Entity|null|mixed
     */
    final public function findById(string|int $id): ?Entity
    {
        $data = $this->fetchById($id);
        if (!$data) {
            return null;
        }

        return $this->initEntity($data);
    }

    /**
     * @inheritDoc
     */
    public function startTransaction(bool $useFreeConnection = false): DbTransaction
    {
        $db = $this->getTable()->getDb();
        $connection = $useFreeConnection
            ? $db->getFreeConnection()
            : $db->getConnection();

        return new DbTransaction($connection->startTransaction());
    }

    /**
     * @inheritDoc
     */
    public function isSameTransactionDb(Transaction $transaction): bool
    {
        if (!$transaction instanceof DbTransaction) {
            return false;
        }
        $connection = $transaction->getDbTransaction()->getConnection();

        return $this->getConnection()->isSame($connection);
    }

    /**
     * @inheritDoc
     */
    public function isDbInTransaction(): bool
    {
        return $this->getTable()->getDb()->isInTransaction();
    }

    public function saveAll(?Transaction $transaction, Entity|\Closure|iterable ...$entities): void
    {
        if ($transaction) {
            $transaction->persistAll(...$entities);
        } else {
            $this->startTransaction()->saveAll(...$entities);
        }
    }

    /**
     * @return Table
     */
    public function getTable(): Table
    {
        if ($this->table === null) {
            $this->table = $this->initTable();
        }

        return $this->table;
    }

    /**
     * @return Connection
     */
    public function getConnection(): Connection
    {
        if ($this->connection === null) {
            $this->connection = $this->getTable()->getConnection();
        }

        return $this->connection;
    }

    public function fetchById(string|int $id): ?array
    {
        $query = $this->createQuery()->whereId($id);

        return $this->fetchSingleRowOrThrow($query->result());
    }

    public function insert(Transaction $transaction, array $data): string|int
    {
        if (!$transaction instanceof DbTransaction) {
            throw new \LogicException();
        }

        return $this->getTable()->insert()->set($data)
            ->insertedId($transaction->getDbTransaction());
    }

    public function update(Transaction $transaction, array $data, string|int $id): bool
    {
        if (!$transaction instanceof DbTransaction) {
            throw new \LogicException();
        }

        return (bool)$this->getTable()->update($data)->whereId($id)
            ->affectedRows($transaction->getDbTransaction());
    }

    /**
     * @param Condition|array $condition
     *
     * @return Entity|null|mixed
     */
    final protected function find(Condition|array $condition): ?Entity
    {
        $data = $this->fetch($condition);
        if (!$data) {
            return null;
        }

        return $this->initEntity($data);
    }

    /**
     * @param Condition|array $condition
     * @param string|null     $keyField
     * @param array|null      $orderBy
     *
     * @return Entity[]
     */
    final protected function findAssocList(
        Condition|array $condition,
        string $keyField = null,
        array $orderBy = null
    ): array {
        $assoc = [];
        $iter = $this->fetchIterator($condition, null, $orderBy);
        foreach ($iter as $data) {
            $entity = $this->initEntity($data);
            $key = $keyField !== null ? $data[$keyField] : $entity->getId();
            if (array_key_exists($key, $assoc)) {
                throw new \RuntimeException("Duplicated key `$key`");
            }
            $assoc[$key] = $entity;
        }

        return $assoc;
    }

    /**
     * @param Condition|array $condition
     * @param int|null        $limit
     * @param array|null      $orderBy
     *
     * @return Entity[]
     */
    final protected function findList(Condition|array $condition, int $limit = null, array $orderBy = null): array
    {
        return $this->iterableToEntityList(
            $this->fetchIterator($condition, $limit, $orderBy)
        );
    }

    /**
     * @param Condition|array $condition
     * @param int|null        $limit
     * @param array|null      $orderBy
     *
     * @return \Traversable|Entity[]
     */
    final protected function findIterator(
        Condition|array $condition,
        int $limit = null,
        array $orderBy = null
    ): \Traversable {
        return $this->iterableToEntityIterator(
            $this->fetchIterator($condition, $limit, $orderBy)
        );
    }

    /**
     * @param SelectQuery $query
     * @param int|null    $fetchSize
     *
     * @return Entity[]
     */
    final protected function queryToEntityList(SelectQuery $query, int $fetchSize = null): array
    {
        return $this->iterableToEntityList(
            $this->queryToIterator($query, $fetchSize)
        );
    }

    /**
     * @param SelectQuery $query
     * @param int|null    $fetchSize
     *
     * @return \Traversable|Entity[]
     */
    final protected function queryToEntityIterator(SelectQuery $query, int $fetchSize = null): \Traversable
    {
        return $this->iterableToEntityIterator(
            $this->queryToIterator($query, $fetchSize)
        );
    }

    /**
     * @param iterable $iter
     *
     * @return Entity[]
     */
    final protected function iterableToEntityList(iterable $iter): array
    {
        $list = [];
        foreach ($iter as $data) {
            $list[] = $this->initEntity($data);
        }

        return $list;
    }

    /**
     * @param iterable $iter
     *
     * @return \Traversable|Entity[]
     */
    final protected function iterableToEntityIterator(iterable $iter): \Traversable
    {
        foreach ($iter as $data) {
            yield $this->initEntity($data);
        }
    }

    /**
     * @param Condition|array $condition
     *
     * @return array|null
     */
    final protected function fetch(Condition|array $condition): ?array
    {
        $query = $this->createQuery()->where($this->createCondition($condition));

        return $this->fetchSingleRowOrThrow($query->result());
    }

    /**
     * @param Condition|array|null $condition
     * @param int|null             $limit
     * @param array|null           $orderBy
     *
     * @return \Traversable
     */
    final protected function fetchIterator(
        Condition|array|null $condition,
        int $limit = null,
        array $orderBy = null
    ): \Traversable {
        $query = $this->createListQuery($condition, $limit, $orderBy);

        return $this->queryToIterator($query);
    }

    /**
     * @param Result $result
     *
     * @return array|null
     */
    final protected function fetchSingleRowOrThrow(Result $result): ?array
    {
        $row = $this->fetchNextRow($result);
        if (!$row) {
            return null;
        }

        if ($this->fetchNextRow($result)) {
            throw new \RuntimeException('Multi record found');
        }

        return $row;
    }

    /**
     * @param Result $queryResult
     *
     * @return array|null
     */
    final protected function fetchNextRow(Result $queryResult): ?array
    {
        $row = $queryResult->fetch();
        if ($row) {
            $this->processFetchedRowWithAll($row);
        }

        return $row ?: null;
    }

    /**
     * @param SelectQuery $query
     *
     * @return array
     */
    final protected function queryToList(SelectQuery $query): array
    {
        $result = $query->result();
        $rows = [];
        while ($row = $this->fetchNextRow($result)) {
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param SelectQuery $query
     * @param int|null    $fetchSize
     *
     * @return \Traversable
     */
    final protected function queryToIterator(SelectQuery $query, int $fetchSize = null): \Traversable
    {
        $result = $query->result(null, null, $fetchSize);

        return $this->queryResultToIterator($result, $fetchSize);
    }

    /**
     * @param Result   $result
     * @param int|null $limit
     *
     * @return \Traversable
     */
    final protected function queryResultToIterator(Result $result, int $limit = null): \Traversable
    {
        $i = 0;
        while ($row = $this->fetchNextRow($result)) {
            yield $row;
            if ($limit && (++$i) >= $limit) {
                break;
            }
        }

        $result->close();
    }

    /**
     * @param Condition|array|null $condition
     * @param int|null             $limit
     * @param array|null           $orderBy
     *
     * @return SelectQuery
     */
    final protected function createListQuery(
        Condition|array|null $condition = null,
        int $limit = null,
        array $orderBy = null
    ): SelectQuery {
        $query = $this->createQuery();

        if ($condition) {
            $query->where($this->createCondition($condition));
        }

        if ($orderBy === null) {
            $orderBy = $this->dfltOrderBy();
        } // todo: remove using of dfltOrderBy
        if ($orderBy) {
            $query->orderBy(...$orderBy);
        }

        if ($limit) {
            $query->limit($limit);
        }

        return $query;
    }

    /**
     * @param string                      $field
     * @param self                        $repo
     * @param string|null                 $alias
     * @param string|array|Condition|null $on
     * @param string|bool                 $virtualColumn
     *
     * @return $this
     */
    final protected function with(
        string $field,
        self $repo,
        string $alias = null,
        string|array|Condition $on = null,
        string|bool $virtualColumn = false
    ): static {
        $this->withList[$field] = [$repo, $alias, $on, $virtualColumn];

        return $this;
    }

    /**
     * @param string $field
     *
     * @return $this
     */
    final protected function without(string $field): static
    {
        unset($this->withList[$field]);

        return $this;
    }

    /**
     * @param string $field
     *
     * @return bool
     */
    final protected function isWith(string $field): bool
    {
        return isset($this->withList[$field]);
    }

    /**
     * @param mixed $condition
     *
     * @return Condition
     */
    final protected function createCondition(mixed $condition = null): Condition
    {
        return Sql::condition($condition);
    }

    /**
     * @return SelectQuery
     */
    protected function createBaseQuery(): SelectQuery
    {
        return $this->getTable()->select(...$this->queryColumns());
    }

    /**
     * @return array
     */
    protected function queryColumns(): array
    {
        throw new \LogicException('Not overridden');
    }

    protected function dfltOrderBy(): array
    {
        return [];
    }

    protected function processFetchedRow(&$row)
    {
    }

    abstract public function initEntity(string|int|array $id): Entity;

    /**
     * @return Table
     */
    abstract protected function initTable(): Table;

    private function processFetchedRowWithAll(&$row)
    {
        $this->processFetchedRow($row);
        foreach ($this->withList as $field => [$repo]) {
            if (!$subRows = &$row[$field]) {
                continue;
            }
            $repo->processFetchedRowWithAll($subRows);
        }
    }
}
