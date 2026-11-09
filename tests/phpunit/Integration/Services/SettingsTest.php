<?php

declare(strict_types=1);

namespace Acms\Plugins\Turnstile\Tests\Integration\Services;

use Acms\Plugins\Turnstile\Services\Settings;
use Acms\TestingFramework\DatabaseTestCase;
use PHPUnit\Framework\Attributes\Test;

final class SettingsTest extends DatabaseTestCase
{
    private int $blogId;

    protected function setUpDatabase(): void
    {
        $this->blogId = $this->createTestBlog();
    }

    protected function tearDown(): void
    {
        unset($_ENV[Settings::SECRET_KEY_ENV]);
        putenv(Settings::SECRET_KEY_ENV);
        parent::tearDown();
    }

    #[Test]
    public function 設定ページで保存したブログのコンフィグを読む(): void
    {
        $this->saveConfig('turnstile_sitekey', ' site-key ');
        $this->saveConfig('turnstile_secret_key', 'secret-key');
        $this->saveConfig('turnstile_hostnames', "example.com\r\nwww.example.com");

        $settings = Settings::load($this->blogId);

        $this->assertSame('site-key', $settings->siteKey);
        $this->assertSame('secret-key', $settings->secretKey);
        $this->assertSame(['example.com', 'www.example.com'], $settings->hostnames);
    }

    #[Test]
    public function 未設定なら空になる(): void
    {
        $settings = Settings::load($this->blogId);

        $this->assertSame('', $settings->siteKey);
        $this->assertSame('', $settings->secretKey);
        $this->assertSame([], $settings->hostnames);
    }

    #[Test]
    public function envのシークレットキーを設定ページの値より優先する(): void
    {
        $this->saveConfig('turnstile_secret_key', 'from-config');
        $_ENV[Settings::SECRET_KEY_ENV] = 'from-env';

        $this->assertSame('from-env', Settings::load($this->blogId)->secretKey);
    }

    #[Test]
    public function サーバーの環境変数のシークレットキーも読む(): void
    {
        $this->saveConfig('turnstile_secret_key', 'from-config');
        putenv(Settings::SECRET_KEY_ENV . '=from-server-env');

        $this->assertSame('from-server-env', Settings::load($this->blogId)->secretKey);
    }

    private function saveConfig(string $key, string $value): void
    {
        $this->insertTestData('config', [
            'config_key' => $key,
            'config_value' => $value,
            'config_sort' => 1,
            'config_blog_id' => $this->blogId,
        ]);
    }
}
