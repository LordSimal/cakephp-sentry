# Logging

> [!WARNING]
> This is only available if 
> - you are on CakePHP 5+, 
> - Version 3.4+ of this plugin and
> - you use Sentry SaaS or self-hosted Sentry version `>= v25.9.0`

Use one of the following configurations to enable logging in Sentry:

::: code-group

```php [config/app_local.php]
return [
    'Log' => [
        // Other already existing log configs
        'sentry' => [
            'className' => \CakeSentry\Log\Engines\SentryLog::class,
            'levels' => ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'],
            'scopes' => [], // Listen for all scopes
        ],
    ]
];
```

```php [ config/bootstrap.php]
\Cake\Log\Log::setConfig('CakeSentry', [
    'className' => \CakeSentry\Log\Engines\SentryLog::class,
    'levels' => ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'],
    'scopes' => [], // Listen for all scopes
]);
```

:::

Adjust the levels and scopes to your needs.

Finally, you have to enable the enable_logs flag in the Sentry SDK as well via:

::: code-group

```php [config/app_local.php]
return [
    'Sentry' => [
        'dsn' => '<sentry-dsn-url>',
        'enable_logs' => true,
    ],
];
```

:::