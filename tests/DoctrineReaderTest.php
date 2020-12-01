<?php

namespace Port\Doctrine\Tests;

use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\TestCase;
use Port\Doctrine\DoctrineReader;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Tools\Setup;
use Port\Doctrine\Tests\Fixtures\Entity\User;

class DoctrineReaderTest extends TestCase
{
    public function testGetFields()
    {
        $fields = $this->getReader()->getFields();
        $this->assertIsArray($fields);
        $this->assertEquals(array('id', 'username'), $fields);
    }

    public function testCount()
    {
        $this->assertEquals(100, $this->getReader()->count());
    }

    public function testIterate()
    {
        $i = 1;
        foreach ($this->getReader() as $data) {
            $this->assertIsArray($data);
            $this->assertEquals('user' . $i, $data['username']);
            $i++;
        }
    }

    protected function getReader()
    {
        $em = $this->getEntityManager();

        for ($i = 1; $i <= 100; $i++) {
            $user = new User();
            $user->setUsername('user'. $i);
            $em->persist($user);
        }

        $em->flush();

        return new DoctrineReader($em, 'Port\Doctrine\Tests\Fixtures\Entity\User');
    }

    protected function getEntityManager()
    {
        $dbParams = array(
            'driver'   => 'pdo_sqlite',
        );

        $paths = array(
            __DIR__.'/../Fixtures/Entity'
        );

        $config = Setup::createAnnotationMetadataConfiguration($paths, true);
        $em = EntityManager::create($dbParams, $config);

        $schemaTool = new SchemaTool($em);
        $schemaTool->createSchema(
            array(
                $em->getMetadataFactory()->getMetadataFor('Port\Doctrine\Tests\Fixtures\Entity\User')
            )
        );

        return $em;
    }
}
