<?php
// Enqueue styles and scripts for Project RORO Sample theme.
function project_roro_sample_enqueue_assets() {
    $theme_uri = get_template_directory_uri();
    // Stylesheet
    wp_enqueue_style('project-roro-sample-styles', $theme_uri . '/assets/css/styles.css', [], filemtime(get_template_directory() . '/assets/css/styles.css'));
    // Common scripts
    wp_enqueue_script('project-roro-sample-main', $theme_uri . '/assets/js/main.js', [], filemtime(get_template_directory() . '/assets/js/main.js'), true);
    wp_enqueue_script('project-roro-sample-lang', $theme_uri . '/assets/js/lang.js', [], filemtime(get_template_directory() . '/assets/js/lang.js'), true);
    // Conditional scripts
    if (is_page_template('page-map.php')) {
        wp_enqueue_script('project-roro-sample-events', $theme_uri . '/assets/data/events.js', [], filemtime(get_template_directory() . '/assets/data/events.js'), true);
        // Google/Here マップ用のスクリプトを読み込み。APIキーは外部から取得します。
        wp_enqueue_script('project-roro-sample-map', $theme_uri . '/assets/js/map.js', [], filemtime(get_template_directory() . '/assets/js/map.js'), true);
        // API キーの取得: 環境変数が優先され、なければ WordPress オプションから取得します
        $gmaps_key = getenv('GOOGLE_MAPS_API_KEY');
        if (! $gmaps_key) {
            // プラグイン roro-core の一般設定を利用してキーを取得
            $options   = get_option( \RoroCore\Settings\General_Settings::OPTION_KEY );
            $gmaps_key = $options['gmaps_key'] ?? '';
        }
        $here_key = getenv('HERE_MAPS_API_KEY');
        if (! $here_key) {
            $options   = get_option( 'project_roro_options' );
            $here_key = $options['here_maps_api_key'] ?? '';
        }
        // JavaScript へローカライズして渡す
        wp_localize_script('project-roro-sample-map', 'roro_globals', [
            'google_maps_api_key' => $gmaps_key,
            'here_maps_api_key'   => $here_key,
        ]);
    }
    if (is_page_template('page-profile.php')) {
        wp_enqueue_script('project-roro-sample-profile', $theme_uri . '/assets/js/profile.js', [], filemtime(get_template_directory() . '/assets/js/profile.js'), true);
    }
    if (is_page_template('page-favorites.php')) {
        wp_enqueue_script('project-roro-sample-favorites', $theme_uri . '/assets/js/favorites.js', [], filemtime(get_template_directory() . '/assets/js/favorites.js'), true);
    }
    if (is_page_template('page-magazine.php')) {
        wp_enqueue_script('project-roro-sample-magazine', $theme_uri . '/assets/js/magazine.js', [], filemtime(get_template_directory() . '/assets/js/magazine.js'), true);
    }
    if (is_page_template('page-dify.php')) {
        wp_enqueue_script('project-roro-sample-dify', $theme_uri . '/assets/js/dify.js', [], filemtime(get_template_directory() . '/assets/js/dify.js'), true);
    }
    if (is_page_template('page-signup.php')) {
        wp_enqueue_script('project-roro-sample-signup', $theme_uri . '/assets/js/signup.js', [], filemtime(get_template_directory() . '/assets/js/signup.js'), true);
    }
    if (is_front_page()) {
        wp_enqueue_script('project-roro-sample-login', $theme_uri . '/assets/js/login.js', [], filemtime(get_template_directory() . '/assets/js/login.js'), true);
    }
}
add_action('wp_enqueue_scripts', 'project_roro_sample_enqueue_assets');
// Support title tag
add_theme_support('title-tag');
?>
