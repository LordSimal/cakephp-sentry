# Queue Integration

> [!WARNING]
> This is only available if you are on **CakePHP 5** and **Version 3.5+** of this plugin

To get queue insights working, your application and/or queue plugin needs to dispatch events according to the following structure:

## `CakeSentry.Queue.enqueue`

```php
$this->dispatchEvent('CakeSentry.Queue.enqueue', [
    'class' => '\App\Job\ExampleJob',
    'id' => 'unique-job-id',
    'queue' => 'some-queue-name',
    'data' => ['some' => 'data'],
]);
```

- `class`: Optional, but recommended. The queue job class name.
- `id`: Optional, but recommended. A unique identifier for the enqueued job.
- `queue`: Optional. Defaults to `default`.
- `data`: Optional. Defaults to `[]`.

## `CakeSentry.Queue.beforeExecute`

```php
$this->dispatchEvent('CakeSentry.Queue.beforeExecute', [
    'class' => '\App\Job\ExampleJob',
    'sentry_trace' => '<sentry-trace-header-value>',
    'sentry_baggage' => '<sentry-baggage-header-value>',
]);
```

- `class`: Optional, but recommended. The queue job class name.
- `sentry_trace`: Optional. The Sentry trace header value propagated from the producer.
- `sentry_baggage`: Optional. The Sentry baggage header value propagated from the producer.

## `CakeSentry.Queue.afterExecute` on Success

```php
$this->dispatchEvent('CakeSentry.Queue.afterExecute', [
    'id' => 'unique-job-id',
    'queue' => 'some-queue-name',
    'data' => ['some' => 'data'],
    'execution_time' => 123,
    'retry_count' => 0,
]);
```

- `id`: Optional, but recommended. The unique identifier for the executed job.
- `queue`: Optional. Defaults to `default`.
- `data`: Optional. Defaults to `[]`.
- `execution_time`: Optional. Execution time in milliseconds.
- `retry_count`: Optional. Number of retries attempted before success.

## `CakeSentry.Queue.afterExecute` on Failure

```php
$this->dispatchEvent('CakeSentry.Queue.afterExecute', [
    'id' => 'unique-job-id',
    'queue' => 'some-queue-name',
    'data' => ['some' => 'data'],
    'execution_time' => 123,
    'retry_count' => 0,
    'exception' => $exception,
]);
```

- `id`: Optional, but recommended. The unique identifier for the executed job.
- `queue`: Optional. Defaults to `default`.
- `data`: Optional. Defaults to `[]`.
- `execution_time`: Optional. Execution time in milliseconds.
- `retry_count`: Optional. Number of retries attempted before failure.
- `exception`: Required. The exception thrown while processing the job.
