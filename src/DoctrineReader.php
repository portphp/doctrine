<?php

namespace Port\Doctrine;

use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\ORM\Internal\Hydration\IterableResult;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Port\Reader\CountableReader;

/**
 * Reads entities through the Doctrine ORM
 *
 * @author David de Boer <david@ddeboer.nl>
 */
class DoctrineReader implements CountableReader
{
    /**
     * @var ObjectManager
     */
    protected $objectManager;

    /**
     * @var string
     */
    protected $objectName;

    /**
     * @var IterableResult
     */
    protected $iterableResult;

    /**
     * @var QueryBuilder
     */
    protected $queryBuilder;

    /**
     * @param ObjectManager $objectManager
     * @param string        $objectName    e.g. YourBundle:YourEntity
     * @param QueryBuilder  $queryBuilder
     */
    public function __construct(ObjectManager $objectManager, $objectName, QueryBuilder $queryBuilder = null)
    {
        $this->objectManager = $objectManager;
        $this->objectName = $objectName;
        if (is_null($queryBuilder)) {
            $queryBuilder = $objectManager->getRepository($objectName)->createQueryBuilder(substr($objectName, 0, 1));
        }
        $this->queryBuilder = $queryBuilder;
    }

    /**
     * @param QueryBuilder $queryBuilder
     */
    public function setQueryBuilder(QueryBuilder $queryBuilder)
    {
        $this->queryBuilder = $queryBuilder;
    }

    /**
     * {@inheritdoc}
     */
    public function getFields()
    {
        return $this->objectManager->getClassMetadata($this->objectName)
                 ->getFieldNames();
    }

    /**
     * {@inheritdoc}
     */
    public function current()
    {
        return current($this->iterableResult->current());
    }

    /**
     * {@inheritdoc}
     */
    public function next()
    {
        $this->iterableResult->next();
    }

    /**
     * {@inheritdoc}
     */
    public function key()
    {
        return $this->iterableResult->key();
    }

    /**
     * {@inheritdoc}
     */
    public function valid()
    {
        return $this->iterableResult->valid();
    }

    /**
     * {@inheritdoc}
     */
    public function rewind()
    {
        if (!$this->iterableResult) {
            $query = $this->queryBuilder->getQuery();
            $this->iterableResult = $query->iterate([], Query::HYDRATE_ARRAY);
        }

        $this->iterableResult->rewind();
    }

    /**
     * {@inheritdoc}
     */
    public function count()
    {
        $paginator = new Paginator($this->queryBuilder->getQuery());

        return count($paginator);
    }
}
