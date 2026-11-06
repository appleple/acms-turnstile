<?php

namespace Acms\Plugins\Turnstile;

/**
 * Turnstile のトークンを Cloudflare の siteverify API で検証する
 *
 * 通信は引数で受け取った関数に任せ、このクラスは本体のグローバルな状態に触れない（単体で確かめられるようにするため）。
 */
class Verifier
{
    public const ENDPOINT = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    /**
     * Cloudflare が示すトークンの最大長
     */
    public const MAX_TOKEN_LENGTH = 2048;

    /**
     * @var string
     */
    private $secretKey;

    /**
     * @var string[]
     */
    private $allowedHostnames;

    /**
     * @var callable(string, array<string, string>): string
     */
    private $transport;

    /**
     * @param string $secretKey
     * @param string[] $allowedHostnames 応答の hostname として許可するホスト名（小文字）
     * @param callable(string, array<string, string>): string $transport URL と POST する値を受け取り、応答の本文を返す。通信に失敗したら例外を投げる
     */
    public function __construct($secretKey, array $allowedHostnames, callable $transport)
    {
        $this->secretKey = $secretKey;
        $this->allowedHostnames = array_map('strtolower', $allowedHostnames);
        $this->transport = $transport;
    }

    /**
     * @param mixed $token 訪問者が送ったトークン（cf-turnstile-response）
     * @param string $remoteIp 訪問者の IP アドレス
     * @return VerifyResult
     */
    public function verify($token, $remoteIp)
    {
        if ($this->secretKey === '') {
            return VerifyResult::failure(VerifyResult::REASON_NOT_CONFIGURED);
        }
        if (!is_string($token) || $token === '') {
            return VerifyResult::failure(VerifyResult::REASON_MISSING_TOKEN);
        }
        if (strlen($token) > self::MAX_TOKEN_LENGTH) {
            return VerifyResult::failure(VerifyResult::REASON_INVALID_TOKEN);
        }

        $params = [
            'secret' => $this->secretKey,
            'response' => $token,
        ];
        if ($remoteIp !== '') {
            $params['remoteip'] = $remoteIp;
        }

        try {
            $body = call_user_func($this->transport, self::ENDPOINT, $params);
        } catch (\Throwable $e) {
            return VerifyResult::failure(VerifyResult::REASON_REQUEST_FAILED, [$e->getMessage()]);
        }

        $json = is_string($body) ? json_decode($body, true) : null;
        if (!is_array($json) || !array_key_exists('success', $json)) {
            return VerifyResult::failure(VerifyResult::REASON_REQUEST_FAILED, ['invalid-json']);
        }

        $errorCodes = isset($json['error-codes']) && is_array($json['error-codes'])
            ? array_values(array_filter($json['error-codes'], 'is_string'))
            : [];
        $hostname = isset($json['hostname']) && is_string($json['hostname']) ? strtolower($json['hostname']) : null;

        if ($json['success'] !== true) {
            return VerifyResult::failure(VerifyResult::REASON_REJECTED, $errorCodes, $hostname);
        }
        // 別のサイトに置いたウィジェットで取ったトークンを持ち込ませない
        if ($hostname === null || !in_array($hostname, $this->allowedHostnames, true)) {
            return VerifyResult::failure(VerifyResult::REASON_HOSTNAME_MISMATCH, $errorCodes, $hostname);
        }

        return VerifyResult::success($hostname);
    }
}
