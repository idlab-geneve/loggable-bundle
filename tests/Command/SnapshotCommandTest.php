<?php

namespace Idlab\Loggable\Tests\Command;

use Idlab\Loggable\Command\SnapshotCommand;
use Idlab\Loggable\Entity\EntityLogEntry;
use PHPUnit\Framework\TestCase;

final class SnapshotCommandTest extends TestCase
{
    public function testSnapshotActionAndCommandNameArePublic(): void
    {
        self::assertSame('snapshot', EntityLogEntry::ACTION_SNAPSHOT);
        self::assertSame('idlab:loggable:snapshot', SnapshotCommand::getDefaultName());
    }

    public function testClassesAreSortedByClassAndManager(): void
    {
        $command = (new \ReflectionClass(SnapshotCommand::class))->newInstanceWithoutConstructor();
        $method = (new \ReflectionClass($command))->getMethod('sortClasses');
        $method->setAccessible(true);

        $classes = [
            ['class' => 'Zed\\Entity', 'manager' => 'default', 'label' => 'Zed\\Entity'],
            ['class' => 'App\\Entity', 'manager' => 'secondary', 'label' => 'App\\Entity (secondary)'],
            ['class' => 'App\\Entity', 'manager' => 'default', 'label' => 'App\\Entity (default)'],
        ];

        self::assertSame([
            $classes[2],
            $classes[1],
            $classes[0],
        ], $method->invoke($command, $classes));
    }

    /** @dataProvider validSelections */
    public function testSelectionParsing(string $selection, array $expected): void
    {
        $command = (new \ReflectionClass(SnapshotCommand::class))->newInstanceWithoutConstructor();
        $method = (new \ReflectionClass($command))->getMethod('parseSelection');
        $method->setAccessible(true);

        self::assertSame($expected, $method->invoke($command, $selection, 10));
    }

    public static function validSelections(): iterable
    {
        yield ['0', [0]];
        yield ['0, 3, 7', [0, 3, 7]];
        yield ['2-5', [2, 3, 4, 5]];
        yield ['0,2-4,8,3', [0, 2, 3, 4, 8]];
    }

    /** @dataProvider invalidSelections */
    public function testInvalidSelectionFails(string $selection): void
    {
        $command = (new \ReflectionClass(SnapshotCommand::class))->newInstanceWithoutConstructor();
        $method = (new \ReflectionClass($command))->getMethod('parseSelection');
        $method->setAccessible(true);

        $this->expectException(\InvalidArgumentException::class);
        $method->invoke($command, $selection, 3);
    }

    public static function invalidSelections(): iterable
    {
        yield [''];
        yield ['1-0'];
        yield ['0,foo'];
        yield ['3'];
    }
}
