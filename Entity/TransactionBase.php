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

abstract class TransactionBase implements Transaction
{
    public function saveAll(Entity|\Closure|iterable ...$entities): void
    {
        try {
            $this->persistAll(...$entities)->commit();
        } catch (\Throwable $e) {
            $this->rollback();
            /** @var \RuntimeException $e */
            throw $e;
        }
    }

    public function persistAll(Entity|\Closure|iterable ...$entities): static
    {
        foreach ($entities as $entity) {
            if (is_iterable($entity)) {
                self::persistAll(...$entity);
            } elseif ($entity instanceof \Closure) {
                $entity($this);
            } else {
                $entity->save($this);
            }
        }

        return $this;
    }
}
