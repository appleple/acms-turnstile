# Turnstile for a-blog cms

a-blog cms の拡張アプリ「Turnstile for a-blog cms」を使うと、Cloudflare の [Turnstile](https://developers.cloudflare.com/turnstile/) でメールフォームの送信をボットから守れます。Turnstile は無料で使え、サイトを Cloudflare 経由にしていなくても利用できます。

使い方は [reCAPTCHA for a-blog cms](https://github.com/appleple/acms-recaptcha) とそろえています。拡張アプリの設定にキーを保存し、フォームIDごとに連携を有効にして、送信ボタンのある form 要素にウィジェットを置きます。

## 動作環境

- a-blog cms: Ver. 3.2.0 以降（動作確認は Ver. 3.2.39）。Twig テンプレート用のモジュール（`V2_Turnstile`）は Ver. 3.2.27 以降
- PHP: 8.1〜8.5
- `config.server.php` で `HOOK_ENABLE` が `1` であること

## 仕組み

- フォームの送信（`ACMS_POST_Form_Submit`）のときだけ、サーバーから Cloudflare の siteverify API でトークンを検証します。確認（`ACMS_POST_Form_Confirm`）では検証しません。Turnstile のトークンは1回しか使えないため、確認と送信の両方で検証すると送信のときに使用済みで失敗するからです。
- 検証には訪問者の IP アドレス（`remoteip`）を渡し、応答の `hostname` が許可したホスト名かどうかも確かめます。別のサイトに置いたウィジェットで取ったトークンは通りません。
- 連携を有効にしたフォームでは、トークンが無い・使用済み・期限切れ・偽造の送信や、Cloudflare と通信できなかった送信をすべて拒否します。拒否した送信はメールを送らず、Formモジュールの `step#forbidden` を表示します。
- 拒否した送信は監査ログに残ります。訪問者が起こせる失敗（トークンの不正など）は NOTICE、シークレットキーの誤りや通信の失敗のようにサイト側で直すべき失敗は WARNING です。シークレットキーはログに残しません。トークン（`cf-turnstile-response`）は、本体が記録する POST の値に含まれます。トークンは1回限り・5分で期限が切れるため、ログから使い回されることはありません。

## ダウンロード

[Releases](https://github.com/appleple/acms-turnstile/releases/latest) から `Turnstile.zip` をダウンロードします。

解凍して **extension/plugins** に設置してください。

* extension/plugins/Turnstile

## インストール

管理ページ > 拡張アプリ の「拡張アプリ管理」で Turnstile をインストールし、使うブログで有効にします。

アンインストールすると、設定ページで保存した値（コンフィグの `turnstile_` で始まるキー）を全ブログから消します。フォームIDの「Turnstile連携」の値はフォームの設定に残りますが、拡張アプリが無ければ使われません。

拡張アプリの処理は Hook で動くため、`config.server.php` で HOOK を有効にしておく必要があります。

```php
define('HOOK_ENABLE', 1);
```

## キーの取得

1. Cloudflare のダッシュボードで「Turnstile」を開き、ウィジェットを追加します。
2. ホスト名にサイトのドメインを登録します（例 `example.com`）。ウィジェットのモードは、通常は「Managed」を選びます。
3. 発行された **サイトキー** と **シークレットキー** を控えます。

## 設定

### 拡張アプリの設定

管理ページ > Turnstile に移動し、次を保存します。

| 項目 | 内容 |
|---|---|
| サイトキー | ウィジェットの表示に使います。ページの HTML に出力されます |
| シークレットキー | サーバーからトークンを検証するときに使います。ページには出力しません |
| 許可するホスト名 | ウィジェットを表示したページのホスト名がこの中にあるときだけ送信を受け付けます。1行に1つ書きます。空のときは、アクセスされたホスト名だけを許可します |

シークレットキーは、サイトの `.env`（またはサーバーの環境変数）に書くこともできます。書いた場合は設定ページの値より優先します。

```
TURNSTILE_SECRET_KEY=0x4AAAAAAA...
```

設定ページに保存した値はブログのコンフィグとして DB に入るため、DB のバックアップやブログのエクスポートに含まれます。また、テーマのテンプレートから `BlogConfig` モジュールなどで読めてしまいます。`.env` に置くと、どちらにも残りません。

### フォームの設定

管理ページ > フォーム で、Turnstile を使いたいフォームIDの「Turnstile設定」にある **Turnstile連携** を有効にします。

検証の有無はこの設定で決まり、テンプレートには依存しません。ウィジェットを外したり、トークンを消して送ったりしても、検証を外すことはできません。

## テンプレート

### ウィジェットの設置（標準テンプレート）

送信ボタン（`ACMS_POST_Form_Submit`）のある form 要素の中にウィジェットを置きます。確認画面のあるフォームでは、**確認画面**の form 要素に置きます。入力画面に置くと、確認画面で時間がたつあいだにトークンが期限切れ（5分）になることがあるためです。確認画面の無いフォームでは入力画面に置きます。

```html
<form action="thanks.html" method="post" enctype="multipart/form-data">
  ...
  <!-- BEGIN_MODULE Turnstile -->
  <div class="js-acms-turnstile" data-sitekey="{sitekey}"></div>
  <!-- END_MODULE Turnstile -->
  <input type="submit" name="ACMS_POST_Form_Submit" value="送信する" />
</form>
```

次の2つのスクリプトを、この順でページに読み込みます（`</body>` の直前など）。同梱の `turnstile.js` が先、Cloudflare の `api.js` が後です。

```html
<script src="%{ROOT_DIR}extension/plugins/Turnstile/assets/turnstile.js"></script>
<script src="https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit&onload=acmsTurnstileOnload" async defer></script>
```

`turnstile.js` は `.js-acms-turnstile` の要素にウィジェットを描き、トークンが取れるまで同じ form 要素の送信ボタン（`name="ACMS_POST_Form_Submit"`）を押せなくします。トークンが無いまま送ると拒否されて最初からやり直しになるためです。

- 押せなくするボタンは `data-submit-selector` で変えられます（例 `data-submit-selector=".js-submit"`）。
- `data-theme`（`auto` / `light` / `dark`）・`data-size`（`normal` / `flexible` / `compact`）・`data-language`・`data-appearance` で、ウィジェットの見た目を指定できます。
- Cloudflare は `api.js` を自分のサーバーに置いたりキャッシュしたりせず、上の URL から読み込むよう求めています。

`turnstile.js` を使わず、Cloudflare の標準の書き方（`<div class="cf-turnstile" data-sitekey="{sitekey}"></div>` と `api.js` の読み込み）でも動きます。その場合、トークンが取れる前に送信ボタンを押せてしまいます。

### ウィジェットの設置（Twig テンプレート）

```twig
{% set turnstile = module('V2_Turnstile') %}
<div class="js-acms-turnstile" data-sitekey="{{ turnstile.sitekey }}"></div>
```

スクリプトの読み込みは標準テンプレートと同じです。

### エラー表示

検証に失敗した送信では、Formモジュールの **step#forbidden** ブロックが表示されます。

```html
<!-- BEGIN step#forbidden -->
<p>送信できませんでした。お手数ですが、最初からやり直してください。</p>
<!-- END step#forbidden -->
```

## 注意

### Cloudflare の HTML キャッシュとフォームのページ

サイトを Cloudflare 経由にし、Cache Rules などで HTML をキャッシュしている場合は、**フォームのページ（入力・確認・完了のすべて）をキャッシュの対象から外してください。** フォームのページには訪問者ごとの CSRF トークン（`formToken`）が入るため、キャッシュしたページを別の訪問者に返すと送信が拒否されます。

### Cloudflare 経由のサイトの IP アドレス

Cloudflare 経由のサイトでは、a-blog cms が受け取る接続元の IP アドレスが Cloudflare のものになります。`config.server.php` の `TRUSTED_PROXY_LIST` に Cloudflare の IP アドレスの範囲を設定すると、訪問者の本当の IP アドレスを siteverify に渡せます。

### 手元での動作確認

Cloudflare は動作確認用のキーを公開しています（[Testing](https://developers.cloudflare.com/turnstile/troubleshooting/testing/)）。

| 用途 | サイトキー | シークレットキー |
|---|---|---|
| 常に成功 | `1x00000000000000000000AA` | `1x0000000000000000000000000000000AA` |
| 常に失敗 | `2x00000000000000000000AB` | `2x0000000000000000000000000000000AA` |
| 使用済みとして失敗 | - | `3x0000000000000000000000000000000AA` |

動作確認用のシークレットキーで検証すると、応答の `hostname` は `example.com` になります。手元で確かめるときは「許可するホスト名」に `example.com` を足し、終わったら消してください。本番のキーに戻すのも忘れないでください。

## 開発

開発環境・テスト・リリースの手順は [DEVELOPMENT.md](./DEVELOPMENT.md) を読んでください。

## ライセンス

MIT
