<?php

namespace Idlab\Loggable\Tests\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Idlab\Loggable\Entity\EntityLogEntry;
use Idlab\Loggable\Command\SnapshotCommand;
use Idlab\Loggable\Tests\Entity\DummyEntity;
use Idlab\Loggable\Tests\Entity\DummyUser;
use Idlab\Loggable\Tests\Entity\ClassLoggedEntity;
use Idlab\Loggable\Tests\Entity\IgnoredByNamespace\DummyIgnored;
use Idlab\Loggable\Tests\Entity\OtherDummyIgnoredByClass;
use Idlab\Loggable\Tests\Entity\OtherDummyWithoutLoggedProperty;
use Idlab\Loggable\Tests\Entity\SnapshotChild;
use Idlab\Loggable\Tests\Entity\SnapshotEntity;
use Idlab\Loggable\Tests\Kernel\TestKernel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Console\Tester\CommandTester;

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

    public function testClassAttributeLogsMappedPropertiesAndHonorsExclusions(): void
    {
        $entity = new ClassLoggedEntity();
        $entity->value = 'logged';
        $entity->excludedValue = 'excluded';
        $entity->status = \Idlab\Loggable\Tests\Entity\TestStatus::Published;

        $this->em->persist($entity);
        $this->em->flush();

        /** @var EntityLogEntry $logEntry */
        $logEntry = $this->em->getRepository(EntityLogEntry::class)->findOneBy([
            'objectClass' => ClassLoggedEntity::class,
        ]);

        $data = $logEntry->getData();
        $this->assertSame('logged', $data['value']);
        $this->assertSame('published', $data['status']);
        $this->assertArrayNotHasKey('excludedValue', $data);
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

    public function testDeleteSnapshotContainsLoggableValuesAndAssociationIdentifiers(): void
    {
        $child = new SnapshotChild();
        $entity = new SnapshotEntity();
        $entity->value = 'before deletion';
        $entity->child = $child;
        $entity->children->add($child);
        $entity->setPrivateValue('private value');
        $entity->setPrivateChild($child);

        $this->em->persist($child);
        $this->em->persist($entity);
        $this->em->flush();

        $entityId = $entity->id;
        $childId = $child->id;
        $this->em->remove($entity);
        $this->em->flush();

        /** @var EntityLogEntry $logEntry */
        $logEntry = $this->em->getRepository(EntityLogEntry::class)->findOneBy([
            'action' => EntityLogEntry::ACTION_REMOVE,
            'objectClass' => SnapshotEntity::class,
            'objectId' => (string) $entityId,
        ]);

        $this->assertNotNull($logEntry);
        $this->assertSame('before deletion', $logEntry->getData()['value']);
        $this->assertSame(['id' => $childId], $logEntry->getData()['child']);
        $this->assertSame([(string) $childId], $logEntry->getData()['children']);
        $this->assertSame('private value', $logEntry->getData()['privateValue']);
        $this->assertSame(['id' => $childId], $logEntry->getData()['privateChild']);
    }

    public function testSnapshotCommandCreatesSnapshotForSelectedClass(): void
    {
        $child = new SnapshotChild();
        $entity = new SnapshotEntity();
        $entity->value = 'command snapshot';
        $entity->child = $child;
        $this->em->persist($child);
        $this->em->persist($entity);
        $this->em->flush();

        /** @var SnapshotCommand $command */
        $command = $this->kernel->getContainer()->get('console.command_loader')->get('idlab:loggable:snapshot')->getCommand();
        $discovery = (new \ReflectionClass($command))->getMethod('discoverClasses');
        $discovery->setAccessible(true);
        $classes = $discovery->invoke($command);
        $index = array_search(SnapshotEntity::class, array_column($classes, 'class'), true);
        self::assertNotFalse($index);

        $tester = new CommandTester($command);
        $tester->execute(['selection' => (string) $index]);

        self::assertSame(0, $tester->getStatusCode());
        $logEntry = $this->em->getRepository(EntityLogEntry::class)->findOneBy([
            'action' => EntityLogEntry::ACTION_SNAPSHOT,
            'objectClass' => SnapshotEntity::class,
            'objectId' => (string) $entity->id,
        ]);
        self::assertNotNull($logEntry);
        self::assertSame('command snapshot', $logEntry->getData()['value']);
        self::assertStringContainsString('1 snapshots created', $tester->getDisplay());
    }

    public function testSnapshotCommandFiltersCreatedAndUnchangedEntities(): void
    {
        $entity = new SnapshotEntity();
        $entity->value = 'initial';
        $this->em->persist($entity);
        $this->em->flush();

        $command = $this->getSnapshotCommand();
        $index = $this->getSnapshotEntityIndex($command);

        $tester = new CommandTester($command);
        $tester->execute(['selection' => (string) $index, '--exclude-created' => true]);
        self::assertSame(0, $tester->getStatusCode());
        self::assertNull($this->em->getRepository(EntityLogEntry::class)->findOneBy([
            'action' => EntityLogEntry::ACTION_SNAPSHOT,
            'objectClass' => SnapshotEntity::class,
        ]));

        $tester->execute(['selection' => (string) $index]);
        $tester->execute(['selection' => (string) $index, '--skip-unchanged' => true]);
        self::assertSame(1, $this->em->getRepository(EntityLogEntry::class)->count([
            'action' => EntityLogEntry::ACTION_SNAPSHOT,
            'objectClass' => SnapshotEntity::class,
        ]));

        $entity->value = 'changed';
        $this->em->flush();
        $tester->execute(['selection' => (string) $index, '--skip-unchanged' => true]);
        self::assertSame(2, $this->em->getRepository(EntityLogEntry::class)->count([
            'action' => EntityLogEntry::ACTION_SNAPSHOT,
            'objectClass' => SnapshotEntity::class,
        ]));
    }

    private function getSnapshotCommand(): SnapshotCommand
    {
        return $this->kernel->getContainer()->get('console.command_loader')->get('idlab:loggable:snapshot')->getCommand();
    }

    private function getSnapshotEntityIndex(SnapshotCommand $command): int
    {
        $method = (new \ReflectionClass($command))->getMethod('discoverClasses');
        $classes = $method->invoke($command);
        $index = array_search(SnapshotEntity::class, array_column($classes, 'class'), true);

        self::assertIsInt($index);

        return $index;
    }
}
