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

abstract class EntityBase implements Entity
{
    public function getId(): string|int
    {
        if (!$this->isInited()) {
            throw new \LogicException('Entity not inited');
        }

        return $this->data()->idField()->getValue();
    }

    public function isInited(): bool
    {
        return $this->data()->idField()->isDefined();
    }

    public function isModified(): bool
    {
        return $this->data()->isModified();
    }

    public function resetData(): static
    {
        $this->data()->reset();

        return $this;
    }

    protected function construct(string|int|array $id = null): static
    {
        if ($id !== null) {
            $this->initSelf($id);
        }

        return $this;
    }

    /**
     * Initializes model with $id or array of fields. Does not load the data from DB right way
     */
    protected function initSelf(string|int|array $id): static
    {
        if ($this->isInited()) {
            throw new \LogicException('Already inited');
        }
        if ($this->isModified()) {
            throw new \LogicException('Can\'t init this entity because data has been modified');
        }

        if (is_array($id)) {
            // preload data
            ($data = $this->data())->preload($id);
            // get actual id
            $id = $data->idField()->getScalar();
        }

        return $this->preloadId($id);
    }

    protected function preloadId(string|int $id): static
    {
        if ((string)$id == '') {
            throw new \InvalidArgumentException('Entity ID can\'t be empty');
        }

        $this->data()->idField()->preload($id);

        return $this;
    }

    /**
     * See {@see PureEntity::save()}
     */
    protected function save(Transaction|Repo|bool|null $transaction = null, Repo $repo = null): static
    {
        $dataRepo = $this->data()->getRepo();
        if ($transaction instanceof Repo) {
            $repo = $transaction;
            $transaction = null;
        } elseif (!$repo) {
            $repo = $dataRepo;
        }
        if (!$repo) {
            throw new \LogicException('Repo is not set for Entity');
        }
        if (!$dataRepo) {
            $this->data()->setRepo($repo);
        }

        $data = $this->data();
        $isNew = !$this->isInited();

        $modified = $data->getModifiedFields();
        if ($isNew) {
            $modified += $data->getPreloadedFields();
        }

        foreach ($modified as $k => $f) {
            $scalar = $f->getScalar();
            if ($scalar instanceof Identifiable) {
                $scalar = $scalar->getId();
            }

            $modified[$k] = $scalar;
        }

        $justSave = function ($transaction) use ($isNew, $repo, $modified) {
            if ($isNew) {
                $id = $repo->insert($transaction, $modified);
                $this->preloadId($id);
            } elseif ($modified) {
                $res = $repo->update($transaction, $modified, $this->getId());
                if (!$res) {
                    throw new \RuntimeException('Record not found for update');
                }
            }
        };

        if ($transaction instanceof Transaction) {
            if (!$repo->isSameTransactionDb($transaction)) {
                throw new \LogicException('Transaction for another DB');
            }
            $justSave($transaction);
        } else {
            $useFree = $transaction === true;
            if (!$useFree && $repo->isDbInTransaction()) {
                throw new \LogicException('Saving during started transaction. Pass $transaction please');
            }

            $repo->startTransaction($useFree)->saveAll($justSave);
        }

        $data->clearModified();

        return $this;
    }

    abstract protected function data(): Data;
}
