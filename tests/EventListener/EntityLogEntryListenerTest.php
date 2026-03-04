<?php

namespace Idlab\Loggable\Tests\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Idlab\Loggable\Entity\EntityLogEntry;
use Idlab\Loggable\Tests\Entity\DummyEntity;
use Idlab\Loggable\Tests\Entity\DummyUser;
use Idlab\Loggable\Tests\Entity\IgnoredByNamespace\DummyIgnored;
use Idlab\Loggable\Tests\Entity\OtherDummyIgnoredByClass;
use Idlab\Loggable\Tests\Entity\OtherDummyWithoutLoggedProperty;
use Idlab\Loggable\Tests\Kernel\TestKernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

class EntityLogEntryListenerTest extends TestCase
{
    private TestKernel $kernel;
    private EntityManagerInterface $em;

    public function __construct(?string $name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);

        $this->kernel = new TestKernel('test', true, 'idlab_loggable_enabled.yaml');
        $this->kernel->boot();

        $this->em = $this->kernel->getContainer()->get('doctrine')->getManager();
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        if (!empty($metadata)) {
            $schemaTool = new SchemaTool($this->em);
            $schemaTool->dropSchema($metadata);
            $schemaTool->createSchema($metadata);
        }

        $security = $this->kernel->getContainer()->get('security.token_storage');
        $user = new DummyUser('idlab_test', 'password', ['ROLE_USER']);

        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
        $security->setToken($token);
    }

    public function testDoctrineListenerIsCalled(): void
    {
        $entity = new DummyEntity();
        $entity->value = 'expected_value';
        $this->em->persist($entity);
        $this->em->flush();

        /** @var EntityLogEntry $logEntry */
        $logEntry = $this->em->getRepository(EntityLogEntry::class)->findOneBy(['objectId' => $entity->id]);
        $this->assertEquals('expected_value', $logEntry->getData()['value']);
        $this->assertEquals(DummyEntity::class, $logEntry->getObjectClass());
        $this->assertEquals('create', $logEntry->getAction());
        $this->assertEquals('idlab_test', $logEntry->getCreatedBy());
    }

    public function testIgnoredNamespaces(): void
    {
        $entity = new DummyIgnored();
        $entity->value = 'expected_value';
        $this->em->persist($entity);
        $this->em->flush();

        $logEntries = $this->em->getRepository(EntityLogEntry::class)->findAll();
        $this->assertEmpty($logEntries);
    }

    public function testIgnoredClasses(): void
    {
        $entity = new OtherDummyIgnoredByClass();
        $entity->value = 'expected_value';
        $this->em->persist($entity);
        $this->em->flush();

        $logEntries = $this->em->getRepository(EntityLogEntry::class)->findAll();
        $this->assertEmpty($logEntries);
    }

    public function testIgnoredEntityWithoutLoggedProperty(): void
    {
        $entity = new OtherDummyWithoutLoggedProperty();
        $entity->value = 'expected_value';
        $this->em->persist($entity);
        $this->em->flush();

        $logEntries = $this->em->getRepository(EntityLogEntry::class)->findAll();
        $this->assertEmpty($logEntries);
    }
}
