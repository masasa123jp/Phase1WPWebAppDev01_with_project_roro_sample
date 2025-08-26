<?php
/**
 * Presentation layer for the RORO magazine.  This class defines
 * front end shortcodes used to embed magazine content within posts
 * and pages.  It also handles enqueueing the necessary styles and
 * scripts and localising messages for JavaScript.  Splitting
 * shortcode rendering into its own class keeps the main plugin
 * bootstrap lean.
 */
class RORO_Mag_Shortcodes {

    /**
     * Register WordPress shortcodes.  Must be called at plugin load
     * time.  Each shortcode delegates to a separate static method
     * for clarity.
     */
    public static function init(): void {
        add_shortcode('roro_magazine', [ __CLASS__, 'magazine' ]);
        add_shortcode('roro_mag_issue', [ __CLASS__, 'issue' ]);
        add_shortcode('roro_mag_article', [ __CLASS__, 'article' ]);
    }

    /**
     * Register the front end styles and scripts.  Styles are kept
     * deliberately lightweight to avoid overriding theme styles.  The
     * slider script is a small vanilla implementation supporting
     * keyboard navigation and responsive sizing.  Scripts are
     * registered only; they are enqueued on demand in individual
     * shortcode handlers.  Version numbers are derived from the
     * plugin version constant to aid cache busting.
     */
    public static function register_assets(): void {
        $ver = RORO_MAG_PLUGIN_VERSION;
        $base = RORO_MAG_PLUGIN_URL . 'assets/';
        // General magazine list and issue view styling
        wp_register_style('roro-magazine', $base . 'css/magazine.css', [], $ver);
        // Slider specific styles
        wp_register_style('roro-magazine-slider', $base . 'css/slider.css', [], $ver);
        // Scripts
        wp_register_script('roro-magazine', $base . 'js/magazine.js', [], $ver, true);
        wp_register_script('roro-magazine-slider', $base . 'js/slider.js', [], $ver, true);
    }

    /**
     * Shortcode handler for `[roro_magazine]`.  Renders a grid of
     * issues or, if a `mag_issue` query parameter is present, a
     * specific issue view.  Accepts optional attributes: `lang`
     * overrides the detected language and `limit`/`offset` for
     * pagination.  Front end assets are enqueued as needed and
     * messages for the Read More/Read Less buttons are injected into
     * JavaScript via `wp_localize_script`.
     *
     * @param array<string,mixed> $atts
     * @return string HTML markup
     */
    public static function magazine(array $atts): string {
        // Normalise attributes
        $atts = shortcode_atts([
            'lang'   => '',
            'limit'  => 12,
            'offset' => 0,
        ], $atts, 'roro_magazine');
        $svc  = new RORO_Mag_Service();
        $lang = $atts['lang'] !== '' && in_array($atts['lang'], ['ja','en','zh','ko'], true)
            ? $atts['lang']
            : $svc->detect_lang();
        $M    = $svc->load_messages($lang);

        // If a specific issue is requested via the URL then render
        // that issue instead of the list.  This allows the same
        // shortcode to handle both views depending on context.  A
        // direct attribute override is not supported here because
        // `[roro_mag_issue]` already covers that use case.
        $issue_id = 0;
        if (isset($_GET['mag_issue'])) {
            $issue_id = intval($_GET['mag_issue']);
        }
        // Enqueue base assets.  These styles apply to both lists and
        // issue views.  The script controls the Read More/Read Less
        // toggling on article summaries.
        wp_enqueue_style('roro-magazine');
        wp_enqueue_script('roro-magazine');
        // Pass translated strings to the JavaScript controlling
        // summary toggle buttons.  Provide sensible defaults if the
        // message dictionary is missing keys.
        wp_localize_script('roro-magazine', 'ROROMAG_I18N', [
            'read_more' => $M['read_more'] ?? __('Read more', 'roro-magazine'),
            'read_less' => $M['read_less'] ?? __('Read less', 'roro-magazine'),
        ]);
        // Build data for the template
        $data = [
            'lang'    => $lang,
            'M'       => $M,
            'issueId' => $issue_id,
            'limit'   => intval($atts['limit']),
            'offset'  => intval($atts['offset']),
        ];
        // Capture output of the appropriate template
        ob_start();
        // If an issue is requested, reuse the issue template via include
        if ($issue_id) {
            include RORO_MAG_PLUGIN_DIR . 'templates/issue-view.php';
        } else {
            include RORO_MAG_PLUGIN_DIR . 'templates/magazine-list.php';
        }
        return ob_get_clean();
    }

    /**
     * Shortcode handler for `[roro_mag_issue]`.  Renders a page
     * turning slider for a single issue.  Accepts attributes:
     *  - `issue`: numeric ID, slug, year‑month string or the word
     *    `latest`.  Defaults to `latest`.
     *  - `lang`: override the detected language.
     *  - `height`: height of the slider in pixels.  Defaults to
     *    `520`.
     *
     * When the requested issue cannot be found a translated error
     * message is returned.  On success a container with prev/next
     * buttons is returned.  The slider script consumes the
     * localised `RORO_MAG_SLIDER` data object.
     *
     * @param array<string,mixed> $atts
     */
    public static function issue(array $atts): string {
        $atts = shortcode_atts([
            'issue'  => 'latest',
            'lang'   => '',
            'height' => 520,
        ], $atts, 'roro_mag_issue');
        $svc  = new RORO_Mag_Service();
        // Resolve language
        $lang = $atts['lang'] !== '' && in_array($atts['lang'], ['ja','en','zh','ko'], true)
            ? $atts['lang']
            : $svc->detect_lang();
        $M    = $svc->load_messages($lang);
        // Resolve issue identifier
        $issue_id = $svc->resolve_issue_id($atts['issue']);
        if (!$issue_id) {
            // Unknown issue
            return '<div class="roro-mag-empty">' . esc_html($M['no_issues'] ?? __('Issue not found.', 'roro-magazine')) . '</div>';
        }
        // Build the slide deck
        $deck   = $svc->build_issue_deck($issue_id, $lang);
        $height = max(200, intval($atts['height']));
        // Enqueue slider assets
        wp_enqueue_style('roro-magazine-slider');
        wp_enqueue_script('roro-magazine-slider');
        // Localise slider configuration and strings.  Pull labels from
        // our message dictionary when available and fall back to
        // WordPress translations otherwise.  This allows site owners
        // to customise the Prev/Next and Sponsored labels via the
        // lang/*.php files without requiring .mo files.
        wp_localize_script('roro-magazine-slider', 'RORO_MAG_SLIDER', [
            'height' => $height,
            'slides' => $deck,
            'i18n'   => [
                'prev'      => $M['prev'] ?? __('Prev', 'roro-magazine'),
                'next'      => $M['next'] ?? __('Next', 'roro-magazine'),
                'sponsored' => $M['sponsored'] ?? __('Sponsored', 'roro-magazine'),
            ],
        ]);
        // Determine labels for the navigation buttons using our
        // message dictionary with a fallback to WordPress
        // translations.  These will be updated again by JS if
        // provided via the RORO_MAG_SLIDER config but we set them
        // here for initial rendering and screen reader labels.
        $prev_label = $M['prev'] ?? __('Prev', 'roro-magazine');
        $next_label = $M['next'] ?? __('Next', 'roro-magazine');
        // Render the slider container.  The JavaScript will populate
        // the track with slides at runtime.
        ob_start();
        ?>
        <div class="roro-mag-slider" style="height:<?php echo esc_attr($height); ?>px;" role="region" aria-label="<?php echo esc_attr($M['magazine'] ?? __('Magazine', 'roro-magazine')); ?>">
            <div class="roro-mag-track"></div>
            <div class="roro-mag-nav">
                <button type="button" class="roro-mag-prev" aria-label="<?php echo esc_attr($prev_label); ?>">&larr; <?php echo esc_html($prev_label); ?></button>
                <button type="button" class="roro-mag-next" aria-label="<?php echo esc_attr($next_label); ?>"><?php echo esc_html($next_label); ?> &rarr;</button>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Shortcode handler for `[roro_mag_article]`.  Renders a single
     * article view.  Accepts a mandatory `id` attribute.  If the
     * article cannot be found a translated message is shown.  The
     * general magazine style is enqueued but the slider assets are
     * omitted since the article view does not use them.
     *
     * @param array<string,mixed> $atts
     */
    public static function article(array $atts): string {
        $atts = shortcode_atts([
            'id'   => 0,
            'lang' => '',
        ], $atts, 'roro_mag_article');
        $id = intval($atts['id']);
        if (!$id) {
            return '';
        }
        $svc  = new RORO_Mag_Service();
        $lang = $atts['lang'] !== '' && in_array($atts['lang'], ['ja','en','zh','ko'], true)
            ? $atts['lang']
            : $svc->detect_lang();
        $M    = $svc->load_messages($lang);
        $post = get_post($id);
        if (!$post || $post->post_type !== 'roro_mag_article') {
            return '<div class="roro-mag-empty">' . esc_html($M['no_articles'] ?? __('Article not found.', 'roro-magazine')) . '</div>';
        }
        // Enqueue base style (not slider)
        wp_enqueue_style('roro-magazine');
        $article = $svc->article_payload($post, $lang);
        $data    = [
            'lang'    => $lang,
            'M'       => $M,
            'article' => $article,
        ];
        ob_start();
        include RORO_MAG_PLUGIN_DIR . 'templates/article-view.php';
        return ob_get_clean();
    }
}