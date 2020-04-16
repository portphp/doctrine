<?php

namespace Port\Doctrine;

use Doctrine\DBAL\Logging\SQLLogger;
use Doctrine\Inflector\Inflector;
use Doctrine\Inflector\InflectorFactory;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\Persistence\Mapping\ClassMetadata;
use Doctrine\Persistence\ObjectManager;
use Doctrine\Persistence\ObjectRepository;
use Port\Doctrine\Exception\UnsupportedDatabaseTypeException;
use Port\Writer;

/**
 * A bulk Doctrine writer
 *
 * See also the {@link http://www.doctrine-project.org/docs/orm/2.1/en/reference/batch-processing.html Doctrine documentation}
 * on batch processing.
 *
 * @author David de Boer <david@ddeboer.nl>
 */
class DoctrineWriter implements Writer, Writer\FlushableWriter
{
    /**
     * Doctrine object manager
     *
     * @var ObjectManager
     */
    protected $objectManager;

    /**
     * Fully qualified model name
     *
     * @var string
     */
    protected $objectName;

    /**
     * Doctrine object repository
     *
     * @var ObjectRepository
     */
    protected $objectRepository;

    /**
     * @var ClassMetadata
     */
    protected $objectMetadata;

    /**
     * Original Doctrine logger
     *
     * @var SQLLogger
     */
    protected $originalLogger;

    /**
     * Whether to truncate the table first
     *
     * @var boolean
     */
    protected $truncate = true;

    /**
     * List of fields used to lookup an object
     *
     * @var array
     */
    protected $lookupFields = [];

    /**
     * Method used for looking up the item
     *
     * @var array
     */
    protected $lookupMethod;

    private Inflector $inflector;

    /**
     * Constructor
     *
     * @param ObjectManager $objectManager
     * @param string        $objectName
     * @param string|array  $index         Field or fields to find current entities by
     * @param string        $lookupMethod  Method used for looking up the item
     */
    public function __construct(
        ObjectManager $objectManager,
        $objectName,
        $index = null,
        $lookupMethod = 'findOneBy'
    ) {
        $this->ensureSupportedObjectManager($objectManager);
        $this->objectManager = $objectManager;
        $this->objectRepository = $objectManager->getRepository($objectName);
        $this->objectMetadata = $objectManager->getClassMetadata($objectName);
        //translate objectName in case a namespace alias is used
        $this->objectName = $this->objectMetadata->getName();
        if ($index) {
            if (is_array($index)) {
                $this->lookupFields = $index;
            } else {
                $this->lookupFields = [$index];
            }
        }

        if (!method_exists($this->objectRepository, $lookupMethod)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Repository %s has no method %s',
                    get_class($this->objectRepository),
                    $lookupMethod
                )
            );
        }
        $this->lookupMethod = [$this->objectRepository, $lookupMethod];
        $this->inflector = InflectorFactory::create()->build();
    }

    /**
     * @return boolean
     */
    public function getTruncate()
    {
        return $this->truncate;
    }

    /**
     * Set whether to truncate the table first
     *
     * @param boolean $truncate
     *
     * @return $this
     */
    public function setTruncate($truncate)
    {
        $this->truncate = $truncate;

        return $this;
    }

    /**
     * Disable truncation
     *
     * @return $this
     */
    public function disableTruncate()
    {
        $this->truncate = false;

        return $this;
    }

    /**
     * Disable Doctrine logging
     *
     * @return $this
     */
    public function prepare()
    {
        $this->disableLogging();

        if (true === $this->truncate) {
            $this->truncateTable();
        }
    }

    /**
     * Re-enable Doctrine logging
     */
    public function finish()
    {
        $this->flush();
        $this->reEnableLogging();
    }

    /**
     * {@inheritdoc}
     */
    public function writeItem(array $item)
    {
        $object = $this->findOrCreateItem($item);

        $this->loadAssociationObjectsToObject($item, $object);
        $this->updateObject($item, $object);

        $this->objectManager->persist($object);
    }

    /**
     * Flush and clear the object manager
     */
    public function flush()
    {
        $this->objectManager->flush();
        $this->objectManager->clear();
    }

    /**
     * Return a new instance of the object
     *
     * @return object
     */
    protected function getNewInstance()
    {
        $className = $this->objectMetadata->getName();

        if (class_exists($className) === false) {
            throw new \RuntimeException('Unable to create new instance of ' . $className);
        }

        return new $className;
    }

    /**
     * Call a setter of the object
     */
    protected function setValue(object $object, $value, string $setter)
    {
        if (method_exists($object, $setter)) {
            $object->$setter($value);
        }
    }

    protected function updateObject(array $item, object $object): void
    {
        $fieldNames = array_merge($this->objectMetadata->getFieldNames(), $this->objectMetadata->getAssociationNames());
        foreach ($fieldNames as $fieldName) {
            $value = null;
            $classifiedFieldName = $this->inflector->classify($fieldName);
            if (isset($item[$fieldName])) {
                $value = $item[$fieldName];
            }

            if (null === $value) {
                continue;
            }

            if (!($value instanceof \DateTime)
                || $value != $this->objectMetadata->getFieldValue($object, $fieldName)
            ) {
                $setter = 'set' . $classifiedFieldName;
                $this->setValue($object, $value, $setter);
            }
        }
    }

    /**
     * Add the associated objects in case the item have for persist its relation
     */
    protected function loadAssociationObjectsToObject(array $item, object $object): void
    {
        foreach ($this->objectMetadata->getAssociationMappings() as $associationMapping) {

            $value = null;
            if (isset($item[$associationMapping['fieldName']]) && !is_object($item[$associationMapping['fieldName']])) {
                $value = $this->objectManager->getReference($associationMapping['targetEntity'], $item[$associationMapping['fieldName']]);
            }

            if (null === $value) {
                continue;
            }

            $setter = 'set' . ucfirst($associationMapping['fieldName']);
            $this->setValue($object, $value, $setter);
        }
    }

    /**
     * Truncate the database table for this writer
     */
    protected function truncateTable()
    {
        if ($this->objectManager instanceof \Doctrine\ORM\EntityManager) {
            $tableName = $this->objectMetadata->table['name'];
            $connection = $this->objectManager->getConnection();
            $query = $connection->getDatabasePlatform()->getTruncateTableSQL($tableName, true);
            $connection->executeQuery($query);
        } elseif ($this->objectManager instanceof DocumentManager) {
            $this->objectManager->getDocumentCollection($this->objectName)->deleteMany([]);
        }
    }

    /**
     * Disable Doctrine logging
     */
    protected function disableLogging()
    {
        //TODO: do we need to add support for MongoDB logging?
        if (!($this->objectManager instanceof \Doctrine\ORM\EntityManager)) return;

        $config = $this->objectManager->getConnection()->getConfiguration();
        $this->originalLogger = $config->getSQLLogger();
        $config->setSQLLogger(null);
    }

    /**
     * Re-enable Doctrine logging
     */
    protected function reEnableLogging()
    {
        //TODO: do we need to add support for MongoDB logging?
        if (!($this->objectManager instanceof \Doctrine\ORM\EntityManager)) return;

        $config = $this->objectManager->getConnection()->getConfiguration();
        $config->setSQLLogger($this->originalLogger);
    }

    protected function findOrCreateItem(array $item): object
    {
        $object = null;
        // If the table was not truncated to begin with, find current object
        // first
        if (!$this->truncate) {
            if (!empty($this->lookupFields)) {
                $lookupConditions = [];
                foreach ($this->lookupFields as $fieldName) {
                    $lookupConditions[$fieldName] = $item[$fieldName];
                }

                $object = call_user_func($this->lookupMethod, $lookupConditions);
            } else {
                $object = $this->objectRepository->find(current($item));
            }
        }

        if (!$object) {
            return $this->getNewInstance();
        }

        return $object;
    }

    protected function ensureSupportedObjectManager(ObjectManager $objectManager)
    {
        if (!($objectManager instanceof \Doctrine\ORM\EntityManager
            || $objectManager instanceof DocumentManager)
        ) {
            throw new UnsupportedDatabaseTypeException($objectManager);
        }
    }
}
