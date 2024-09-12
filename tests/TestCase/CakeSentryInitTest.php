<?php
declare(strict_types=1);

namespace CakeSentry\Test;

use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use CakeSentry\CakeSentryInit;

class CakeSentryInitTest extends TestCase
{
    /**
     * @inheritDoc
     */
    public function setUp(): void
    {
        parent::setUp();

        Configure::write('Sentry.dsn', 'https://yourtoken@example.com/yourproject/1');
    }

    /**
     * Check the configuration values are merged into the default-config.
     */
    public function testSetUpClientMergeConfig(): void
    {
        $userConfig = [
            'dsn' => false,
            'in_app_exclude' => ['/app/vendor', '/app/tmp',],
            'server_name' => 'test-server',
        ];

        Configure::write('Sentry', $userConfig);
        CakeSentryInit::init();

        $sentryConfig = CakeSentryInit::getConfig('sentry');
        $this->assertSame([APP], $sentryConfig['prefixes'], 'Default value not applied');
        $this->assertSame($userConfig['in_app_exclude'], $sentryConfig['in_app_exclude'], 'Default value is not overwritten');
        $this->assertSame(false, $sentryConfig['dsn'], 'Set value is not addes');
    }
}
