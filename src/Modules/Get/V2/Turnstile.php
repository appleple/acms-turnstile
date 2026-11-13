<?php

declare(strict_types=1);

namespace Acms\Plugins\Turnstile\Modules\Get\V2;

use Acms\Modules\Get\V2\Base;
use Acms\Plugins\Turnstile\Services\Scripts;
use Acms\Plugins\Turnstile\Services\Settings;

/**
 * Twig テンプレート用: module('V2_Turnstile').sitekey でサイトキーを返し、ウィジェットのスクリプトを </body> の直前に出す
 * シークレットキーは返さない
 */
class Turnstile extends Base
{
    /**
     * @return array{sitekey: string}
     */
    public function get(): array
    {
        Scripts::enqueue();

        return [
            'sitekey' => Settings::load(BID)->siteKey,
        ];
    }
}
