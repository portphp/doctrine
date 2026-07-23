<?php

namespace Port\Doctrine\Tests;

use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Connection;
use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ODM\MongoDB\Mapping\ClassMetadata as MongoClassMetadata;
use Doctrine\ODM\MongoDB\Repository\DocumentRepository;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\ObjectManager;
use MongoDB\Collection;
use PHPUnit\Framework\TestCase;
use Port\Doctrine\DoctrineWriter;
use Port\Doctrine\LookupStrategy;
use Port\Doctrine\LookupStrategy\FieldsLookupStrategy;
use Port\Doctrine\Tests\Fixtures\Entity\TestEntity;

class DoctrineWriterTest extends TestCase
{
    const TEST_ENTITY = TestEntity::class;

    public function testWriteItem()
    {
        $em = $this->getEntityManager();

        $em->expects($this->once())
            ->method('persist');

        $writer = new DoctrineWriter($em, 'Port:TestEntity');

        $association = new TestEntity();
        $item = [
            'firstProperty' => 'some value',
            'secondProperty' => 'some other value',
            'firstAssociation' => $association
        ];
        $writer->writeItem($item);
    }

    public function testWriteItemMongodb()
    {
        $em = $this->getMongoDocumentManager();

        $em->expects($this->once())
            ->method('persist');

        $writer = new DoctrineWriter($em, 'Port:TestEntity');

        $association = new TestEntity();
        $item = [
            'firstProperty' => 'some value',
            'secondProperty' => 'some other value',
            'firstAssociation' => $association
        ];
        $writer->prepare();
        $writer->writeItem($item);
    }

    public function testUnsupportedDatabaseTypeException()
    {
        $this->expectException('Port\Doctrine\Exception\UnsupportedDatabaseTypeException');
        $em = $this->getMockBuilder(ObjectManager::class)
            ->getMock();
        new DoctrineWriter($em, 'Port:TestEntity');
    }

    protected function getMongoDocumentManager()
    {
        $dm = $this->getMockBuilder(DocumentManager::class)
            ->setMethods(['getRepository', 'getClassMetadata', 'persist', 'flush', 'clear', 'getConnection', 'getDocumentCollection'])
            ->disableOriginalConstructor()
            ->getMock();

        $repo = $this->getMockBuilder(DocumentRepository::class)
            ->disableOriginalConstructor()
            ->getMock();

        $metadata = $this->getMockBuilder(MongoClassMetadata::class)
            ->setMethods(['getName', 'getFieldNames', 'getAssociationNames', 'setFieldValue', 'getAssociationMappings'])
            ->disableOriginalConstructor()
            ->getMock();

        $metadata->expects($this->any())
            ->method('getName')
            ->will($this->returnValue(self::TEST_ENTITY));

        $metadata->expects($this->any())
            ->method('getFieldNames')
            ->will($this->returnValue(['firstProperty', 'secondProperty']));

        $metadata->expects($this->any())
            ->method('getAssociationNames')
            ->will($this->returnValue(['firstAssociation']));

        $metadata->expects($this->any())
            ->method('getAssociationMappings')
            ->will($this->returnValue([['fieldName' => 'firstAssociation', 'targetEntity' => self::TEST_ENTITY]]));

        $configuration = $this->getMockBuilder(Configuration::class)
            ->setMethods(['getConnection'])
            ->disableOriginalConstructor()
            ->getMock();

        $connection = $this->getMockBuilder(Connection::class)
            ->setMethods(['getConfiguration', 'getDatabasePlatform', 'getTruncateTableSQL', 'executeQuery'])
            ->disableOriginalConstructor()
            ->getMock();

        $connection->expects($this->any())
            ->method('getConfiguration')
            ->will($this->returnValue($configuration));

        $connection->expects($this->any())
            ->method('getDatabasePlatform')
            ->will($this->returnSelf());

        $connection->expects($this->never())
            ->method('getTruncateTableSQL');

        $connection->expects($this->never())
            ->method('executeQuery');

        $dm->expects($this->once())
            ->method('getRepository')
            ->will($this->returnValue($repo));

        $dm->expects($this->once())
            ->method('getClassMetadata')
            ->will($this->returnValue($metadata));

        $dm->expects($this->any())
            ->method('getConnection')
            ->will($this->returnValue($connection));

        $documentCollection = $this->getMockBuilder(Collection::class)
            ->disableOriginalConstructor()
            ->getMock();

        $documentCollection
            ->expects($this->once())
            ->method('deleteMany');

        $dm->expects($this->once())
            ->method('getDocumentCollection')
            ->will($this->returnValue($documentCollection));

        $self = $this;
        $dm->expects($this->any())
            ->method('persist')
            ->will(
                $this->returnCallback(function ($argument) use ($self) {
                    $self->assertNotNull($argument->getFirstAssociation());
                    return true;
                }));

        return $dm;
    }

    public function testLoadAssociationWithoutObject()
    {
        $em = $this->getEntityManager();

        $em->expects($this->once())
            ->method('persist');

        $em->expects($this->once())
            ->method('getReference');

        $writer = new DoctrineWriter($em, 'Port:TestEntity');

        $item = [
            'firstProperty' => 'some value',
            'secondProperty' => 'some other value',
            'firstAssociation' => 'firstAssociationId'
        ];

        $writer->writeItem($item);
    }

    public function testLoadAssociationWithPresetObject()
    {
        $em = $this->getEntityManager();

        $em->expects($this->once())
            ->method('persist');

        $em->expects($this->never())
            ->method('getReference');

        $writer = new DoctrineWriter($em, 'Port:TestEntity');

        $association = new TestEntity();
        $item = [
            'firstProperty' => 'some value',
            'secondProperty' => 'some other value',
            'firstAssociation' => $association,
        ];

        $writer->writeItem($item);
    }

    /**
     * Test to make sure that we are clearing the write entity
     */
    public function testFlushAndClear()
    {
        $em = $this->getEntityManager();

        $em->expects($this->once())
            ->method('clear');

        $writer = new DoctrineWriter($em, 'Port:TestEntity');
        $writer->finish();
    }

    public function testWithLookupStrategyFactory()
    {
        $em = $this->getEntityManager();

        $lookupStrategy = $this->createMock(LookupStrategy::class);
        // Default truncate=true means lookup is not used during writeItem
        $lookupStrategy->expects($this->never())
            ->method('lookup');

        $writer = DoctrineWriter::withLookupStrategy(
            $em,
            'Port:TestEntity',
            $lookupStrategy
        );

        $item = [
            'firstProperty' => 'some value',
            'secondProperty' => 'some other value',
            'firstAssociation' => new TestEntity(),
        ];
        $writer->writeItem($item);
        $writer->finish();

        $this->assertInstanceOf(DoctrineWriter::class, $writer);
    }

    public function testDisableTruncateUsesLookupStrategy()
    {
        $em = $this->getEntityManager(['getRepository' => $this->any()]);
        $existing = new TestEntity();
        $existing->setFirstProperty('from-db');

        $lookupStrategy = $this->createMock(LookupStrategy::class);
        $lookupStrategy->expects($this->once())
            ->method('lookup')
            ->with($this->callback(function (array $item) {
                return $item['firstProperty'] === 'lookup-key';
            }))
            ->willReturn($existing);

        $writer = DoctrineWriter::withLookupStrategy(
            $em,
            'Port:TestEntity',
            $lookupStrategy
        );
        $writer->disableTruncate();

        $writer->writeItem([
            'firstProperty' => 'lookup-key',
            'secondProperty' => 'updated',
            'firstAssociation' => new TestEntity(),
        ]);

        $this->assertSame('updated', $existing->getSecondProperty());
    }

    public function testDisableTruncateCreatesWhenLookupReturnsNull()
    {
        $persisted = null;
        $em = $this->getEntityManager([
            'onPersist' => function ($object) use (&$persisted) {
                $persisted = $object;
            },
        ]);

        $lookupStrategy = $this->createMock(LookupStrategy::class);
        $lookupStrategy->expects($this->once())
            ->method('lookup')
            ->willReturn(null);

        $writer = DoctrineWriter::withLookupStrategy(
            $em,
            'Port:TestEntity',
            $lookupStrategy
        );
        $writer->disableTruncate();

        $writer->writeItem([
            'firstProperty' => 'new-value',
            'secondProperty' => 'other',
            'firstAssociation' => new TestEntity(),
        ]);

        $this->assertInstanceOf(TestEntity::class, $persisted);
        $this->assertSame('new-value', $persisted->getFirstProperty());
    }

    public function testConstructorIndexUsesFieldsLookupStrategy()
    {
        $repo = $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findOneBy'])
            ->getMock();

        $existing = new TestEntity();
        $repo->expects($this->once())
            ->method('findOneBy')
            ->with(['firstProperty' => 'key'])
            ->willReturn($existing);

        $em = $this->getEntityManager([
            'repository' => $repo,
            'getRepository' => $this->any(),
        ]);

        $writer = new DoctrineWriter($em, 'Port:TestEntity', 'firstProperty');
        $writer->disableTruncate();

        $writer->writeItem([
            'firstProperty' => 'key',
            'secondProperty' => 'updated-via-index',
            'firstAssociation' => new TestEntity(),
        ]);

        $this->assertSame('updated-via-index', $existing->getSecondProperty());
    }

    public function testFieldsLookupStrategyWithoutIndexUsesFind()
    {
        $repo = $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['find', 'findOneBy'])
            ->getMock();

        $existing = new TestEntity();
        $repo->expects($this->once())
            ->method('find')
            ->with('first-value')
            ->willReturn($existing);
        $repo->expects($this->never())
            ->method('findOneBy');

        $strategy = new FieldsLookupStrategy($repo);
        $this->assertSame($existing, $strategy->lookup([
            'firstProperty' => 'first-value',
            'secondProperty' => 'x',
        ]));
    }

    public function testFieldsLookupStrategyWithFieldsIsImmutable()
    {
        $repo = $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['findOneBy'])
            ->getMock();

        $existing = new TestEntity();
        $repo->expects($this->once())
            ->method('findOneBy')
            ->with(['secondProperty' => 'match'])
            ->willReturn($existing);

        $base = new FieldsLookupStrategy($repo);
        $withField = $base->withLookupField('secondProperty');

        // Original must not be mutated
        $this->assertNotSame($base, $withField);
        $this->assertSame($existing, $withField->lookup([
            'firstProperty' => 'ignored',
            'secondProperty' => 'match',
        ]));
    }

    public function testFieldsLookupStrategyInvalidMethodThrows()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('has no method doesNotExist');

        $repo = $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->getMock();

        new FieldsLookupStrategy($repo, null, 'doesNotExist');
    }

    /**
     * @param array{
     *     repository?: EntityRepository|\PHPUnit\Framework\MockObject\MockObject,
     *     getRepository?: mixed,
     *     getClassMetadata?: mixed,
     *     onPersist?: callable
     * } $options
     */
    protected function getEntityManager(array $options = [])
    {
        $em = $this->getMockBuilder(EntityManager::class)
            ->setMethods(['getRepository', 'getClassMetadata', 'persist', 'flush', 'clear', 'getConnection', 'getReference'])
            ->disableOriginalConstructor()
            ->getMock();

        $repo = $options['repository'] ?? $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->getMock();

        $metadata = $this->getMockBuilder(ClassMetadata::class)
            ->setMethods(['getName', 'getFieldNames', 'getAssociationNames', 'setFieldValue', 'getAssociationMappings', 'getFieldValue'])
            ->disableOriginalConstructor()
            ->getMock();

        $metadata->expects($this->any())
            ->method('getName')
            ->will($this->returnValue(self::TEST_ENTITY));

        $metadata->expects($this->any())
            ->method('getFieldNames')
            ->will($this->returnValue(['firstProperty', 'secondProperty']));

        $metadata->expects($this->any())
            ->method('getAssociationNames')
            ->will($this->returnValue(['firstAssociation']));

        $metadata->expects($this->any())
            ->method('getAssociationMappings')
            ->will($this->returnValue([['fieldName' => 'firstAssociation', 'targetEntity' => self::TEST_ENTITY]]));

        $configuration = $this->getMockBuilder(Configuration::class)
            ->setMethods(['getConnection'])
            ->disableOriginalConstructor()
            ->getMock();

        $connection = $this->getMockBuilder(Connection::class)
            ->setMethods(['getConfiguration', 'getDatabasePlatform', 'getTruncateTableSQL', 'executeQuery'])
            ->disableOriginalConstructor()
            ->getMock();

        $connection->expects($this->any())
            ->method('getConfiguration')
            ->will($this->returnValue($configuration));

        $connection->expects($this->any())
            ->method('getDatabasePlatform')
            ->will($this->returnSelf());

        $connection->expects($this->any())
            ->method('getTruncateTableSQL')
            ->will($this->returnValue('TRUNCATE SQL'));

        $connection->expects($this->any())
            ->method('executeQuery')
            ->with('TRUNCATE SQL');

        $em->expects($options['getRepository'] ?? $this->once())
            ->method('getRepository')
            ->will($this->returnValue($repo));

        $em->expects($options['getClassMetadata'] ?? $this->once())
            ->method('getClassMetadata')
            ->will($this->returnValue($metadata));

        $em->expects($this->any())
            ->method('getConnection')
            ->will($this->returnValue($connection));

        $self = $this;
        $onPersist = $options['onPersist'] ?? null;
        $em->expects($this->any())
            ->method('persist')
            ->will(
                $this->returnCallback(function ($argument) use ($self, $onPersist) {
                    $self->assertNotNull($argument->getFirstAssociation());
                    if ($onPersist !== null) {
                        $onPersist($argument);
                    }
                    return true;
                }));

        return $em;
    }
}
