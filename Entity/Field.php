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

final class Field
{
    /** Field name */
    private string $name;

    /** Uses for lazy loading of data and type conversions */
    private Data $data;

    private bool $virtual;

    /** Scalar (raw) representation of value. Usually as stored in DB */
    private mixed $scalar;

    /** True if value was init or set */
    private bool $scalarDefined = false;

    /** Final representation of value: or same as scalar value, or object, or another Entity */
    private mixed $value;

    /** True if value was init or set */
    private bool $valueDefined = false;

    /** True if field was modified */
    private bool $modified = false;

    public function __construct(string $name, Data $data, bool $virtual = false)
    {
        $this->name = $name;
        $this->data = $data;
        $this->virtual = $virtual;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isVirtual(): bool
    {
        return $this->virtual;
    }

    public function isDefined(): bool
    {
        return $this->scalarDefined || $this->valueDefined;
    }

    public function preload(mixed $scalar): self
    {
        $this->setRawScalar($scalar);
        if (!$this->virtual) {
            $this->data->addPreloadedField($this);
        }

        return $this;
    }

    public function preloadValue(mixed $value): self
    {
        $this->setRawValue($value);
        if (!$this->virtual) {
            $this->data->addPreloadedField($this);
        }

        return $this;
    }

    public function getScalar(): mixed
    {
        if (!$this->scalarDefined) {
            if ($this->valueDefined) {
                $scalar = $this->toScalar($this->value);
            } else {
                $scalar = $this->data->loadField($this);
            }

            $this->setRawScalar($scalar, false);
        }

        $scalar = &$this->scalar;
        if ($scalar instanceof \Closure) {
            $scalar = $scalar();
        }

        return $scalar;
    }

    public function setScalar($scalar): self
    {
        if ((string)$scalar == (string)$this->getScalar()) {
            return $this;
        }

        $this->data->addModifiedField($this);

        return $this->setRawScalar($scalar)->setModified(true);
    }

    /**
     * Returns final-typified value of $field
     */
    public function getValue(): mixed
    {
        if (!$this->valueDefined) {
            $value = $this->toValue($scalar = $this->getScalar());
            $this->setRawValue($value, is_array($scalar));
        }

        return $this->value;
    }

    /**
     * Modifies $value for $field
     */
    public function setValue(mixed $value, mixed &$preValue = null): self
    {
        if ($this->virtual) {
            throw new \LogicException('Can\'t change virtual field');
        }

        // do not change order of $preValue and $prevScalar (if scalar is array)
        $preValue = $this->getValue();

        if ($value instanceof Entity && !$value->isInited()) {
            // scalar value will be loaded before first save
            $newScalar = $value;
        } else {
            // check whether is scalars is same
            $newScalar = $this->toScalar($value);
            $prevScalar = $this->getScalar();

            $isNewDt = $newScalar instanceof \DateTimeInterface;
            $isPrevDt = $prevScalar instanceof \DateTimeInterface;
            if (($isNewDt || $isPrevDt) && $newScalar && $prevScalar) {
                if (!$isPrevDt) {
                    /** @noinspection PhpUnhandledExceptionInspection */
                    $prevScalar = new \DateTimeImmutable($prevScalar);
                }
                if (!$isNewDt) {
                    /** @noinspection PhpUnhandledExceptionInspection */
                    $newScalar = new \DateTimeImmutable($prevScalar);
                }
                if ($newScalar == $prevScalar) {
                    return $this;
                }
            } elseif ($newScalar === $prevScalar) {
                return $this;
            }
        }

        $this->data->addModifiedField($this);

        return $this
            ->setRawValue($value, false)
            ->setRawScalar($newScalar, false)
            ->setModified(true);
    }

    /**
     * Set $value for $field forced - without previous value comparison
     */
    public function setValueForce(mixed $value): self
    {
        if ($this->virtual) {
            throw new \LogicException('Can\'t change virtual field');
        }

        $this->data->addModifiedField($this);

        return $this->setRawValue($value)->setModified(true);
    }

    public function isModified(): bool
    {
        return $this->modified;
    }

    public function clearModified(): self
    {
        return $this->setModified(false);
    }

    protected function setRawScalar(mixed $scalar, bool $resetValue = true): self
    {
        $this->scalar = $scalar;
        $this->scalarDefined = true;
        if ($resetValue) {
            $this->valueDefined = false;
        }

        return $this;
    }

    protected function setRawValue(mixed $value, bool $resetScalar = true): self
    {
        $this->value = $value;
        $this->valueDefined = true;
        if ($resetScalar) {
            $this->scalarDefined = false;
        }

        return $this;
    }

    protected function setModified(bool $modified): self
    {
        $this->modified = $modified;

        return $this;
    }

    protected function toValue(mixed $scalar): mixed
    {
        $value = $this->data->toValue($this->getName(), $scalar);
        if ($value instanceof InitManager) {
            $value = $value->initEntity($scalar);
        }

        return $value;
    }

    protected function toScalar(mixed $value): mixed
    {
        return $this->data->toScalar($this->getName(), $value);
    }
}
