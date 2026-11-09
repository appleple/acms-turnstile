<?php

declare(strict_types=1);

namespace Acms\Plugins\Turnstile;

use ACMS_App;
use Acms\Services\Common\HookFactory;
use Acms\Services\Common\InjectTemplate;
use Acms\Services\Facades\Database;
use Acms\Services\Facades\Logger;
use RuntimeException;
use SQL;

class ServiceProvider extends ACMS_App
{
    /**
     * 動作する a-blog cms の最小バージョン
     */
    private const MIN_ACMS_VERSION = '3.2.0';

    /**
     * @var string
     */
    public $version = '1.0.0';

    /**
     * @var string
     */
    public $name = 'Turnstile';

    /**
     * @var string
     */
    public $author = 'com.appleple';

    /**
     * @var bool
     */
    public $module = false;

    /**
     * @var false|string
     */
    public $menu = 'turnstile_index';

    /**
     * @var string
     */
    public $desc = 'Cloudflare Turnstile でメールフォームの送信をボットから守ります。';

    /**
     * サービスの初期処理（拡張アプリを有効にしたブログへのリクエストのたびに呼ばれる）
     */
    public function init(): void
    {
        // 本体を古い版に戻したサイトでも落ちないよう、対応外の版では何も登録しない
        if (!self::isSupportedVersion()) {
            return;
        }
        HookFactory::singleton()->attach('TurnstileHook', new Hook());

        $inject = InjectTemplate::singleton();
        // 差し込んだテンプレートを描く本体側は権限を確かめないため、設定ページは管理者のときだけ差し込む
        if (ADMIN === 'app_' . $this->menu && sessionWithAdministration()) {
            $inject->add('admin-main', PLUGIN_DIR . 'Turnstile/template/index.html');
            $inject->add('admin-topicpath', PLUGIN_DIR . 'Turnstile/template/topicpath.html');
        }
        $inject->add('admin-form', PLUGIN_DIR . 'Turnstile/template/admin/form.html');
    }

    /**
     * インストールする前の環境チェック処理
     *
     * @return bool
     */
    public function checkRequirements()
    {
        if (!self::isSupportedVersion()) {
            Logger::notice('Turnstile: a-blog cms Ver. ' . self::MIN_ACMS_VERSION . ' 以降が必要なため、インストールできません', [
                'version' => self::acmsVersion(),
            ]);

            return false;
        }

        return function_exists('curl_init');
    }

    /**
     * インストールするときの処理
     *
     * @return void
     */
    public function install()
    {
    }

    /**
     * アンインストールするときの処理
     * 設定ページで保存したコンフィグ（turnstile_ で始まるキー）を消す
     *
     * @return void
     */
    public function uninstall()
    {
        $sql = SQL::newDelete('config');
        $sql->addWhereOpr('config_key', 'turnstile_%', 'LIKE');
        Database::query($sql->get(dsn()), 'exec');
    }

    /**
     * アップデートするときの処理（checkRequirements() は呼ばれないため、ここでも版を確かめる）
     *
     * @return bool
     */
    public function update()
    {
        if (!self::isSupportedVersion()) {
            throw new RuntimeException('Turnstile: a-blog cms Ver. ' . self::MIN_ACMS_VERSION . ' 以降が必要です');
        }

        return true;
    }

    /**
     * 有効化するときの処理
     *
     * @return bool
     */
    public function activate()
    {
        return true;
    }

    /**
     * 無効化するときの処理
     *
     * @return bool
     */
    public function deactivate()
    {
        return true;
    }

    private static function isSupportedVersion(): bool
    {
        return version_compare(self::acmsVersion(), self::MIN_ACMS_VERSION, '>=');
    }

    /**
     * 本体のバージョン（本体が定数 VERSION で定義する）
     */
    private static function acmsVersion(): string
    {
        $version = defined('VERSION') ? constant('VERSION') : '';

        return is_string($version) ? $version : '';
    }
}
