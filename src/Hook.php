<?php

declare(strict_types=1);

namespace Acms\Plugins\Turnstile;

use ACMS_POST;
use ACMS_POST_Form_Submit;
use Acms\Plugins\Turnstile\Services\Hostname;
use Acms\Plugins\Turnstile\Services\Settings;
use Acms\Plugins\Turnstile\Services\Verifier;
use Acms\Plugins\Turnstile\Services\VerifyResult;
use Acms\Services\Facades\Http;
use Acms\Services\Facades\Logger;
use Throwable;

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
     * @param ACMS_POST $thisModule
     */
    public function beforePostFire($thisModule): void
    {
        if (!($thisModule instanceof ACMS_POST_Form_Submit)) {
            return;
        }

        try {
            $formId = $thisModule->Post->get('id');
            $info = $thisModule->loadForm($formId);
            if (!is_array($info)) {
                return; // 存在しないフォーム ID は本体が拒否する
            }
            if ($info['data']->getChild('mail')->get(self::FORM_ENABLE_KEY) !== 'on') {
                return;
            }
            $result = $this->verify($thisModule->Post->get(self::TOKEN_FIELD));
            $this->log($result, $formId);
            $valid = $result->success;
        } catch (Throwable $e) {
            // Hook の例外は本体が受け止めないため外へ投げない。検証できなかった送信は通さない
            Logger::warning('Turnstile: 検証中に例外が起きたため、フォームの送信を拒否しました', [
                'message' => $e->getMessage(),
            ]);
            $valid = false;
        }

        if (!$valid) {
            $thisModule->Post->setValidator('turnstile', 'validator', false);
            $thisModule->Post->set('error', 'forbidden');
        }
    }

    private function verify(mixed $token): VerifyResult
    {
        $settings = Settings::load(BID);
        $hostnames = $settings->hostnames;
        if ($hostnames === []) {
            $hostnames = [Hostname::fromHostHeader($this->requestHost())];
        }
        $verifier = new Verifier($settings->secretKey, $hostnames, static function (string $url, array $params): string {
            // Why not メソッドチェーン: Http ファサードの型宣言では setPostData() / send() の戻り値が自身と保証されていない
            $http = Http::init($url, 'post');
            $http->setPostData($params);
            $http->send();

            return $http->getResponseBody();
        });

        return $verifier->verify($token, $this->remoteAddr());
    }

    /**
     * 失敗を監査ログに残す
     *
     * 訪問者が起こせる失敗（トークン無し・使用済み・偽造）は notice、サイト側で直すべき失敗は warning にする。
     * シークレットキーはログに入れない（トークンは本体が POST の値として記録するが、1回限りで期限も短い）。
     */
    private function log(VerifyResult $result, mixed $formId): void
    {
        if ($result->success) {
            return;
        }
        $context = [
            'formId' => $formId,
            'reason' => $result->reason?->value,
            // Why not errorCodes: 監査ログは キー名に code・token などを含む値を伏せ字にするため、調査に要るこの値が消える
            'siteverifyErrors' => $result->errorCodes,
            'hostname' => $result->hostname,
        ];
        if ($result->isSiteProblem()) {
            Logger::warning('Turnstile: 設定または通信の問題でトークンを検証できず、フォームの送信を拒否しました', $context);

            return;
        }
        Logger::notice('Turnstile: トークンの検証に失敗したため、フォームの送信を拒否しました', $context);
    }

    /**
     * 訪問者の IP アドレス
     *
     * 本体の REMOTE_ADDR 定数は TRUSTED_PROXY_LIST を設定すると、Cloudflare などのプロキシ越しでも訪問者の IP になる
     */
    private function remoteAddr(): string
    {
        return defined('REMOTE_ADDR') ? REMOTE_ADDR : '';
    }

    private function requestHost(): string
    {
        $host = defined('HTTP_HOST') ? HTTP_HOST : '';

        return is_string($host) ? $host : '';
    }
}
