# Idlab Loggable bundle

This bundle supports Symfony 6.4, 7.x, and 8.x on PHP 8.2 or newer. Symfony 8 requires PHP 8.4 or newer.

## Configuration in doctrine.yaml

To set under the wished connection configuration :
```yaml
doctrine:
  orm:
    entity_manager:
      default:
        idlab_loggable:
          prefix: Idlab\Loggable\Entity
          dir: "%kernel.project_dir%/vendor/idlab/loggable/src/Entity"
```
> **_NOTE:_**  If you are using the short syntax for the ORM configuration, the ``mappings`` key is directly under ``orm:``

## Package file configuration

You can add a config file name "idlab_loggable.yaml" in config/packages in you Symfony project :
```yaml
idlab_loggable:
  enabled: true
  logs_target_connection_name: 'default'
  table_prefix: 'example_table_prefix_'
  disallowed_namespaces: [
    'App\Entity\IgnoredByNamespace'
  ],
  disallowed_classes: [
    'App\Entity\IgnoredByClass'
  ]
```
## Add IdlabLoggable attribute

```php
Import : 
use Idlab\Loggable\Mapping\Attributes\IdlabLoggable;

#[IdlabLoggable]
public ?string $value = null;
```

## Deprecated accessors

Version 2 renamed the log entry properties and database columns from
`createdAt`/`createdBy` to `loggedAt`/`username`. The old accessors remain
available for compatibility but are deprecated:

| Deprecated | Use instead |
| --- | --- |
| `getCreatedAt()` | `getLoggedAt()` |
| `getCreatedBy()` | `getUsername()` |

Update application code to use the replacement methods. The deprecated
accessors may be removed in a future major version.

`EntityLogEntry` is immutable after construction. Version 2 removes all
setters from the entity so persisted audit records cannot be changed through
the entity API. Doctrine can hydrate the private fields without setters.
