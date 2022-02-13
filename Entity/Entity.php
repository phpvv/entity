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

interface Entity extends Identifiable
{
    /**
     * Returns true if the Entity has ID: the {@see init()} or {@see save()} method was previously called
     */
    public function isInited(): bool;

    /**
     * Returns true if any data was changed
     */
    public function isModified(): bool;

    /**
     * Resets (clears) already loaded data
     */
    public function resetData(): static;
}
