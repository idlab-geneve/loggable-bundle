<?php

namespace Idlab\Loggable\Config;

class IdlabLoggableConfig
{
    public function __construct(
        public readonly bool $enabled,
        public readonly string $loginTargetConnectionName,
        public readonly string $tablePrefix,
        public readonly array $disallowedNamespaces,
        public readonly array $disallowedClasses,
    ) {}
}
