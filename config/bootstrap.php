<?php

use Cake\Core\Configure;
use Cake\Error\ErrorTrap;
use Cake\Error\ExceptionTrap;
use Cake\Log\Log;
use CakeSentry\CakeSentryInit;
use CakeSentry\Error\SentryErrorLogger;
use CakeSentry\Log\Engines\SentryLog;

Configure::write('Error.logger', SentryErrorLogger::class);

(new ErrorTrap(Configure::read('Error')))->register();
(new ExceptionTrap(Configure::read('Error')))->register();

CakeSentryInit::init();

$enableSentryLogs = Configure::read('Sentry.enable_logs');
if ($enableSentryLogs) {
    Log::setConfig('CakeSentry', [
        'className' => SentryLog::class,
        'levels' => ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'],
        'scopes' => [], // Listen for all scopes
    ]);
}
