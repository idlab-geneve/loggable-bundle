# CHANGELOG 

## [Unrelead] - 2026-03-04

- Add exceptions for all log creations

## [1.1.0] - 2026-03-04

- Do not create a log if no properties are loggable + add test about this
- ID not yet known on flush : preserve collections to create logs in postPersist and postUpdate
- Get differences only for collections updates and not whole after changes
- Add collection action column

## [1.0.6] - 2026-03-03

- If createdBy is null, set 'anonymous' as value
- Fix README.md

## [1.0.5] - 2026-02-26

- Init Idlab Loggable library
- Add Proxies as default disallowed namespace merged to config
- Fix TreeBuilder for array of strings in idlab_loggable configuration file
- Add tests for ignored namespaces and classes
- Update README.md for configuration
- Fix dir in README.md