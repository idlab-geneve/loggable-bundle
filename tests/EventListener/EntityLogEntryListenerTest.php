<?php

namespace Idlab\Loggable\Tests\EventListener;

use Doctrine\ORM\Tools\SchemaTool;
use Idlab\Loggable\Entity\EntityLogEntry;
use Idlab\Loggable\Tests\Entity\DummyEntity;
use Idlab\Loggable\Tests\Entity\DummyUser;
use Idlab\Loggable\Tests\Kernel\TestKernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

class EntityLogEntryListenerTest extends TestCase
{
    public function testDoctrineListenerIsCalled(): void
    {
        $kernel = new TestKernel('test', true, 'idlab_loggable_enabled.yaml');
        $kernel->boot();

        $em = $kernel->getContainer()->get('doctrine')->getManager();

        $schemaTool = new SchemaTool($em);
        $schemaTool->createSchema($em->getMetadataFactory()->getAllMetadata());

        $security = $kernel->getContainer()->get('security.token_storage');
        $user = new DummyUser('idlab_test', 'password', ['ROLE_USER']);

        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
        $security->setToken($token);

        $entity = new DummyEntity();
        $entity->value = 'expected_value';
        $em->persist($entity);
        $em->flush();

        /** @var EntityLogEntry $logEntry */
        $logEntry = $em->getRepository(EntityLogEntry::class)->findOneBy(['objectId' => $entity->id]);
        $this->assertEquals('expected_value', $logEntry->getData()['value']);
        $this->assertEquals(DummyEntity::class, $logEntry->getObjectClass());
        $this->assertEquals('create', $logEntry->getAction());
        $this->assertEquals('idlab_test', $logEntry->getCreatedBy());
    }
}
