# Event Hooks

CakeSentry exposes custom CakePHP events so you can adjust Sentry behavior during setup and capture.

## Available Events

| Event name                        | Description                                              |
|-----------------------------------|----------------------------------------------------------|
| `CakeSentry.Client.afterSetup`    | General client setup, for example release metadata       |
| `CakeSentry.Client.beforeCapture` | Runs before an error or exception is sent to Sentry      |
| `CakeSentry.Client.afterCapture`  | Runs after an error or exception has been sent to Sentry |

## Registering Listeners

Register event listeners in `config/bootstrap.php`:

::: code-group

```php [config/bootstrap.php]
\Cake\Event\EventManager::instance()->on(new SentryOptionsContext());
```

:::

## `CakeSentry.Client.afterSetup`

Use this hook to adjust static Sentry client options such as environment or release:

::: code-group

```php [src/Events/SentryOptionsContext.php]
namespace App\Events;

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

:::

## `CakeSentry.Client.beforeCapture`

Use this hook to attach request-specific metadata before the event is sent:

::: code-group

```php [src/Events/SentryErrorContext.php]
namespace App\Events;

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
                $scope->setTag('app_version', $request->getHeaderLine('App-Version') ?: 1.0);
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

:::

Then register it:

::: code-group

```php [config/bootstrap.php]
\Cake\Event\EventManager::instance()->on(new SentryErrorContext());
```

:::

## `CakeSentry.Client.afterCapture`

Use this hook when you need access to the generated Sentry event id after submission:

::: code-group

```php [src/Events/SentryErrorContext.php]
namespace App\Events;

use Cake\Event\Event;
use Cake\Event\EventListenerInterface;

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

:::
