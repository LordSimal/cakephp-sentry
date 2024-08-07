# CakePHP Sentry Plugin

[![Latest Stable Version](https://poser.pugx.org/lordsimal/cakephp-sentry/v)](https://packagist.org/packages/lordsimal/cakephp-sentry) [![Total Downloads](https://poser.pugx.org/lordsimal/cakephp-sentry/downloads)](https://packagist.org/packages/lordsimal/cakephp-sentry) [![Latest Unstable Version](https://poser.pugx.org/lordsimal/cakephp-sentry/v/unstable)](https://packagist.org/packages/lordsimal/cakephp-sentry) [![License](https://poser.pugx.org/lordsimal/cakephp-sentry/license)](https://packagist.org/packages/lordsimal/cakephp-sentry) [![PHP Version Require](https://poser.pugx.org/lordsimal/cakephp-sentry/require/php)](https://packagist.org/packages/lordsimal/cakephp-sentry)
[![codecov](https://codecov.io/gh/LordSimal/cakephp-sentry/branch/main/graph/badge.svg?token=99W08MNO6S)](https://codecov.io/gh/LordSimal/cakephp-sentry)

CakePHP integration for Sentry.

ℹ️ This is a refactored version of https://github.com/Connehito/cake-sentry to remove deprecation warnings introduced in CakePHP 4.4

ℹ️ If you are using CakePHP 4.4+ please use the 1.x version of this plugin

ℹ️ If you are using CakePHP 3.x or 4.0 - 4.3 please use the plugin from Connehito linked above

## Requirements
- PHP 8.1+
- CakePHP 5+
- and a [Sentry](https://sentry.io) account
  - if you use self-hosted sentry make sure you are on at least version `>= v20.6.0`

## Version table
|     | PHP              | CakePHP | self-hosted Sentry |
|-----|------------------|---------|--------------------|
| 1.x | `^7.4 \|\| ^8.0` | `^4.4`  | 🤷🏻               |
| 2.x | `^8.1`           | `^5.0`  | 🤷🏻               |
| 3.x | `^8.1`           | `^5.0`  | `>= v20.6.0`       |

## Installation
```
composer require lordsimal/cakephp-sentry
```

## Usage

### Set config files
```php
// in `config/app.php`
return [
    'Sentry' => [
        'dsn' => '<sentry-dsn-url>',
        'environment' => 'production',
    ]
];
```

### Loading plugin
In Application.php

```php
public function bootstrap()
{
    parent::bootstrap();

    $this->addPlugin(\CakeSentry\CakeSentryPlugin::class);
}
```

Or use the cake CLI.
```
bin/cake plugin load CakeSentry
```

That's all! 🎉

⚠️️ If events (error/exception) are not captured in Sentry try changing the order in which the plugins are loaded.

### Advanced Usage

#### Ignore specific exceptions
You can filter out noisy exceptions which should not be debugged further.

```php
// in `config/app.php`
'Error' => [
    'skipLog' => [
        NotFoundException::class,
        MissingRouteException::class,
        MissingControllerException::class,
    ],
]
```

Also see [CakePHP Cookbook](https://book.cakephp.org/4/en/development/errors.html#error-exception-configuration)

### Set Options
Everything inside the `'Sentry'` configuration key will be passed to `\Sentry\init()`.
Please check Sentry's official documentation on [about configuration](https://docs.sentry.io/error-reporting/configuration/?platform=php) and [about php-sdk's configuraion](https://docs.sentry.io/platforms/php/#php-specific-options).

CakeSentry also provides custom event hooks to set dynamic values.

| Event Name                        | Description                                          |
|-----------------------------------|------------------------------------------------------|
| `CakeSentry.Client.afterSetup`    | General config for e.g. a release info               |
| `CakeSentry.Client.beforeCapture` | Before an error or exception is being sent to sentry |
| `CakeSentry.Client.afterCapture`  | After an error or exception has been sent to sentry  |

### Example for `CakeSentry.Client.afterSetup`

```php
use Cake\Event\Event;
use Cake\Event\EventListenerInterface;

class SentryOptionsContext implements EventListenerInterface
{
    public function implementedEvents(): array
    {
        return [
            'CakeSentry.Client.afterSetup' => 'setServerContext',
        ];
    }

    public function setServerContext(Event $event): void
    {
        /** @var \CakeSentry\Http\SentryClient $subject */
        $subject = $event->getSubject();
        $options = $subject->getHub()->getClient()->getOptions();

        $options->setEnvironment('test_app');
        $options->setRelease('3.0.0@dev');
    }
}
```

And in `config/bootstrap.php`
```php
\Cake\Event\EventManager::instance()->on(new SentryOptionsContext());
```

### Example for `CakeSentry.Client.beforeCapture`

```php
use Cake\Event\Event;
use Cake\Event\EventListenerInterface;
use Sentry\State\Scope;

use function Sentry\configureScope as sentryConfigureScope;

class SentryErrorContext implements EventListenerInterface
{
    public function implementedEvents(): array
    {
        return [
            'CakeSentry.Client.beforeCapture' => 'setContext',
        ];
    }

    public function setContext(Event $event): void
    {
        if (PHP_SAPI !== 'cli') {
            sentryConfigureScope(function (Scope $scope) use ($event) {
                $request = \Cake\Routing\Router::getRequest();
                $scope->setTag('app_version',  $request->getHeaderLine('App-Version') ?: 1.0);
                $exception = $event->getData('exception');
                if ($exception) {
                    assert($exception instanceof \Exception);
                    $scope->setTag('status', $exception->getCode());
                }
                $scope->setUser(['ip_address' => $request->clientIp()]);
                $scope->setExtras([
                    'foo' => 'bar',
                    'request attributes' => $request->getAttributes(),
                ]);
            });
        }
    }
}
```

And in `config/bootstrap.php`
```php
\Cake\Event\EventManager::instance()->on(new SentryErrorContext());
```

### Example for `CakeSentry.Client.afterCapture`

```php
use Cake\Event\Event;

class SentryErrorContext implements EventListenerInterface
{
    public function implementedEvents(): array
    {
        return [
            'CakeSentry.Client.afterCapture' => 'callbackAfterCapture',
        ];
    }

    public function callbackAfterCapture(Event $event): void
    {
        $lastEventId = $event->getData('lastEventId');
    }
}
```

### Query logging (optional)

If you want sentry events to also have query logging enabled you can do this via your config:

```php
'CakeSentry' => [
    'enableQueryLogging' => true
]
```

If you want queries related to schema reflection also inside your events then you can enable that via

```php
'CakeSentry' => [
    'includeSchemaReflection' => true
]
```

### Performance monitoring (optional)

If you want to use the performance monitoring feature of sentry you have to enable these 2 settings

```php
'CakeSentry' => [
    'enableQueryLogging' => true,
    'enablePerformanceMonitoring' => true
]
```

as well as set the corresponding [Sentry SDK options](https://docs.sentry.io/platforms/php/performance/#configure)

```php
'Sentry' => [
    'dsn' => '<sentry-dsn-url>',
    'traces_sample_rate' => 1,
]
```

to see SQL query execution and duration inside the performance monitoring section of sentry make sure to enable logging for your desired datasource like so:
```
'Datasources' => [
    'default' => [
        'sentryLog' => true
    ]
]
```

## Upgrade from 2 to 3

There are a few major changes from 2.0 to 3.0

- The Sentry PHP SDK was upgraded from `^3.3` to [^4.0](https://github.com/getsentry/sentry-php/releases/tag/4.0.0)
- `CakeSentryMiddleware` has been renamed to `CakeSentryQueryMiddleware`
- Properties are not prefixed `_` anymore
- The `CakeSentryPerformanceMiddleware` has been added to add support for the [Performance Monitoring Feature](https://docs.sentry.io/product/performance/)

## License
The plugin is available as open source under the terms of the [MIT License](https://github.com/lordsimal/cakephp-sentry/blob/master/LICENSE).
