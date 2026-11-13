/**
 * Turnstile for a-blog cms
 *
 * .js-acms-turnstile の要素に Turnstile のウィジェットを描き、トークンが取れるまで同じ form の送信ボタンを押せなくする。
 * トークンが無いまま送ると送信が拒否されて最初からやり直しになるため、押せる時点をトークンの取得後に合わせる。
 *
 * Turnstile モジュール（Services/Scripts.php）が、このファイルと Cloudflare の api.js を本体の Asset API で </body> の直前に出力する。
 * api.js の onload で acmsTurnstileOnload を呼ぶため、このファイルを先に読み込む。
 */
(function () {
  'use strict';

  var SELECTOR = '.js-acms-turnstile';
  // ウィジェットの見た目などを data 属性で指定できるようにする（data-theme="light" など）
  // Why not response-field-name: サーバー側は既定の項目名（cf-turnstile-response）を読むため、変えさせない
  var OPTION_ATTRIBUTES = {
    theme: 'theme',
    size: 'size',
    language: 'language',
    appearance: 'appearance',
    action: 'action',
  };

  function submitButtons(container) {
    var form = container.closest('form');
    if (!form) {
      return [];
    }
    var selector = container.getAttribute('data-submit-selector') || '[name="ACMS_POST_Form_Submit"]';
    return Array.prototype.slice.call(form.querySelectorAll(selector));
  }

  function setEnabled(buttons, enabled) {
    buttons.forEach(function (button) {
      button.disabled = !enabled;
    });
  }

  function render(container) {
    if (container.getAttribute('data-acms-turnstile-rendered') === 'true') {
      return;
    }
    var sitekey = container.getAttribute('data-sitekey');
    if (!sitekey) {
      // サイトキーが未設定のときは送信ボタンを止めない（検証はサーバー側で行われ、拒否される）
      console.warn('Turnstile: サイトキーが設定されていません。管理ページの Turnstile 設定を確認してください。');
      return;
    }
    container.setAttribute('data-acms-turnstile-rendered', 'true');

    var buttons = submitButtons(container);
    setEnabled(buttons, false);

    var options = {
      sitekey: sitekey,
      callback: function () {
        setEnabled(buttons, true);
      },
      'expired-callback': function () {
        setEnabled(buttons, false);
      },
      'error-callback': function () {
        setEnabled(buttons, false);
      },
    };
    Object.keys(OPTION_ATTRIBUTES).forEach(function (attr) {
      var value = container.getAttribute('data-' + attr);
      if (value) {
        options[OPTION_ATTRIBUTES[attr]] = value;
      }
    });
    window.turnstile.render(container, options);
  }

  window.acmsTurnstileOnload = function () {
    Array.prototype.slice.call(document.querySelectorAll(SELECTOR)).forEach(render);
  };
})();
