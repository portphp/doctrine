<?php

namespace Port\Doctrine;

/**
 * Finds existing objects in the database.
 */
interface LookupStrategy
{
    /**
     * Look up an item in the database.
     *
     * @param array $item
     *
     * @return mixed | null Null if no object was found.
     */
    public function lookup(array $item);
}
