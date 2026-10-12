# Doctrine1 Bundle

[![Build](https://github.com/diablomedia/doctrine1-bundle/workflows/Build/badge.svg?event=push)](https://github.com/diablomedia/doctrine1-bundle/actions?query=workflow%3ABuild+event%3Apush)
[![Latest Stable Version](https://poser.pugx.org/diablomedia/doctrine1-bundle/v/stable)](https://packagist.org/packages/diablomedia/doctrine1-bundle)
[![Total Downloads](https://poser.pugx.org/diablomedia/doctrine1-bundle/downloads)](https://packagist.org/packages/diablomedia/doctrine1-bundle)
[![License](https://poser.pugx.org/diablomedia/doctrine1-bundle/license)](https://packagist.org/packages/diablomedia/doctrine1-bundle)

Symfony Bundle for Doctrine1 ORM

This is heavily based on the Symfony/Doctrine DoctrineBundle (https://github.com/doctrine/DoctrineBundle) but adapted to work with Doctrine1. This bundle allows you to configure Doctrine1 through Symfony's configuration and also adds a section to the Symfony profiler/debug toolbar so you can view query information in the same way you would with the DoctrineBundle.

## Installation

Install using composer:

```
composer require diablomedia/doctrine1-bundle
```

While this bundle should work with the original Doctrine1 library, we recommend using our fork that is better tested against recent versions of PHP.

```
composer require diablomedia/doctrine1
```

## Configuration

Enable the Bundle in Symfony:

```php
<?php
// config/bundles.php

return [
    // ...
    DiabloMedia\Bundle\Doctrine1Bundle\Doctrine1Bundle::class => ['all' => true],
    // ...
];
```

To configure your database connection, create a `doctrine1.yaml` file in your `config/packages` folder with the necessary connection credentials, here's a sample for mysql:

```yaml
doctrine1:
    default_connection: writer
    connections:
        writer:
            url: '%env(resolve:WRITE_DATABASE_URL)%'
            cache_class: 'Doctrine_Cache_Array'
            enable_query_cache: true
            enable_result_cache: true
        reader:
            url: '%env(resolve:READ_DATABASE_URL)%'
            cache_class: 'Doctrine_Cache_Array'
            enable_query_cache: true
            enable_result_cache: true
```

## Generating models

Generate Doctrine 1 models from your YAML schema with:

```sh
php bin/console doctrine1:generate-models
```

By default, the command reads schemas from `application/doctrine/schema`, writes models to `application/models` (both relative to the project directory), and uses `Doctrine_Record` as the base class. It does not require a database connection.

Customize the paths and base class in `config/packages/doctrine1.yaml`, alongside your connection configuration:

```yaml
doctrine1:
    model_generation:
        schema_path: '%kernel.project_dir%/application/doctrine/schema'
        models_path: '%kernel.project_dir%/application/models'
        base_class: Custom_Record
```

The schema path may point to a YAML file or a directory of schemas. A custom base class must be autoloadable. Doctrine regenerates base models in the output directory's `generated` subdirectory and preserves existing concrete model files.
