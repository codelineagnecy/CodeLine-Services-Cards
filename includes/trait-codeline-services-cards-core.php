<?php
/**
 * CodeLine_Services_Cards_Core_Trait responsibilities for CodeLine Services Cards.
 *
 * @package CodeLineServicesCards
 */

if (!defined('ABSPATH')) {
    exit;
}

trait CodeLine_Services_Cards_Core_Trait {
    public function register_service_page_content() {
            register_post_type(self::SERVICE_POST_TYPE, array(
                'labels' => array(
                    'name'          => __('Service Pages', 'codeline-services-cards'),
                    'singular_name' => __('Service Page', 'codeline-services-cards'),
                    'menu_name'     => __('Service Page', 'codeline-services-cards'),
                    'add_new_item'  => __('Nieuwe Service Page', 'codeline-services-cards'),
                    'edit_item'     => __('Service Page bewerken', 'codeline-services-cards'),
                ),
                'public'            => true,
                'show_ui'           => true,
                'show_in_menu'      => true,
                'show_in_admin_bar' => false,
                'menu_position'     => 26,
                'menu_icon'         => 'dashicons-screenoptions',
                'supports'          => array('title', 'editor', 'excerpt', 'thumbnail'),
                'has_archive'       => false,
                'rewrite'           => array('slug' => self::SERVICE_REWRITE_SLUG, 'with_front' => false),
                'capability_type'   => 'post',
                'capabilities'      => array(
                    'create_posts' => 'do_not_allow',
                ),
                'map_meta_cap'      => true,
                'show_in_rest'      => true,
            ));

            register_taxonomy(self::SERVICE_TAXONOMY, self::SERVICE_POST_TYPE, array(
                'labels' => array(
                    'name'          => __('Categorieen', 'codeline-services-cards'),
                    'singular_name' => __('Categorie', 'codeline-services-cards'),
                    'search_items'  => __('Categorieen zoeken', 'codeline-services-cards'),
                    'all_items'     => __('Alle categorieen', 'codeline-services-cards'),
                    'edit_item'     => __('Categorie bewerken', 'codeline-services-cards'),
                    'update_item'   => __('Categorie updaten', 'codeline-services-cards'),
                    'add_new_item'  => __('Nieuwe categorie toevoegen', 'codeline-services-cards'),
                    'new_item_name' => __('Nieuwe categorienaam', 'codeline-services-cards'),
                    'menu_name'     => __('Categorieen', 'codeline-services-cards'),
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

    public function maybe_migrate_service_links() {
            $migrated = (string) get_option(self::DATA_MIGRATION_OPTION, '');
            if (self::DATA_MIGRATION_VERSION === $migrated) {
                return;
            }

            $terms = get_terms(array(
                'taxonomy'   => self::SERVICE_TAXONOMY,
                'hide_empty' => false,
            ));

            if (!is_wp_error($terms) && is_array($terms)) {
                foreach ($terms as $term) {
                    if (!($term instanceof WP_Term)) {
                        continue;
                    }

                    $page_id = (int) get_term_meta($term->term_id, self::TERM_META_SERVICE_PAGE_ID, true);
                    if ($page_id <= 0 || self::SERVICE_POST_TYPE !== get_post_type($page_id)) {
                        $matched = get_page_by_path($term->slug, OBJECT, self::SERVICE_POST_TYPE);
                        if ($matched instanceof WP_Post && (int) $matched->ID > 0) {
                            $page_id = (int) $matched->ID;
                            update_term_meta($term->term_id, self::TERM_META_SERVICE_PAGE_ID, $page_id);
                        }
                    }

                    if ($page_id > 0 && self::SERVICE_POST_TYPE === get_post_type($page_id)) {
                        $this->link_service_page_to_term($page_id, (int) $term->term_id);
                    }
                }
            }

            update_option(self::DATA_MIGRATION_OPTION, self::DATA_MIGRATION_VERSION, false);
        }

    public function maybe_flush_rewrite_rules() {
            $flushed_version = (string) get_option(self::REWRITE_FLUSH_OPTION, '');
            if ($flushed_version === self::VERSION) {
                return;
            }

            flush_rewrite_rules(false);
            update_option(self::REWRITE_FLUSH_OPTION, self::VERSION, false);
        }

    private function link_service_page_to_term($post_id, $term_id) {
            $post_id = absint($post_id);
            $term_id = absint($term_id);

            if ($post_id <= 0 || $term_id <= 0 || self::SERVICE_POST_TYPE !== get_post_type($post_id)) {
                return;
            }

            wp_set_object_terms($post_id, array($term_id), self::SERVICE_TAXONOMY, false);
        }

    private function ensure_service_page_for_term($term_id) {
            $term_id = absint($term_id);
            if ($term_id <= 0) {
                return 0;
            }

            $term = get_term($term_id, self::SERVICE_TAXONOMY);
            if (!($term instanceof WP_Term)) {
                return 0;
            }

            $existing_id = (int) get_term_meta($term_id, self::TERM_META_SERVICE_PAGE_ID, true);
            if ($existing_id > 0 && self::SERVICE_POST_TYPE === get_post_type($existing_id)) {
                $existing_post = get_post($existing_id);
                if ($existing_post instanceof WP_Post) {
                    $target_title = (string) $term->name;
                    $target_slug  = sanitize_title((string) $term->slug);
                    $needs_update = ($existing_post->post_title !== $target_title) || ($existing_post->post_name !== $target_slug);

                    if ($needs_update) {
                        wp_update_post(array(
                            'ID'         => $existing_id,
                            'post_title' => $target_title,
                            'post_name'  => $target_slug,
                        ));
                    }
                }

                $this->link_service_page_to_term($existing_id, $term_id);
                return $existing_id;
            }

            $matched = get_page_by_path($term->slug, OBJECT, self::SERVICE_POST_TYPE);
            if ($matched instanceof WP_Post && (int) $matched->ID > 0) {
                $matched_id = (int) $matched->ID;
                update_term_meta($term_id, self::TERM_META_SERVICE_PAGE_ID, $matched_id);
                $this->link_service_page_to_term($matched_id, $term_id);
                return $matched_id;
            }

            $new_id = wp_insert_post(array(
                'post_type'    => self::SERVICE_POST_TYPE,
                'post_status'  => 'publish',
                'post_title'   => $term->name,
                'post_name'    => $term->slug,
                'post_content' => '',
            ), true);

            if (!is_wp_error($new_id) && (int) $new_id > 0) {
                $new_id = (int) $new_id;
                update_term_meta($term_id, self::TERM_META_SERVICE_PAGE_ID, $new_id);
                $this->link_service_page_to_term($new_id, $term_id);

                // New service pages do not enter the homepage cards until explicitly enabled.
                update_post_meta($new_id, self::META_SERVICE_VISIBLE, '0');
                $initial_order = (int) get_term_meta($term_id, self::TERM_META_SERVICE_ORDER, true);
                update_post_meta($new_id, self::META_CARD_ORDER, max(0, $initial_order));

                return $new_id;
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

            $page_id = $this->ensure_service_page_for_term((int) $term->term_id);
            if ($page_id > 0 && 'publish' === get_post_status($page_id)) {
                $url = get_permalink($page_id);
                return is_string($url) ? $url : '';
            }

            return '';
        }

    private function get_service_term_for_page($post_id) {
            $post_id = absint($post_id);
            if ($post_id <= 0) {
                return null;
            }

            $assigned = wp_get_object_terms($post_id, self::SERVICE_TAXONOMY, array('number' => 1));
            if (!is_wp_error($assigned) && is_array($assigned) && !empty($assigned) && $assigned[0] instanceof WP_Term) {
                return $assigned[0];
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

            if (!is_wp_error($terms) && is_array($terms) && !empty($terms) && $terms[0] instanceof WP_Term) {
                return $terms[0];
            }

            $page = get_post($post_id);
            if ($page instanceof WP_Post && '' !== $page->post_name) {
                $slug_term = get_term_by('slug', $page->post_name, self::SERVICE_TAXONOMY);
                if ($slug_term instanceof WP_Term) {
                    return $slug_term;
                }
            }

            return null;
        }

    private function get_service_card_order($post_id) {
            $post_id = absint($post_id);
            if ($post_id <= 0 || !metadata_exists('post', $post_id, self::META_CARD_ORDER)) {
                return PHP_INT_MAX;
            }

            return max(0, (int) get_post_meta($post_id, self::META_CARD_ORDER, true));
        }

    private function is_service_card_visible($post_id) {
            $post_id = absint($post_id);
            if ($post_id <= 0) {
                return false;
            }

            // Backward compatibility: cards created before visibility existed stay visible.
            if (!metadata_exists('post', $post_id, self::META_SERVICE_VISIBLE)) {
                return true;
            }

            return '1' === (string) get_post_meta($post_id, self::META_SERVICE_VISIBLE, true);
        }

    private function sort_service_posts($posts) {
            if (!is_array($posts)) {
                return array();
            }

            usort($posts, function ($a, $b) {
                if (!($a instanceof WP_Post) || !($b instanceof WP_Post)) {
                    return 0;
                }

                $order_a = $this->get_service_card_order($a->ID);
                $order_b = $this->get_service_card_order($b->ID);

                if ($order_a !== $order_b) {
                    return $order_a <=> $order_b;
                }

                $date_a = strtotime((string) $a->post_date_gmt);
                $date_b = strtotime((string) $b->post_date_gmt);
                if ($date_a !== $date_b) {
                    return $date_b <=> $date_a;
                }

                return (int) $a->ID <=> (int) $b->ID;
            });

            return $posts;
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

}
