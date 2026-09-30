<?php
/**
 * CodeLine_Services_Cards_Admin_Trait responsibilities for CodeLine Services Cards.
 *
 * @package CodeLineServicesCards
 */

if (!defined('ABSPATH')) {
    exit;
}

trait CodeLine_Services_Cards_Admin_Trait {
    public function render_service_cards_admin_page() {
            if (!current_user_can('edit_posts')) {
                return;
            }

            $posts = get_posts(array(
                'post_type'           => self::SERVICE_POST_TYPE,
                'post_status'         => 'publish',
                'posts_per_page'      => -1,
                'orderby'             => 'date',
                'order'               => 'DESC',
                'ignore_sticky_posts' => true,
            ));
            $posts = $this->sort_service_posts($posts);

            echo '<div class="wrap">';
            echo '<h1>' . esc_html__('Service Cards', 'codeline-services-cards') . '</h1>';
            echo '<p>' . esc_html__('Beheer hier welke service cards zichtbaar zijn en in welke volgorde ze op de website staan.', 'codeline-services-cards') . '</p>';

            if (empty($posts)) {
                echo '<p>' . esc_html__('Geen service cards gevonden.', 'codeline-services-cards') . '</p>';
                echo '</div>';
                return;
            }

            echo '<table class="widefat fixed striped clsc-admin-table">';
            echo '<thead><tr>';
            echo '<th>' . esc_html__('Afbeelding', 'codeline-services-cards') . '</th>';
            echo '<th>' . esc_html__('Card Titel', 'codeline-services-cards') . '</th>';
            echo '<th>' . esc_html__('Card Beschrijving', 'codeline-services-cards') . '</th>';
            echo '<th>' . esc_html__('Volgorde', 'codeline-services-cards') . '</th>';
            echo '<th>' . esc_html__('Zichtbaar', 'codeline-services-cards') . '</th>';
            echo '<th>' . esc_html__('Service Page', 'codeline-services-cards') . '</th>';
            echo '<th>' . esc_html__('Acties', 'codeline-services-cards') . '</th>';
            echo '</tr></thead><tbody>';

            foreach ($posts as $post) {
                $image_id      = (int) get_post_meta($post->ID, self::META_IMAGE_ID, true);
                $card_title    = (string) get_post_meta($post->ID, self::META_CARD_TITLE, true);
                $card_desc     = (string) get_post_meta($post->ID, self::META_CARD_DESC, true);
                $has_order     = metadata_exists('post', $post->ID, self::META_CARD_ORDER);
                $card_order    = $has_order ? max(0, (int) get_post_meta($post->ID, self::META_CARD_ORDER, true)) : '';
                $visible       = $this->is_service_card_visible($post->ID);
                $display_title = '' !== trim($card_title) ? $card_title : $post->post_title;
                $desc_source   = '' !== trim($card_desc) ? $card_desc : ($post->post_excerpt ?: wp_strip_all_tags($post->post_content));

                $linked_page_title = $post->post_title;
                $service_term      = $this->get_service_term_for_page($post->ID);
                if ($service_term instanceof WP_Term) {
                    $custom_title = (string) get_term_meta($service_term->term_id, self::TERM_META_SERVICE_TITLE, true);
                    $linked_page_title = '' !== trim($custom_title) ? $custom_title : $service_term->name;
                }

                echo '<tr data-clsc-post-id="' . esc_attr((string) $post->ID) . '">';
                echo '<td class="clsc-col-image">';
                if ($image_id > 0) {
                    echo wp_get_attachment_image($image_id, array(80, 80), false, array('class' => 'clsc-thumb'));
                } elseif (has_post_thumbnail($post->ID)) {
                    echo get_the_post_thumbnail($post->ID, array(80, 80), array('class' => 'clsc-thumb'));
                } else {
                    echo '<span class="clsc-empty">-</span>';
                }
                echo '</td>';
                echo '<td class="clsc-col-card-title">' . esc_html($display_title) . '</td>';
                echo '<td class="clsc-col-card-desc">' . esc_html(wp_trim_words($desc_source, 20)) . '</td>';
                echo '<td class="clsc-col-order">' . ('' === $card_order ? '&mdash;' : esc_html((string) $card_order)) . '</td>';
                echo '<td class="clsc-col-visible"><span class="clsc-visibility ' . ($visible ? 'is-visible' : 'is-hidden') . '">' . esc_html($visible ? __('Ja', 'codeline-services-cards') : __('Nee', 'codeline-services-cards')) . '</span></td>';
                echo '<td class="clsc-col-service-page">' . esc_html($linked_page_title) . '</td>';
                echo '<td><button type="button" class="button clsc-edit-card-btn"'
                    . ' data-id="' . esc_attr((string) $post->ID) . '"'
                    . ' data-image-id="' . esc_attr((string) $image_id) . '"'
                    . ' data-title="' . esc_attr($card_title) . '"'
                    . ' data-desc="' . esc_attr($card_desc) . '"'
                    . ' data-order="' . esc_attr((string) $card_order) . '"'
                    . ' data-visible="' . esc_attr($visible ? '1' : '0') . '">'
                    . esc_html__('Bewerken', 'codeline-services-cards') . '</button></td>';
                echo '</tr>';
            }

            echo '</tbody></table></div>';
        }

    public function add_admin_columns($columns) {
            $new = array();

            if (isset($columns['cb'])) {
                $new['cb'] = $columns['cb'];
            }

            $new['cl_image']        = __('Afbeelding', 'codeline-services-cards');
            $new['title']           = isset($columns['title']) ? $columns['title'] : __('Title', 'codeline-services-cards');
            $new['cl_card_title']   = __('Card Titel', 'codeline-services-cards');
            $new['cl_card_desc']    = __('Card Beschrijving', 'codeline-services-cards');
            $new['cl_card_order']   = __('Volgorde', 'codeline-services-cards');
            $new['cl_card_visible'] = __('Zichtbaar', 'codeline-services-cards');

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
                    echo esc_html('' !== trim($card_title) ? $card_title : get_the_title($post_id));
                    break;

                case 'cl_card_desc':
                    $card_desc = (string) get_post_meta($post_id, self::META_CARD_DESC, true);
                    if ('' !== trim($card_desc)) {
                        echo esc_html(wp_trim_words($card_desc, 20));
                    } else {
                        $excerpt = (string) get_post_field('post_excerpt', $post_id);
                        $content = (string) get_post_field('post_content', $post_id);
                        echo esc_html(wp_trim_words('' !== trim($excerpt) ? $excerpt : wp_strip_all_tags($content), 20));
                    }
                    break;

                case 'cl_card_order':
                    if (metadata_exists('post', $post_id, self::META_CARD_ORDER)) {
                        echo esc_html((string) max(0, (int) get_post_meta($post_id, self::META_CARD_ORDER, true)));
                    } else {
                        echo '&mdash;';
                    }
                    break;

                case 'cl_card_visible':
                    echo esc_html($this->is_service_card_visible($post_id) ? __('Ja', 'codeline-services-cards') : __('Nee', 'codeline-services-cards'));
                    break;
            }
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
                'text'  => __('CodeLine Agency delivers tailored digital solutions and services designed to strengthen business results and create meaningful customer impact.', 'codeline-services-cards'),
            );

            $saved = get_option(self::OPTION_SERVICE_PAGE_INTRO, array());
            if (!is_array($saved)) {
                return $defaults;
            }

            $title = isset($saved['title']) ? sanitize_text_field((string) $saved['title']) : '';
            $text  = isset($saved['text']) ? sanitize_textarea_field((string) $saved['text']) : '';

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
                <p><?php esc_html_e('Hier kun je de kleuren van de "Lees meer" knop op de Services pagina aanpassen.', 'codeline-services-cards'); ?></p>

                <form method="post" action="">
                    <?php wp_nonce_field('clsc_save_service_style', 'clsc_service_style_nonce'); ?>

                    <table class="form-table" role="presentation">
                        <tbody>
                            <tr>
                                <th scope="row"><label for="clsc_service_style_readmore_show"><?php esc_html_e('Lees meer knop tonen', 'codeline-services-cards'); ?></label></th>
                                <td>
                                    <input type="checkbox" id="clsc_service_style_readmore_show" name="clsc_service_style[readmore_show]" value="1" <?php checked($settings['readmore_show'], '1'); ?> />
                                    <span><?php esc_html_e('Aan/uit voor de "Lees meer" knop op de volledige Services pagina', 'codeline-services-cards'); ?></span>
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

    private function get_display_settings() {
            $defaults = array(
                'desktop' => 4,
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
            <?php wp_nonce_field('clsc_save_service_category', 'clsc_service_category_nonce'); ?>
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

    public function render_category_edit_fields($term) {
            $enabled = (string) get_term_meta($term->term_id, self::TERM_META_SERVICE_ENABLED, true);
            $title = (string) get_term_meta($term->term_id, self::TERM_META_SERVICE_TITLE, true);
            $text = (string) get_term_meta($term->term_id, self::TERM_META_SERVICE_TEXT, true);
            $labels = (string) get_term_meta($term->term_id, self::TERM_META_SERVICE_LABELS, true);
            $service_order = (int) get_term_meta($term->term_id, self::TERM_META_SERVICE_ORDER, true);
            $selected_case_1 = (int) get_term_meta($term->term_id, self::TERM_META_SERVICE_CASE_1, true);
            $selected_case_2 = (int) get_term_meta($term->term_id, self::TERM_META_SERVICE_CASE_2, true);
            $case_posts = $this->get_case_posts_for_selector();
            $service_page_id = $this->find_service_page_for_term((int) $term->term_id);
            $service_page_edit_url = $service_page_id > 0 ? get_edit_post_link($service_page_id, '') : '';
            ?>
            <tr class="form-field term-group-wrap">
                <th scope="row"><label for="cl_service_enabled"><?php esc_html_e('Gebruik op Services pagina', 'codeline-services-cards'); ?></label></th>
                <td>
                    <?php wp_nonce_field('clsc_save_service_category', 'clsc_service_category_nonce'); ?>
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

            if (
                !isset($_POST['clsc_service_category_nonce']) ||
                !wp_verify_nonce(
                    sanitize_text_field(wp_unslash($_POST['clsc_service_category_nonce'])),
                    'clsc_save_service_category'
                )
            ) {
                return;
            }

            $enabled       = isset($_POST['cl_service_enabled']) ? '1' : '0';
            $title         = isset($_POST['cl_service_title']) ? sanitize_text_field(wp_unslash($_POST['cl_service_title'])) : '';
            $text          = isset($_POST['cl_service_text']) ? sanitize_textarea_field(wp_unslash($_POST['cl_service_text'])) : '';
            $labels        = isset($_POST['cl_service_labels']) ? sanitize_text_field(wp_unslash($_POST['cl_service_labels'])) : '';
            $service_order = isset($_POST['cl_service_order']) ? max(0, absint(wp_unslash($_POST['cl_service_order']))) : 0;
            $case_1        = isset($_POST['cl_service_case_1']) ? absint(wp_unslash($_POST['cl_service_case_1'])) : 0;
            $case_2        = isset($_POST['cl_service_case_2']) ? absint(wp_unslash($_POST['cl_service_case_2'])) : 0;

            update_term_meta($term_id, self::TERM_META_SERVICE_ENABLED, $enabled);
            update_term_meta($term_id, self::TERM_META_SERVICE_TITLE, $title);
            update_term_meta($term_id, self::TERM_META_SERVICE_TEXT, $text);
            update_term_meta($term_id, self::TERM_META_SERVICE_LABELS, $labels);
            update_term_meta($term_id, self::TERM_META_SERVICE_ORDER, $service_order);
            update_term_meta($term_id, self::TERM_META_SERVICE_CASE_1, $case_1);
            update_term_meta($term_id, self::TERM_META_SERVICE_CASE_2, $case_2);

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
            $has_order  = metadata_exists('post', $post->ID, self::META_CARD_ORDER);
            $card_order = $has_order ? max(0, (int) get_post_meta($post->ID, self::META_CARD_ORDER, true)) : '';
            $visible    = $this->is_service_card_visible($post->ID);
            ?>
            <p>
                <label>
                    <input type="checkbox" name="cl_service_visible" value="1" <?php checked($visible); ?> />
                    <strong><?php esc_html_e('Toon deze card op de website', 'codeline-services-cards'); ?></strong>
                </label>
            </p>
            <p class="clsc-muted"><?php esc_html_e('Nieuwe service pages zijn standaard verborgen totdat je ze hier zichtbaar maakt.', 'codeline-services-cards'); ?></p>
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
                        // Build the preview with DOM properties so attachment data is never parsed as HTML.
                        var img = document.createElement('img');
                        img.src = String(attachment.url);
                        img.alt = attachment.alt ? String(attachment.alt) : '';
                        img.className = 'clsc-preview-img';
                        while (preview.firstChild) preview.removeChild(preview.firstChild);
                        preview.appendChild(img);
                    }
                });

                frame.open();
            });";

            wp_add_inline_script('jquery', $script);
        }

    public function enqueue_cards_admin_script($hook = '') {
            $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
            if ('clsc-cards' !== $page) {
                return;
            }

            wp_enqueue_media();

            $style_path = CLSC_PLUGIN_DIR . 'assets/admin-cards-styles.css';
            $script_path = CLSC_PLUGIN_DIR . 'assets/admin-cards.js';

            wp_enqueue_style(
                'codeline-services-cards-admin-styles',
                CLSC_PLUGIN_URL . 'assets/admin-cards-styles.css',
                array(),
                file_exists($style_path) ? (string) filemtime($style_path) : self::VERSION
            );

            wp_enqueue_script(
                'codeline-services-cards-admin',
                CLSC_PLUGIN_URL . 'assets/admin-cards.js',
                array('jquery', 'media-editor'),
                file_exists($script_path) ? (string) filemtime($script_path) : self::VERSION,
                true
            );

            wp_localize_script('codeline-services-cards-admin', 'CLSCAdmin', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('clsc_card_update'),
                'labels'   => array(
                    'yes' => __('Ja', 'codeline-services-cards'),
                    'no'  => __('Nee', 'codeline-services-cards'),
                ),
            ));
        }

    public function ajax_update_card() {
            if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'clsc_card_update')) {
                wp_send_json_error(array('message' => __('Nonce mismatch', 'codeline-services-cards')), 403);
            }

            $post_id = isset($_POST['post_id']) ? absint(wp_unslash($_POST['post_id'])) : 0;
            if ($post_id <= 0 || self::SERVICE_POST_TYPE !== get_post_type($post_id)) {
                wp_send_json_error(array('message' => __('Ongeldige service page', 'codeline-services-cards')), 400);
            }

            if (!current_user_can('edit_post', $post_id)) {
                wp_send_json_error(array('message' => __('Geen permissie', 'codeline-services-cards')), 403);
            }

            $image_id   = isset($_POST['image_id']) ? absint(wp_unslash($_POST['image_id'])) : 0;
            $card_title = isset($_POST['card_title']) ? sanitize_text_field(wp_unslash($_POST['card_title'])) : '';
            $card_desc  = isset($_POST['card_desc']) ? sanitize_textarea_field(wp_unslash($_POST['card_desc'])) : '';
            $card_order = isset($_POST['card_order']) && '' !== trim((string) wp_unslash($_POST['card_order']))
                ? max(0, absint(wp_unslash($_POST['card_order'])))
                : 0;
            $visible_supplied = isset($_POST['visible']);
            $visible_raw      = $visible_supplied ? sanitize_text_field(wp_unslash($_POST['visible'])) : '';
            $visible          = $visible_supplied
                ? (in_array($visible_raw, array('1', 'on', 'true', 'yes'), true) ? '1' : '0')
                : ($this->is_service_card_visible($post_id) ? '1' : '0');

            update_post_meta($post_id, self::META_IMAGE_ID, $image_id);
            update_post_meta($post_id, self::META_CARD_ORDER, $card_order);
            if ($visible_supplied) {
                update_post_meta($post_id, self::META_SERVICE_VISIBLE, $visible);
            }

            if ('' !== $card_title) {
                update_post_meta($post_id, self::META_CARD_TITLE, $card_title);
            } else {
                delete_post_meta($post_id, self::META_CARD_TITLE);
            }

            if ('' !== $card_desc) {
                update_post_meta($post_id, self::META_CARD_DESC, $card_desc);
            } else {
                delete_post_meta($post_id, self::META_CARD_DESC);
            }

            wp_send_json_success(array(
                'message' => __('Opgeslagen', 'codeline-services-cards'),
                'visible' => $visible,
                'order'   => $card_order,
            ));
        }

    public function save_meta_boxes($post_id) {
            if (self::SERVICE_POST_TYPE !== get_post_type($post_id)) {
                return;
            }

            if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
                return;
            }

            if (wp_is_post_revision($post_id) || !current_user_can('edit_post', $post_id)) {
                return;
            }

            if (!isset($_POST['cl_service_image_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['cl_service_image_nonce'])), 'cl_service_image_nonce_action')) {
                return;
            }

            if (!isset($_POST['cl_service_content_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['cl_service_content_nonce'])), 'cl_service_content_nonce_action')) {
                return;
            }

            $image_id   = isset($_POST['cl_service_image_id']) ? absint(wp_unslash($_POST['cl_service_image_id'])) : 0;
            $card_title = isset($_POST['cl_service_card_title']) ? sanitize_text_field(wp_unslash($_POST['cl_service_card_title'])) : '';
            $card_desc  = isset($_POST['cl_service_card_desc']) ? sanitize_textarea_field(wp_unslash($_POST['cl_service_card_desc'])) : '';
            $card_order = isset($_POST['cl_service_card_order']) && '' !== trim((string) wp_unslash($_POST['cl_service_card_order']))
                ? max(0, absint(wp_unslash($_POST['cl_service_card_order'])))
                : 0;
            $visible = isset($_POST['cl_service_visible']) ? '1' : '0';

            update_post_meta($post_id, self::META_IMAGE_ID, $image_id);
            update_post_meta($post_id, self::META_CARD_ORDER, $card_order);
            update_post_meta($post_id, self::META_SERVICE_VISIBLE, $visible);

            if ('' !== $card_title) {
                update_post_meta($post_id, self::META_CARD_TITLE, $card_title);
            } else {
                delete_post_meta($post_id, self::META_CARD_TITLE);
            }

            if ('' !== $card_desc) {
                update_post_meta($post_id, self::META_CARD_DESC, $card_desc);
            } else {
                delete_post_meta($post_id, self::META_CARD_DESC);
            }
        }

}
