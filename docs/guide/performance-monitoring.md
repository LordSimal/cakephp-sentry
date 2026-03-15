# Performance Monitoring

## General Setup

> [!WARNING]
> This is only available if you are on CakePHP 5 and Version 3+ of this plugin

::: code-group

```php [config/app_local.php]
return [
    'CakeSentry' => [
        'enableQueryLogging' => true,
        'enablePerformanceMonitoring' => true
    ]
    'Sentry' => [
        'dsn' => '<sentry-dsn-url>',
        'traces_sample_rate' => 1,
    ]
];
```

:::

to see SQL query execution and duration inside the performance monitoring section of sentry make sure to enable logging for your desired datasource like so:

::: code-group

```php [config/app_local.php]
return [
    'Datasources' => [
        'default' => [
            'sentryLog' => true,
            // ...
        ],
    ],
```     

:::

## Cache Tracing

> [!WARNING]
> This is only available if you are on CakePHP 5.3+ and Version 3.5+ of this plugin

[CakePHP 5.3 added events](https://book.cakephp.org/5.x/core-libraries/caching.html#cache-events) for cache operations. If you already had performance monitoring enabled, you can now also see cache operations in Sentry.