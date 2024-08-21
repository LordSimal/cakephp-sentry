<?php
declare(strict_types=1);

namespace CakeSentry;

use Cake\Core\Configure;
use Cake\Core\StaticConfigTrait;
use Cake\Event\Event;
use Cake\Event\EventManager;
use Cake\Utility\Hash;
use function Sentry\init;

class CakeSentryInit
{
    use StaticConfigTrait;

    /**
     * @var array|array<array>
     */
    protected static array $_defaultConfig = [
        'sentry' => [
            'prefixes' => [
                APP,
            ],
            'in_app_exclude' => [
                ROOT . DS . 'vendor' . DS,
            ],
        ],
    ];

    /**
     * @return void
     */
    public static function init(): void
    {
        $userConfig = Configure::read('Sentry');
        if ($userConfig) {
            self::$_config['sentry'] = array_merge(self::$_defaultConfig['sentry'], $userConfig);
        }

        $config = self::getConfig('sentry');
        if (is_array($config) && Hash::check($config, 'dsn')) {
            init($config);
            $event = new Event('CakeSentry.Client.afterSetup');
            EventManager::instance()->dispatch($event);
        }
    }
}
