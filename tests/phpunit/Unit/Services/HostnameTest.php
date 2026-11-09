<?php

declare(strict_types=1);

namespace Acms\Plugins\Turnstile\Tests\Unit\Services;

use Acms\Plugins\Turnstile\Services\Hostname;
use Acms\TestingFramework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class HostnameTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function Hostヘッダー(): iterable
    {
        yield 'ポートなし' => ['example.com', 'example.com'];
        yield 'ポートあり' => ['example.com:8080', 'example.com'];
        yield '大文字' => ['Example.COM', 'example.com'];
        yield 'IPv6' => ['[::1]', '[::1]'];
        yield 'IPv6 とポート' => ['[::1]:8080', '[::1]'];
        yield '空' => ['', ''];
    }

    #[Test]
    #[DataProvider('Hostヘッダー')]
    public function Hostヘッダーからポートを除く(string $host, string $expected): void
    {
        $this->assertSame($expected, Hostname::fromHostHeader($host));
    }

    #[Test]
    public function 改行とカンマと空白で区切ったホスト名を配列にする(): void
    {
        $this->assertSame(
            ['example.com', 'www.example.com', 'example.jp'],
            Hostname::parseList("Example.com\r\nwww.example.com, example.jp example.com\n")
        );
    }

    #[Test]
    public function 空なら空の配列を返す(): void
    {
        $this->assertSame([], Hostname::parseList(" \n "));
    }
}
