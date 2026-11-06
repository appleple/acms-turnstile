<?php

namespace Acms\Plugins\Turnstile;

/**
 * Turnstile のトークン検証の結果
 */
class VerifyResult
{
    /** トークンが送られていない */
    public const REASON_MISSING_TOKEN = 'missing-token';
    /** トークンの形式が不正（長すぎるなど） */
    public const REASON_INVALID_TOKEN = 'invalid-token';
    /** siteverify が失敗を返した（使用済み・期限切れ・偽造など。詳細は errorCodes） */
    public const REASON_REJECTED = 'rejected';
    /** 応答の hostname が許可したホスト名と一致しない */
    public const REASON_HOSTNAME_MISMATCH = 'hostname-mismatch';
    /** siteverify と通信できない、または応答を解釈できない */
    public const REASON_REQUEST_FAILED = 'request-failed';
    /** シークレットキーが設定されていない */
    public const REASON_NOT_CONFIGURED = 'not-configured';

    /**
     * @var bool
     */
    private $success;

    /**
     * @var string|null
     */
    private $reason;

    /**
     * @var string[]
     */
    private $errorCodes;

    /**
     * @var string|null
     */
    private $hostname;

    /**
     * @param bool $success
     * @param string|null $reason
     * @param string[] $errorCodes
     * @param string|null $hostname
     */
    private function __construct($success, $reason, array $errorCodes, $hostname)
    {
        $this->success = $success;
        $this->reason = $reason;
        $this->errorCodes = $errorCodes;
        $this->hostname = $hostname;
    }

    /**
     * @param string $hostname
     * @return self
     */
    public static function success($hostname)
    {
        return new self(true, null, [], $hostname);
    }

    /**
     * @param string $reason
     * @param string[] $errorCodes
     * @param string|null $hostname
     * @return self
     */
    public static function failure($reason, array $errorCodes = [], $hostname = null)
    {
        return new self(false, $reason, $errorCodes, $hostname);
    }

    /**
     * @return bool
     */
    public function isSuccess()
    {
        return $this->success;
    }

    /**
     * @return string|null
     */
    public function reason()
    {
        return $this->reason;
    }

    /**
     * @return string[]
     */
    public function errorCodes()
    {
        return $this->errorCodes;
    }

    /**
     * @return string|null
     */
    public function hostname()
    {
        return $this->hostname;
    }

    /**
     * 訪問者ではなくサイトの設定・環境の問題による失敗か（管理者が対処すべきか）
     *
     * @return bool
     */
    public function isSiteProblem()
    {
        if ($this->reason === self::REASON_NOT_CONFIGURED || $this->reason === self::REASON_REQUEST_FAILED) {
            return true;
        }
        // シークレットキーの誤り・Cloudflare 側の内部エラーは訪問者には直せない
        $siteErrors = ['missing-input-secret', 'invalid-input-secret', 'internal-error'];
        return count(array_intersect($siteErrors, $this->errorCodes)) > 0;
    }
}
