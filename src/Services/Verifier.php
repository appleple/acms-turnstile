<?php

declare(strict_types=1);

namespace Acms\Plugins\Turnstile\Services;

use Closure;
use Throwable;

/**
 * Turnstile のトークンを Cloudflare の siteverify API で検証する
 *
 * 通信は引数で受け取った関数に任せ、このクラスは本体のグローバルな状態に触れない（単体で確かめられるようにするため）。
 */
final class Verifier
{
    public const ENDPOINT = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    /**
     * Cloudflare が示すトークンの最大長
     */
    public const MAX_TOKEN_LENGTH = 2048;

    /**
     * @var list<string>
     */
    private readonly array $allowedHostnames;

    /**
     * @param list<string> $allowedHostnames 応答の hostname として許可するホスト名
     * @param Closure(string, array<string, string>): string $transport URL と POST する値を受け取り、応答の本文を返す。通信に失敗したら例外を投げる
     */
    public function __construct(
        private readonly string $secretKey,
        array $allowedHostnames,
        private readonly Closure $transport,
    ) {
        $this->allowedHostnames = array_map('strtolower', $allowedHostnames);
    }

    /**
     * @param mixed $token 訪問者が送ったトークン（cf-turnstile-response）。POST の値なので型は保証されない
     * @param string $remoteIp 訪問者の IP アドレス（空なら送らない）
     */
    public function verify(mixed $token, string $remoteIp): VerifyResult
    {
        if ($this->secretKey === '') {
            return VerifyResult::failure(FailureReason::NotConfigured);
        }
        if (!is_string($token) || $token === '') {
            return VerifyResult::failure(FailureReason::MissingToken);
        }
        if (strlen($token) > self::MAX_TOKEN_LENGTH) {
            return VerifyResult::failure(FailureReason::InvalidToken);
        }

        $params = [
            'secret' => $this->secretKey,
            'response' => $token,
        ];
        if ($remoteIp !== '') {
            $params['remoteip'] = $remoteIp;
        }

        try {
            $body = ($this->transport)(self::ENDPOINT, $params);
        } catch (Throwable $e) {
            return VerifyResult::failure(FailureReason::RequestFailed, [$e->getMessage()]);
        }

        $json = json_decode($body, true);
        if (!is_array($json) || !array_key_exists('success', $json)) {
            return VerifyResult::failure(FailureReason::RequestFailed, ['invalid-json']);
        }

        $errorCodes = [];
        if (isset($json['error-codes']) && is_array($json['error-codes'])) {
            $errorCodes = array_values(array_filter($json['error-codes'], 'is_string'));
        }
        $hostname = isset($json['hostname']) && is_string($json['hostname']) ? strtolower($json['hostname']) : null;

        if ($json['success'] !== true) {
            return VerifyResult::failure(FailureReason::Rejected, $errorCodes, $hostname);
        }
        // 別のサイトに置いたウィジェットで取ったトークンを持ち込ませない
        if ($hostname === null || !in_array($hostname, $this->allowedHostnames, true)) {
            return VerifyResult::failure(FailureReason::HostnameMismatch, $errorCodes, $hostname);
        }

        return VerifyResult::success($hostname);
    }
}
