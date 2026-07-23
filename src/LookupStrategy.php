<?php

namespace Port\Doctrine;

/**
 * Finds existing objects in the database for upsert-style writes.
 *
 * Implement this interface to customize how {@see DoctrineWriter} looks up
 * entities (e.g. via QueryBuilder or custom repository methods).
 */
interface LookupStrategy
{
    /**
     * Look up an existing object for the given import item.
     *
     * @param array $item Import item (field => value)
     *
     * @return object|null Null if no object was found (writer will create a new instance)
     */
    public function lookup(array $item): ?object;
}
