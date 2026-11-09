<?php

declare(strict_types=1);

namespace Acms\Plugins\Turnstile\Services;

/**
 * Turnstile のトークン検証の結果
 */
final class VerifyResult
{
    /**
     * @param list<string> $errorCodes siteverify が返した error-codes（通信の失敗では例外のメッセージ）
     */
    private function __construct(
        public readonly bool $success,
        public readonly ?FailureReason $reason,
        public readonly array $errorCodes,
        public readonly ?string $hostname,
    ) {
    }

    public static function success(string $hostname): self
    {
        return new self(true, null, [], $hostname);
    }

    /**
     * @param list<string> $errorCodes
     */
    public static function failure(FailureReason $reason, array $errorCodes = [], ?string $hostname = null): self
    {
        return new self(false, $reason, $errorCodes, $hostname);
    }

    /**
     * 訪問者ではなくサイトの設定・環境の問題による失敗か（管理者が対処すべきか）
     */
    public function isSiteProblem(): bool
    {
        if ($this->reason === FailureReason::NotConfigured || $this->reason === FailureReason::RequestFailed) {
            return true;
        }
        // シークレットキーの誤り・Cloudflare 側の内部エラーは訪問者には直せない
        $siteErrors = ['missing-input-secret', 'invalid-input-secret', 'internal-error'];

        return array_intersect($siteErrors, $this->errorCodes) !== [];
    }
}
