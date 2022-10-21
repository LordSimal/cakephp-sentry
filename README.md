# CakePHP Sentry Plugin
CakePHP integration for Sentry.

ℹ️ This is a refactored version of https://github.com/Connehito/cake-sentry to remove deprecation warnings introduced in CakePHP 4.4 ℹ️

## Requirements
- PHP 7.4+ / PHP 8.0+
- CakePHP 4.4+
- and a [Sentry](https://sentry.io) account

## Installation
### With composer install.
```
composer require lordsimal/cakephp-sentry
```

## Usage

### Set config files.
```php
// in `config/app.php`
return [
  'Sentry' => [
    'dsn' => '<sentry-dsn-url>'
  ]
];
```

### Loading plugin.
In Application.php
```php
public function bootstrap()
{
    parent::bootstrap();

    $this->addPlugin(\CakeSentry\Plugin::class);
}
```

Or use the cake CLI.
```
bin/cake plugin load LordSimal/CakeSentry
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
        /** @var Client $subject */
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
use Cake\Http\ServerRequest;
use Cake\Http\ServerRequestFactory;
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
            /** @var ServerRequest $request */
            $request = $event->getData('request') ?? ServerRequestFactory::fromGlobals();
            $request->trustProxy = true;

            sentryConfigureScope(function (Scope $scope) use ($request, $event) {
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

## License
The plugin is available as open source under the terms of the [MIT License](https://github.com/lordsimal/cakephp-sentry/blob/master/LICENSE).
