<?php
/**
 * app-index.php（ハブページ・テンプレート）
 *
 * 目的:
 *  - テーマや複数固定ページに分解せず、1ページで「Magazine / Map / Favorites / AI」を並べて
 *    “アプリ風”のトップ画面を提供するためのテンプレート。
 *  - 既存の index.html の役割を WP 内へ移植したもの。
 *
 * 注意:
 *  - プラグインのエントリは roro-core-wp.php。通常の画面はショートコードで提供可能なので、
 *    app-index.php は“必須”ではありません。ハブページが欲しい場合のみ使ってください。
 *  - このテンプレートは「テンプレート片」です。テーマで get_header()/get_footer() を呼ぶ構成に
 *    している場合は固定ページテンプレートとしても使えますし、ショートコード側から require して
 *    使うことも出来ます（下の enqueue 部分で必要アセットを読み込みます）。
 */

if (!defined('ABSPATH')) exit;

// ---- 必要アセットの読み込み --------------------------------------------
// ここで enqueue しておくと、ショートコードから require しても確実に読み込まれます。
// （テーマ側の header.php で読み込んでもOKですが、重複防止のため register→enqueue が推奨）
wp_enqueue_style('roro-core');        // 共通CSS
wp_enqueue_script('roro-main');       // 共通JS（ナビ/ログインチェック等）
wp_enqueue_script('roro-lang');       // 多言語UI

// 各セクションで使うJS（Magazine/Map/Favorites/AI）
wp_enqueue_script('roro-magazine');
wp_enqueue_script('roro-map');        // Mapセクションは「カード一覧」用途。地図キャンバス表示は map-template.php を推奨
wp_enqueue_script('roro-favorites');
wp_enqueue_script('roro-dify-switch'); // AIセクションの表示切替（Dify or Custom）
wp_enqueue_script('roro-dify-embed');  // Dify埋め込み（iframe/script）
wp_enqueue_script('roro-custom-chat'); // CustomチャットUI（APIが無いときはデモ応答）
?>
<div class="roro-container" role="main" aria-label="RORO App">
  <header class="roro-header" role="banner">
    <img src="<?php echo esc_url(RORO_CORE_WP_URL.'assets/images/logo_roro.png'); ?>" alt="RORO" class="roro-logo" />

    <!-- グローバルナビ:
         - ログイン状態に応じて表示を分岐
         - “#profile” “#favorites” はテーマのフロントページにセクションがある想定のアンカー
           ※本テンプレート内にプロフィール編集を入れない理由: フォーム送信・状態管理が煩雑になるため。
             プロフィールは専用ページ/ショートコード [roro_profile] を推奨。 -->
    <nav class="roro-nav" role="navigation" aria-label="Primary">
      <a href="<?php echo esc_url( home_url('/') ); ?>">
        <?php echo esc_html__('Home', 'roro-core-wp'); ?>
      </a>
      <?php if (is_user_logged_in()): ?>
        <a href="<?php echo esc_url( get_permalink( get_option('page_on_front') ) ); ?>#profile">
          <?php echo esc_html__('Profile', 'roro-core-wp'); ?>
        </a>
        <a href="<?php echo esc_url( get_permalink( get_option('page_on_front') ) ); ?>#favorites">
          <?php echo esc_html__('Favorites', 'roro-core-wp'); ?>
        </a>
      <?php else: ?>
        <a href="<?php echo esc_url( site_url('/login/') ); ?>">
          <?php echo esc_html__('Login', 'roro-core-wp'); ?>
        </a>
        <a href="<?php echo esc_url( site_url('/signup/') ); ?>">
          <?php echo esc_html__('Sign up', 'roro-core-wp'); ?>
        </a>
      <?php endif; ?>
    </nav>
  </header>

  <!-- ========== Magazine セクション ==========
       - roro-magazine.js が #magazine-list にカードを描画
       - コンテンツは静的/ダミー。将来的に REST で OPAM/CDL と連携 -->
  <section id="magazine" class="roro-section" aria-labelledby="magazine-title">
    <h2 id="magazine-title"><?php echo esc_html__('Magazine', 'roro-core-wp'); ?></h2>
    <div id="magazine-list" class="grid" role="region" aria-live="polite"></div>
  </section>

  <!-- ========== Map セクション（カード一覧） ==========
       - ここでは “カード一覧” を描画する簡易UI（地図キャンバスは別テンプレートで提供）
       - 検索条件: キーワード / 都道府県。REST未連携のため roro-map.js 内で簡易描画 -->
  <section id="map" class="roro-section" aria-labelledby="map-title">
    <h2 id="map-title"><?php echo esc_html__('Map', 'roro-core-wp'); ?></h2>
    <div class="map-search" role="search" aria-label="Map search">
      <input type="text" id="map-q" placeholder="<?php echo esc_attr__('キーワード', 'roro-core-wp'); ?>" aria-label="<?php echo esc_attr__('検索キーワード', 'roro-core-wp'); ?>">
      <label for="map-pref" class="screen-reader-text"><?php echo esc_html__('都道府県', 'roro-core-wp'); ?></label>
      <select id="map-pref">
        <option value=""><?php echo esc_html__('都道府県', 'roro-core-wp'); ?></option>
      </select>
      <button id="map-search-btn" class="btn" aria-label="<?php echo esc_attr__('検索', 'roro-core-wp'); ?>">
        <?php echo esc_html__('検索', 'roro-core-wp'); ?>
      </button>
    </div>
    <div id="map-list" class="grid" role="region" aria-live="polite"></div>
  </section>

  <!-- ========== Favorites セクション ==========
       - ここは “カード一覧” の即時表示のみ。
       - 永続化はローカルストレージ。将来的に REST (RORO_MAP_FAVORITE) で置換 -->
  <section id="favorites" class="roro-section" aria-labelledby="favorites-title">
    <h2 id="favorites-title"><?php echo esc_html__('Favorites', 'roro-core-wp'); ?></h2>
    <div id="favorites-list" class="grid" role="region" aria-live="polite"></div>
  </section>

  <!-- ========== AI セクション ==========
       - roro-dify-switch.js が #roro-ai-switch に「公式Dify or Customチャット」をマウント
       - /api/chat が無ければ Custom はデモ応答で動作 -->
  <section id="ai" class="roro-section" aria-labelledby="ai-title">
    <h2 id="ai-title"><?php echo esc_html__('AI Assistant', 'roro-core-wp'); ?></h2>
    <div id="roro-ai-switch" data-mode="dify" role="region" aria-live="polite"></div>
  </section>
</div>
