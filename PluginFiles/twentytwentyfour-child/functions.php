<?php
// 子テーマ用 functions.php

// 親テーマのスタイルを読み込む
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style('parent-style', get_template_directory_uri() . '/style.css');
    wp_enqueue_style('child-style', get_stylesheet_directory_uri() . '/style.css', ['parent-style']);
});

// 1. クラシックメニュー機能を追加（FSEでも「外観→メニュー」を使えるようにする）
add_action('after_setup_theme', function() {
    register_nav_menus([
        'primary' => __('Primary Menu', 'your-theme-textdomain'),
    ]);
});

// 2. 未ログイン時に requires-login クラスを含むメニュー項目を削除
add_filter('wp_nav_menu_objects', function ($items, $args) {
    if (!is_user_logged_in()) {
        foreach ($items as $index => $item) {
            if (in_array('requires-login', (array) $item->classes, true)) {
                unset($items[$index]);
            }
        }
    }
    return $items;
}, 10, 2);
