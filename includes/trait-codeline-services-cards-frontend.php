<?php
/**
 * CodeLine_Services_Cards_Frontend_Trait responsibilities for CodeLine Services Cards.
 *
 * @package CodeLineServicesCards
 */

if (!defined('ABSPATH')) {
    exit;
}

trait CodeLine_Services_Cards_Frontend_Trait {
    public function prepend_service_page_title_to_content($content) {
            if (is_admin() || !is_singular(self::SERVICE_POST_TYPE) || !in_the_loop() || !is_main_query()) {
                return $content;
            }

            $post_id = get_the_ID();
            if (!$post_id) {
                return $content;
            }

            $term = $this->get_service_term_for_page((int) $post_id);
            $title = '';

            if ($term instanceof WP_Term) {
                $custom_title = (string) get_term_meta($term->term_id, self::TERM_META_SERVICE_TITLE, true);
                $title = trim($custom_title) !== '' ? $custom_title : $term->name;
            }

            if ($title === '') {
                $title = get_the_title($post_id);
            }

            if ($title === '') {
                return $content;
            }

            $intro_content = '';
            $remaining_content = $content;
            if (preg_match('/^(\s*<p\b[^>]*>.*?<\/p>)(.*)$/is', (string) $content, $matches)) {
                $intro_content = (string) $matches[1];
                $remaining_content = (string) $matches[2];
            }

            $media = '';
            if (has_post_thumbnail($post_id)) {
                $media = '<section class="clsp-detail-media">' .
                    get_the_post_thumbnail($post_id, 'full', array('loading' => 'eager', 'class' => 'clsp-detail-media-image')) .
                    '</section>';
            }

                $heading = '<h1 class="clsp-detail-title">' . esc_html($title) . '</h1>';

                $extra = '';

                $rows = '';
                $detail_items = get_post_meta($post_id, self::META_SERVICE_DETAIL_ITEMS, true);
                if (is_array($detail_items) && !empty($detail_items)) {
                    foreach ($detail_items as $item) {
                        if (!is_array($item)) {
                            continue;
                        }
                        $item_title = isset($item['title']) ? trim((string) $item['title']) : '';
                        $item_desc = isset($item['description']) ? trim((string) $item['description']) : '';
                        if ($item_title === '' && $item_desc === '') {
                            continue;
                        }

                        $rows .= '<article class="clsp-detail-row">';
                        $rows .= '<h3 class="clsp-detail-row-title">' . esc_html($item_title) . '</h3>';
                        $rows .= '<div class="clsp-detail-row-text">' . wp_kses_post(wpautop($item_desc)) . '</div>';
                        $rows .= '</article>';
                    }
                }

                $case_ids = get_post_meta($post_id, self::META_SERVICE_DETAIL_CASE_IDS, true);
                $cards = '';
                if (is_array($case_ids) && !empty($case_ids)) {
                    $case_ids = array_values(array_unique(array_filter(array_map('absint', $case_ids))));
                    if (!empty($case_ids)) {
                        $case_query = new WP_Query(array(
                            'post_type'           => $this->resolve_case_post_type(self::CASE_POST_TYPE_DEFAULT),
                            'post_status'         => 'publish',
                            'posts_per_page'      => count($case_ids),
                            'post__in'            => $case_ids,
                            'orderby'             => 'post__in',
                            'ignore_sticky_posts' => true,
                        ));

                        if ($case_query->have_posts()) {
                            while ($case_query->have_posts()) {
                                $case_query->the_post();
                                $thumb = get_the_post_thumbnail(get_the_ID(), 'large', array('class' => 'clsp-detail-case-image'));
                                if ($thumb === '') {
                                    $thumb = '<div class="clsp-detail-case-image clsp-detail-case-image--placeholder"></div>';
                                }

                                $cards .= '<a class="clsp-detail-case" href="' . esc_url(get_permalink()) . '">';
                                $cards .= $thumb;
                                $cards .= '<span class="clsp-detail-case-title">' . esc_html(get_the_title()) . '</span>';
                                $cards .= '</a>';
                            }
                            wp_reset_postdata();
                        }
                    }
                }

                if ($rows !== '' || $cards !== '') {
                    $extra .= '<section class="clsp-detail-split" aria-label="Service onderdelen en cases">';
                    if ($rows !== '') {
                        $extra .= '<div class="clsp-detail-split-left"><div class="clsp-detail-rows">' . $rows . '</div></div>';
                    }
                    if ($cards !== '') {
                        $extra .= '<aside class="clsp-detail-split-right"><div class="clsp-detail-cases" aria-label="Service cases">' . $cards . '</div></aside>';
                    }
                    $extra .= '</section>';
                }

                $before_media = '<div class="clsp-detail-content clsp-detail-content--before-media">' . $heading . $intro_content . '</div>';
                $after_media = '<div class="clsp-detail-content clsp-detail-content--after-media">' . $remaining_content . $extra . '</div>';

                return '<div class="clsp-detail-wrap">' . $before_media . $media . $after_media . '</div>';
        }

    private function parse_labels($labels_raw) {
            if ('' === trim((string) $labels_raw)) {
                return array();
            }

            $parts = array_map('trim', explode(',', (string) $labels_raw));
            $parts = array_filter($parts, static function ($item) {
                return '' !== $item;
            });

            return array_values(array_unique($parts));
        }

    public function render_services_page_shortcode($atts) {
            $late_style_markup = $this->ensure_services_page_assets();
            $atts = shortcode_atts(
                array(
                    'taxonomy' => self::SERVICE_TAXONOMY,
                    'limit' => -1,
                    'case_post_type' => self::CASE_POST_TYPE_DEFAULT,
                    'case_taxonomy' => self::CASE_TAXONOMY_DEFAULT,
                    'case_count' => 2,
                    'case_link_mode' => 'single',
                    'case_target_url' => '',
                    'case_query_key' => 'category',
                ),
                $atts,
                'codeline_services_page'
            );

            $taxonomy = sanitize_key((string) $atts['taxonomy']);
            if (!taxonomy_exists($taxonomy)) {
                return '';
            }

            $case_post_type = $this->resolve_case_post_type((string) $atts['case_post_type']);

            $case_taxonomy = $this->resolve_case_taxonomy($case_post_type, (string) $atts['case_taxonomy']);

            $case_count = max(1, min(8, (int) $atts['case_count']));

            $case_link_mode = strtolower((string) $atts['case_link_mode']);
            if (!in_array($case_link_mode, array('single', 'archive'), true)) {
                $case_link_mode = 'single';
            }

            $case_target_url = esc_url_raw((string) $atts['case_target_url']);
            $case_query_key = sanitize_key((string) $atts['case_query_key']);
            if ('' === $case_query_key) {
                $case_query_key = 'category';
            }

            $service_terms = get_terms(array(
                'taxonomy'   => $taxonomy,
                'hide_empty' => false,
                'number'     => (int) $atts['limit'] > 0 ? (int) $atts['limit'] : 0,
                'meta_query' => array(
                    array(
                        'key'   => self::TERM_META_SERVICE_ENABLED,
                        'value' => '1',
                    ),
                ),
            ));

            if (is_array($service_terms) && !empty($service_terms)) {
                usort($service_terms, function ($a, $b) {
                    $a_order = (int) get_term_meta($a->term_id, self::TERM_META_SERVICE_ORDER, true);
                    $b_order = (int) get_term_meta($b->term_id, self::TERM_META_SERVICE_ORDER, true);

                    if ($a_order === $b_order) {
                        return strcasecmp((string) $a->name, (string) $b->name);
                    }

                    return $a_order <=> $b_order;
                });
            }

            if (is_wp_error($service_terms) || empty($service_terms)) {
                return '';
            }

            $intro = $this->get_service_page_intro_settings();
            $intro_title = isset($intro['title']) ? (string) $intro['title'] : '';
            $intro_text = isset($intro['text']) ? (string) $intro['text'] : '';

            ob_start();
            $style_settings = $this->get_service_style_settings();
            $style_vars = $this->get_style_css_vars();
            ?>
            <section class="clsp" aria-label="Services pagina" style="<?php echo esc_attr($style_vars); ?>">
                <?php if ('' !== trim($intro_title) || '' !== trim($intro_text)) : ?>
                    <header class="clsp-page-intro">
                        <?php if ('' !== trim($intro_title)) : ?>
                            <h2 class="clsp-page-intro-title"><?php echo esc_html($intro_title); ?></h2>
                        <?php endif; ?>

                        <?php if ('' !== trim($intro_text)) : ?>
                            <p class="clsp-page-intro-text"><?php echo esc_html($intro_text); ?></p>
                        <?php endif; ?>
                    </header>
                <?php endif; ?>

                <div class="clsp-list">
                    <?php foreach ($service_terms as $term) : ?>
                        <?php
                        $service_title = (string) get_term_meta($term->term_id, self::TERM_META_SERVICE_TITLE, true);
                        $service_text = (string) get_term_meta($term->term_id, self::TERM_META_SERVICE_TEXT, true);
                        $service_labels_raw = (string) get_term_meta($term->term_id, self::TERM_META_SERVICE_LABELS, true);
                        if ('' === trim($service_title)) {
                            $service_title = $term->name;
                        }

                        if ('' === trim($service_text)) {
                            $service_text = $term->description;
                        }

                        $service_labels = $this->parse_labels($service_labels_raw);
                        $read_more_url = $this->resolve_service_read_more_url($term);
                        $selected_case_1 = (int) get_term_meta($term->term_id, self::TERM_META_SERVICE_CASE_1, true);
                        $selected_case_2 = (int) get_term_meta($term->term_id, self::TERM_META_SERVICE_CASE_2, true);
                        $selected_case_ids = array_values(array_unique(array_filter(array($selected_case_1, $selected_case_2))));

                        $case_term_field = 'term_id';
                        $case_term_value = (int) $term->term_id;
                        if ($case_taxonomy !== $taxonomy) {
                            $case_term_field = 'slug';
                            $case_term_value = $term->slug;
                        }

                        $posts_query = null;
                        if ('' !== $case_post_type && '' !== $case_taxonomy) {
                            $posts_query_args = array(
                                'post_type'           => $case_post_type,
                                'post_status'         => 'publish',
                                'posts_per_page'      => $case_count,
                                'ignore_sticky_posts' => true,
                            );

                            if (!empty($selected_case_ids)) {
                                $posts_query_args['post__in'] = $selected_case_ids;
                                $posts_query_args['orderby'] = 'post__in';
                                $posts_query_args['posts_per_page'] = count($selected_case_ids);
                            } else {
                                $posts_query_args['tax_query'] = array(
                                    array(
                                        'taxonomy' => $case_taxonomy,
                                        'field'    => $case_term_field,
                                        'terms'    => array($case_term_value),
                                    ),
                                );
                            }

                            if (self::POST_TYPE === $case_post_type) {
                                $posts_query_args['meta_query'] = array(
                                    array(
                                        'key'   => self::META_SERVICE_VISIBLE,
                                        'value' => '1',
                                    ),
                                );
                            }

                            $posts_query = new WP_Query($posts_query_args);
                        }

                        $term_archive_link = '';
                        if ('archive' === $case_link_mode && '' !== $case_taxonomy) {
                            if ($case_taxonomy === $taxonomy) {
                                $term_archive_link = get_term_link((int) $term->term_id, $case_taxonomy);
                            } else {
                                $case_term = get_term_by('slug', $term->slug, $case_taxonomy);
                                if ($case_term && !is_wp_error($case_term)) {
                                    $term_archive_link = get_term_link((int) $case_term->term_id, $case_taxonomy);
                                }
                            }

                            if (!empty($case_target_url)) {
                                $term_archive_link = add_query_arg($case_query_key, $term->slug, $case_target_url);
                            } elseif (is_wp_error($term_archive_link)) {
                                $term_archive_link = '';
                            }
                        }
                        ?>
                        <article class="clsp-item">
                            <div class="clsp-copy">
                                <h3 class="clsp-title"><?php echo esc_html($service_title); ?></h3>

                                <?php if ('' !== trim($service_text)) : ?>
                                    <p class="clsp-text"><?php echo esc_html($service_text); ?></p>
                                <?php endif; ?>

                                <?php if (!empty($service_labels)) : ?>
                                    <div class="clsp-labels" aria-label="Service labels">
                                        <?php foreach ($service_labels as $label) : ?>
                                            <span class="clsp-pill"><?php echo esc_html($label); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($style_settings['readmore_show'] === '1') : ?>
                                <a class="clsp-cta" href="<?php echo esc_url($read_more_url !== '' ? $read_more_url : home_url('/')); ?>"><?php esc_html_e('Lees meer', 'codeline-services-cards'); ?></a>
                                <?php endif; ?>
                            </div>

                            <div class="clsp-cases" aria-label="Service cases">
                                <?php if ($posts_query instanceof WP_Query && $posts_query->have_posts()) : ?>
                                    <?php while ($posts_query->have_posts()) : $posts_query->the_post(); ?>
                                        <?php $case_image_id = (int) get_post_meta(get_the_ID(), self::META_IMAGE_ID, true); ?>
                                        <?php
                                        $case_link = get_permalink();
                                        if ('archive' === $case_link_mode && '' !== $term_archive_link) {
                                            $case_link = $term_archive_link;
                                        }
                                        ?>
                                        <a class="clsp-case" href="<?php echo esc_url($case_link); ?>">
                                            <?php if (has_post_thumbnail()) : ?>
                                                <?php the_post_thumbnail('large', array('class' => 'clsp-case-image')); ?>
                                            <?php elseif ($case_image_id > 0) : ?>
                                                <?php echo wp_get_attachment_image($case_image_id, 'large', false, array('class' => 'clsp-case-image')); ?>
                                            <?php else : ?>
                                                <div class="clsp-case-image clsp-case-image--placeholder"></div>
                                            <?php endif; ?>
                                            <span class="clsp-case-title"><?php echo esc_html(get_the_title()); ?></span>
                                        </a>
                                    <?php endwhile; ?>
                                <?php else : ?>
                                    <div class="clsp-case clsp-case--empty">
                                        <div class="clsp-case-image clsp-case-image--placeholder"></div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </article>
                        <?php if ($posts_query instanceof WP_Query) { wp_reset_postdata(); } ?>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php

            return $late_style_markup . ob_get_clean();
        }

    private function asset_version($relative_path) {
            // Cache busting follows the plugin version (bumped on every
            // release), matching the CodeLine Cases plugin's approach.
            return self::VERSION;
        }

    private function register_frontend_assets() {
            if (!wp_style_is('codeline-services-cards', 'registered')) {
                wp_register_style(
                    'codeline-services-cards',
                    CLSC_PLUGIN_URL . 'assets/services-cards.css',
                    array(),
                    $this->asset_version('assets/services-cards.css')
                );
            }

            if (!wp_style_is('codeline-services-enhancements', 'registered')) {
                wp_register_style(
                    'codeline-services-enhancements',
                    CLSC_PLUGIN_URL . 'assets/services-enhancements.css',
                    array('codeline-services-cards'),
                    $this->asset_version('assets/services-enhancements.css')
                );
            }

            if (!wp_style_is('codeline-services-background', 'registered')) {
                wp_register_style(
                    'codeline-services-background',
                    CLSC_PLUGIN_URL . 'assets/services-background.css',
                    array(),
                    $this->asset_version('assets/services-background.css')
                );
            }

            if (!wp_script_is('codeline-services-cards', 'registered')) {
                wp_register_script(
                    'codeline-services-cards',
                    CLSC_PLUGIN_URL . 'assets/services-cards.js',
                    array(),
                    $this->asset_version('assets/services-cards.js'),
                    true
                );
            }

            if (!wp_script_is('codeline-services-background', 'registered')) {
                wp_register_script(
                    'codeline-services-background',
                    CLSC_PLUGIN_URL . 'assets/services-background.js',
                    array(),
                    $this->asset_version('assets/services-background.js'),
                    true
                );
            }
        }

    private function current_post_contains_marker($needle) {
            $needle = (string) $needle;
            if ('' === $needle) {
                return false;
            }

            $post_id = get_queried_object_id();
            if ($post_id <= 0) {
                return false;
            }

            $content = (string) get_post_field('post_content', $post_id);
            if (false !== strpos($content, $needle)) {
                return true;
            }

            $elementor_data = get_post_meta($post_id, '_elementor_data', true);
            if (is_string($elementor_data) && false !== strpos($elementor_data, $needle)) {
                return true;
            }

            return false;
        }

    private function current_page_uses_cards() {
            return $this->current_post_contains_marker('codeline_services_cards');
        }

    private function current_page_uses_services_page() {
            return $this->current_post_contains_marker('codeline_services_page');
        }

    private function current_page_uses_background() {
            return $this->current_post_contains_marker('codeline-services-background');
        }

    private function capture_late_style_markup($handles) {
            if (!did_action('wp_head')) {
                return '';
            }

            $pending = array();
            foreach ((array) $handles as $handle) {
                if (wp_style_is($handle, 'enqueued') && !wp_style_is($handle, 'done')) {
                    $pending[] = $handle;
                }
            }

            if (empty($pending)) {
                return '';
            }

            ob_start();
            wp_print_styles($pending);
            return (string) ob_get_clean();
        }

    private function ensure_card_assets() {
            $this->register_frontend_assets();
            wp_enqueue_style('codeline-services-cards');
            wp_enqueue_style('codeline-services-enhancements');
            wp_enqueue_style('codeline-services-background');
            wp_enqueue_script('codeline-services-cards');
            wp_enqueue_script('codeline-services-background');

            // The Canvas background auto-detects the Services section from this
            // shortcode's own `.clsc` markup, so its assets are always loaded
            // alongside the cards themselves rather than depending on a
            // manually-added Elementor class being present in the page content.
            return $this->capture_late_style_markup(array('codeline-services-cards', 'codeline-services-enhancements', 'codeline-services-background'));
        }

    private function ensure_services_page_assets() {
            $this->register_frontend_assets();
            wp_enqueue_style('codeline-services-cards');

            return $this->capture_late_style_markup(array('codeline-services-cards'));
        }

    public function enqueue_assets() {
            if (is_admin()) {
                return;
            }

            $this->register_frontend_assets();

            $uses_cards         = $this->current_page_uses_cards();
            $uses_services_page = $this->current_page_uses_services_page();
            $uses_background    = $this->current_page_uses_background();
            $is_service_detail  = is_singular(self::SERVICE_POST_TYPE);

            if ($uses_cards || $uses_services_page || $is_service_detail) {
                wp_enqueue_style('codeline-services-cards');
            }

            if ($uses_cards) {
                wp_enqueue_style('codeline-services-enhancements');
                wp_enqueue_script('codeline-services-cards');

                // The cards shortcode's own `.clsc` markup is what the Canvas
                // background auto-detects, so its assets belong wherever the
                // cards render, independent of any manual Elementor class.
                wp_enqueue_style('codeline-services-background');
                wp_enqueue_script('codeline-services-background');
            }

            if ($uses_background) {
                wp_enqueue_style('codeline-services-background');
                wp_enqueue_script('codeline-services-background');
            }
        }

    public function print_dynamic_asset_fallback_loader() {
            if (is_admin()) {
                return;
            }

            $this->register_frontend_assets();

            // Shortcodes injected from PHP or an Elementor Global Template call their
            // renderer directly, which enqueues card assets (including the Canvas
            // background) there. This tiny fallback is only needed when the Services
            // markup (the plugin's own `.clsc` wrapper, or a manual background class)
            // was not visible in post_content or _elementor_data before wp_head.
            if (wp_style_is('codeline-services-background', 'enqueued') && wp_script_is('codeline-services-background', 'enqueued')) {
                return;
            }

            $style_url  = CLSC_PLUGIN_URL . 'assets/services-background.css?ver=' . rawurlencode($this->asset_version('assets/services-background.css'));
            $script_url = CLSC_PLUGIN_URL . 'assets/services-background.js?ver=' . rawurlencode($this->asset_version('assets/services-background.js'));

            $loader = '(function(){"use strict";'
                . 'if(!document.querySelector(".codeline-services-background, .clsc")){return;}'
                . 'if(!document.getElementById("codeline-services-background-css")){' 
                . 'var l=document.createElement("link");l.id="codeline-services-background-css";l.rel="stylesheet";l.href=' . wp_json_encode($style_url) . ';document.head.appendChild(l);}'
                . 'if(!document.getElementById("codeline-services-background-js")){' 
                . 'var s=document.createElement("script");s.id="codeline-services-background-js";s.src=' . wp_json_encode($script_url) . ';s.defer=true;document.body.appendChild(s);}'
                . '}());';

            wp_print_inline_script_tag($loader, array('id' => 'clsc-dynamic-background-loader'));
        }

    private function render_service_card_image($post_id, $image_id, $title) {
            $post_id = absint($post_id);
            $image_id = absint($image_id);
            $title = sanitize_text_field((string) $title);
            $attributes = array(
                'class'    => 'clsc-image',
                'loading'  => 'lazy',
                'decoding' => 'async',
                'sizes'    => '(max-width: 767px) 84vw, (max-width: 1024px) 46vw, 25vw',
            );

            if ($image_id > 0) {
                $alt = trim((string) get_post_meta($image_id, '_wp_attachment_image_alt', true));
                if ('' === $alt) {
                    $attributes['alt'] = $title;
                }
                return (string) wp_get_attachment_image($image_id, 'large', false, $attributes);
            }

            if (has_post_thumbnail($post_id)) {
                $thumbnail_id = (int) get_post_thumbnail_id($post_id);
                if ($thumbnail_id > 0 && '' === trim((string) get_post_meta($thumbnail_id, '_wp_attachment_image_alt', true))) {
                    $attributes['alt'] = $title;
                }
                return (string) get_the_post_thumbnail($post_id, 'large', $attributes);
            }

            return '<div class="clsc-image clsc-image--placeholder" role="img" aria-label="' . esc_attr($title) . '"></div>';
        }

    private function get_service_visibility_meta_query() {
            return array(
                'relation' => 'OR',
                array(
                    'key'     => self::META_SERVICE_VISIBLE,
                    'value'   => '1',
                    'compare' => '=',
                ),
                array(
                    'key'     => self::META_SERVICE_VISIBLE,
                    'compare' => 'NOT EXISTS',
                ),
            );
        }

    private function get_visible_service_posts($limit, $category) {
            // A limit of -1 (or any non-positive value) means "no limit": render
            // every visible Service Page, in Volgorde order, instead of the old
            // fixed 4-card cap.
            $limit    = ((int) $limit) > 0 ? min(200, absint($limit)) : -1;
            $unlimited = (-1 === $limit);
            $category = sanitize_title((string) $category);

            $base_args = array(
                'post_type'           => self::SERVICE_POST_TYPE,
                'post_status'         => 'publish',
                'ignore_sticky_posts' => true,
                'no_found_rows'       => true,
            );

            if ('' !== $category) {
                $base_args['tax_query'] = array(
                    array(
                        'taxonomy' => self::SERVICE_TAXONOMY,
                        'field'    => 'slug',
                        'terms'    => array($category),
                    ),
                );
            }

            // Query ordered cards first. This mirrors the existing display rule without
            // loading every visible Service Page into PHP just to slice the first items.
            $ordered_args = $base_args;
            $ordered_args['posts_per_page'] = $unlimited ? -1 : $limit;
            $ordered_args['meta_query'] = array(
                'relation' => 'AND',
                'visibility_clause' => $this->get_service_visibility_meta_query(),
                'card_order_clause' => array(
                    'key'     => self::META_CARD_ORDER,
                    'compare' => 'EXISTS',
                    'type'    => 'NUMERIC',
                ),
            );
            $ordered_args['orderby'] = array(
                'card_order_clause' => 'ASC',
                'date'              => 'DESC',
                'ID'                => 'ASC',
            );

            $posts = get_posts($ordered_args);
            $remaining = $unlimited ? -1 : ($limit - count($posts));

            if (!$unlimited && $remaining <= 0) {
                return $posts;
            }

            // Backward compatibility for older cards without explicit order metadata:
            // they follow ordered cards and keep the previous newest-first fallback.
            $unordered_args = $base_args;
            $unordered_args['posts_per_page'] = $unlimited ? -1 : $remaining;
            $unordered_args['meta_query'] = array(
                'relation' => 'AND',
                'visibility_clause' => $this->get_service_visibility_meta_query(),
                'card_order_missing_clause' => array(
                    'key'     => self::META_CARD_ORDER,
                    'compare' => 'NOT EXISTS',
                ),
            );
            $unordered_args['orderby'] = array(
                'date' => 'DESC',
                'ID'   => 'ASC',
            );

            return array_merge($posts, get_posts($unordered_args));
        }

    public function render_shortcode($atts) {
            $late_style_markup = $this->ensure_card_assets();

            $display       = $this->get_display_settings();
            $style_vars    = $this->get_style_css_vars();
            $slider_vars   = $this->get_slider_style_css_vars();
            $slider_style  = self::get_slider_style_settings();

            $atts = shortcode_atts(
                array(
                    'limit'        => -1,
                    'desktop'      => $display['desktop'],
                    'tablet'       => $display['tablet'],
                    'mobile'       => $display['mobile'],
                    'desktop_peek' => 72,
                    'tablet_peek'  => 56,
                    'mobile_peek'  => 56,
                    'category'     => '',
                ),
                $atts,
                'codeline_services_cards'
            );

            // -1 (or any non-positive value) means "no limit": show every
            // visible card. A positive value keeps the old explicit-cap behavior
            // for callers that pass one.
            $limit        = (int) $atts['limit'] > 0 ? min(200, (int) $atts['limit']) : -1;
            $desktop      = max(1, min(6, (int) $atts['desktop']));
            $tablet       = max(1, min(4, (int) $atts['tablet']));
            $mobile       = max(1, min(2, (int) $atts['mobile']));
            $desktop_peek = max(0, min(220, (int) $atts['desktop_peek']));
            $tablet_peek  = max(0, min(180, (int) $atts['tablet_peek']));
            $mobile_peek  = 1 === $mobile ? max(0, min(140, (int) $atts['mobile_peek'])) : 0;
            $category     = sanitize_title((string) $atts['category']);

            $posts = $this->get_visible_service_posts($limit, $category);

            if (empty($posts)) {
                return '';
            }

            ob_start();
            ?>
            <section
                class="clsc"
                aria-label="<?php echo esc_attr__('Services', 'codeline-services-cards'); ?>"
                style="--clsc-desktop: <?php echo esc_attr((string) $desktop); ?>; --clsc-tablet: <?php echo esc_attr((string) $tablet); ?>; --clsc-mobile: <?php echo esc_attr((string) $mobile); ?>; --clsc-desktop-peek: <?php echo esc_attr((string) $desktop_peek); ?>px; --clsc-tablet-peek: <?php echo esc_attr((string) $tablet_peek); ?>px; --clsc-mobile-peek: <?php echo esc_attr((string) $mobile_peek); ?>px; <?php echo esc_attr($style_vars); ?> <?php echo esc_attr($slider_vars); ?>"
                data-clsc-autoplay="<?php echo esc_attr($slider_style['autoplay_enabled'] ? '1' : '0'); ?>"
            >
                <div class="clsc-track" role="list" tabindex="0" aria-label="<?php echo esc_attr__('Services carousel, gebruik de pijltjestoetsen om te navigeren', 'codeline-services-cards'); ?>">
                    <?php foreach ($posts as $post) : ?>
                        <?php
                        setup_postdata($post);
                        $post_id     = (int) $post->ID;
                        $link        = get_permalink($post_id);
                        $image_id    = (int) get_post_meta($post_id, self::META_IMAGE_ID, true);
                        $card_title  = (string) get_post_meta($post_id, self::META_CARD_TITLE, true);
                        $card_desc   = (string) get_post_meta($post_id, self::META_CARD_DESC, true);
                        $title       = '' !== trim($card_title) ? $card_title : get_the_title($post_id);
                        $description = '' !== trim($card_desc) ? $card_desc : get_the_excerpt($post_id);

                        if ('' === trim($description)) {
                            $description = wp_trim_words(wp_strip_all_tags((string) get_post_field('post_content', $post_id)), 24);
                        }
                        ?>
                        <article class="clsc-card" role="listitem">
                            <a class="clsc-link" href="<?php echo esc_url($link); ?>" aria-label="<?php echo esc_attr(sprintf(__('Bekijk service: %s', 'codeline-services-cards'), $title)); ?>">
                                <div class="clsc-image-wrap">
                                    <?php echo $this->render_service_card_image($post_id, $image_id, $title); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- WordPress image HTML is escaped by core. ?>
                                </div>
                                <h3 class="clsc-title"><?php echo esc_html($title); ?></h3>
                                <div class="clsc-desc"><?php echo wp_kses_post(wpautop($description)); ?></div>
                            </a>
                        </article>
                    <?php endforeach; ?>
                    <?php wp_reset_postdata(); ?>
                </div>
                <div class="clsc-controls" aria-label="<?php echo esc_attr__('Services navigatie', 'codeline-services-cards'); ?>">
                    <button type="button" class="clsc-btn clsc-arrow clsc-arrow--prev" aria-label="<?php esc_attr_e('Vorige service', 'codeline-services-cards'); ?>"></button>
                    <button type="button" class="clsc-btn clsc-arrow clsc-arrow--next" aria-label="<?php esc_attr_e('Volgende service', 'codeline-services-cards'); ?>"></button>
                </div>
            </section>
            <?php

            return $late_style_markup . ob_get_clean();
        }

}
