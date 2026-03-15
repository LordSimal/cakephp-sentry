# Getting Started

CakeSentry integrates [Sentry](https://sentry.io) with CakePHP applications.

## Requirements

- PHP 7.4+ or PHP 8.0+
- CakePHP 4.4+ or CakePHP 5.0+
- A Sentry account on [sentry.io](https://sentry.io) or a self-hosted Sentry instance

## Plugin Compatibility

| Version | PHP              | CakePHP | Self-hosted Sentry |
|---------|------------------|---------|--------------------|
| `1.x`   | `^7.4` or `^8.0` | `^4.4`  | 🤷🏻               |
| `2.x`   | `^8.1`           | `^5.0`  | 🤷🏻               |
| `3.x`   | `^8.1`           | `^5.0`  | `>= v20.6.0`       |

## Install

```bash
composer require lordsimal/cakephp-sentry
```

## Configure

Add the Sentry DSN and environment to your CakePHP config:

::: code-group

```php [config/app.php]
return [
    'Sentry' => [
        'dsn' => '<sentry-dsn-url>',
        'environment' => 'production',
    ],
];
```

:::

## Load The Plugin

::: code-group

```php [src/Application.php]
public function bootstrap()
{
    parent::bootstrap();

    $this->addPlugin(\CakeSentry\CakeSentryPlugin::class);
}
```

:::

Or with the CakePHP CLI:

```bash
bin/cake plugin load CakeSentry
```

If events are not captured in Sentry, adjust the plugin load order in your application bootstrap.

## Notes For Older CakePHP Versions

> [!NOTE]
> This plugin is a refactored version of [Connehito/cake-sentry](https://github.com/Connehito/cake-sentry) that removes deprecation warnings introduced in CakePHP 4.4. 
> If you are using CakePHP 3.x or CakePHP 4.0 to 4.3, use the original Connehito plugin instead.
