<?php

namespace Port\Doctrine\LookupStrategy;

use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\Persistence\ObjectRepository;
use Port\Doctrine\LookupStrategy;

/**
 * Default lookup strategy using object fields.
 */
class FieldsLookupStrategy implements LookupStrategy
{
    /**
     * @var ObjectManager
     */
    private $objectManager;

    /**
     * @var ObjectRepository
     */
    private $objectRepository;

    /**
     * @var array
     */
    private $lookupFields;

    /**
     * @var string
     */
    private $lookupMethod = 'findOneBy';

    /**
     * @param ObjectManager $objectManager
     * @param string        $objectName    Fully qualified model name
     */
    public function __construct(ObjectManager $objectManager, $objectName)
    {
        $this->objectManager = $objectManager;
        $this->objectRepository = $objectManager->getRepository($objectName);
        $this->lookupFields = $objectManager->getClassMetadata($objectName)->getIdentifierFieldNames();
    }

    /**
     * @param string $field Field to current current objects by.
     *
     * @return self
     */
    public function withLookupField($field)
    {
        return $this->withLookupFields([$field]);
    }

    /**
     * Create lookup strategy with index
     *
     * @param array $fields Fields to find current objects by.
     *
     * @return self
     */
    public function withLookupFields(array $fields)
    {
        $new = clone $this;
        $this->lookupFields = $fields;

        return $new;
    }

    /**
     * Doctrine repository method for finding objects.
     *
     * @param string $lookupMethod
     *
     * @return self
     */
    public function withLookupMethod($lookupMethod)
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

        $new = clone $this;
        $new->lookupMethod = [$this->objectRepository, $lookupMethod];

        return $new;
    }

    /**
     * {@inheritdoc}
     */
    public function lookup(array $item)
    {
        $lookupConditions = array();
        foreach ($this->lookupFields as $fieldName) {
            $lookupConditions[$fieldName] = $item[$fieldName];
        }

        return call_user_func($this->lookupMethod, $lookupConditions);
    }
}
