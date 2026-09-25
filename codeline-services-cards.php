<?php
/**
 * Plugin Name: CodeLine Services Cards
 * Description: Beheer en toon CodeLine service cards, service pages en een lichte geanimeerde achtergrond.
 * Version: 1.5.5
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: codeline-services-cards
 * Domain Path: /languages
 * Author: CodeLine Agency
 * License: GPL-2.0+
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('CLSC_PLUGIN_DIR')) {
    define('CLSC_PLUGIN_DIR', plugin_dir_path(__FILE__));
}
if (!defined('CLSC_PLUGIN_URL')) {
    define('CLSC_PLUGIN_URL', plugin_dir_url(__FILE__));
}

require_once CLSC_PLUGIN_DIR . 'includes/trait-codeline-services-cards-core.php';
require_once CLSC_PLUGIN_DIR . 'includes/trait-codeline-services-cards-admin.php';
require_once CLSC_PLUGIN_DIR . 'includes/trait-codeline-services-cards-frontend.php';
require_once CLSC_PLUGIN_DIR . 'includes/trait-codeline-services-cards-slider-settings.php';

final class CodeLine_Services_Cards {
    use CodeLine_Services_Cards_Core_Trait;
    use CodeLine_Services_Cards_Admin_Trait;
    use CodeLine_Services_Cards_Frontend_Trait;
    use CodeLine_Services_Cards_Slider_Settings_Trait;

    const VERSION = '1.5.5';
    const DEFAULT_CARD_LIMIT = 4;
    const POST_TYPE = 'post';
    const SERVICE_POST_TYPE = 'cl_service_page';
    const SERVICE_TAXONOMY = 'cl_service_category';
    const SERVICE_REWRITE_SLUG = 'service-page';
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
    const OPTION_SLIDER_STYLE = 'clsc_slider_style';
    const SLIDER_STYLE_SETTINGS_GROUP = 'clsc_slider_style_group';
    const SLIDER_STYLE_MENU_SLUG = 'clsc-slider-style';
    const REWRITE_FLUSH_OPTION = 'clsc_rewrite_flushed_version';
    const DATA_MIGRATION_OPTION = 'clsc_data_migration_version';
    const DATA_MIGRATION_VERSION = '1';

    public function __construct() {
        add_action('init', array($this, 'register_service_page_content'));
        add_action('init', array($this, 'maybe_migrate_service_links'), 15);
        add_action('init', array($this, 'maybe_flush_rewrite_rules'), 20);

        add_action('admin_menu', array($this, 'adjust_service_page_admin_menu'), 999);
        add_action('admin_menu', array($this, 'register_service_style_page'), 1000);
        add_action('admin_menu', array($this, 'register_slider_style_page'), 1001);
        add_action('admin_init', array($this, 'register_slider_style_setting'));
        add_action('add_meta_boxes', array($this, 'register_meta_boxes'));
        add_action('add_meta_boxes_' . self::SERVICE_POST_TYPE, array($this, 'register_service_page_detail_meta_box'));
        add_action('save_post_' . self::SERVICE_POST_TYPE, array($this, 'save_meta_boxes'));
        add_action('save_post_' . self::SERVICE_POST_TYPE, array($this, 'save_service_page_detail_meta'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_cards_admin_script'));
        add_action('wp_ajax_clsc_update_card', array($this, 'ajax_update_card'));
        add_filter('manage_edit_' . self::SERVICE_POST_TYPE . '_columns', array($this, 'add_admin_columns'));
        add_action('manage_' . self::SERVICE_POST_TYPE . '_posts_custom_column', array($this, 'render_admin_column'), 10, 2);
        add_action(self::SERVICE_TAXONOMY . '_add_form_fields', array($this, 'render_category_add_fields'));
        add_action(self::SERVICE_TAXONOMY . '_edit_form_fields', array($this, 'render_category_edit_fields'));
        add_action('created_' . self::SERVICE_TAXONOMY, array($this, 'save_category_fields'));
        add_action('edited_' . self::SERVICE_TAXONOMY, array($this, 'save_category_fields'));
        add_action('delete_' . self::SERVICE_TAXONOMY, array($this, 'delete_category_service_page'), 10, 4);

        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('wp_footer', array($this, 'print_dynamic_asset_fallback_loader'), 1);
        add_filter('the_content', array($this, 'prepend_service_page_title_to_content'));
        add_shortcode('codeline_services_cards', array($this, 'render_shortcode'));
        add_shortcode('codeline_services_page', array($this, 'render_services_page_shortcode'));
    }
}

new CodeLine_Services_Cards();
