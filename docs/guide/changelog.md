# Changelog

## Version 3.5.0

Added the following features:

- [Queue Integration](https://losimal.github.io/cakephp-sentry/guide/queue-integration.html)
- [Cache Tracing](https://losimal.github.io/cakephp-sentry/guide/performance-monitoring.html#cache-tracing)

## Version 3.4.0

Sentry `>= v25.9.0` added Support for [Logging](https://docs.sentry.io/platforms/php/logs/). This has now been integrated into the [plugin](https://losimal.github.io/cakephp-sentry/guide/logging.html).

## Version 3.3.0

Added PHPUnit 12 support

## Version 3.2.0

HTTP requests being done by the CakePHP HTTP Client are now being traced if you have enabled [Performance Monitoring](https://losimal.github.io/cakephp-sentry/guide/performance-monitoring.html).

## Version 3.1.0

Leverage the new `Server.terminate` event to flush the Sentry queue before the server terminates.

## Version 3.0.0

Refactored Version with breaking changes.

- Sentry PHP SDK updated from `^3.3` to `^4.0`
- `CakeSentryMiddleware` has been renamed to `CakeSentryQueryMiddleware`
- Properties are not prefixed with `_` anymore

The `CakeSentryPerformanceMiddleware` has been added to add support for the [Performance Monitoring Feature](https://docs.sentry.io/product/sentry-basics/performance-monitoring/).
See the [Performance Monitoring documentation](https://losimal.github.io/cakephp-sentry/guide/performance-monitoring.html) for more details.


## Version 2.0.0

First CakePHP 5 compatible release with no new features 


## Version 1.0.0

First stable release for CakePHP 4.4+ and Sentry SDK 3.x as CakePHP changed the way errors are handled in 4.4
