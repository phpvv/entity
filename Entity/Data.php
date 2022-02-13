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
 * Class Data
 *
 * @package VV\Entity
 */
abstract class Data
{
    public const F_ID = null;
    public const FIELD_NAMES = [];
    public const VIRTUAL_FIELD_NAMES = [];
    public const DEFAULTS = [];

    /** @var Field[] */
    private array $fields = [];
    /** @var Field[] */
    private array $preloadedFields = [];
    /** @var Field[] */
    private array $modifiedFields = [];
    private ?array $loadedData = null;

    public function __construct(Repo $repo = null)
    {
        $this->setRepo($repo)->preload(static::DEFAULTS);
    }

    abstract public function getRepo(): ?Repo;

    abstract public function setRepo(?Repo $repo): static;

    /**
     * @param string $name
     *
     * @return Field
     */
    public function getField(string $name): Field
    {
        $field = &$this->fields[$name];
        if (!$field) {
            $virtual = in_array($name, static::getVirtualFieldNames());
            $field = $this->createField($name, $virtual);
        }

        return $field;
    }

    /**
     * @return Field[]
     */
    public function getFieldIterator(): iterable
    {
        foreach ($this->fields as $field) {
            yield $field;
        }
    }

    /**
     * @param array $scalars
     *
     * @return $this
     */
    public function preload(array $scalars): static
    {
        foreach ($scalars as $name => $value) {
            $this->getField($name)->preload($value);
        }

        return $this;
    }

    /**
     * @param array $values
     *
     * @return $this
     */
    public function preloadValues(array $values): static
    {
        foreach ($values as $name => $value) {
            $this->getField($name)->preloadValue($value);
        }

        return $this;
    }

    /**
     * @return Field[]
     */
    public function getPreloadedFields(): array
    {
        return $this->preloadedFields;
    }

    /**
     * @return Field[]
     */
    public function getModifiedFields(): array
    {
        return $this->modifiedFields;
    }

    /**
     * @return bool
     */
    public function isModified(): bool
    {
        return !empty($this->modifiedFields);
    }

    /**
     * @return $this
     */
    public function clearModified(): static
    {
        $this->modifiedFields = [];
        foreach ($this->getFieldIterator() as $f) {
            $f->clearModified();
        }

        return $this;
    }

    /**
     * @return $this
     */
    public function reset(): static
    {
        if ($this->isModified()) {
            throw new \LogicException('Data is modified - can\'t reset');
        }

        $id = ($f = $this->idField())->isDefined() ? $f->getScalar() : null;

        $this->loadedData = null;
        $this->preloadedFields = [];
        $this->fields = [];

        if ($id !== null) {
            $this->idField()->preload($id);
        }

        return $this;
    }

    /**
     * @return Field
     */
    public function idField(): Field
    {
        $fieldName = static::F_ID;
        if (!$fieldName) {
            throw new \UnexpectedValueException('static::F_ID is empty');
        }

        return $this->getField($fieldName);
    }

    /**
     * @param Field $field
     *
     * @return mixed
     */
    public function loadField(Field $field): mixed
    {
        $loaData = &$this->getLoadedData();
        $name = $field->getName();
        if (!array_key_exists($name, $loaData)) {
            if ($field->isVirtual()) {
                throw new \LogicException('Virtual field not inited');
            }
            throw new \RuntimeException('Data not found');
        }

        $value = $loaData[$name];
        unset($loaData[$name]); // clear unnecessary data

        return $value;
    }

    /**
     * @param string $fieldName
     * @param mixed  $scalar
     *
     * @return mixed
     */
    public function toValue(string $fieldName, mixed $scalar): mixed
    {
        return $scalar;
    }

    /**
     * @param string $fieldName
     * @param mixed  $value
     *
     * @return mixed
     */
    public function toScalar(string $fieldName, mixed $value): mixed
    {
        if ($value instanceof Identifiable) {
            return $value->getId();
        }

        if ($value instanceof \Stringable) {
            return (string)$value;
        }

        return $value;
    }

    /**
     * @param Field $field
     *
     * @return $this
     * @internal
     */
    public function addPreloadedField(Field $field): static
    {
        $this->checkFriendFieldAccess($field);
        $this->preloadedFields[$field->getName()] = $field;

        return $this;
    }

    /**
     * @param Field $field
     *
     * @return $this
     * @internal
     */
    public function addModifiedField(Field $field): static
    {
        $this->checkFriendFieldAccess($field);
        $this->modifiedFields[$field->getName()] = $field;

        return $this;
    }

    /**
     * @return array|null
     */
    protected function &getLoadedData(): ?array
    {
        if ($this->loadedData === null) {
            $this->loadedData = $this->loadData();
        }

        return $this->loadedData;
    }

    /**
     * @return array|null
     */
    protected function loadData(): ?array
    {
        $idField = $this->idField();

        // fill all fields with null for NOT initiated entities
        if (!$idField->isDefined()) {
            return array_fill_keys($this->getFieldNames(), null);
        }

        // load all fields values from repo for initiated entities
        if (!$repo = $this->getRepo()) {
            throw new \LogicException('Repo is not set');
        }

        $loaData = $repo->fetchById($idField->getScalar());
        if (!$loaData || !is_array($loaData)) {
            throw new \RuntimeException('Record not found');
        }
        if ($diff = array_diff($this->getFieldNames(), array_keys($loaData))) {
            throw new \LogicException('Not full data is loaded: ' . implode(', ', $diff));
        }

        return $loaData;
    }

    /**
     * @param string $name
     * @param bool   $virtual
     *
     * @return Field
     */
    protected function createField(string $name, bool $virtual): Field
    {
        return new Field($name, $this, $virtual);
    }

    /**
     * @param Field $field
     */
    protected function checkFriendFieldAccess(Field $field): void
    {
        $ownField = $this->fields[$field->getName()] ?? null;
        if (!$ownField || $ownField !== $field) {
            throw new \LogicException('Field is not in list');
        }
    }

    /**
     * @return array
     */
    public static function getFieldNames(): array
    {
        return static::FIELD_NAMES;
    }

    /**
     * @return array
     */
    public static function getVirtualFieldNames(): array
    {
        return static::VIRTUAL_FIELD_NAMES;
    }
}
