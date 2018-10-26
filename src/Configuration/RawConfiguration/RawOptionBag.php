<?php
declare(strict_types=1);

namespace PHPSu\Configuration\RawConfiguration;

class RawOptionBag implements \Iterator, \ArrayAccess
{
    protected $optionValues = [];

    public function __construct(array $optionValues = [])
    {
        foreach ($optionValues as $key => $value) {
            $this[$key] = $value;
        }
    }

    public function current(): string
    {
        return current($this->optionValues);
    }

    public function next()
    {
        next($this->optionValues);
    }

    public function key()
    {
        return key($this->optionValues);
    }

    public function valid()
    {
        $key = $this->key();
        if ($key === null) {
            return false;
        }
        return isset($this->optionValues[$key]);
    }

    public function rewind()
    {
        reset($this->optionValues);
    }

    public function offsetExists($offset)
    {
        return isset($this->optionValues[$offset]);
    }

    public function offsetGet($offset): string
    {
        return $this->optionValues[$offset];
    }

    public function offsetSet($offset, $value)
    {
        if (!is_string($value)) {
            throw new \InvalidArgumentException('a ' . static::class . ' can only hold strings');
        }
        $this->optionValues[$offset] = $value;
    }

    public function offsetUnset($offset)
    {
        unset($this->optionValues[$offset]);
    }

    public static function __set_state(array $data)
    {
        return new static(
            $data['optionValues'] ?? []
        );
    }
}
