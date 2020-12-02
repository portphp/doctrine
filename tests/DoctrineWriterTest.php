<?php

namespace Port\Doctrine\Tests;

use Doctrine\ODM\MongoDB;
use Doctrine\Persistence\ObjectManager;
use MongoDB\Collection;
use PHPUnit\Framework\TestCase;
use Port\Doctrine\DoctrineWriter;
use Port\Doctrine\Tests\Fixtures\Entity\TestEntity;

class DoctrineWriterTest extends TestCase
{
    const TEST_ENTITY = 'Port\Doctrine\Tests\Fixtures\Entity\TestEntity';

    public function testWriteItem()
    {
        $em = $this->getEntityManager();

        $em->expects($this->once())
            ->method('persist');

        $writer = new DoctrineWriter($em, 'Port:TestEntity');

        $association = new TestEntity();
        $item = array(
            'firstProperty' => 'some value',
            'secondProperty' => 'some other value',
            'firstAssociation' => $association
        );
        $writer->writeItem($item);
    }

    public function testWriteItemMongodb()
    {
        $em = $this->getMongoDocumentManager();

        $em->expects($this->once())
            ->method('persist');

        $writer = new DoctrineWriter($em, 'Port:TestEntity');

        $association = new TestEntity();
        $item = array(
            'firstProperty' => 'some value',
            'secondProperty' => 'some other value',
            'firstAssociation' => $association
        );
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

    protected function getEntityManager()
    {
        $em = $this->getMockBuilder('Doctrine\ORM\EntityManager')
            ->onlyMethods(array('getRepository', 'getClassMetadata', 'persist', 'flush', 'clear', 'getConnection', 'getReference'))
            ->disableOriginalConstructor()
            ->getMock();

        $repo = $this->getMockBuilder('Doctrine\ORM\EntityRepository')
            ->disableOriginalConstructor()
            ->getMock();

        $metadata = $this->getMockBuilder('Doctrine\ORM\Mapping\ClassMetadata')
            ->onlyMethods(array('getName', 'getFieldNames', 'getAssociationNames', 'setFieldValue', 'getAssociationMappings'))
            ->disableOriginalConstructor()
            ->getMock();

        $metadata->expects($this->any())
            ->method('getName')
            ->will($this->returnValue(self::TEST_ENTITY));

        $metadata->expects($this->any())
            ->method('getFieldNames')
            ->will($this->returnValue(array('firstProperty', 'secondProperty')));

        $metadata->expects($this->any())
            ->method('getAssociationNames')
            ->will($this->returnValue(array('firstAssociation')));

        $metadata->expects($this->any())
            ->method('getAssociationMappings')
            ->will($this->returnValue(array(array('fieldName' => 'firstAssociation', 'targetEntity' => self::TEST_ENTITY))));

        $configuration = $this->getMockBuilder('Doctrine\DBAL\Configuration')
            ->setMethods(array('getConnection'))
            ->disableOriginalConstructor()
            ->getMock();

        $connection = $this->getMockBuilder('Doctrine\DBAL\Connection')
            ->setMethods(array('getConfiguration', 'getDatabasePlatform', 'getTruncateTableSQL', 'executeQuery'))
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

        $em->expects($this->once())
            ->method('getRepository')
            ->will($this->returnValue($repo));

        $em->expects($this->once())
            ->method('getClassMetadata')
            ->will($this->returnValue($metadata));

        $em->expects($this->any())
            ->method('getConnection')
            ->will($this->returnValue($connection));

        $self = $this;
        $em->expects($this->any())
            ->method('persist')
            ->will($this->returnCallback(function ($argument) use ($self) {
                $self->assertNotNull($argument->getFirstAssociation());
                return true;
            }));

        return $em;
    }


    protected function getMongoDocumentManager()
    {
        $dm = $this->getMockBuilder(MongoDB\DocumentManager::class)
            ->addMethods(array('getConnection'))
            ->onlyMethods(array('getRepository', 'getClassMetadata', 'persist', 'flush', 'clear', 'getDocumentCollection'))
            ->disableOriginalConstructor()
            ->getMock();

        $repo = $this->getMockBuilder(MongoDB\Repository\DocumentRepository::class)
            ->disableOriginalConstructor()
            ->getMock();

        $metadata = $this->getMockBuilder(MongoDB\Mapping\ClassMetadata::class)
            ->addMethods(['getAssociationMappings'])
            ->onlyMethods(array('getName', 'getFieldNames', 'getAssociationNames', 'setFieldValue'))
            ->disableOriginalConstructor()
            ->getMock();

        $metadata->expects($this->any())
            ->method('getName')
            ->will($this->returnValue(self::TEST_ENTITY));

        $metadata->expects($this->any())
            ->method('getFieldNames')
            ->will($this->returnValue(array('firstProperty', 'secondProperty')));

        $metadata->expects($this->any())
            ->method('getAssociationNames')
            ->will($this->returnValue(array('firstAssociation')));

        $metadata->expects($this->any())
            ->method('getAssociationMappings')
            ->will($this->returnValue(array(array('fieldName' => 'firstAssociation', 'targetEntity' => self::TEST_ENTITY))));

        $configuration = $this->getMockBuilder('Doctrine\DBAL\Configuration')
            ->addMethods(array('getConnection'))
            ->disableOriginalConstructor()
            ->getMock();

        $connection = $this->getMockBuilder('Doctrine\DBAL\Connection')
            ->addMethods(array( 'getTruncateTableSQL'))
            ->onlyMethods(array('getConfiguration', 'getDatabasePlatform', 'executeQuery'))
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

//        $documentCollection
//            ->expects($this->once())
//            ->method('remove');

        $dm->expects($this->once())
            ->method('getDocumentCollection')
            ->will($this->returnValue($documentCollection));

        $self = $this;
        $dm->expects($this->any())
            ->method('persist')
            ->will($this->returnCallback(function ($argument) use ($self) {
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

        $item = array(
            'firstProperty' => 'some value',
            'secondProperty' => 'some other value',
            'firstAssociation' => 'firstAssociationId'
        );

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
        $item = array(
            'firstProperty' => 'some value',
            'secondProperty' => 'some other value',
            'firstAssociation' => $association,
        );

        $writer->writeItem($item);
    }

    /**
     * Test to make sure that we are clearing the write entity
     */
    public function testFlushAndClear()
    {
        $em = $this->getEntityManager();

        $em->expects($this->once())
            ->method('clear')
            ->with($this->equalTo(self::TEST_ENTITY));

        $writer = new DoctrineWriter($em, 'Port:TestEntity');
        $writer->finish();
    }
}
