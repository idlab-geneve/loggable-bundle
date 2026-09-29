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
  snapshot_on_delete: false
  include_inverse_associations: false
  logs_target_connection_name: 'default'
  table_prefix: 'example_table_prefix_'
  disallowed_namespaces: [
    'App\Entity\IgnoredByNamespace'
  ],
  disallowed_classes: [
    'App\Entity\IgnoredByClass'
  ]
```

Set `snapshot_on_delete` to `true` to include a snapshot of all loggable
properties in the `data` field of remove log entries. Scalar values use the
same serialization as other log entries; associations are stored by identifier
and collections as arrays of identifiers. The option defaults to `false`.
After changing this setting, rebuild or clear the Symfony container cache.

By default, only owning-side Doctrine associations are stored. Inverse
associations, such as an aggregate's `OneToMany` collection or an inverse
`ManyToMany` collection, are omitted because they are derived from the owning
side. Set `include_inverse_associations` to `true` to make inverse associations
eligible for logging when Doctrine reports them as changed.

Snapshots contain the complete current value of each included association.
Update logs contain only changes: collection changes are stored as identifier
diffs under `__inserted__` and `__removed__`, for example:

```json
{
  "children": {
    "__inserted__": ["12"],
    "__removed__": ["8"]
  }
}
```

Changing an owning-side association does not automatically create a separate
log for the inverse-side entity. Doctrine persists associations through their
owning side, so an inverse collection must itself be changed to produce an
inverse collection update log. The option also does not make Doctrine persist
changes made only to an inverse side.

## Create entity snapshots

Use the `idlab:loggable:snapshot` command to create a snapshot log for every
persisted entity of one or more selected loggable classes:

```bash
php bin/console idlab:loggable:snapshot 0,2-4
```

Without a selection, the command lists the supported classes and asks for a
selection. Use `--exclude-created` to skip entities that already have a create
log, or `--skip-unchanged` to skip entities whose latest snapshot is unchanged.
Selections are zero-indexed and support comma-separated indexes and inclusive
ranges.

## Add IdlabLoggable attribute

```php
Import : 
use Idlab\Loggable\Mapping\Attributes\IdlabLoggable;

#[IdlabLoggable]
public ?string $value = null;
```

To log all Doctrine-mapped properties of an entity, put the attribute on the
class. Exclude individual properties with `IdlabLoggableExclude`:

```php
use Idlab\Loggable\Mapping\Attributes\IdlabLoggable;
use Idlab\Loggable\Mapping\Attributes\IdlabLoggableExclude;

#[IdlabLoggable]
class Order
{
    private string $status;

    #[IdlabLoggableExclude]
    private string $internalNote;
}
```

Property-level `IdlabLoggable` remains supported for selective logging. A
property exclusion takes precedence over both class-level and property-level
logging attributes. Backed enums are logged using their backing value; pure
enums are logged using their case name.

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
