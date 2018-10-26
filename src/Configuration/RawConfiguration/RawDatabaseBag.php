<?php
declare(strict_types=1);

namespace PHPSu\Configuration\RawConfiguration;

use PHPSu\Configuration\RawConfiguration\RawDatabaseDto as Item;
use PHPSu\Core\AbstractBag;

class RawDatabaseBag extends AbstractBag
{
    public function __construct(Item ...$databases)
    {
        parent::__construct($databases, Item::class);
    }

    public function current(): Item
    {
        return current($this->bagContent);
    }

    public function offsetGet($offset): Item
    {
        return $this->bagContent[$offset];
    }
}
