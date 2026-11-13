<?php

declare(strict_types=1);

namespace Acms\Plugins\Turnstile\Tests\Unit\Services;

use Acms\Plugins\Turnstile\Services\Scripts;
use Acms\TestingFramework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class ScriptsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function 設置場所(): iterable
    {
        yield 'ドキュメントルート' => ['', '/extension/plugins/Turnstile/assets/turnstile.js'];
        yield 'サブディレクトリ' => ['cms/', '/cms/extension/plugins/Turnstile/assets/turnstile.js'];
    }

    #[Test]
    #[DataProvider('設置場所')]
    public function 同梱のturnstile_jsをインストール先からのURLで登録する(string $dirOffset, string $expected): void
    {
        $definitions = Scripts::definitions($dirOffset, '/extension/plugins/');

        $this->assertSame(Scripts::HANDLE, $definitions[0]['handle']);
        $this->assertSame($expected, $definitions[0]['src']);
    }

    #[Test]
    public function api_jsはturnstile_jsのあとに非同期で読み込む(): void
    {
        $api = Scripts::definitions('', '/extension/plugins/')[1];

        $this->assertSame(Scripts::API_HANDLE, $api['handle']);
        $this->assertSame(Scripts::API_URL, $api['src']);
        $this->assertSame([Scripts::HANDLE], $api['options']['deps'] ?? null);
        $this->assertSame(['async' => true, 'defer' => true], $api['options']['attrs'] ?? null);
    }

    #[Test]
    public function api_jsの読み込み後にturnstile_jsの関数を呼ぶ(): void
    {
        $this->assertStringContainsString('render=explicit', Scripts::API_URL);
        $this->assertStringContainsString('onload=acmsTurnstileOnload', Scripts::API_URL);
    }
}
