<?php

namespace Acms\Plugins\Turnstile;

use Config;

/**
 * 設定ページ（ブログのコンフィグ）と環境変数から Turnstile の設定を読む
 */
class Settings
{
    /**
     * シークレットキーを置ける環境変数名（.env など）。設定ページの値より優先する
     */
    public const SECRET_KEY_ENV = 'TURNSTILE_SECRET_KEY';

    /**
     * @var string
     */
    private $siteKey;

    /**
     * @var string
     */
    private $secretKey;

    /**
     * @var string[]
     */
    private $hostnames;

    /**
     * @param string $siteKey
     * @param string $secretKey
     * @param string[] $hostnames
     */
    public function __construct($siteKey, $secretKey, array $hostnames)
    {
        $this->siteKey = $siteKey;
        $this->secretKey = $secretKey;
        $this->hostnames = $hostnames;
    }

    /**
     * 現在のブログの設定を読む
     *
     * Why not config(): ブログにコンフィグセットが割り当てられていると config() はコンフィグセットの値を返し、
     * 設定ページで保存したブログ自体のコンフィグを見ないため、loadBlogConfig() で読む。
     *
     * @return self
     */
    public static function load()
    {
        $config = Config::loadDefaultField();
        $config->overload(Config::loadBlogConfig(BID));

        $secretKey = self::secretKeyFromEnv();
        if ($secretKey === '') {
            $secretKey = trim((string) $config->get('turnstile_secret_key'));
        }

        return new self(
            trim((string) $config->get('turnstile_sitekey')),
            $secretKey,
            self::parseHostnames((string) $config->get('turnstile_hostnames'))
        );
    }

    /**
     * 改行・カンマ・空白区切りのホスト名を配列にする
     *
     * @param string $value
     * @return string[]
     */
    public static function parseHostnames($value)
    {
        $hostnames = preg_split('/[\s,]+/', strtolower($value), -1, PREG_SPLIT_NO_EMPTY);
        return $hostnames === false ? [] : array_values(array_unique($hostnames));
    }

    /**
     * @return string
     */
    public function siteKey()
    {
        return $this->siteKey;
    }

    /**
     * @return string
     */
    public function secretKey()
    {
        return $this->secretKey;
    }

    /**
     * 応答の hostname として許可するホスト名。未設定なら空配列（呼び出し側でリクエストのホスト名を使う）
     *
     * @return string[]
     */
    public function hostnames()
    {
        return $this->hostnames;
    }

    /**
     * @return string
     */
    private static function secretKeyFromEnv()
    {
        // .env の値（本体が $_ENV に読み込む）を優先し、無ければサーバーの環境変数を見る
        $value = function_exists('env') ? env(self::SECRET_KEY_ENV, '') : '';
        if ($value === '') {
            $value = getenv(self::SECRET_KEY_ENV);
        }
        return is_string($value) ? trim($value) : '';
    }
}
