<?php

namespace Idlab\Loggable\Tests\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Idlab\Loggable\Tests\Entity\DummyEntity;
use Idlab\Loggable\Tests\Kernel\TestKernel;
use PHPUnit\Framework\TestCase;

class EntityLogEntryListenerTest extends TestCase
{
    public function testDoctrineListenerIsCalled(): void
    {
        $kernel = new TestKernel('test', true, 'idlab_loggable_enabled.yaml');
        $kernel->boot();

        $em = $kernel->getContainer()->get('doctrine')->getManager();

        $schemaTool = new SchemaTool($em);
        $schemaTool->createSchema($em->getMetadataFactory()->getAllMetadata());

        $entity = new DummyEntity();
        $em->persist($entity);
        $em->flush();

        //        $this->assertSame('modified', $entity->value);

        //        $logEntry = $entityManager->getRepository(EntityLogEntry::class)->findOneBy(['objectId' => $entity->id]);
    }
}
