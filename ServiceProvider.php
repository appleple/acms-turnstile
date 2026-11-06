<?php

namespace Acms\Plugins\Turnstile;

use ACMS_App;
use Acms\Services\Common\HookFactory;
use Acms\Services\Common\InjectTemplate;
use DB;
use SQL;

class ServiceProvider extends ACMS_App
{
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
     * @var bool|string
     */
    public $menu = 'turnstile_index';

    /**
     * @var string
     */
    public $desc = 'Cloudflare Turnstile でメールフォームの送信をボットから守ります。';

    /**
     * サービスの初期処理
     */
    public function init()
    {
        $hook = HookFactory::singleton();
        $hook->attach('TurnstileHook', new Hook());

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
        DB::query($sql->get(dsn()), 'exec');
    }

    /**
     * アップデートするときの処理
     *
     * @return bool
     */
    public function update()
    {
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
}
