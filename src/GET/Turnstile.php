<?php

declare(strict_types=1);

namespace Acms\Plugins\Turnstile\GET;

use ACMS_Corrector;
use ACMS_GET;
use Acms\Plugins\Turnstile\Services\Settings;
use Template;

/**
 * 標準テンプレート用: ウィジェットに渡すサイトキーを {sitekey} で出力する
 * シークレットキーは出力しない
 */
class Turnstile extends ACMS_GET
{
    public function get()
    {
        $tpl = new Template($this->tpl, new ACMS_Corrector());
        $tpl->add(null, [
            'sitekey' => Settings::load(BID)->siteKey,
        ]);

        return $tpl->get();
    }
}
