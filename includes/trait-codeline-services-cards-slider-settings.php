<?php
/**
 * CodeLine_Services_Cards_Slider_Settings_Trait responsibilities for CodeLine Services Cards.
 *
 * Admin-configurable style/behaviour of the [codeline_services_cards]
 * slider: card/image aspect ratio, card width and gap per breakpoint, image
 * border radius and the desktop autoplay on/off switch.
 *
 * Mirrors the Settings API architecture of the CodeLine Cases "Homepage
 * Slider Style" page, but with fully independent Services identifiers:
 * - Option name:    clsc_slider_style
 * - Settings group: clsc_slider_style_group
 * - Submenu slug:   clsc-slider-style (under Service Page)
 *
 * Stored as one option holding sanitized, bounds-checked numeric/boolean
 * values only -- no arbitrary CSS/HTML accepted. Any missing or invalid
 * value falls back to its default, so an empty/corrupt option can never
 * break the slider, and nothing is written until an administrator saves.
 *
 * @package CodeLineServicesCards
 */

if (!defined('ABSPATH')) {
    exit;
}

trait CodeLine_Services_Cards_Slider_Settings_Trait {
    /**
     * Defaults match the homepage Cases slider's effective appearance on DEV
     * (ratio 9:16, card width 210/195/180px, gap 20/16/12px, radius 10px).
     * Autoplay defaults to off: desktop navigation is arrow-based.
     *
     * @return array
     */
    public static function get_slider_style_defaults() {
        return array(
            'ratio_w'          => 9,
            'ratio_h'          => 16,
            'width_desktop'    => 210,
            'width_tablet'     => 195,
            'width_mobile'     => 180,
            'gap_desktop'      => 20,
            'gap_tablet'       => 16,
            'gap_mobile'       => 12,
            'border_radius'    => 10,
            'autoplay_enabled' => 0,
        );
    }

    /**
     * key => array(min, max). autoplay_enabled is a boolean, sanitized
     * separately, so it is intentionally not listed here.
     *
     * @return array
     */
    public static function get_slider_style_bounds() {
        return array(
            'ratio_w'       => array(1, 100),
            'ratio_h'       => array(1, 100),
            'width_desktop' => array(120, 480),
            'width_tablet'  => array(100, 400),
            'width_mobile'  => array(80, 320),
            'gap_desktop'   => array(0, 80),
            'gap_tablet'    => array(0, 80),
            'gap_mobile'    => array(0, 80),
            'border_radius' => array(0, 48),
        );
    }

    /**
     * Current, always-complete, always-valid settings.
     *
     * @return array
     */
    public static function get_slider_style_settings() {
        $defaults = self::get_slider_style_defaults();
        $bounds   = self::get_slider_style_bounds();
        $stored   = get_option(self::OPTION_SLIDER_STYLE, array());
        if (!is_array($stored)) {
            $stored = array();
        }

        $out = array();
        foreach ($defaults as $key => $default) {
            if ('autoplay_enabled' === $key) {
                $out[$key] = isset($stored[$key]) ? (int) (bool) $stored[$key] : $default;
                continue;
            }

            $value = isset($stored[$key]) && is_numeric($stored[$key]) ? (int) $stored[$key] : $default;
            list($min, $max) = $bounds[$key];
            $out[$key] = ($value < $min || $value > $max) ? $default : $value;
        }

        return $out;
    }

    /**
     * @param array $input Raw $_POST-derived value from the Settings API.
     * @return array
     */
    public function sanitize_slider_style($input) {
        $defaults = self::get_slider_style_defaults();
        $bounds   = self::get_slider_style_bounds();
        $out      = array();

        if (!is_array($input)) {
            $input = array();
        }

        foreach ($defaults as $key => $default) {
            if ('autoplay_enabled' === $key) {
                // Checkbox: absent from $_POST entirely when unchecked.
                $out[$key] = !empty($input[$key]) ? 1 : 0;
                continue;
            }

            $raw   = isset($input[$key]) ? $input[$key] : '';
            $value = ('' === $raw || !is_numeric($raw)) ? $default : (int) round(floatval($raw));
            list($min, $max) = $bounds[$key];
            if ($value < $min || $value > $max) {
                $value = $default;
            }
            $out[$key] = $value;
        }

        return $out;
    }

    public function register_slider_style_page() {
        add_submenu_page(
            'edit.php?post_type=' . self::SERVICE_POST_TYPE,
            'Services Slider Style',
            'Services Slider Style',
            'manage_options',
            self::SLIDER_STYLE_MENU_SLUG,
            array($this, 'render_slider_style_page')
        );
    }

    public function register_slider_style_setting() {
        register_setting(
            self::SLIDER_STYLE_SETTINGS_GROUP,
            self::OPTION_SLIDER_STYLE,
            array(
                'type'              => 'array',
                'sanitize_callback' => array($this, 'sanitize_slider_style'),
                'default'           => self::get_slider_style_defaults(),
            )
        );
    }

    /**
     * Inline custom properties for the .clsc root. Every value is a bounded
     * integer from get_slider_style_settings(), never raw user input.
     *
     * @return string
     */
    private function get_slider_style_css_vars() {
        $style = self::get_slider_style_settings();

        return sprintf(
            '--clsc-ratio-w: %1$d; --clsc-ratio-h: %2$d; --clsc-width-desktop: %3$dpx; --clsc-width-tablet: %4$dpx; --clsc-width-mobile: %5$dpx; --clsc-gap-desktop: %6$dpx; --clsc-gap-tablet: %7$dpx; --clsc-gap-mobile: %8$dpx; --clsc-radius: %9$dpx;',
            (int) $style['ratio_w'],
            (int) $style['ratio_h'],
            (int) $style['width_desktop'],
            (int) $style['width_tablet'],
            (int) $style['width_mobile'],
            (int) $style['gap_desktop'],
            (int) $style['gap_tablet'],
            (int) $style['gap_mobile'],
            (int) $style['border_radius']
        );
    }

    private function render_slider_style_number_field($key, $label, $suffix = 'px') {
        $values = self::get_slider_style_settings();
        $bounds = self::get_slider_style_bounds();
        list($min, $max) = $bounds[$key];
        ?>
        <label style="display:block;margin-bottom:14px;max-width:320px;">
            <span style="display:block;font-weight:600;margin-bottom:4px;"><?php echo esc_html($label); ?></span>
            <input type="number" step="1" min="<?php echo esc_attr($min); ?>" max="<?php echo esc_attr($max); ?>"
                name="<?php echo esc_attr(self::OPTION_SLIDER_STYLE); ?>[<?php echo esc_attr($key); ?>]"
                value="<?php echo esc_attr($values[$key]); ?>" style="width:120px;" />
            <span style="color:#666;"><?php echo esc_html($suffix); ?> (<?php echo esc_html($min); ?>&ndash;<?php echo esc_html($max); ?>)</span>
        </label>
        <?php
    }

    private function render_slider_style_checkbox_field($key, $label) {
        $values = self::get_slider_style_settings();
        ?>
        <label style="display:block;margin-bottom:14px;">
            <input type="checkbox" name="<?php echo esc_attr(self::OPTION_SLIDER_STYLE); ?>[<?php echo esc_attr($key); ?>]" value="1" <?php checked($values[$key], 1); ?> />
            <?php echo esc_html($label); ?>
        </label>
        <?php
    }

    public function render_slider_style_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        ?>
        <div class="wrap">
            <h1>Services Slider Style</h1>
            <p>Controls the card proportions, size, spacing, corner radius and autoplay of the <strong>[codeline_services_cards]</strong> shortcode. Invalid or empty values automatically fall back to their defaults.</p>
            <form method="post" action="options.php">
                <?php settings_fields(self::SLIDER_STYLE_SETTINGS_GROUP); ?>

                <h2 class="title">Autoplay</h2>
                <?php $this->render_slider_style_checkbox_field('autoplay_enabled', 'Enable automatic movement on desktop'); ?>
                <p style="color:#666;max-width:520px;margin-top:-8px;">Only affects desktop/laptop screens (1200px and up). Mobile and tablet never autoplay, and visitors with "reduce motion" enabled in their system never see autoplay either, regardless of this setting. Manual navigation (arrows on desktop, touch swipe on tablet/mobile) always works.</p>

                <h2 class="title">Card aspect ratio</h2>
                <?php
                $this->render_slider_style_number_field('ratio_w', 'Width', 'units');
                $this->render_slider_style_number_field('ratio_h', 'Height', 'units');
                ?>

                <h2 class="title">Card width</h2>
                <?php
                $this->render_slider_style_number_field('width_desktop', 'Desktop (1200px and up)');
                $this->render_slider_style_number_field('width_tablet', 'Tablet (768px&ndash;1199px)');
                $this->render_slider_style_number_field('width_mobile', 'Mobile (below 768px)');
                ?>

                <h2 class="title">Gap between cards</h2>
                <?php
                $this->render_slider_style_number_field('gap_desktop', 'Desktop (1200px and up)');
                $this->render_slider_style_number_field('gap_tablet', 'Tablet (768px&ndash;1199px)');
                $this->render_slider_style_number_field('gap_mobile', 'Mobile (below 768px)');
                ?>

                <h2 class="title">Border radius</h2>
                <?php $this->render_slider_style_number_field('border_radius', 'Image corners'); ?>

                <?php submit_button('Save Slider Style'); ?>
            </form>
        </div>
        <?php
    }
}
