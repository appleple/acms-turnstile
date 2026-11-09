<?php

declare(strict_types=1);

namespace Acms\Plugins\Turnstile\Tests\Unit\Services;

use Acms\Plugins\Turnstile\Services\FailureReason;
use Acms\Plugins\Turnstile\Services\Verifier;
use Acms\TestingFramework\TestCase;
use Closure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

final class VerifierTest extends TestCase
{
    /**
     * @var array<mixed>|null 最後に siteverify へ送った値
     */
    private ?array $sent = null;

    #[Test]
    public function 成功し許可したホスト名ならトークンを受け付ける(): void
    {
        $verifier = new Verifier('secret', ['Example.com'], $this->respond('{"success":true,"hostname":"example.com"}'));

        $result = $verifier->verify('token', '203.0.113.1');

        $this->assertTrue($result->success);
        $this->assertNull($result->reason);
        $this->assertSame('example.com', $result->hostname);
    }

    #[Test]
    public function シークレットキーとトークンと訪問者のIPをsiteverifyへ送る(): void
    {
        $verifier = new Verifier('secret', ['example.com'], $this->respond('{"success":true,"hostname":"example.com"}'));

        $verifier->verify('token', '203.0.113.1');

        $this->assertSame(['secret' => 'secret', 'response' => 'token', 'remoteip' => '203.0.113.1'], $this->sent);
    }

    #[Test]
    public function 訪問者のIPが空ならremoteipを送らない(): void
    {
        $verifier = new Verifier('secret', ['example.com'], $this->respond('{"success":true,"hostname":"example.com"}'));

        $verifier->verify('token', '');

        $this->assertNotNull($this->sent);
        $this->assertArrayNotHasKey('remoteip', $this->sent);
    }

    /**
     * @return iterable<string, array{mixed, FailureReason}>
     */
    public static function 通信する前に拒否するトークン(): iterable
    {
        yield '送られていない' => [null, FailureReason::MissingToken];
        yield '空文字' => ['', FailureReason::MissingToken];
        yield '配列（field[] などで送られた）' => [['token'], FailureReason::MissingToken];
        yield '最大長を超える' => [str_repeat('a', Verifier::MAX_TOKEN_LENGTH + 1), FailureReason::InvalidToken];
    }

    #[Test]
    #[DataProvider('通信する前に拒否するトークン')]
    public function 不正なトークンはsiteverifyへ送らずに拒否する(mixed $token, FailureReason $reason): void
    {
        $verifier = new Verifier('secret', ['example.com'], $this->respond('{"success":true,"hostname":"example.com"}'));

        $result = $verifier->verify($token, '203.0.113.1');

        $this->assertFalse($result->success);
        $this->assertSame($reason, $result->reason);
        $this->assertNull($this->sent);
    }

    #[Test]
    public function シークレットキーが未設定なら拒否する(): void
    {
        $verifier = new Verifier('', ['example.com'], $this->respond('{"success":true,"hostname":"example.com"}'));

        $result = $verifier->verify('token', '203.0.113.1');

        $this->assertSame(FailureReason::NotConfigured, $result->reason);
        $this->assertNull($this->sent);
    }

    /**
     * @return iterable<string, array{string, FailureReason, list<string>}>
     */
    public static function 拒否する応答(): iterable
    {
        yield '使用済み' => [
            '{"success":false,"error-codes":["timeout-or-duplicate"]}',
            FailureReason::Rejected,
            ['timeout-or-duplicate'],
        ];
        yield 'success が真偽値でない' => ['{"success":"true","hostname":"example.com"}', FailureReason::Rejected, []];
        yield '別のサイトで取ったトークン' => ['{"success":true,"hostname":"evil.example"}', FailureReason::HostnameMismatch, []];
        yield 'hostname が無い' => ['{"success":true}', FailureReason::HostnameMismatch, []];
        yield 'JSON でない' => ['<html>', FailureReason::RequestFailed, ['invalid-json']];
        yield 'success が無い' => ['{"hostname":"example.com"}', FailureReason::RequestFailed, ['invalid-json']];
    }

    /**
     * @param list<string> $errorCodes
     */
    #[Test]
    #[DataProvider('拒否する応答')]
    public function siteverifyの応答に応じて拒否する(string $body, FailureReason $reason, array $errorCodes): void
    {
        $verifier = new Verifier('secret', ['example.com'], $this->respond($body));

        $result = $verifier->verify('token', '203.0.113.1');

        $this->assertFalse($result->success);
        $this->assertSame($reason, $result->reason);
        $this->assertSame($errorCodes, $result->errorCodes);
    }

    #[Test]
    public function 通信に失敗したら拒否する(): void
    {
        $verifier = new Verifier('secret', ['example.com'], static function (): string {
            throw new RuntimeException('Operation timed out');
        });

        $result = $verifier->verify('token', '203.0.113.1');

        $this->assertSame(FailureReason::RequestFailed, $result->reason);
        $this->assertSame(['Operation timed out'], $result->errorCodes);
    }

    /**
     * @return Closure(string, array<string, string>): string
     */
    private function respond(string $body): Closure
    {
        return function (string $url, array $params) use ($body): string {
            $this->assertSame(Verifier::ENDPOINT, $url);
            $this->sent = $params;

            return $body;
        };
    }
}
