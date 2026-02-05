# Idlab Loggable

## Configuration in services.yaml :

Add the logger listener. Set the connection name as first argument (the default value is 'default') :

    App\Tools\IdlabLoggable\EventListener\EntityLogEntryListener:
      tags:
        - name: 'doctrine.orm.entity_listener'
      arguments: ['eco21_logs']

By default, the table name is 'entity_log_entries'.
OPTIONAL : Prefix the table is possible. If you want, you can set the parameter and add the listener in the services.yaml :

    parameters:
        table_prefix:
          idlab_loggable: prefix_

    App\Tools\IdlabLoggable\EventListener\TablePrefixListener:
      tags:
        - name: 'doctrine.orm.entity_listener'
      calls:
        - [ setConfig, [ '%table_prefix%' ] ]

## Configuration in doctrine.yaml

To set under the wished connection configuration :

    IdlabLoggable:
        dir: '%kernel.project_dir%/src/Tools/IdlabLoggable/Entity'
        prefix: App\Tools\IdlabLoggable\Entity

## Table name

The table name can be replaced on the App\Tools\IdlabLoggable\Entity\EntityLogEntry entity. 