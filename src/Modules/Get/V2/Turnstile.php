<?php

declare(strict_types=1);

namespace Acms\Plugins\Turnstile\Modules\Get\V2;

use Acms\Modules\Get\V2\Base;
use Acms\Plugins\Turnstile\Services\Settings;

/**
 * Twig テンプレート用（Ver. 3.2.27 以降）: module('V2_Turnstile').sitekey でサイトキーを返す
 * シークレットキーは返さない
 */
class Turnstile extends Base
{
    /**
     * @return array{sitekey: string}
     */
    public function get(): array
    {
        return [
            'sitekey' => Settings::load(BID)->siteKey,
        ];
    }
}
