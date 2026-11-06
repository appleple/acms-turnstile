<?php

namespace Acms\Plugins\Turnstile;

use AcmsLogger;
use Http;

class Hook
{
    /**
     * ウィジェットが送るトークンの項目名（Turnstile の既定値）
     */
    public const TOKEN_FIELD = 'cf-turnstile-response';

    /**
     * フォーム設定（mail）に保存する、Turnstile 連携を有効にするかのキー
     */
    public const FORM_ENABLE_KEY = 'turnstile_enable';

    /**
     * POSTモジュール処理前
     *
     * フォームの送信（ACMS_POST_Form_Submit）だけを検証する。トークンは1回しか使えないため、確認（Confirm）では検証しない。
     * Why not 検証オプション（入力チェック）: 確認と送信の2回走るため、2回目が使用済みで失敗する。
     *
     * @param \ACMS_POST $thisModule
     * @return void
     */
    public function beforePostFire($thisModule)
    {
        if (!($thisModule instanceof \ACMS_POST_Form_Submit)) {
            return;
        }

        try {
            $formId = $thisModule->Post->get('id');
            $info = $thisModule->loadForm($formId);
            if (empty($info)) {
                return; // 存在しないフォーム ID は本体が拒否する
            }
            $mail = $info['data']->getChild('mail');
            if ($mail->get(self::FORM_ENABLE_KEY) !== 'on') {
                return;
            }
            $result = $this->verify($thisModule->Post->get(self::TOKEN_FIELD));
            $this->log($result, $formId);
            $valid = $result->isSuccess();
        } catch (\Throwable $e) {
            // Hook の例外は本体が受け止めないため外へ投げない。検証できなかった送信は通さない
            AcmsLogger::warning('Turnstile: 検証中に例外が起きたため、フォームの送信を拒否しました', [
                'message' => $e->getMessage(),
            ]);
            $valid = false;
        }

        if (!$valid) {
            $thisModule->Post->setValidator('turnstile', 'validator', false);
            $thisModule->Post->set('error', 'forbidden');
        }
    }

    /**
     * @param mixed $token
     * @return VerifyResult
     */
    private function verify($token)
    {
        $settings = Settings::load();
        $hostnames = $settings->hostnames();
        if (count($hostnames) === 0) {
            $hostnames = [$this->requestHostname()];
        }
        $verifier = new Verifier($settings->secretKey(), $hostnames, function ($url, array $params) {
            return Http::init($url, 'post')->setPostData(http_build_query($params, '', '&'))->send()->getResponseBody();
        });

        return $verifier->verify($token, $this->remoteAddr());
    }

    /**
     * 失敗を監査ログに残す
     *
     * 訪問者が起こせる失敗（トークン無し・使用済み・偽造）は notice、サイト側で直すべき失敗は warning にする。
     * シークレットキーはログに入れない（トークンは本体が POST の値として記録するが、1回限りで期限も短い）。
     *
     * @param VerifyResult $result
     * @param mixed $formId
     * @return void
     */
    private function log(VerifyResult $result, $formId)
    {
        if ($result->isSuccess()) {
            return;
        }
        $context = [
            'formId' => $formId,
            'reason' => $result->reason(),
            // Why not errorCodes: 監査ログは キー名に code・token などを含む値を伏せ字にするため、調査に要るこの値が消える
            'siteverifyErrors' => $result->errorCodes(),
            'hostname' => $result->hostname(),
        ];
        if ($result->isSiteProblem()) {
            AcmsLogger::warning('Turnstile: 設定または通信の問題でトークンを検証できず、フォームの送信を拒否しました', $context);
            return;
        }
        AcmsLogger::notice('Turnstile: トークンの検証に失敗したため、フォームの送信を拒否しました', $context);
    }

    /**
     * 訪問者の IP アドレス
     *
     * 本体の REMOTE_ADDR 定数は TRUSTED_PROXY_LIST を設定すると、Cloudflare などのプロキシ越しでも訪問者の IP になる
     *
     * @return string
     */
    private function remoteAddr()
    {
        if (defined('REMOTE_ADDR')) {
            return (string) REMOTE_ADDR;
        }
        return isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
    }

    /**
     * リクエストのホスト名（ポートを除く）
     *
     * @return string
     */
    private function requestHostname()
    {
        if (defined('HTTP_HOST')) {
            $host = (string) HTTP_HOST;
        } else {
            $host = isset($_SERVER['HTTP_HOST']) ? (string) $_SERVER['HTTP_HOST'] : '';
        }
        $host = strtolower($host);
        // [::1]:8080 のような IPv6 と、host:port の両方からポートを外す
        if (preg_match('/^(\[[^\]]+\]|[^:]+)(?::\d+)?$/', $host, $matches)) {
            return $matches[1];
        }
        return $host;
    }
}
