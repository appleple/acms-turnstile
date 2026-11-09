<?php

declare(strict_types=1);

namespace Acms\Plugins\Turnstile\Services;

use Acms\Services\Facades\Config;

/**
 * 設定ページ（ブログのコンフィグ）と環境変数から Turnstile の設定を読む
 */
final class Settings
{
    /**
     * シークレットキーを置ける環境変数名（.env など）。設定ページの値より優先する
     */
    public const SECRET_KEY_ENV = 'TURNSTILE_SECRET_KEY';

    /**
     * @param list<string> $hostnames 応答の hostname として許可するホスト名。空なら呼び出し側でリクエストのホスト名を使う
     */
    public function __construct(
        public readonly string $siteKey,
        public readonly string $secretKey,
        public readonly array $hostnames,
    ) {
    }

    /**
     * ブログの設定を読む
     *
     * Why not config(): ブログにコンフィグセットが割り当てられていると config() はコンフィグセットの値を返し、
     * 設定ページで保存したブログ自体のコンフィグを見ないため、loadBlogConfig() で読む。
     */
    public static function load(int $blogId): self
    {
        $config = Config::loadDefaultField();
        $config->overload(Config::loadBlogConfig($blogId));

        $secretKey = self::secretKeyFromEnv();
        if ($secretKey === '') {
            $secretKey = trim(self::stringValue($config->get('turnstile_secret_key')));
        }

        return new self(
            trim(self::stringValue($config->get('turnstile_sitekey'))),
            $secretKey,
            Hostname::parseList(self::stringValue($config->get('turnstile_hostnames'))),
        );
    }

    private static function secretKeyFromEnv(): string
    {
        // .env の値（本体が $_ENV に読み込む）を優先し、無ければサーバーの環境変数を見る
        $value = $_ENV[self::SECRET_KEY_ENV] ?? getenv(self::SECRET_KEY_ENV);

        return is_string($value) ? trim($value) : '';
    }

    private static function stringValue(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
