<?php

declare(strict_types=1);

namespace Acms\Plugins\Turnstile\Services;

/**
 * トークンの検証に失敗した理由（監査ログに値を残す）
 */
enum FailureReason: string
{
    /** シークレットキーが設定されていない */
    case NotConfigured = 'not-configured';
    /** トークンが送られていない */
    case MissingToken = 'missing-token';
    /** トークンの形式が不正（長すぎるなど） */
    case InvalidToken = 'invalid-token';
    /** siteverify が失敗を返した（使用済み・期限切れ・偽造など。詳細は error-codes） */
    case Rejected = 'rejected';
    /** 応答の hostname が許可したホスト名と一致しない */
    case HostnameMismatch = 'hostname-mismatch';
    /** siteverify と通信できない、または応答を解釈できない */
    case RequestFailed = 'request-failed';
}
