# 開発ガイド

Turnstile for a-blog cms の開発・テスト・リリースの手順です。構成は公式のひな型 [ablogcms/plugin-skeleton](https://packagist.org/packages/ablogcms/plugin-skeleton) に合わせています。利用者向けの説明は [README.md](./README.md) にあります。

## 構成

```
.
├── src/                      # 拡張アプリ本体（配布する zip の中身。extension/plugins/Turnstile になる）
│   ├── ServiceProvider.php   # Hook とテンプレートの登録、インストール・アンインストール
│   ├── Hook.php              # beforePostFire: フォームの送信時にトークンを検証する
│   ├── Services/             # 検証のロジック（テストの対象）
│   │   ├── Verifier.php      # siteverify の呼び出しと応答の判定（通信は引数の関数に任せる）
│   │   ├── VerifyResult.php  # 検証結果
│   │   ├── FailureReason.php # 失敗の理由（監査ログに残す値）
│   │   ├── Settings.php      # 設定ページ・.env から設定を読む
│   │   └── Hostname.php      # ホスト名の整形
│   ├── GET/Turnstile.php     # 標準テンプレート用のモジュール（{sitekey}）
│   ├── Modules/Get/V2/       # Twig 用のモジュール（V2_Turnstile）
│   ├── assets/turnstile.js   # ウィジェットの描画と送信ボタンの制御
│   └── template/             # 設定ページ・フォーム設定に差し込むテンプレート
├── tests/phpunit/
│   ├── Unit/                 # DB を使わないテスト
│   └── Integration/          # DB を使うテスト（テストごとにロールバック）
├── scripts/                  # zip の作成・バージョンの更新・リリース（PHP で動く）
├── docker-compose.yml        # a-blog cms 本体入りの appleple/acms イメージ + MySQL
└── .github/workflows/        # CI（test.yml）とリリース（release.yml）
```

`src/` が拡張アプリのルートです。本体のオートローダーは `Acms\Plugins\` を `extension/plugins/` に対応づけるため、`Acms\Plugins\Turnstile\Services\Verifier` は `src/Services/Verifier.php` から読まれます。

## 開発環境

Docker で a-blog cms と MySQL を起動します。`src/` は `extension/plugins/Turnstile` に、リポジトリ全体は `/workspace` にマウントされます。

```bash
cp .env.example .env
docker compose up -d --wait
docker compose exec acms bash -lc "cd /workspace && composer install"
```

ブラウザで確かめるときは http://localhost:8080 を開き、a-blog cms のセットアップを済ませてから、拡張アプリをインストールします。テストの実行だけならセットアップは要りません。

既存の手元のサイトで確かめる場合は、そのサイトの `extension/plugins/Turnstile` から、このリポジトリの `src/` へシンボリックリンクを張ります。

### 動作確認用のキー

Cloudflare が公開している動作確認用のキーを使います（[Testing](https://developers.cloudflare.com/turnstile/troubleshooting/testing/)）。応答の `hostname` が `example.com` になるため、設定ページの「許可するホスト名」に `example.com` を足します。

| 用途 | サイトキー | シークレットキー |
|---|---|---|
| 常に成功 | `1x00000000000000000000AA` | `1x0000000000000000000000000000000AA` |
| 常に失敗 | `2x00000000000000000000AB` | `2x0000000000000000000000000000000AA` |
| 使用済みとして失敗 | - | `3x0000000000000000000000000000000AA` |

## テストと静的解析

```bash
docker compose exec acms bash -lc "cd /workspace && ACMS_ROOT=/var/www/html vendor/bin/acms-create-database"  # 初回だけ
docker compose exec acms bash -lc "cd /workspace && composer check"
```

| コマンド | 内容 |
|---|---|
| `composer lint` | PHP_CodeSniffer（PSR-12 + PHPCompatibility。PHP 8.1〜8.5） |
| `composer format` | phpcbf で自動整形 |
| `composer analyse` | PHPStan（level max + strict-rules。PHP 8.1〜8.5 の範囲で解析） |
| `composer test` | PHPUnit（Unit と Integration） |
| `composer check` | 上の lint・analyse・test をまとめて実行 |

- ロジックは `src/Services/` に置いてテストします。Hook・GET・V2 モジュールは直接テストせず、Services のテストで間接的に確かめます。
- `Verifier` は siteverify との通信を引数の関数で受け取るため、Cloudflare に接続せずにテストできます。
- 統合テストは `ablogcms/testing-framework` の `DatabaseTestCase` を使い、テストごとにロールバックされます。

## CI

GitHub Actions（`.github/workflows/test.yml`）で、push と pull request のたびに次を実行します。

- `quality`: phpcs と PHPStan を1回だけ実行する（対応する PHP の範囲全体を対象に解析するため）
- `phpunit`: a-blog cms 3.2 × PHP 8.1〜8.5 のマトリクスで PHPUnit を実行する
- `test`: 上の2つが通ったかをまとめる。ブランチ保護の必須チェックにはこれを指定する

a-blog cms 3.3 が出たら、マトリクスの `acms` に `'3.3'` を足します。`ablogcms/testing-framework` は CI の中でテスト対象の a-blog cms に合わせて入れ替えます。

Dependabot（`.github/dependabot.yml`）が GitHub Actions の更新を週1回提案し、パッチ・マイナーの更新は CI が通れば自動でマージします（リポジトリの設定で「Allow auto-merge」と、`test` を必須にしたブランチ保護が要ります）。

## リリース

バージョンの正は `src/ServiceProvider.php` の `$version` です（`composer.json` に `version` を書かない）。

```bash
composer package             # build/Turnstile.zip を作る（git の追跡対象外）
composer version:set 1.2.3   # バージョンを設定する（patch / minor / major で繰り上げ）
composer release:patch       # バージョンを上げてコミットし、タグ v1.2.3 を作る（push はしない）
git push --follow-tags       # タグを push すると release.yml が zip を作って GitHub Release に公開する
```

`release.yml` はタグと `$version` が食い違うと失敗し、ずれた zip を公開しません。zip には `src/` の中身と `README.md`・`LICENSE` が入ります（`composer.json` の `extra.acms-plugin-tools`）。
