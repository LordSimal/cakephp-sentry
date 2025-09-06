<?php
declare(strict_types=1);

namespace CakeSentry\Log\Engines;

use Cake\Log\Engine\BaseLog;
use Psr\Log\LogLevel;
use Sentry\Logs\Logs;
use Stringable;

class SentryLog extends BaseLog
{
    /**
     * @param array $config
     */
    public function __construct(array $config = [])
    {
        parent::__construct($config);
    }

    /**
     * @param string $level
     * @param \Stringable|string $message
     * @param array $context
     * @return void
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ParameterTypeHint.MissingNativeTypeHint
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $message = $this->interpolate($message, $context);
        $message = $this->formatter->format($level, $message, $context);

        $sentryLogger = Logs::getInstance();

        match ($level) {
            LogLevel::EMERGENCY, LogLevel::ALERT, LogLevel::CRITICAL => $sentryLogger->fatal($message, [], $context),
            LogLevel::ERROR => $sentryLogger->error($message),
            LogLevel::WARNING => $sentryLogger->warn($message, [], $context),
            LogLevel::NOTICE, LogLevel::INFO => $sentryLogger->info($message, [], $context),
            LogLevel::DEBUG => $sentryLogger->debug($message, [], $context),
            default => $sentryLogger->trace($message, [], $context),
        };

        $sentryLogger->flush();
    }
}
