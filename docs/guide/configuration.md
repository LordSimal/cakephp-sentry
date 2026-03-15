# Configuration

Everything inside the `Sentry` configuration key is passed to `\Sentry\init()`.

::: code-group

```php [config/app_local.php]
return [
    'Sentry' => [
        'dsn' => '<sentry-dsn-url>',
        'environment' => 'production',
    ],
];
```

:::

Refer to Sentry's official documentation for available options:

- [Sentry configuration](https://docs.sentry.io/error-reporting/configuration/?platform=php)
- [PHP SDK options](https://docs.sentry.io/platforms/php/#php-specific-options)

## Ignoring Specific Exceptions

Use CakePHP's error configuration to skip noisy exceptions that should not be reported:

::: code-group

```php [config/app_local.php]
use Cake\Http\Exception\NotFoundException;
use Cake\Http\Exception\MissingControllerException;
use Cake\Routing\Exception\MissingRouteException;

return [
    'Error' => [
        'skipLog' => [
            NotFoundException::class,
            MissingRouteException::class,
            MissingControllerException::class,
        ],
    ],
];
```

:::

See the [CakePHP Cookbook error configuration](https://book.cakephp.org/4/en/development/errors.html#error-exception-configuration) for additional details.
