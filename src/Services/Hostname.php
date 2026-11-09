<?php

declare(strict_types=1);

namespace Acms\Plugins\Turnstile\Services;

/**
 * ホスト名の扱い
 */
final class Hostname
{
    /**
     * Host ヘッダーの値からポートを除いたホスト名を返す（小文字）
     *
     * example.com:8080 と [::1]:8080 のような IPv6 の両方に対応する
     */
    public static function fromHostHeader(string $host): string
    {
        $host = strtolower(trim($host));
        if (preg_match('/^(\[[^\]]+\]|[^:]+)(?::\d+)?$/', $host, $matches) === 1) {
            return $matches[1];
        }

        return $host;
    }

    /**
     * 改行・カンマ・空白区切りのホスト名を配列にする（小文字・重複なし）
     *
     * @return list<string>
     */
    public static function parseList(string $value): array
    {
        $hostnames = preg_split('/[\s,]+/', strtolower($value), -1, PREG_SPLIT_NO_EMPTY);
        if ($hostnames === false) {
            return [];
        }

        return array_values(array_unique($hostnames));
    }
}
