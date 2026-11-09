<?php

declare(strict_types=1);

namespace Acms\Plugins\Turnstile\Tests\Unit\Services;

use Acms\Plugins\Turnstile\Services\FailureReason;
use Acms\Plugins\Turnstile\Services\VerifyResult;
use Acms\TestingFramework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class VerifyResultTest extends TestCase
{
    /**
     * @return iterable<string, array{VerifyResult, bool}>
     */
    public static function 失敗の結果(): iterable
    {
        yield 'シークレットキー未設定' => [VerifyResult::failure(FailureReason::NotConfigured), true];
        yield '通信の失敗' => [VerifyResult::failure(FailureReason::RequestFailed, ['timeout']), true];
        yield 'シークレットキーの誤り' => [VerifyResult::failure(FailureReason::Rejected, ['invalid-input-secret']), true];
        yield 'Cloudflare の内部エラー' => [VerifyResult::failure(FailureReason::Rejected, ['internal-error']), true];
        yield 'トークン無し' => [VerifyResult::failure(FailureReason::MissingToken), false];
        yield '使用済みのトークン' => [VerifyResult::failure(FailureReason::Rejected, ['timeout-or-duplicate']), false];
        yield '偽造したトークン' => [VerifyResult::failure(FailureReason::Rejected, ['invalid-input-response']), false];
        yield 'ホスト名の不一致' => [VerifyResult::failure(FailureReason::HostnameMismatch, [], 'evil.example'), false];
    }

    #[Test]
    #[DataProvider('失敗の結果')]
    public function サイト側で直すべき失敗かを判定する(VerifyResult $result, bool $expected): void
    {
        $this->assertSame($expected, $result->isSiteProblem());
    }

    #[Test]
    public function 成功の結果は理由を持たない(): void
    {
        $result = VerifyResult::success('example.com');

        $this->assertTrue($result->success);
        $this->assertNull($result->reason);
        $this->assertFalse($result->isSiteProblem());
    }
}
