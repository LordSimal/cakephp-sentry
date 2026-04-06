<?php
declare(strict_types=1);

namespace CakeSentry\Log\Engines;

use CakeSentry\Log\Engine\SentryLog;
use function Cake\Core\deprecationWarning;

$msg = 'Use `CakeSentry\Log\Engine\SentryLog` instead of `CakeSentry\Log\Engines\SentryLog`.';
deprecationWarning('3.5.3', $msg);

class_exists(SentryLog::class);
