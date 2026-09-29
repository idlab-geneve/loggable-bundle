<?php

namespace Idlab\Loggable\Command;

use Doctrine\Persistence\ManagerRegistry;
use Idlab\Loggable\Config\IdlabLoggableConfig;
use Idlab\Loggable\Entity\EntityLogEntry;
use Idlab\Loggable\Service\EntitySnapshotter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;

#[AsCommand(
    name: 'idlab:loggable:snapshot',
    description: 'Create snapshots for persisted entities with loggable properties.',
)]
final class SnapshotCommand extends Command
{
    private const BATCH_SIZE = 100;

    public function __construct(
        private readonly ManagerRegistry $registry,
        private readonly IdlabLoggableConfig $config,
        private readonly EntitySnapshotter $snapshotter,
        private readonly Security $security,
        private readonly TokenStorageInterface $tokenStorage,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('selection', InputArgument::OPTIONAL, 'Class indexes to snapshot, for example 0,2-4')
            ->addOption('exclude-created', null, InputOption::VALUE_NONE, 'Skip entities with an existing create log')
            ->addOption('skip-unchanged', null, InputOption::VALUE_NONE, 'Skip entities matching their latest snapshot log');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $classes = $this->discoverClasses();
        if ($classes === []) {
            $io->warning('No supported loggable entity classes were found.');

            return Command::SUCCESS;
        }

        try {
            $selected = $this->selectClasses($input, $io, $classes);
        } catch (\InvalidArgumentException $exception) {
            $io->error($exception->getMessage());

            return Command::INVALID;
        }

        $logManager = $this->registry->getManager($this->config->loginTargetConnectionName);
        $repository = $logManager->getRepository(EntityLogEntry::class);
        [$username, $userId, $impersonatedBy] = $this->userContext();
        $scanned = $created = $skipped = 0;
        $batch = [];

        foreach ($selected as $entry) {
            $manager = $this->registry->getManager($entry['manager']);
            $sourceRepository = $manager->getRepository($entry['class']);
            foreach ($sourceRepository->findAll() as $entity) {
                ++$scanned;
                $objectId = $this->snapshotter->getIdentifier($manager, $entity);
                $data = $this->snapshotter->snapshot($manager, $entity, $entry['class']);

                if ($input->getOption('exclude-created') && $repository->count([
                    'action' => EntityLogEntry::ACTION_CREATE,
                    'objectClass' => $entry['class'],
                    'objectId' => $objectId,
                ]) > 0) {
                    ++$skipped;
                    continue;
                }

                if ($input->getOption('skip-unchanged')) {
                    $latest = $repository->findOneBy(
                        ['action' => EntityLogEntry::ACTION_SNAPSHOT, 'objectClass' => $entry['class'], 'objectId' => $objectId],
                        ['loggedAt' => 'DESC', 'id' => 'DESC'],
                    );
                    if ($latest?->getData() === $data) {
                        ++$skipped;
                        continue;
                    }
                }

                $batch[] = new EntityLogEntry(
                    action: EntityLogEntry::ACTION_SNAPSHOT,
                    username: $username,
                    userId: $userId,
                    objectId: $objectId ?? '',
                    objectClass: $entry['class'],
                    data: $data,
                    impersonatedBy: $impersonatedBy,
                );
                ++$created;

                if (count($batch) >= self::BATCH_SIZE) {
                    $this->flushBatch($logManager, $batch);
                }
            }
        }
        $this->flushBatch($logManager, $batch);

        $io->success(sprintf(
            'Snapshot complete for %s: %d entities scanned, %d snapshots created, %d skipped.',
            implode(', ', array_map(fn(array $entry): string => $entry['label'], $selected)),
            $scanned,
            $created,
            $skipped,
        ));

        return Command::SUCCESS;
    }

    /** @return list<array{class: string, manager: string, label: string}> */
    private function discoverClasses(): array
    {
        $discovered = [];
        foreach ($this->registry->getManagers() as $managerName => $manager) {
            foreach ($manager->getMetadataFactory()->getAllMetadata() as $metadata) {
                $className = $metadata->getName();
                if (!$this->snapshotter->supportsClass($className, $metadata)) {
                    continue;
                }
                $discovered[] = ['class' => $className, 'manager' => $managerName, 'label' => $className];
            }
        }

        $counts = [];
        foreach ($discovered as $entry) {
            $counts[$entry['class']] = ($counts[$entry['class']] ?? 0) + 1;
        }
        foreach ($discovered as $index => $entry) {
            if ($counts[$entry['class']] > 1) {
                $discovered[$index]['label'] .= sprintf(' (%s)', $entry['manager']);
            }
        }

        return $this->sortClasses($discovered);
    }

    /** @param list<array{class: string, manager: string, label: string}> $classes */
    private function sortClasses(array $classes): array
    {
        usort($classes, static function (array $left, array $right): int {
            return [$left['class'], $left['manager']] <=> [$right['class'], $right['manager']];
        });

        return $classes;
    }

    /** @param list<array{class: string, manager: string, label: string}> $classes */
    private function selectClasses(InputInterface $input, SymfonyStyle $io, array $classes): array
    {
        $selection = $input->getArgument('selection');
        if ($selection === null) {
            $io->section('Available loggable entity classes');
            $io->listing(array_map(fn(int $index): string => sprintf('%d: %s', $index, $classes[$index]['label']), array_keys($classes)));
            $selection = $io->ask('Select classes (for example 0,2-4)');
        }

        $indexes = $this->parseSelection((string) $selection, count($classes));

        return array_map(fn(int $index): array => $classes[$index], $indexes);
    }

    /** @return list<int> */
    private function parseSelection(string $selection, int $classCount): array
    {
        $selection = trim($selection);
        if ($selection === '') {
            throw new \InvalidArgumentException('Selection cannot be empty.');
        }

        $indexes = [];
        foreach (preg_split('/\s*,\s*/', $selection) ?: [] as $part) {
            if (preg_match('/^(\d+)\s*-\s*(\d+)$/', trim($part), $matches)) {
                $start = (int) $matches[1];
                $end = (int) $matches[2];
                if ($start > $end) {
                    throw new \InvalidArgumentException(sprintf('Invalid reversed range "%s".', $part));
                }
                foreach (range($start, $end) as $index) {
                    $indexes[] = $index;
                }
                continue;
            }
            if (!preg_match('/^\d+$/', trim($part))) {
                throw new \InvalidArgumentException(sprintf('Invalid selection "%s".', $part));
            }
            $indexes[] = (int) trim($part);
        }

        $indexes = array_values(array_unique($indexes));
        foreach ($indexes as $index) {
            if ($index < 0 || $index >= $classCount) {
                throw new \InvalidArgumentException(sprintf('Selection index %d is out of range.', $index));
            }
        }

        return $indexes;
    }

    /** @param list<EntityLogEntry> $batch */
    private function flushBatch(object $manager, array &$batch): void
    {
        foreach ($batch as $entry) {
            $manager->persist($entry);
        }
        if ($batch !== []) {
            $manager->flush();
        }
        $batch = [];
    }

    /** @return array{string, string, ?string} */
    private function userContext(): array
    {
        $user = $this->security->getUser();
        $username = $user?->getUserIdentifier() ?? 'anonymous';
        $userId = 'anonymous';
        $manager = $user ? $this->registry->getManagerForClass(get_class($user)) : null;
        if ($user && $manager) {
            $identifier = $this->snapshotter->getIdentifier($manager, $user);
            $userId = $identifier ?? 'anonymous';
        }
        $impersonatedBy = null;
        $token = $this->tokenStorage->getToken();
        if ($token instanceof SwitchUserToken) {
            $impersonatedBy = $token->getOriginalToken()->getUser()?->getUserIdentifier();
        }

        return [$username, $userId, $impersonatedBy];
    }
}
