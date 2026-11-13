<?php

declare(strict_types=1);

namespace PhotosSh\WordPress\Tests\Unit;

use PhotosSh\WordPress\Admin\SettingsPage;
use PhotosSh\WordPress\ClientFactory;
use PhotosSh\WordPress\NotConfiguredException;
use PhotosSh\WordPress\Settings;
use PhotosSh\WordPress\Tests\Support\WpStubs;
use PHPUnit\Framework\TestCase;

final class SettingsTest extends TestCase
{
    protected function setUp(): void
    {
        WpStubs::reset();
        putenv('PHOTOS_API_KEY');
    }

    protected function tearDown(): void
    {
        putenv('PHOTOS_API_KEY');
    }

    public function testNoKey(): void
    {
        $settings = new Settings();

        self::assertSame('', $settings->api_key());
        self::assertSame(Settings::SOURCE_NONE, $settings->api_key_source());
        self::assertSame('', $settings->masked_key());
        self::assertFalse($settings->is_key_locked());
        self::assertSame('https://api.photos.sh', $settings->base_url());

        $this->expectException(NotConfiguredException::class);
        (new ClientFactory($settings))->create();
    }

    public function testTheSavedKeyAndBaseUrl(): void
    {
        WpStubs::$options[Settings::OPTION] = ['api_key' => ' sk-0123456789abcdef ', 'base_url' => 'http://127.0.0.1:8098/api/'];
        $settings = new Settings();

        self::assertSame('sk-0123456789abcdef', $settings->api_key());
        self::assertSame(Settings::SOURCE_OPTION, $settings->api_key_source());
        self::assertFalse($settings->is_key_locked());
        self::assertSame('http://127.0.0.1:8098/api', $settings->base_url());
        self::assertFalse($settings->is_default_base_url());
    }

    public function testTheEnvironmentWinsOverTheSavedKey(): void
    {
        WpStubs::$options[Settings::OPTION] = ['api_key' => 'saved-key-0123456789'];
        putenv('PHOTOS_API_KEY=env-key-0123456789');
        $settings = new Settings();

        self::assertSame('env-key-0123456789', $settings->api_key());
        self::assertSame(Settings::SOURCE_ENVIRONMENT, $settings->api_key_source());
        self::assertTrue($settings->is_key_locked());
    }

    public function testTheMaskedKeyShowsOnlyTheLastFourCharacters(): void
    {
        WpStubs::$options[Settings::OPTION] = ['api_key' => 'sk-0123456789abcdef'];
        $masked = (new Settings())->masked_key();

        self::assertStringEndsWith('cdef', $masked);
        self::assertStringNotContainsString('0123456789ab', $masked);

        WpStubs::$options[Settings::OPTION] = ['api_key' => 'short'];
        self::assertStringNotContainsString('short', (new Settings())->masked_key());
        self::assertStringNotContainsString('hort', (new Settings())->masked_key());
    }

    public function testSanitizeKeepsTheSavedKeyWhenTheFieldIsEmpty(): void
    {
        WpStubs::$options[Settings::OPTION] = ['api_key' => 'saved-key', 'base_url' => ''];

        self::assertSame(
            ['api_key' => 'saved-key', 'base_url' => ''],
            (new Settings())->sanitize(['api_key' => '  ', 'base_url' => '']),
        );
    }

    public function testSanitizeReplacesAndRemovesTheKey(): void
    {
        WpStubs::$options[Settings::OPTION] = ['api_key' => 'saved-key', 'base_url' => ''];
        $settings = new Settings();

        self::assertSame('new-key', $settings->sanitize(['api_key' => " new-key\n"])['api_key']);
        self::assertSame('', $settings->sanitize(['api_key' => 'ignored', 'remove_key' => '1'])['api_key']);
        self::assertSame('saved-key', $settings->sanitize('nonsense')['api_key']);
    }

    public function testSanitizeIsStableWhenWordPressRunsItTwice(): void
    {
        $settings = new Settings();
        $once = $settings->sanitize(['api_key' => 'new-key', 'base_url' => 'http://127.0.0.1:8098/api/']);

        self::assertSame(['api_key' => 'new-key', 'base_url' => 'http://127.0.0.1:8098/api'], $once);
        self::assertSame($once, $settings->sanitize($once));
    }

    public function testSanitizeTheBaseUrl(): void
    {
        WpStubs::$options[Settings::OPTION] = ['api_key' => '', 'base_url' => 'http://old.test/api'];
        $settings = new Settings();

        self::assertSame('', $settings->sanitize(['base_url' => ''])['base_url'], 'empty means the default');
        self::assertSame('', $settings->sanitize(['base_url' => 'https://api.photos.sh/'])['base_url']);
        self::assertSame('https://eu.photos.test/api', $settings->sanitize(['base_url' => ' https://eu.photos.test/api/ '])['base_url']);

        self::assertSame('http://old.test/api', $settings->sanitize(['base_url' => 'ftp://files.test/api'])['base_url']);
        self::assertSame('http://old.test/api', $settings->sanitize(['base_url' => 'javascript:alert(1)'])['base_url']);
        self::assertSame(['photos_sh_base_url', 'photos_sh_base_url'], WpStubs::$settingsErrors);
    }

    public function testTheAccountLine(): void
    {
        self::assertSame(
            'Connected to the account "sdk": unlimited use.',
            SettingsPage::account_line(['account' => 'sdk', 'unlimited' => true]),
        );
        self::assertSame(
            'Connected to the account "shop": 1,250 credits left.',
            SettingsPage::account_line(['account' => 'shop', 'credits' => 1250, 'prices' => ['upscale' => 2]]),
        );
        self::assertSame('Connected: 1 credit left.', SettingsPage::account_line(['credits' => 1]));
        self::assertSame('Connected to the account "shop": 0 credits left.', SettingsPage::account_line(['account' => 'shop', 'credits' => 0]));
        self::assertSame('Connected.', SettingsPage::account_line([]));
    }
}
