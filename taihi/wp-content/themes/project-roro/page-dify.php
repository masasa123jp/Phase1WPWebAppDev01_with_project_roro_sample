<?php
/* Template Name: AIアシスタントページ */
get_header();
?>
<header class="app-header">
  <img src="<?php echo get_template_directory_uri(); ?>/assets/images/logo_roro.png" alt="ロゴ" class="small-logo" />
  <h2 data-i18n-key="ai_title">AIアシスタント</h2>
  <button id="lang-toggle-btn" class="lang-toggle" title="Change language">
    <img src="<?php echo get_template_directory_uri(); ?>/assets/images/switch-language.png" alt="Language" />
  </button>
</header>
<main class="dify-container">
  <p data-i18n-key="ai_intro">
    以下のチャットボックスでは、DifyのAIアシスタントと対話することができます。ペットの
    イベント情報やおすすめスポットなど、気になることを気軽に質問してみましょう。
  </p>
  <div id="dify-chat">
    <div class="chat-messages">
      <div class="message bot" data-i18n-key="ai_welcome">こんにちは！どんなイベントをお探しですか？</div>
    </div>
    <form id="chat-form">
      <input type="text" id="chat-input" placeholder="メッセージを入力..." autocomplete="off" data-i18n-placeholder="chat_placeholder" />
      <button type="submit" class="btn primary-btn" data-i18n-key="send">送信</button>
    </form>
  </div>
  <p class="note" data-i18n-key="ai_note">
    ※このチャットはモックアップです。実際のAI連携にはDifyが提供するスクリプトを読み込んでください。
  </p>
</main>
<?php get_footer(); ?>
