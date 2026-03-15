# Query Logging

CakeSentry can include SQL query logging in captured Sentry events.

## Enable Query Logging

::: code-group

```php [config/app_local.php]
return [
    'CakeSentry' => [
        'enableQueryLogging' => true,
    ],
];
```

:::

## Include Schema Reflection Queries

If you also want schema reflection queries included:

::: code-group

```php [config/app_local.php]
return [
    'CakeSentry' => [
        'includeSchemaReflection' => true,
    ],
];
```

:::
