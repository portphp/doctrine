<?php

namespace Port\Doctrine;

use Doctrine\Persistence\ObjectManager;
use Doctrine\ORM\Internal\Hydration\IterableResult;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
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

    /** @var QueryBuilder */
    protected $queryBuilder;

    /**
     * @param ObjectManager $objectManager
     * @param string $objectName e.g. YourBundle:YourEntity
     */
    public function __construct(ObjectManager $objectManager, $objectName)
    {
        $this->objectManager = $objectManager;
        $this->objectName = $objectName;
    }

    public function setQueryBuilder(QueryBuilder $queryBuilder)
    {
        $this->queryBuilder = $queryBuilder;

        return $this;
    }

    protected function getQueryBuilder()
    {
        if ($this->queryBuilder === null) {
            $this->queryBuilder = $this->objectManager->createQueryBuilder()
                ->from($this->objectName, 'o');
        }

        return clone $this->queryBuilder;
    }

    public function getFields(): array
    {
        return $this->objectManager->getClassMetadata($this->objectName)
            ->getFieldNames();
    }

    public function current(): array
    {
        return current($this->iterableResult->current());
    }

    public function next(): void
    {
        $this->iterableResult->next();
    }

    public function key(): int
    {
        return $this->iterableResult->key();
    }

    public function valid(): bool
    {
        return $this->iterableResult->valid();
    }

    public function rewind(): void
    {
        if (!$this->iterableResult) {
            $query = $this->getQueryBuilder()->select('o')->getQuery();

            $this->iterableResult = $query->iterate([], Query::HYDRATE_ARRAY);
        }

        $this->iterableResult->rewind();
    }

    public function count(): int
    {
        $query = $this->getQueryBuilder()->select('count(o)')->getQuery();

        return $query->getSingleScalarResult();
    }
}
