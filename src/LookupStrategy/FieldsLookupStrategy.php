<?php

namespace Port\Doctrine\LookupStrategy;

use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ObjectRepository;
use Port\Doctrine\LookupStrategy;

/**
 * Default lookup strategy using repository field criteria.
 *
 * Behavior matches historical DoctrineWriter lookup:
 * - With lookup fields: call the repository method (default findOneBy) with those criteria
 * - Without lookup fields: ObjectRepository::find(current($item))
 */
class FieldsLookupStrategy implements LookupStrategy
{
    private ObjectRepository $objectRepository;

    /** @var list<string> */
    private array $lookupFields;

    private string $lookupMethod;

    /**
     * @param ObjectRepository $objectRepository
     * @param string|array|null $index Field or fields used as lookup criteria (null = find by first item value)
     * @param string $lookupMethod Repository method used when lookup fields are set
     */
    public function __construct(
        ObjectRepository $objectRepository,
        string|array|null $index = null,
        string $lookupMethod = 'findOneBy'
    ) {
        $this->objectRepository = $objectRepository;
        $this->lookupFields = $this->normalizeIndex($index);
        $this->assertLookupMethod($lookupMethod);
        $this->lookupMethod = $lookupMethod;
    }

    /**
     * Convenience factory from an object manager and class name.
     */
    public static function fromObjectManager(
        ObjectManager $objectManager,
        string $objectName,
        string|array|null $index = null,
        string $lookupMethod = 'findOneBy'
    ): self {
        return new self(
            $objectManager->getRepository($objectName),
            $index,
            $lookupMethod
        );
    }

    /**
     * @param string $field Field to find current objects by
     */
    public function withLookupField(string $field): self
    {
        return $this->withLookupFields([$field]);
    }

    /**
     * @param list<string> $fields Fields to find current objects by
     */
    public function withLookupFields(array $fields): self
    {
        $new = clone $this;
        $new->lookupFields = array_values($fields);

        return $new;
    }

    /**
     * Accept string or list of fields (mirrors DoctrineWriter $index constructor arg).
     *
     * @param string|array $index
     */
    public function withIndex(string|array $index): self
    {
        if (is_array($index)) {
            return $this->withLookupFields($index);
        }

        return $this->withLookupField($index);
    }

    /**
     * Doctrine repository method for finding objects when lookup fields are set.
     */
    public function withLookupMethod(string $lookupMethod): self
    {
        $this->assertLookupMethod($lookupMethod);

        $new = clone $this;
        $new->lookupMethod = $lookupMethod;

        return $new;
    }

    public function lookup(array $item): ?object
    {
        if (!empty($this->lookupFields)) {
            $lookupConditions = [];
            foreach ($this->lookupFields as $fieldName) {
                $lookupConditions[$fieldName] = $item[$fieldName] ?? null;
            }

            $result = $this->objectRepository->{$this->lookupMethod}($lookupConditions);

            return is_object($result) ? $result : null;
        }

        $result = $this->objectRepository->find(current($item));

        return is_object($result) ? $result : null;
    }

    /**
     * @param string|array|null $index
     *
     * @return list<string>
     */
    private function normalizeIndex(string|array|null $index): array
    {
        if ($index === null) {
            return [];
        }

        if (is_array($index)) {
            return array_values($index);
        }

        return [$index];
    }

    private function assertLookupMethod(string $lookupMethod): void
    {
        if (!method_exists($this->objectRepository, $lookupMethod)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Repository %s has no method %s',
                    get_class($this->objectRepository),
                    $lookupMethod
                )
            );
        }
    }
}
