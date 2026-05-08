<?php
/**
 * Plugin Name: CodeLine Services Cards
 * Description: Toon service-cards op basis van gewone WordPress posts met afbeelding, titel en beschrijving.
 * Version: 1.4.0
 * Author: CodeLine Agency
 * License: GPL-2.0+
 */

if (!defined('ABSPATH')) {
    exit;
}

final class CodeLine_Services_Cards {
    const VERSION = '1.4.0';
    const POST_TYPE = 'post';
    const SERVICE_POST_TYPE = 'cl_service_page';
    const SERVICE_TAXONOMY = 'cl_service_category';
    const CASE_POST_TYPE_DEFAULT = 'product_review';
    const CASE_TAXONOMY_DEFAULT = 'product_review_category';
    const META_IMAGE_ID = '_cl_service_image_id';
    const META_CARD_TITLE = '_cl_service_card_title';
    const META_CARD_DESC = '_cl_service_card_desc';
    const META_CARD_ORDER = '_cl_service_card_order';
    const META_SERVICE_VISIBLE = '_cl_service_visible';
    const TERM_META_SERVICE_ENABLED = '_cl_service_enabled';
    const TERM_META_SERVICE_TITLE = '_cl_service_title';
    const TERM_META_SERVICE_TEXT = '_cl_service_text';
    const TERM_META_SERVICE_LABELS = '_cl_service_labels';
    const TERM_META_SERVICE_ORDER = '_cl_service_order';
    const TERM_META_SERVICE_CASE_1 = '_cl_service_case_1';
    const TERM_META_SERVICE_CASE_2 = '_cl_service_case_2';
    const TERM_META_SERVICE_PAGE_ID = '_cl_service_page_id';
    const META_SERVICE_DETAIL_ITEMS = '_cl_service_detail_items';
    const META_SERVICE_DETAIL_CASE_IDS = '_cl_service_detail_case_ids';
    const OPTION_KEY = 'clsc_display_settings';
    const OPTION_SERVICE_PAGE_INTRO = 'clsc_service_page_intro';
    const OPTION_SERVICE_STYLE = 'clsc_service_style';
    const REWRITE_FLUSH_OPTION = 'clsc_rewrite_flushed_version';

    public function __construct() {
        add_action('init', array($this, 'register_service_page_content'));
        add_action('init', array($this, 'maybe_flush_rewrite_rules'), 20);
        add_action('template_redirect', array($this, 'handle_service_page_404_redirect'));
        add_action('admin_menu', array($this, 'adjust_service_page_admin_menu'), 999);
        add_action('admin_menu', array($this, 'register_service_style_page'), 1000);
        add_action('add_meta_boxes', array($this, 'register_meta_boxes'));
        add_action('add_meta_boxes_' . self::SERVICE_POST_TYPE, array($this, 'register_service_page_detail_meta_box'));
        add_action('save_post_' . self::SERVICE_POST_TYPE, array($this, 'save_meta_boxes'));
        add_action('save_post_' . self::SERVICE_POST_TYPE, array($this, 'save_service_page_detail_meta'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_cards_admin_script'));
        add_action('wp_ajax_clsc_update_card', array($this, 'ajax_update_card'));
        add_action('admin_head', array($this, 'admin_list_head'));
        add_filter('manage_edit_' . self::SERVICE_POST_TYPE . '_columns', array($this, 'add_admin_columns'));
        add_action('manage_' . self::SERVICE_POST_TYPE . '_posts_custom_column', array($this, 'render_admin_column'), 10, 2);
        add_action(self::SERVICE_TAXONOMY . '_add_form_fields', array($this, 'render_category_add_fields'));
        add_action(self::SERVICE_TAXONOMY . '_edit_form_fields', array($this, 'render_category_edit_fields'));
        add_action('created_' . self::SERVICE_TAXONOMY, array($this, 'save_category_fields'));
        add_action('edited_' . self::SERVICE_TAXONOMY, array($this, 'save_category_fields'));
        add_action('delete_' . self::SERVICE_TAXONOMY, array($this, 'delete_category_service_page'), 10, 4);
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_filter('the_content', array($this, 'prepend_service_page_title_to_content'));
        add_shortcode('codeline_services_cards', array($this, 'render_shortcode'));
        add_shortcode('codeline_services_page', array($this, 'render_services_page_shortcode'));
    }

    public function render_service_cards_admin_page() {
        if (!current_user_can('edit_posts')) {
            return;
        }

        $args = array(
            'post_type'      => self::SERVICE_POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'meta_value_num date',
            'meta_key'       => self::META_CARD_ORDER,
        );

        $posts = get_posts($args);

        echo '<div class="wrap">';
        echo '<h1>' . esc_html__('Service Cards', 'codeline-services-cards') . '</h1>';

        if (empty($posts)) {
            echo '<p>' . esc_html__('Geen service cards gevonden.', 'codeline-services-cards') . '</p>';
            echo '</div>';
            return;
        }

        echo '<table class="widefat fixed striped">';
        echo '<thead><tr><th>' . esc_html__('Afbeelding', 'codeline-services-cards') . '</th><th>' . esc_html__('Card Titel', 'codeline-services-cards') . '</th><th>' . esc_html__('Card Beschrijving', 'codeline-services-cards') . '</th><th>' . esc_html__('Service Page', 'codeline-services-cards') . '</th><th>' . esc_html__('Acties', 'codeline-services-cards') . '</th></tr></thead>';
        echo '<tbody>';
        foreach ($posts as $p) {
            $image_id = (int) get_post_meta($p->ID, self::META_IMAGE_ID, true);
            $card_title = (string) get_post_meta($p->ID, self::META_CARD_TITLE, true);
            $card_desc = (string) get_post_meta($p->ID, self::META_CARD_DESC, true);
            
            // Get linked service page (by looking for matching term or post slug)
            $linked_page_title = $p->post_title;
            $service_term = $this->get_service_term_for_page($p->ID);
            if ($service_term instanceof WP_Term) {
                $custom_title = (string) get_term_meta($service_term->term_id, self::TERM_META_SERVICE_TITLE, true);
                $linked_page_title = trim($custom_title) !== '' ? $custom_title : $service_term->name;
            }

            echo '<tr>';
            echo '<td class="clsc-col-image">';
            if ($image_id > 0) {
                echo wp_get_attachment_image($image_id, array(80, 80), false, array('class' => 'clsc-thumb'));
            } elseif (has_post_thumbnail($p->ID)) {
                echo get_the_post_thumbnail($p->ID, array(80, 80), array('class' => 'clsc-thumb'));
            } else {
                echo '<span class="clsc-empty">-</span>';
            }
            echo '</td>';

            echo '<td>' . esc_html($card_title !== '' ? $card_title : $p->post_title) . '</td>';
            $desc_source = $card_desc !== '' ? $card_desc : ($p->post_excerpt ?: wp_strip_all_tags($p->post_content));
            echo '<td>' . esc_html(wp_trim_words($desc_source, 20)) . '</td>';
            echo '<td>' . esc_html($linked_page_title) . '</td>';
            echo '<td><button type="button" class="button clsc-edit-card-btn" data-id="' . esc_attr((string) $p->ID) . '" data-image-id="' . esc_attr((string) $image_id) . '">' . esc_html__('Bewerken', 'codeline-services-cards') . '</button></td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';

        echo '</div>';


    }

    public function admin_list_head() {
        if (!is_admin()) {
            return;
        }

        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || $screen->id !== 'edit-' . self::SERVICE_POST_TYPE) {
            return;
        }

        // CSS is now enqueued in enqueue_cards_admin_script()
    }

    public function add_admin_columns($columns) {
        $new = array();
        // Preserve checkbox if present
        if (isset($columns['cb'])) {
            $new['cb'] = $columns['cb'];
        }

        $new['cl_image'] = __('Afbeelding', 'codeline-services-cards');
        // Keep title column label
        $new['title'] = isset($columns['title']) ? $columns['title'] : __('Title', 'codeline-services-cards');
        $new['cl_card_title'] = __('Card Titel', 'codeline-services-cards');
        $new['cl_card_desc'] = __('Card Beschrijving', 'codeline-services-cards');

        // Add remaining default columns (date etc.)
        if (isset($columns['date'])) {
            $new['date'] = $columns['date'];
        }

        return $new;
    }

    public function render_admin_column($column, $post_id) {
        switch ($column) {
            case 'cl_image':
                $image_id = (int) get_post_meta($post_id, self::META_IMAGE_ID, true);
                if ($image_id > 0) {
                    echo wp_get_attachment_image($image_id, array(80, 80), false, array('class' => 'clsc-thumb'));
                } elseif (has_post_thumbnail($post_id)) {
                    echo get_the_post_thumbnail($post_id, array(80, 80), array('class' => 'clsc-thumb'));
                } else {
                    echo '<span class="clsc-empty">-</span>';
                }
                break;

            case 'cl_card_title':
                $card_title = (string) get_post_meta($post_id, self::META_CARD_TITLE, true);
                if ('' !== trim($card_title)) {
                    echo esc_html($card_title);
                } else {
                    echo esc_html(get_the_title($post_id));
                }
                break;

            case 'cl_card_desc':
                $card_desc = (string) get_post_meta($post_id, self::META_CARD_DESC, true);
                if ('' !== trim($card_desc)) {
                    echo esc_html(wp_trim_words($card_desc, 20));
                } else {
                    $excerpt = get_post_field('post_excerpt', $post_id);
                    if ('' !== trim($excerpt)) {
                        echo esc_html(wp_trim_words($excerpt, 20));
                    } else {
                        $content = get_post_field('post_content', $post_id);
                        echo esc_html(wp_trim_words(wp_strip_all_tags($content), 20));
                    }
                }
                break;
        }
    }

    public function register_service_page_content() {
        register_post_type(self::SERVICE_POST_TYPE, array(
            'labels' => array(
                'name'          => __('Service Pages', 'codeline-services-cards'),
                'singular_name' => __('Service Page', 'codeline-services-cards'),
                'menu_name'     => __('Service Page', 'codeline-services-cards'),
                'add_new_item'  => __('Nieuwe Service Page', 'codeline-services-cards'),
                'edit_item'     => __('Service Page bewerken', 'codeline-services-cards'),
            ),
            'public'             => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'show_in_admin_bar'  => false,
            'menu_position'      => 26,
            'menu_icon'          => 'dashicons-screenoptions',
            'supports'           => array('title', 'editor', 'excerpt', 'thumbnail'),
            'has_archive'        => false,
            'rewrite'            => array('slug' => 'service-page', 'with_front' => false),
            'capability_type'    => 'post',
            'capabilities'       => array(
                'create_posts' => 'do_not_allow',
            ),
            'map_meta_cap'       => true,
            'show_in_rest'       => true,
        ));

        register_taxonomy(self::SERVICE_TAXONOMY, self::SERVICE_POST_TYPE, array(
            'labels' => array(
                'name'              => __('Categorieen', 'codeline-services-cards'),
                'singular_name'     => __('Categorie', 'codeline-services-cards'),
                'search_items'      => __('Categorieen zoeken', 'codeline-services-cards'),
                'all_items'         => __('Alle categorieen', 'codeline-services-cards'),
                'edit_item'         => __('Categorie bewerken', 'codeline-services-cards'),
                'update_item'       => __('Categorie updaten', 'codeline-services-cards'),
                'add_new_item'      => __('Nieuwe categorie toevoegen', 'codeline-services-cards'),
                'new_item_name'     => __('Nieuwe categorienaam', 'codeline-services-cards'),
                'menu_name'         => __('Categorieen', 'codeline-services-cards'),
            ),
            'public'            => false,
            'show_ui'           => true,
            'show_in_menu'      => true,
            'show_admin_column' => false,
            'hierarchical'      => true,
            'rewrite'           => false,
            'show_in_rest'      => false,
        ));
    }

    public function maybe_flush_rewrite_rules() {
        $flushed_version = (string) get_option(self::REWRITE_FLUSH_OPTION, '');
        if ($flushed_version === self::VERSION) {
            return;
        }

        flush_rewrite_rules(false);
        update_option(self::REWRITE_FLUSH_OPTION, self::VERSION, false);
    }

    public function handle_service_page_404_redirect() {
        if (!is_404()) {
            return;
        }

        $request_uri = isset($_SERVER['REQUEST_URI']) ? (string) wp_unslash($_SERVER['REQUEST_URI']) : '';
        if ($request_uri === '') {
            return;
        }

        $request_path = (string) wp_parse_url($request_uri, PHP_URL_PATH);
        $home_path = (string) wp_parse_url(home_url('/'), PHP_URL_PATH);

        $request_path = trim($request_path, '/');
        $home_path = trim($home_path, '/');

        if ($home_path !== '' && strpos($request_path, $home_path . '/') === 0) {
            $request_path = substr($request_path, strlen($home_path) + 1);
        }

        if (strpos($request_path, 'service-page/') !== 0) {
            return;
        }

        $slug = trim(substr($request_path, strlen('service-page/')), '/');
        if ($slug === '') {
            return;
        }

        $service_page = get_page_by_path($slug, OBJECT, self::SERVICE_POST_TYPE);
        if (!($service_page instanceof WP_Post) || (int) $service_page->ID <= 0) {
            return;
        }

        $fallback_url = add_query_arg(array(
            'p'         => (int) $service_page->ID,
            'post_type' => self::SERVICE_POST_TYPE,
        ), home_url('/'));

        wp_safe_redirect($fallback_url, 302);
        exit;
    }

    public function adjust_service_page_admin_menu() {
        $parent_slug = 'edit.php?post_type=' . self::SERVICE_POST_TYPE;
        remove_submenu_page($parent_slug, 'post-new.php?post_type=' . self::SERVICE_POST_TYPE);
        add_submenu_page(
            $parent_slug,
            __('Service Cards', 'codeline-services-cards'),
            __('Service Cards', 'codeline-services-cards'),
            'edit_posts',
            'clsc-cards',
            array($this, 'render_service_cards_admin_page')
        );

        add_submenu_page(
            $parent_slug,
            __('Services Intro', 'codeline-services-cards'),
            __('Services Intro', 'codeline-services-cards'),
            'manage_options',
            'clsc-services-intro',
            array($this, 'render_service_page_intro_admin_page')
        );
    }

    private function get_service_page_intro_settings() {
        $defaults = array(
            'title' => __('Services', 'codeline-services-cards'),
            'text'  => __('Flatline Agency excels in delivering customized digital solutions and services specifically crafted to enhance your bottom-line results. We are dedicated to improving your business outcomes and maximizing the impact on your customers.', 'codeline-services-cards'),
        );

        $saved = get_option(self::OPTION_SERVICE_PAGE_INTRO, array());
        if (!is_array($saved)) {
            return $defaults;
        }

        $title = isset($saved['title']) ? sanitize_text_field((string) $saved['title']) : '';
        $text = isset($saved['text']) ? sanitize_textarea_field((string) $saved['text']) : '';

        return array(
            'title' => '' !== trim($title) ? $title : $defaults['title'],
            'text'  => '' !== trim($text) ? $text : $defaults['text'],
        );
    }

    public function render_service_page_intro_admin_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (
            isset($_POST['clsc_service_intro_nonce'])
            && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['clsc_service_intro_nonce'])), 'clsc_save_service_intro')
        ) {
            $title = isset($_POST['clsc_intro_title']) ? sanitize_text_field(wp_unslash($_POST['clsc_intro_title'])) : '';
            $text = isset($_POST['clsc_intro_text']) ? sanitize_textarea_field(wp_unslash($_POST['clsc_intro_text'])) : '';

            update_option(self::OPTION_SERVICE_PAGE_INTRO, array(
                'title' => $title,
                'text'  => $text,
            ), false);

            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Services intro opgeslagen.', 'codeline-services-cards') . '</p></div>';
        }

        $settings = $this->get_service_page_intro_settings();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Services Intro', 'codeline-services-cards'); ?></h1>
            <p><?php esc_html_e('Deze titel en tekst worden bovenaan [codeline_services_page] getoond.', 'codeline-services-cards'); ?></p>

            <form method="post" action="">
                <?php wp_nonce_field('clsc_save_service_intro', 'clsc_service_intro_nonce'); ?>

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="clsc_intro_title"><?php esc_html_e('Titel', 'codeline-services-cards'); ?></label></th>
                            <td>
                                <input
                                    name="clsc_intro_title"
                                    type="text"
                                    id="clsc_intro_title"
                                    value="<?php echo esc_attr($settings['title']); ?>"
                                    class="regular-text"
                                />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="clsc_intro_text"><?php esc_html_e('Tekst', 'codeline-services-cards'); ?></label></th>
                            <td>
                                <textarea
                                    name="clsc_intro_text"
                                    id="clsc_intro_text"
                                    rows="6"
                                    class="large-text"
                                ><?php echo esc_textarea($settings['text']); ?></textarea>
                            </td>
                        </tr>
                    </tbody>
                </table>

                <?php submit_button(__('Opslaan', 'codeline-services-cards')); ?>
            </form>
        </div>
        <?php
    }

    public function register_service_style_page() {
        add_submenu_page(
            'edit.php?post_type=' . self::SERVICE_POST_TYPE,
            __('Service Stijl', 'codeline-services-cards'),
            __('Service Stijl', 'codeline-services-cards'),
            'manage_options',
            'clsc-service-style',
            array($this, 'render_service_style_admin_page')
        );
    }

    private function get_service_style_settings() {
        $defaults = array(
            'page_bg'              => '#ffffff',
            'intro_bg'             => '#ffffff',
            'title_color'          => '#111111',
            'text_color'           => '#7a7a7a',
            'pill_bg'              => '#efefef',
            'pill_text'            => '#5f5f5f',
            'card_bg'              => '#ffffff',
            'card_border'          => '#e6e6e6',
            'button_bg'            => '#111111',
            'button_text'          => '#ffffff',
            'button_border'        => '#111111',
            'button_hover_bg'      => '#000000',
            'button_hover_text'    => '#ffffff',
            'button_hover_border'  => '#000000',
            'detail_bg'            => '#ffffff',
            'detail_title_color'   => '#111111',
            'detail_text_color'    => '#7d7d7d',
            'detail_border_color'  => '#e8e8e8',
            'readmore_show'        => '1',
            'readmore_bg'          => '#111111',
            'readmore_text'        => '#ffffff',
            'readmore_border'      => '#111111',
            'readmore_hover_bg'    => '#000000',
            'readmore_hover_text'  => '#ffffff',
        );

        $saved = get_option(self::OPTION_SERVICE_STYLE, array());
        if (!is_array($saved)) {
            return $defaults;
        }

        $settings = array();
        foreach ($defaults as $key => $default) {
            if ($key === 'readmore_show') {
                $settings[$key] = isset($saved[$key]) ? ($saved[$key] === '1' ? '1' : '0') : $default;
            } else {
                $value = isset($saved[$key]) ? sanitize_hex_color((string) $saved[$key]) : '';
                $settings[$key] = $value ? $value : $default;
            }
        }

        return $settings;
    }

    private function get_style_css_vars() {
        $settings = $this->get_service_style_settings();

        return '--clsp-page-bg: ' . $settings['page_bg'] . '; '
            . '--clsp-intro-bg: ' . $settings['intro_bg'] . '; '
            . '--clsp-title-color: ' . $settings['title_color'] . '; '
            . '--clsp-text-color: ' . $settings['text_color'] . '; '
            . '--clsp-pill-bg: ' . $settings['pill_bg'] . '; '
            . '--clsp-pill-text: ' . $settings['pill_text'] . '; '
            . '--clsp-card-bg: ' . $settings['card_bg'] . '; '
            . '--clsp-card-border: ' . $settings['card_border'] . '; '
            . '--clsp-button-bg: ' . $settings['button_bg'] . '; '
            . '--clsp-button-text: ' . $settings['button_text'] . '; '
            . '--clsp-button-border: ' . $settings['button_border'] . '; '
            . '--clsp-button-hover-bg: ' . $settings['button_hover_bg'] . '; '
            . '--clsp-button-hover-text: ' . $settings['button_hover_text'] . '; '
            . '--clsp-button-hover-border: ' . $settings['button_hover_border'] . '; '
            . '--clsp-detail-bg: ' . $settings['detail_bg'] . '; '
            . '--clsp-detail-title-color: ' . $settings['detail_title_color'] . '; '
            . '--clsp-detail-text-color: ' . $settings['detail_text_color'] . '; '
            . '--clsp-detail-border-color: ' . $settings['detail_border_color'] . '; '
            . '--clsp-readmore-bg: ' . $settings['readmore_bg'] . '; '
            . '--clsp-readmore-text: ' . $settings['readmore_text'] . '; '
            . '--clsp-readmore-border: ' . $settings['readmore_border'] . '; '
            . '--clsp-readmore-hover-bg: ' . $settings['readmore_hover_bg'] . '; '
            . '--clsp-readmore-hover-text: ' . $settings['readmore_hover_text'] . ';';
    }

    private function build_service_style_vars() {
        return $this->get_style_css_vars();
    }

    public function render_service_style_admin_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (
            isset($_POST['clsc_service_style_nonce'])
            && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['clsc_service_style_nonce'])), 'clsc_save_service_style')
        ) {
            $input = isset($_POST['clsc_service_style']) && is_array($_POST['clsc_service_style']) ? wp_unslash($_POST['clsc_service_style']) : array();
            $defaults = $this->get_service_style_settings();
            $saved = array();

            foreach ($defaults as $key => $default) {
                if ($key === 'readmore_show') {
                    $saved[$key] = isset($input[$key]) ? '1' : '0';
                } else {
                    $value = isset($input[$key]) ? sanitize_hex_color((string) $input[$key]) : '';
                    $saved[$key] = $value ? $value : $default;
                }
            }

            update_option(self::OPTION_SERVICE_STYLE, $saved, false);
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Service stijl opgeslagen.', 'codeline-services-cards') . '</p></div>';
        }

        $settings = $this->get_service_style_settings();
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Service Stijl', 'codeline-services-cards'); ?></h1>
            <p><?php esc_html_e('Hier kun je de kleuren van de "Lees meer" knop op de service kaarten aanpassen.', 'codeline-services-cards'); ?></p>

            <form method="post" action="">
                <?php wp_nonce_field('clsc_save_service_style', 'clsc_service_style_nonce'); ?>

                <table class="form-table" role="presentation">
                    <tbody>
                        <tr>
                            <th scope="row"><label for="clsc_service_style_readmore_show"><?php esc_html_e('Lees meer knop tonen', 'codeline-services-cards'); ?></label></th>
                            <td>
                                <input type="checkbox" id="clsc_service_style_readmore_show" name="clsc_service_style[readmore_show]" value="1" <?php checked($settings['readmore_show'], '1'); ?> />
                                <span><?php esc_html_e('Aan/uit voor de "Lees meer" knop op kaarten', 'codeline-services-cards'); ?></span>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="clsc_service_style_readmore_bg"><?php esc_html_e('Lees meer achtergrond', 'codeline-services-cards'); ?></label></th>
                            <td><input type="color" id="clsc_service_style_readmore_bg" name="clsc_service_style[readmore_bg]" value="<?php echo esc_attr($settings['readmore_bg']); ?>" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="clsc_service_style_readmore_text"><?php esc_html_e('Lees meer tekstkleur', 'codeline-services-cards'); ?></label></th>
                            <td><input type="color" id="clsc_service_style_readmore_text" name="clsc_service_style[readmore_text]" value="<?php echo esc_attr($settings['readmore_text']); ?>" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="clsc_service_style_readmore_border"><?php esc_html_e('Lees meer randkleur', 'codeline-services-cards'); ?></label></th>
                            <td><input type="color" id="clsc_service_style_readmore_border" name="clsc_service_style[readmore_border]" value="<?php echo esc_attr($settings['readmore_border']); ?>" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="clsc_service_style_readmore_hover_bg"><?php esc_html_e('Lees meer hover achtergrond', 'codeline-services-cards'); ?></label></th>
                            <td><input type="color" id="clsc_service_style_readmore_hover_bg" name="clsc_service_style[readmore_hover_bg]" value="<?php echo esc_attr($settings['readmore_hover_bg']); ?>" /></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="clsc_service_style_readmore_hover_text"><?php esc_html_e('Lees meer hover tekstkleur', 'codeline-services-cards'); ?></label></th>
                            <td><input type="color" id="clsc_service_style_readmore_hover_text" name="clsc_service_style[readmore_hover_text]" value="<?php echo esc_attr($settings['readmore_hover_text']); ?>" /></td>
                        </tr>
                    </tbody>
                </table>

                <?php submit_button(__('Opslaan', 'codeline-services-cards')); ?>
            </form>
        </div>
        <?php
    }

    public function register_settings_page() {
        add_submenu_page(
            'edit.php?post_type=' . self::POST_TYPE,
            __('Services Cards Instellingen', 'codeline-services-cards'),
            __('Instellingen', 'codeline-services-cards'),
            'manage_options',
            'clsc-settings',
            array($this, 'render_settings_page')
        );
    }

    public function register_settings() {
        register_setting(
            'clsc_settings_group',
            self::OPTION_KEY,
            array($this, 'sanitize_settings')
        );

        add_settings_section(
            'clsc_main_settings',
            __('Cards Per View', 'codeline-services-cards'),
            '__return_false',
            'clsc-settings'
        );

        add_settings_field(
            'desktop',
            __('Desktop', 'codeline-services-cards'),
            array($this, 'render_number_field'),
            'clsc-settings',
            'clsc_main_settings',
            array('key' => 'desktop', 'min' => 1, 'max' => 6)
        );

        add_settings_field(
            'tablet',
            __('Tablet', 'codeline-services-cards'),
            array($this, 'render_number_field'),
            'clsc-settings',
            'clsc_main_settings',
            array('key' => 'tablet', 'min' => 1, 'max' => 4)
        );

        add_settings_field(
            'mobile',
            __('Mobiel', 'codeline-services-cards'),
            array($this, 'render_number_field'),
            'clsc-settings',
            'clsc_main_settings',
            array('key' => 'mobile', 'min' => 1, 'max' => 2)
        );
    }

    public function sanitize_settings($input) {
        $defaults = $this->get_display_settings();

        $desktop = isset($input['desktop']) ? (int) $input['desktop'] : $defaults['desktop'];
        $tablet  = isset($input['tablet']) ? (int) $input['tablet'] : $defaults['tablet'];
        $mobile  = isset($input['mobile']) ? (int) $input['mobile'] : $defaults['mobile'];

        return array(
            'desktop' => max(1, min(6, $desktop)),
            'tablet'  => max(1, min(4, $tablet)),
            'mobile'  => max(1, min(2, $mobile)),
        );
    }

    public function render_number_field($args) {
        $settings = $this->get_display_settings();
        $key = $args['key'];
        $value = isset($settings[$key]) ? (int) $settings[$key] : 1;
        $min = (int) $args['min'];
        $max = (int) $args['max'];
        ?>
        <input
            type="number"
            name="<?php echo esc_attr(self::OPTION_KEY); ?>[<?php echo esc_attr($key); ?>]"
            value="<?php echo esc_attr((string) $value); ?>"
            min="<?php echo esc_attr((string) $min); ?>"
            max="<?php echo esc_attr((string) $max); ?>"
            step="1"
        />
        <?php
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Services Cards Instellingen', 'codeline-services-cards'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('clsc_settings_group');
                do_settings_sections('clsc-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    private function get_display_settings() {
        $defaults = array(
            'desktop' => 3,
            'tablet'  => 2,
            'mobile'  => 1,
        );

        $saved = get_option(self::OPTION_KEY, array());
        if (!is_array($saved)) {
            return $defaults;
        }

        return array(
            'desktop' => isset($saved['desktop']) ? max(1, min(6, (int) $saved['desktop'])) : $defaults['desktop'],
            'tablet'  => isset($saved['tablet']) ? max(1, min(4, (int) $saved['tablet'])) : $defaults['tablet'],
            'mobile'  => isset($saved['mobile']) ? max(1, min(2, (int) $saved['mobile'])) : $defaults['mobile'],
        );
    }

    public function render_category_add_fields() {
        ?>
        <div class="form-field term-group">
            <label for="cl_service_enabled"><?php esc_html_e('Gebruik op Services pagina', 'codeline-services-cards'); ?></label>
            <input type="checkbox" id="cl_service_enabled" name="cl_service_enabled" value="1" />
            <p><?php esc_html_e('Alleen categorieen met dit vinkje komen in de shortcode [codeline_services_page].', 'codeline-services-cards'); ?></p>
        </div>

        <div class="form-field term-group">
            <label for="cl_service_title"><?php esc_html_e('Service titel', 'codeline-services-cards'); ?></label>
            <input type="text" id="cl_service_title" name="cl_service_title" value="" />
            <p><?php esc_html_e('Leeg laten = categorienaam gebruiken.', 'codeline-services-cards'); ?></p>
        </div>

        <div class="form-field term-group">
            <label for="cl_service_text"><?php esc_html_e('Service tekst', 'codeline-services-cards'); ?></label>
            <textarea id="cl_service_text" name="cl_service_text" rows="5"></textarea>
        </div>

        <div class="form-field term-group">
            <label for="cl_service_labels"><?php esc_html_e('Labels', 'codeline-services-cards'); ?></label>
            <input type="text" id="cl_service_labels" name="cl_service_labels" value="" />
            <p><?php esc_html_e('Bijv: DESIGN, HEADLESS, B2C, SHOPIFY PLUS (komma-gescheiden).', 'codeline-services-cards'); ?></p>
        </div>

        <div class="form-field term-group">
            <label for="cl_service_order"><?php esc_html_e('Volgorde', 'codeline-services-cards'); ?></label>
            <input type="number" id="cl_service_order" name="cl_service_order" value="0" min="0" step="1" />
            <p><?php esc_html_e('Lager getal = eerder tonen op de Services pagina.', 'codeline-services-cards'); ?></p>
        </div>

        <?php
    }

    private function get_posts_for_term($term_id) {
        $posts = get_posts(array(
            'post_type'           => self::POST_TYPE,
            'post_status'         => 'publish',
            'posts_per_page'      => -1,
            'orderby'             => 'date',
            'order'               => 'DESC',
            'ignore_sticky_posts' => true,
            'cat'                 => (int) $term_id,
            'fields'              => 'ids',
        ));

        return is_array($posts) ? $posts : array();
    }

    private function get_case_posts_for_selector() {
        $post_type = $this->resolve_case_post_type(self::CASE_POST_TYPE_DEFAULT);
        if ('' === $post_type) {
            return array();
        }

        $taxonomy = $this->resolve_case_taxonomy($post_type, self::CASE_TAXONOMY_DEFAULT);
        if ('' === $taxonomy) {
            return array();
        }

        $posts = get_posts(array(
            'post_type'              => $post_type,
            'post_status'            => 'publish',
            'posts_per_page'         => -1,
            'orderby'                => 'date',
            'order'                  => 'DESC',
            'ignore_sticky_posts'    => true,
            'no_found_rows'          => true,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ));

        return is_array($posts) ? $posts : array();
    }

    private function get_service_pages_for_selector() {
        $pages = get_posts(array(
            'post_type'           => self::SERVICE_POST_TYPE,
            'post_status'         => 'publish',
            'posts_per_page'      => -1,
            'orderby'             => 'date',
            'order'               => 'DESC',
            'ignore_sticky_posts' => true,
        ));

        return is_array($pages) ? $pages : array();
    }

    private function ensure_service_page_for_term($term_id) {
        $term_id = (int) $term_id;
        if ($term_id <= 0) {
            return 0;
        }

        $term = get_term($term_id, self::SERVICE_TAXONOMY);
        if (!$term || is_wp_error($term)) {
            return 0;
        }

        $existing_id = (int) get_term_meta($term_id, self::TERM_META_SERVICE_PAGE_ID, true);
        if ($existing_id > 0 && get_post_type($existing_id) === self::SERVICE_POST_TYPE) {
            $existing_post = get_post($existing_id);
            if ($existing_post instanceof WP_Post) {
                $target_title = (string) $term->name;
                $target_slug = sanitize_title((string) $term->slug);
                $needs_update = ($existing_post->post_title !== $target_title) || ($existing_post->post_name !== $target_slug);

                if ($needs_update) {
                    wp_update_post(array(
                        'ID'         => (int) $existing_id,
                        'post_title' => $target_title,
                        'post_name'  => $target_slug,
                    ));
                }
            }

            return $existing_id;
        }

        $matched = get_page_by_path($term->slug, OBJECT, self::SERVICE_POST_TYPE);
        if ($matched instanceof WP_Post && $matched->ID > 0) {
            update_term_meta($term_id, self::TERM_META_SERVICE_PAGE_ID, (int) $matched->ID);
            return (int) $matched->ID;
        }

        $new_id = wp_insert_post(array(
            'post_type'    => self::SERVICE_POST_TYPE,
            'post_status'  => 'publish',
            'post_title'   => $term->name,
            'post_name'    => $term->slug,
            'post_content' => '',
        ), true);

        if (!is_wp_error($new_id) && (int) $new_id > 0) {
            update_term_meta($term_id, self::TERM_META_SERVICE_PAGE_ID, (int) $new_id);
            return (int) $new_id;
        }

        return 0;
    }

    public function delete_category_service_page($term, $tt_id = 0, $deleted_term = null, $object_ids = array()) {
        $term_id = 0;

        if ($deleted_term instanceof WP_Term) {
            $term_id = (int) $deleted_term->term_id;
        } elseif ($term instanceof WP_Term) {
            $term_id = (int) $term->term_id;
        } elseif (is_numeric($term)) {
            $term_id = (int) $term;
        }

        if ($term_id <= 0) {
            return;
        }

        $linked_page_id = (int) get_term_meta($term_id, self::TERM_META_SERVICE_PAGE_ID, true);
        if ($linked_page_id <= 0) {
            return;
        }

        if (get_post_type($linked_page_id) !== self::SERVICE_POST_TYPE) {
            return;
        }

        // Force delete so no orphan Service Page remains after category deletion.
        wp_delete_post($linked_page_id, true);
    }

    private function resolve_service_read_more_url($term) {
        if (!($term instanceof WP_Term)) {
            return '';
        }

        $ensured_page_id = $this->ensure_service_page_for_term((int) $term->term_id);
        if ($ensured_page_id > 0 && get_post_status($ensured_page_id) === 'publish') {
            return (string) add_query_arg(array(
                'p'         => $ensured_page_id,
                'post_type' => self::SERVICE_POST_TYPE,
            ), home_url('/'));
        }

        $linked_page_id = (int) get_term_meta($term->term_id, self::TERM_META_SERVICE_PAGE_ID, true);
        if ($linked_page_id > 0 && get_post_type($linked_page_id) === self::SERVICE_POST_TYPE && get_post_status($linked_page_id) === 'publish') {
            return (string) add_query_arg(array(
                'p'         => $linked_page_id,
                'post_type' => self::SERVICE_POST_TYPE,
            ), home_url('/'));
        }

        $matched = get_page_by_path($term->slug, OBJECT, self::SERVICE_POST_TYPE);
        if ($matched instanceof WP_Post && $matched->ID > 0 && $matched->post_status === 'publish') {
            return (string) add_query_arg(array(
                'p'         => (int) $matched->ID,
                'post_type' => self::SERVICE_POST_TYPE,
            ), home_url('/'));
        }

        return '';
    }

    private function get_service_term_for_page($post_id) {
        $post_id = (int) $post_id;
        if ($post_id <= 0) {
            return null;
        }

        $terms = get_terms(array(
            'taxonomy'   => self::SERVICE_TAXONOMY,
            'hide_empty' => false,
            'number'     => 1,
            'meta_query' => array(
                array(
                    'key'   => self::TERM_META_SERVICE_PAGE_ID,
                    'value' => (string) $post_id,
                ),
            ),
        ));

        if (is_array($terms) && !empty($terms) && $terms[0] instanceof WP_Term) {
            return $terms[0];
        }

        $page = get_post($post_id);
        if ($page instanceof WP_Post && $page->post_name !== '') {
            $slug_term = get_term_by('slug', $page->post_name, self::SERVICE_TAXONOMY);
            if ($slug_term instanceof WP_Term) {
                return $slug_term;
            }
        }

        return null;
    }

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

    private function resolve_case_post_type($requested) {
        $requested = sanitize_key((string) $requested);
        if ('' !== $requested && post_type_exists($requested)) {
            return $requested;
        }

        $candidates = array(
            self::CASE_POST_TYPE_DEFAULT,
            'blc-product-review',
            'blc_product_review',
            'product_reviews',
            'product_review_case',
            'product-review-case',
            'product-review',
            'product-reviews',
            'case',
            'cases',
        );

        foreach ($candidates as $candidate) {
            $candidate = sanitize_key((string) $candidate);
            if ('' !== $candidate && post_type_exists($candidate)) {
                return $candidate;
            }
        }

        // Last-resort detection for other websites with custom slugs.
        $all_post_types = get_post_types(array('show_ui' => true), 'objects');
        foreach ($all_post_types as $post_type_obj) {
            $name = isset($post_type_obj->name) ? strtolower((string) $post_type_obj->name) : '';
            $label = isset($post_type_obj->label) ? strtolower((string) $post_type_obj->label) : '';
            $plural = isset($post_type_obj->labels->name) ? strtolower((string) $post_type_obj->labels->name) : '';

            if (in_array($name, array('post', 'page', 'attachment', self::SERVICE_POST_TYPE), true)) {
                continue;
            }

            $haystack = trim($name . ' ' . $label . ' ' . $plural);
            $is_product_review = (false !== strpos($haystack, 'product') && false !== strpos($haystack, 'review'));
            $is_case = (false !== strpos($haystack, 'case'));

            if ($is_product_review || $is_case) {
                return $post_type_obj->name;
            }
        }

        return '';
    }

    private function resolve_case_taxonomy($post_type, $requested_taxonomy) {
        $post_type = sanitize_key((string) $post_type);
        $requested_taxonomy = sanitize_key((string) $requested_taxonomy);

        if ('' !== $requested_taxonomy && taxonomy_exists($requested_taxonomy) && is_object_in_taxonomy($post_type, $requested_taxonomy)) {
            return $requested_taxonomy;
        }

        $default_taxonomy = sanitize_key((string) self::CASE_TAXONOMY_DEFAULT);
        if (taxonomy_exists($default_taxonomy) && is_object_in_taxonomy($post_type, $default_taxonomy)) {
            return $default_taxonomy;
        }

        // Many Product Review plugins attach categories to built-in category.
        if (taxonomy_exists('category') && is_object_in_taxonomy($post_type, 'category')) {
            return 'category';
        }

        $object_taxonomies = get_object_taxonomies($post_type, 'objects');
        if (!empty($object_taxonomies)) {
            foreach ($object_taxonomies as $taxonomy_obj) {
                if (!empty($taxonomy_obj->hierarchical)) {
                    return (string) $taxonomy_obj->name;
                }
            }
        }

        return '';
    }

    public function render_category_edit_fields($term) {
        $enabled = (string) get_term_meta($term->term_id, self::TERM_META_SERVICE_ENABLED, true);
        $title = (string) get_term_meta($term->term_id, self::TERM_META_SERVICE_TITLE, true);
        $text = (string) get_term_meta($term->term_id, self::TERM_META_SERVICE_TEXT, true);
        $labels = (string) get_term_meta($term->term_id, self::TERM_META_SERVICE_LABELS, true);
        $service_order = (int) get_term_meta($term->term_id, self::TERM_META_SERVICE_ORDER, true);
        $selected_case_1 = (int) get_term_meta($term->term_id, self::TERM_META_SERVICE_CASE_1, true);
        $selected_case_2 = (int) get_term_meta($term->term_id, self::TERM_META_SERVICE_CASE_2, true);
        $case_posts = $this->get_case_posts_for_selector();
        $service_page_id = $this->ensure_service_page_for_term((int) $term->term_id);
        $service_page_edit_url = $service_page_id > 0 ? get_edit_post_link($service_page_id, '') : '';
        ?>
        <tr class="form-field term-group-wrap">
            <th scope="row"><label for="cl_service_enabled"><?php esc_html_e('Gebruik op Services pagina', 'codeline-services-cards'); ?></label></th>
            <td>
                <label>
                    <input type="checkbox" id="cl_service_enabled" name="cl_service_enabled" value="1" <?php checked('1', $enabled); ?> />
                    <?php esc_html_e('Toon deze categorie in [codeline_services_page]', 'codeline-services-cards'); ?>
                </label>
            </td>
        </tr>
        <tr class="form-field term-group-wrap">
            <th scope="row"><label for="cl_service_title"><?php esc_html_e('Service titel', 'codeline-services-cards'); ?></label></th>
            <td>
                <input type="text" id="cl_service_title" name="cl_service_title" value="<?php echo esc_attr($title); ?>" class="regular-text" />
                <p class="description"><?php esc_html_e('Leeg laten = categorienaam gebruiken.', 'codeline-services-cards'); ?></p>
            </td>
        </tr>
        <tr class="form-field term-group-wrap">
            <th scope="row"><label for="cl_service_text"><?php esc_html_e('Service tekst', 'codeline-services-cards'); ?></label></th>
            <td>
                <textarea id="cl_service_text" name="cl_service_text" rows="6" class="large-text"><?php echo esc_textarea($text); ?></textarea>
            </td>
        </tr>
        <tr class="form-field term-group-wrap">
            <th scope="row"><label for="cl_service_labels"><?php esc_html_e('Labels', 'codeline-services-cards'); ?></label></th>
            <td>
                <input type="text" id="cl_service_labels" name="cl_service_labels" value="<?php echo esc_attr($labels); ?>" class="regular-text" />
                <p class="description"><?php esc_html_e('Bijv: DESIGN, HEADLESS, B2C, SHOPIFY PLUS (komma-gescheiden).', 'codeline-services-cards'); ?></p>
            </td>
        </tr>
        <tr class="form-field term-group-wrap">
            <th scope="row"><label for="cl_service_order"><?php esc_html_e('Volgorde', 'codeline-services-cards'); ?></label></th>
            <td>
                <input type="number" id="cl_service_order" name="cl_service_order" value="<?php echo esc_attr((string) $service_order); ?>" min="0" step="1" class="small-text" />
                <p class="description"><?php esc_html_e('Lager getal = eerder tonen op de Services pagina.', 'codeline-services-cards'); ?></p>
            </td>
        </tr>
        <tr class="form-field term-group-wrap">
            <th scope="row"><label><?php esc_html_e('Edit Page', 'codeline-services-cards'); ?></label></th>
            <td>
                <?php if (!empty($service_page_edit_url)) : ?>
                    <a href="<?php echo esc_url($service_page_edit_url); ?>"><?php echo esc_html($service_page_edit_url); ?></a>
                <?php else : ?>
                    <span class="description"><?php esc_html_e('Service page will be created automatically after saving this category.', 'codeline-services-cards'); ?></span>
                <?php endif; ?>
            </td>
        </tr>
        <tr class="form-field term-group-wrap">
            <th scope="row"><label for="cl_service_case_search"><?php esc_html_e('Cases zoeken', 'codeline-services-cards'); ?></label></th>
            <td>
                <input type="search" id="cl_service_case_search" class="regular-text" placeholder="<?php echo esc_attr__('Zoek case...', 'codeline-services-cards'); ?>" />
                <p class="description"><?php esc_html_e('Eén zoekbalk voor beide case-selecties (nieuwste bovenaan).', 'codeline-services-cards'); ?></p>
            </td>
        </tr>
        <tr class="form-field term-group-wrap">
            <th scope="row"><label for="cl_service_case_1"><?php esc_html_e('Case 1 afbeelding', 'codeline-services-cards'); ?></label></th>
            <td>
                <p class="description"><?php esc_html_e('Zoek en kies de eerste Product Review/Case (nieuwste bovenaan).', 'codeline-services-cards'); ?></p>
                <select id="cl_service_case_1" name="cl_service_case_1" class="regular-text clsc-case-select clsc-maxwidth-480">
                    <option value="0"><?php esc_html_e('- Geen selectie -', 'codeline-services-cards'); ?></option>
                    <?php foreach ($case_posts as $case_post) : ?>
                        <option value="<?php echo esc_attr((string) $case_post->ID); ?>" <?php selected($selected_case_1, (int) $case_post->ID); ?>>
                            <?php echo esc_html($case_post->post_title . ' (' . get_the_date('Y-m-d', $case_post) . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </td>
        </tr>
        <tr class="form-field term-group-wrap">
            <th scope="row"><label for="cl_service_case_2"><?php esc_html_e('Case 2 afbeelding', 'codeline-services-cards'); ?></label></th>
            <td>
                <p class="description"><?php esc_html_e('Zoek en kies de tweede Product Review/Case (nieuwste bovenaan).', 'codeline-services-cards'); ?></p>
                <select id="cl_service_case_2" name="cl_service_case_2" class="regular-text clsc-case-select clsc-maxwidth-480">
                    <option value="0"><?php esc_html_e('- Geen selectie -', 'codeline-services-cards'); ?></option>
                    <?php foreach ($case_posts as $case_post) : ?>
                        <option value="<?php echo esc_attr((string) $case_post->ID); ?>" <?php selected($selected_case_2, (int) $case_post->ID); ?>>
                            <?php echo esc_html($case_post->post_title . ' (' . get_the_date('Y-m-d', $case_post) . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (empty($case_posts)) : ?>
                    <p class="description clsc-error-note"><?php esc_html_e('Geen Product Review/Case post type gevonden in deze website. De plugin toont hier pas cases zodra het juiste post type bestaat.', 'codeline-services-cards'); ?></p>
                <?php endif; ?>
            </td>
        </tr>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            var sharedSearch = document.getElementById('cl_service_case_search');
            var selects = document.querySelectorAll('.clsc-case-select');
            if (!sharedSearch || !selects.length) {
                return;
            }

            sharedSearch.addEventListener('input', function () {
                var q = String(sharedSearch.value || '').toLowerCase().trim();
                selects.forEach(function (select) {
                    Array.prototype.forEach.call(select.options, function (opt, idx) {
                        if (idx === 0) {
                            opt.hidden = false;
                            return;
                        }
                        opt.hidden = q !== '' && opt.text.toLowerCase().indexOf(q) === -1;
                    });
                });
            });
        });
        </script>
        <?php
    }

    public function save_category_fields($term_id) {
        if (!current_user_can('manage_categories')) {
            return;
        }

        $enabled = isset($_POST['cl_service_enabled']) ? '1' : '0';
        $title = isset($_POST['cl_service_title']) ? sanitize_text_field(wp_unslash($_POST['cl_service_title'])) : '';
        $text = isset($_POST['cl_service_text']) ? sanitize_textarea_field(wp_unslash($_POST['cl_service_text'])) : '';
        $labels = isset($_POST['cl_service_labels']) ? sanitize_text_field(wp_unslash($_POST['cl_service_labels'])) : '';
        $service_order = isset($_POST['cl_service_order']) ? max(0, absint(wp_unslash($_POST['cl_service_order']))) : 0;
        $case_1 = isset($_POST['cl_service_case_1']) ? absint(wp_unslash($_POST['cl_service_case_1'])) : 0;
        $case_2 = isset($_POST['cl_service_case_2']) ? absint(wp_unslash($_POST['cl_service_case_2'])) : 0;

        update_term_meta($term_id, self::TERM_META_SERVICE_ENABLED, $enabled);
        update_term_meta($term_id, self::TERM_META_SERVICE_TITLE, $title);
        update_term_meta($term_id, self::TERM_META_SERVICE_TEXT, $text);
        update_term_meta($term_id, self::TERM_META_SERVICE_LABELS, $labels);
        update_term_meta($term_id, self::TERM_META_SERVICE_ORDER, $service_order);
        update_term_meta($term_id, self::TERM_META_SERVICE_CASE_1, $case_1);
        update_term_meta($term_id, self::TERM_META_SERVICE_CASE_2, $case_2);

        // Always keep a linked editable Service Page for READ MORE.
        $this->ensure_service_page_for_term((int) $term_id);
    }

    public function register_meta_boxes() {
        add_meta_box(
            'cl_service_image_box',
            __('Service Card Afbeelding', 'codeline-services-cards'),
            array($this, 'render_image_meta_box'),
            self::SERVICE_POST_TYPE,
            'normal',
            'high'
        );

        add_meta_box(
            'cl_service_content_box',
            __('Service Card Titel & Beschrijving', 'codeline-services-cards'),
            array($this, 'render_content_meta_box'),
            self::SERVICE_POST_TYPE,
            'normal',
            'high'
        );
    }

    public function register_service_page_detail_meta_box() {
        add_meta_box(
            'cl_service_page_detail_blocks',
            __('Service Details', 'codeline-services-cards'),
            array($this, 'render_service_page_detail_meta_box'),
            self::SERVICE_POST_TYPE,
            'normal',
            'high'
        );
    }

    public function render_service_page_detail_meta_box($post) {
        wp_nonce_field('cl_service_page_detail_nonce_action', 'cl_service_page_detail_nonce');

        $items = get_post_meta($post->ID, self::META_SERVICE_DETAIL_ITEMS, true);
        if (!is_array($items)) {
            $items = array();
        }

        $single_item = isset($items[0]) && is_array($items[0]) ? $items[0] : array();

        $selected_case_ids = get_post_meta($post->ID, self::META_SERVICE_DETAIL_CASE_IDS, true);
        if (!is_array($selected_case_ids)) {
            $selected_case_ids = array();
        }
        $selected_case_ids = array_map('absint', $selected_case_ids);

        $case_posts = $this->get_case_posts_for_selector();

        $case_options_html = '<option value="">' . esc_html__('Kies een case', 'codeline-services-cards') . '</option>';
        foreach ($case_posts as $case_post) {
            $case_options_html .= '<option value="' . esc_attr((string) $case_post->ID) . '">' . esc_html($case_post->post_title . ' (' . get_the_date('Y-m-d', $case_post) . ')') . '</option>';
        }

        $case_rows = $selected_case_ids;
        if (empty($case_rows)) {
            $case_rows = array(0);
        }
        ?>
        <div id="clsp-detail-items-wrapper">
            <?php
            $row_title = isset($single_item['title']) ? (string) $single_item['title'] : '';
            $row_description = isset($single_item['description']) ? (string) $single_item['description'] : '';
            ?>
            <div class="clsp-detail-item-row">
                <p><strong><?php esc_html_e('Titel', 'codeline-services-cards'); ?></strong></p>
                <input type="text" name="cl_service_detail_items[0][title]" value="<?php echo esc_attr($row_title); ?>" class="clsc-fullinput" />

                <p><strong><?php esc_html_e('Beschrijving', 'codeline-services-cards'); ?></strong></p>
                <textarea name="cl_service_detail_items[0][description]" rows="4" class="clsc-fullinput"><?php echo esc_textarea($row_description); ?></textarea>
            </div>
        </div>

        <hr class="clsc-hr" />

        <p><strong><?php esc_html_e('Cases onderaan tonen', 'codeline-services-cards'); ?></strong></p>
        <p class="clsc-muted"><?php esc_html_e('Voeg hier cases toe of verwijder ze met de knoppen.', 'codeline-services-cards'); ?></p>
        <div id="clsp-detail-cases-wrapper">
            <?php foreach ($case_rows as $case_index => $selected_case_id) : ?>
                <div class="clsp-detail-case-row">
                    <select name="cl_service_detail_case_ids[]" class="clsc-select-full">
                        <?php echo str_replace(
                            'value="' . esc_attr((string) (int) $selected_case_id) . '"',
                            'value="' . esc_attr((string) (int) $selected_case_id) . '" selected="selected"',
                            $case_options_html
                        ); ?>
                    </select>
                    <button type="button" class="button clsp-remove-case-row"><?php esc_html_e('Verwijder', 'codeline-services-cards'); ?></button>
                </div>
            <?php endforeach; ?>
        </div>
        <p>
            <button type="button" class="button button-secondary" id="clsp-add-case-row"><?php esc_html_e('Case toevoegen', 'codeline-services-cards'); ?></button>
        </p>

        <script>
        document.addEventListener('DOMContentLoaded', function () {
            var caseWrap = document.getElementById('clsp-detail-cases-wrapper');
            var addCaseBtn = document.getElementById('clsp-add-case-row');
            if (!caseWrap || !addCaseBtn) {
                return;
            }

            var caseOptionsMarkup = <?php echo wp_json_encode($case_options_html); ?>;

            function bindCaseRemoveButtons() {
                var removeButtons = caseWrap.querySelectorAll('.clsp-remove-case-row');
                removeButtons.forEach(function (btn) {
                    if (btn.dataset.bound === '1') {
                        return;
                    }
                    btn.dataset.bound = '1';
                    btn.addEventListener('click', function () {
                        var row = btn.closest('.clsp-detail-case-row');
                        if (row) {
                            row.remove();
                        }
                    });
                });
            }

            addCaseBtn.addEventListener('click', function () {
                var row = document.createElement('div');
                row.className = 'clsp-detail-case-row';
                row.innerHTML = '' +
                    '<select name="cl_service_detail_case_ids[]" class="clsc-fullinput">' + caseOptionsMarkup + '</select>' +
                    '<button type="button" class="button clsp-remove-case-row">Verwijder</button>';
                caseWrap.appendChild(row);
                bindCaseRemoveButtons();
            });

            bindCaseRemoveButtons();
        });
        </script>
        <?php
    }

    public function save_service_page_detail_meta($post_id) {
        if (!isset($_POST['cl_service_page_detail_nonce'])) {
            return;
        }

        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['cl_service_page_detail_nonce'])), 'cl_service_page_detail_nonce_action')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $items_in = isset($_POST['cl_service_detail_items']) && is_array($_POST['cl_service_detail_items'])
            ? wp_unslash($_POST['cl_service_detail_items'])
            : array();

        $clean_items = array();
        foreach ($items_in as $item) {
            if (!is_array($item)) {
                continue;
            }

            $title = isset($item['title']) ? sanitize_text_field((string) $item['title']) : '';
            $description = isset($item['description']) ? sanitize_textarea_field((string) $item['description']) : '';

            if (trim($title) === '' && trim($description) === '') {
                continue;
            }

            $clean_items[] = array(
                'title'       => $title,
                'description' => $description,
            );
        }

        if (empty($clean_items)) {
            delete_post_meta($post_id, self::META_SERVICE_DETAIL_ITEMS);
        } else {
            update_post_meta($post_id, self::META_SERVICE_DETAIL_ITEMS, $clean_items);
        }

        $case_ids_in = isset($_POST['cl_service_detail_case_ids']) && is_array($_POST['cl_service_detail_case_ids'])
            ? wp_unslash($_POST['cl_service_detail_case_ids'])
            : array();

        $case_ids = array_values(array_unique(array_filter(array_map('absint', $case_ids_in))));
        if (empty($case_ids)) {
            delete_post_meta($post_id, self::META_SERVICE_DETAIL_CASE_IDS);
        } else {
            update_post_meta($post_id, self::META_SERVICE_DETAIL_CASE_IDS, $case_ids);
        }
    }

    public function render_image_meta_box($post) {
        wp_nonce_field('cl_service_image_nonce_action', 'cl_service_image_nonce');
        $image_id = (int) get_post_meta($post->ID, self::META_IMAGE_ID, true);
        $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : '';
        ?>
        <div class="clsc-image-field">
            <p>
                <input type="hidden" id="cl_service_image_id" name="cl_service_image_id" value="<?php echo esc_attr((string) $image_id); ?>" />
                <button type="button" class="button button-primary clsc-image-select"><?php esc_html_e('Voeg foto toe', 'codeline-services-cards'); ?></button>
            </p>
            <div class="clsc-image-preview">
                <?php if (!empty($image_url)) : ?>
                    <img src="<?php echo esc_url($image_url); ?>" alt="" class="clsc-preview-img" />
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    public function render_content_meta_box($post) {
        wp_nonce_field('cl_service_content_nonce_action', 'cl_service_content_nonce');
        $card_title = (string) get_post_meta($post->ID, self::META_CARD_TITLE, true);
        $card_desc  = (string) get_post_meta($post->ID, self::META_CARD_DESC, true);
        $card_order = (int) get_post_meta($post->ID, self::META_CARD_ORDER, true);
        ?>
        <p>
            <label for="cl_service_card_order"><strong><?php esc_html_e('Card Volgorde', 'codeline-services-cards'); ?></strong></label>
        </p>
        <p>
            <input
                type="number"
                id="cl_service_card_order"
                name="cl_service_card_order"
                value="<?php echo esc_attr((string) $card_order); ?>"
                min="0"
                step="1"
                class="clsc-input-small"
            />
            <span class="clsc-muted"><?php esc_html_e('Lager getal = eerder tonen', 'codeline-services-cards'); ?></span>
        </p>
        <p>
            <label for="cl_service_card_title"><strong><?php esc_html_e('Card Titel', 'codeline-services-cards'); ?></strong></label>
        </p>
        <p>
            <input
                type="text"
                id="cl_service_card_title"
                name="cl_service_card_title"
                value="<?php echo esc_attr($card_title); ?>"
                placeholder="Titel op de service card"
                class="clsc-maxwidth-700"
            />
        </p>
        <p>
            <label for="cl_service_card_desc"><strong><?php esc_html_e('Card Beschrijving', 'codeline-services-cards'); ?></strong></label>
        </p>
        <p>
            <textarea
                id="cl_service_card_desc"
                name="cl_service_card_desc"
                rows="4"
                placeholder="Beschrijving op de service card"
                class="clsc-maxwidth-700"
            ><?php echo esc_textarea($card_desc); ?></textarea>
        </p>
        <?php
    }

    public function enqueue_admin_assets($hook) {
        global $post_type;

        if (!in_array($hook, array('post.php', 'post-new.php'), true) || $post_type !== self::SERVICE_POST_TYPE) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_script('jquery');

        $script = "document.addEventListener('click', function(e) {
            var button = e.target.closest('.clsc-image-select');
            if (!button) return;
            e.preventDefault();

            var wrapper = button.closest('.clsc-image-field');
            if (!wrapper || !window.wp || !wp.media) return;

            var input = wrapper.querySelector('#cl_service_image_id');
            var preview = wrapper.querySelector('.clsc-image-preview');

            var frame = wp.media({
                title: 'Selecteer afbeelding',
                button: { text: 'Gebruik deze afbeelding' },
                multiple: false
            });

            frame.on('select', function() {
                var attachment = frame.state().get('selection').first().toJSON();
                if (input && attachment.id) input.value = attachment.id;
                if (preview && attachment.url) {
                    preview.innerHTML = '<img src=\'' + attachment.url + '\' alt=\'' + (attachment.alt || '') + '\' class=\'clsc-preview-img\' />';
                }
            });

            frame.open();
        });";

        wp_add_inline_script('jquery', $script);
    }

    public function enqueue_cards_admin_script($hook = '') {
        // Load only on the dedicated Service Cards admin page.
        if (!isset($_GET['page']) || $_GET['page'] !== 'clsc-cards') {
            return;
        }

        wp_enqueue_media();
        
        // Enqueue admin styles
        wp_enqueue_style(
            'codeline-services-cards-admin-styles',
            plugin_dir_url(__FILE__) . 'assets/admin-cards-styles.css',
            array(),
            filemtime(plugin_dir_path(__FILE__) . 'assets/admin-cards-styles.css')
        );
        
        // Enqueue admin script
        wp_enqueue_script('codeline-services-cards-admin', plugin_dir_url(__FILE__) . 'assets/admin-cards.js', array('jquery', 'media-editor'), filemtime(plugin_dir_path(__FILE__) . 'assets/admin-cards.js'));
        wp_localize_script('codeline-services-cards-admin', 'CLSCAdmin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('clsc_card_update'),
        ));
    }

    public function ajax_update_card() {
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'clsc_card_update')) {
            wp_send_json_error(array('message' => 'Nonce mismatch'), 403);
        }

        $post_id = isset($_POST['post_id']) ? absint(wp_unslash($_POST['post_id'])) : 0;
        if ($post_id <= 0 || !current_user_can('edit_post', $post_id)) {
            wp_send_json_error(array('message' => 'Geen permissie'), 403);
        }

        $image_id = isset($_POST['image_id']) ? absint(wp_unslash($_POST['image_id'])) : 0;
        $card_title = isset($_POST['card_title']) ? sanitize_text_field(wp_unslash($_POST['card_title'])) : '';
        $card_desc = isset($_POST['card_desc']) ? sanitize_textarea_field(wp_unslash($_POST['card_desc'])) : '';
        $visible = isset($_POST['visible']) && in_array($_POST['visible'], array('1','on','true','yes'), true) ? '1' : '';

        update_post_meta($post_id, self::META_IMAGE_ID, $image_id);
        if ($card_title !== '') {
            update_post_meta($post_id, self::META_CARD_TITLE, $card_title);
        } else {
            delete_post_meta($post_id, self::META_CARD_TITLE);
        }

        if ($card_desc !== '') {
            update_post_meta($post_id, self::META_CARD_DESC, $card_desc);
        } else {
            delete_post_meta($post_id, self::META_CARD_DESC);
        }

        if ($visible === '1') {
            update_post_meta($post_id, self::META_SERVICE_VISIBLE, '1');
        } else {
            delete_post_meta($post_id, self::META_SERVICE_VISIBLE);
        }

        wp_send_json_success(array('message' => 'Opgeslagen'));
    }

    public function save_meta_boxes($post_id) {
        if (!isset($_POST['cl_service_image_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['cl_service_image_nonce'])), 'cl_service_image_nonce_action')) {
            return;
        }

        if (!isset($_POST['cl_service_content_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['cl_service_content_nonce'])), 'cl_service_content_nonce_action')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $image_id = 0;
        if (isset($_POST['cl_service_image_id'])) {
            $image_id = absint(wp_unslash($_POST['cl_service_image_id']));
        }

        $card_title = '';
        if (isset($_POST['cl_service_card_title'])) {
            $card_title = sanitize_text_field(wp_unslash($_POST['cl_service_card_title']));
        }

        $card_desc = '';
        if (isset($_POST['cl_service_card_desc'])) {
            $card_desc = sanitize_textarea_field(wp_unslash($_POST['cl_service_card_desc']));
        }

        $card_order = 0;
        if (isset($_POST['cl_service_card_order']) && '' !== trim((string) wp_unslash($_POST['cl_service_card_order']))) {
            $card_order = max(0, absint(wp_unslash($_POST['cl_service_card_order'])));
        }

        update_post_meta($post_id, self::META_IMAGE_ID, $image_id);
        update_post_meta($post_id, self::META_CARD_TITLE, $card_title);
        update_post_meta($post_id, self::META_CARD_DESC, $card_desc);
        update_post_meta($post_id, self::META_CARD_ORDER, $card_order);
        update_post_meta($post_id, self::META_SERVICE_VISIBLE, '1');
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

        return ob_get_clean();
    }

    public function enqueue_assets() {
        if (is_admin()) {
            return;
        }

        $base_url  = plugin_dir_url(__FILE__);
        $base_path = plugin_dir_path(__FILE__);

        wp_enqueue_style(
            'codeline-services-cards',
            $base_url . 'assets/services-cards.css',
            array(),
            filemtime($base_path . 'assets/services-cards.css')
        );

        wp_enqueue_script(
            'codeline-services-cards',
            $base_url . 'assets/services-cards.js',
            array(),
            filemtime($base_path . 'assets/services-cards.js'),
            true
        );

        wp_localize_script(
            'codeline-services-cards',
            'CodeLineServicesCards',
            array(
                'labels' => array(
                    'prev' => __('Vorige service', 'codeline-services-cards'),
                    'next' => __('Volgende service', 'codeline-services-cards'),
                ),
            )
        );
    }

    public function render_shortcode($atts) {
        $display = $this->get_display_settings();
        $settings = $this->get_service_style_settings();
        $style_vars = $this->get_style_css_vars();

        $atts = shortcode_atts(
            array(
                'limit'   => -1,
                'desktop' => $display['desktop'],
                'tablet'  => $display['tablet'],
                'mobile'  => $display['mobile'],
                'desktop_peek' => 72,
                'tablet_peek'  => 56,
                'mobile_peek'  => 56,
                'category' => '',
            ),
            $atts,
            'codeline_services_cards'
        );

        $desktop = max(1, min(6, (int) $atts['desktop']));
        $tablet  = max(1, min(4, (int) $atts['tablet']));
        $mobile  = max(1, min(2, (int) $atts['mobile']));
        $desktop_peek = max(0, min(220, (int) $atts['desktop_peek']));
        $tablet_peek = max(0, min(180, (int) $atts['tablet_peek']));
        $mobile_peek = 1 === $mobile ? max(0, min(140, (int) $atts['mobile_peek'])) : 0;
        $category = sanitize_title((string) $atts['category']);

        $query_args = array(
            'post_type'      => self::SERVICE_POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => (int) $atts['limit'],
            'meta_query'     => array(),
            'meta_key'       => self::META_CARD_ORDER,
            'orderby'        => array(
                'meta_value_num' => 'ASC',
                'date'           => 'DESC',
            ),
        );

        if (!empty($category)) {
            $query_args['category_name'] = $category;
        }

        $query = new WP_Query($query_args);

        if (!$query->have_posts()) {
            return '';
        }

        ob_start();
        ?>
        <section
            class="clsc"
            aria-label="Services"
            style="--clsc-desktop: <?php echo esc_attr((string) $desktop); ?>; --clsc-tablet: <?php echo esc_attr((string) $tablet); ?>; --clsc-mobile: <?php echo esc_attr((string) $mobile); ?>; --clsc-desktop-peek: <?php echo esc_attr((string) $desktop_peek); ?>px; --clsc-tablet-peek: <?php echo esc_attr((string) $tablet_peek); ?>px; --clsc-mobile-peek: <?php echo esc_attr((string) $mobile_peek); ?>px; <?php echo esc_attr($style_vars); ?>"
        >
            <div class="clsc-track" role="list">
                <?php while ($query->have_posts()) : $query->the_post(); ?>
                    <?php
                    $link = get_permalink();
                    $image_id = (int) get_post_meta(get_the_ID(), self::META_IMAGE_ID, true);
                    $card_title = (string) get_post_meta(get_the_ID(), self::META_CARD_TITLE, true);
                    $card_desc = (string) get_post_meta(get_the_ID(), self::META_CARD_DESC, true);
                    $title = '' !== trim($card_title) ? $card_title : get_the_title();
                    $description = '' !== trim($card_desc) ? $card_desc : get_the_excerpt();

                    if ('' === trim($description)) {
                        $description = wp_trim_words(wp_strip_all_tags(get_the_content()), 24);
                    }
                    ?>
                    <article class="clsc-card" role="listitem">
                        <a class="clsc-link" href="<?php echo esc_url($link); ?>">
                            <div class="clsc-image-wrap">
                                <?php if (!empty($image_id)) : ?>
                                    <?php echo wp_get_attachment_image($image_id, 'full', false, array('class' => 'clsc-image', 'alt' => get_the_title())); ?>
                                <?php elseif (has_post_thumbnail()) : ?>
                                    <?php the_post_thumbnail(array(800, 800), array('class' => 'clsc-image')); ?>
                                <?php else : ?>
                                    <div class="clsc-image clsc-image--placeholder"></div>
                                <?php endif; ?>
                            </div>
                            <h3 class="clsc-title"><?php echo esc_html($title); ?></h3>
                            <div class="clsc-desc"><?php echo wp_kses_post(wpautop($description)); ?></div>
                            <?php if ($settings['readmore_show'] === '1') : ?>
                                <span class="clsc-readmore"><?php esc_html_e('Lees meer', 'codeline-services-cards'); ?></span>
                            <?php endif; ?>
                        </a>
                    </article>
                <?php endwhile; ?>
            </div>
            <div class="clsc-controls" aria-label="Services navigatie">
                <button type="button" class="clsc-btn clsc-arrow clsc-arrow--prev" aria-label="<?php esc_attr_e('Vorige service', 'codeline-services-cards'); ?>">
                    <span aria-hidden="true">&#8249;</span>
                </button>
                <button type="button" class="clsc-btn clsc-arrow clsc-arrow--next" aria-label="<?php esc_attr_e('Volgende service', 'codeline-services-cards'); ?>">
                    <span aria-hidden="true">&#8250;</span>
                </button>
            </div>
        </section>
        <?php

        wp_reset_postdata();
        return ob_get_clean();
    }
}

new CodeLine_Services_Cards();
