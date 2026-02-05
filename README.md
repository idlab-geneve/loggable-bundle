# Idlab Loggable bundle

## Configuration in doctrine.yaml

To set under the wished connection configuration :

    idlab_loggable:
        prefix: Idlab\Loggable\Entity
        dir: "%kernel.project_dir%/vendor/idlab_loggable/src/Entity"

## Package file configuration

You can add a config file name "idlab_loggable.yaml" in config/packages in you Symfony project : 

    idlab_loggable:
        enabled: true
        disallowed_namespaces: []
        disallowed_classes: []
        logs_target_connection_name: 'default'
        table_prefix: 'example_table_prefix'