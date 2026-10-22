<?php
/**
 * Copyright (c) 2019, Commify Ltd.
 * All rights reserved.
 * 
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *     * Redistributions of source code must retain the above copyright
 *       notice, this list of conditions and the following disclaimer.
 *     * Redistributions in binary form must reproduce the above copyright
 *       notice, this list of conditions and the following disclaimer in the
 *       documentation and/or other materials provided with the distribution.
 *     * Neither the name of Commify nor the
 *       names of its contributors may be used to endorse or promote products
 *       derived from this software without specific prior written permission.
 * 
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL <COPYRIGHT HOLDER> BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * @category   Model
 * @package    Esendex
 * @author     Commify Support <support@esendex.com>
 * @copyright  2019 Commify Ltd.
 * @license    http://opensource.org/licenses/BSD-3-Clause  BSD 3-Clause
 * @link       https://github.com/esendex/esendex-php-sdk
 */
namespace Esendex\Model;

class InboxPage implements \ArrayAccess, \Countable, \Iterator
{
    private $startIndex;
    private $totalCount;
    private $messages;
    private $position = 0;

    /**
     * @param $startIndex
     * @param $totalCount
     */
    public function __construct($startIndex, $totalCount)
    {
        $this->startIndex = (int)$startIndex;
        $this->totalCount = (int)$totalCount;
        $this->messages = array();
    }

    public function startIndex(): int
    {
        return $this->startIndex;
    }

    public function totalCount(): int
    {
        return $this->totalCount;
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->messages[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return isset($this->messages[$offset])
            ? $this->messages[$offset]
            : null;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (!is_null($offset)) {
            throw new \Esendex\Exceptions\ArgumentException("InboxPage does not support explicitly set offsets");
        }

        $this->messages[] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->messages[$offset]);
    }

    public function count(): int
    {
        return count($this->messages);
    }

    public function current(): mixed
    {
        return $this->offsetGet($this->position);
    }

    public function next(): void
    {
        ++$this->position;
    }

    public function key(): int
    {
        return $this->position;
    }

    public function valid(): bool
    {
        return $this->offsetExists($this->position);
    }

    public function rewind(): void
    {
        $this->position = 0;
    }
}
