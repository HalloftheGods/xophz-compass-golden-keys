<?php

/**
 * The API functionality of the plugin.
 */

class Xophz_Compass_Golden_Keys_API {

    private $plugin_name;
    private $version;

    public function __construct( $plugin_name, $version ) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    public function register_routes() {
        register_rest_route( 'golden-keys/v1', '/lexicon', array(
            'methods'  => 'GET',
            'callback' => array( $this, 'get_content_lexicon' ),
            'permission_callback' => array( $this, 'get_items_permissions_check' ),
        ) );

        register_rest_route( 'golden-keys/v1', '/traffic', array(
            'methods'  => 'GET',
            'callback' => array( $this, 'get_traffic_vectors' ),
            'permission_callback' => array( $this, 'get_items_permissions_check' ),
        ) );

        register_rest_route( 'golden-keys/v1', '/focus-keywords', array(
            array(
                'methods'             => 'GET',
                'callback'            => array( $this, 'get_focus_keywords' ),
                'permission_callback' => array( $this, 'get_items_permissions_check' ),
            ),
            array(
                'methods'             => 'POST',
                'callback'            => array( $this, 'update_focus_keywords' ),
                'permission_callback' => array( $this, 'get_items_permissions_check' ),
            ),
        ) );

        register_rest_route( 'golden-keys/v1', '/opportunities', array(
            'methods'  => 'GET',
            'callback' => array( $this, 'get_keyword_opportunities' ),
            'permission_callback' => array( $this, 'get_items_permissions_check' ),
        ) );

        register_rest_route( 'golden-keys/v1', '/license', array(
            'methods'  => 'GET',
            'callback' => array( $this, 'get_my_license' ),
            'permission_callback' => 'is_user_logged_in',
        ) );

        register_rest_route( 'golden-keys/v1', '/license/validate', array(
            'methods'  => 'POST',
            'callback' => array( $this, 'validate_license_key_route' ),
            'permission_callback' => '__return_true',
        ) );
    }

    public static function generate_license_key( $user_id, $tier = 'pro_chef' ) {
        $prefix = ( $tier === 'enterprise_pantry' || $tier === 'commercial' ) ? 'GOLDEN-BIZ' : 'GOLDEN-PRO';
        $bytes  = bin2hex( random_bytes( 8 ) );
        $parts  = str_split( strtoupper( $bytes ), 4 );
        $key    = $prefix . '-' . implode( '-', $parts );

        $license_data = array(
            'license_key' => $key,
            'user_id'     => $user_id,
            'tier'        => $tier,
            'status'      => 'active',
            'created_at'  => current_time( 'mysql' ),
            'expires_at'  => date( 'Y-m-d H:i:s', strtotime( '+1 year' ) ),
        );

        update_user_meta( $user_id, 'xophz_golden_license', $license_data );
        update_user_meta( $user_id, 'kitchensynk_user_type', $tier );

        return $license_data;
    }

    public function get_my_license( $request ) {
        $user_id = get_current_user_id();
        $license = get_user_meta( $user_id, 'xophz_golden_license', true );
        
        if ( empty( $license ) || ! is_array( $license ) ) {
            return new WP_REST_Response( array(
                'has_license' => false,
                'tier'        => 'starter',
                'message'     => 'No active license key found for this user account.'
            ), 200 );
        }

        return new WP_REST_Response( array(
            'has_license' => true,
            'license'     => $license
        ), 200 );
    }

    public function validate_license_key_route( $request ) {
        $params = $request->get_json_params();
        $key    = sanitize_text_field( $params['license_key'] ?? '' );

        if ( empty( $key ) ) {
            return new WP_Error( 'missing_key', 'License key is required.', array( 'status' => 400 ) );
        }

        $users = get_users( array(
            'meta_key'   => 'xophz_golden_license',
            'number'     => 1,
        ) );

        foreach ( $users as $u ) {
            $lic = get_user_meta( $u->ID, 'xophz_golden_license', true );
            if ( is_array( $lic ) && isset( $lic['license_key'] ) && $lic['license_key'] === $key ) {
                return new WP_REST_Response( array(
                    'valid'     => true,
                    'status'    => $lic['status'],
                    'tier'      => $lic['tier'],
                    'expires_at'=> $lic['expires_at'],
                    'user_email'=> $u->user_email
                ), 200 );
            }
        }

        return new WP_REST_Response( array(
            'valid'   => false,
            'message' => 'Invalid or expired Golden License Key.'
        ), 200 );
    }

    public function get_items_permissions_check( $request ) {
        // Must be logged in and can edit posts to view SEO/analytics data
        return current_user_can( 'edit_posts' );
    }

    public function get_content_lexicon( $request ) {
        // 1. Fetch posts and pages
        $args = array(
            'post_type' => array( 'post', 'page' ),
            'post_status' => 'publish',
            'posts_per_page' => 100, // Limit for performance during initial build
        );
        
        $query = new WP_Query( $args );
        
        $text_corpus = '';
        
        if ( $query->have_posts() ) {
            foreach ( $query->posts as $post ) {
                $text_corpus .= ' ' . $post->post_title;
                $text_corpus .= ' ' . wp_strip_all_tags( $post->post_content );
            }
        }
        
        // 2. Clean and count frequencies
        $text_corpus = strtolower( $text_corpus );
        // Remove punctuation
        $text_corpus = preg_replace( '/[^\p{L}\p{N}\s]/u', '', $text_corpus );
        $words = explode( ' ', $text_corpus );
        
        // Basic stop words filter
        $stop_words = array( 'the', 'and', 'a', 'to', 'of', 'in', 'i', 'is', 'that', 'it', 'on', 'you', 'this', 'for', 'but', 'with', 'are', 'have', 'be', 'at', 'or', 'as', 'was', 'so', 'if', 'out', 'not', 'we', 'your', 'from', 'an', 'by', 'about', 'can', 'has', 'will', 'what', 'all', 'were', 'my', 'when', 'up', 'one', 'there', 'who', 'which', 'do', 'their', 'how', 'more', 'them', 'some', 'me', 'would', 'into', 'just', 'no', 'make', 'could', 'like', 'then', 'than', 'over', 'also', 'our', '', '1' ); // Adding common stopwords
        
        $word_counts = array();
        
        foreach ( $words as $word ) {
            $word = trim( $word );
            if ( ! in_array( $word, $stop_words ) && strlen( $word ) > 2 ) {
                if ( isset( $word_counts[ $word ] ) ) {
                    $word_counts[ $word ]++;
                } else {
                    $word_counts[ $word ] = 1;
                }
            }
        }
        
        arsort( $word_counts );
        $top_words = array_slice( $word_counts, 0, 100 ); // Top 100 words
        
        $formatted_data = array();
        foreach ( $top_words as $word => $count ) {
            $formatted_data[] = array(
                'name' => $word,
                'value' => $count * 10 // Multiply arbitrarily for better word cloud sizing if counts are low
            );
        }
        
        return new WP_REST_Response( $formatted_data, 200 );
    }

    public function get_traffic_vectors( $request ) {
        $response = array(
            'connected' => false,
            'message'   => 'Google Search Console is not yet connected. Connect it in Golden Keys settings to see real keyword traffic data.',
            'terms'     => array(),
        );
        
        return new WP_REST_Response( $response, 200 );
    }

    public function get_focus_keywords( $request ) {
        $post_type   = $request->get_param( 'post_type' );
        $post_status = $request->get_param( 'status' );
        $search      = $request->get_param( 'search' );
        $per_page    = (int) ( $request->get_param( 'per_page' ) ?: 50 );
        $page        = (int) ( $request->get_param( 'page' ) ?: 1 );

        if ( empty( $post_type ) || 'all' === $post_type ) {
            $post_types = get_post_types( array( 'public' => true ), 'names' );
            unset( $post_types['attachment'] );
            $post_types = array_values( $post_types );
        } else {
            $post_types = array( sanitize_key( $post_type ) );
        }

        $args = array(
            'post_type'      => $post_types,
            'post_status'    => ! empty( $post_status ) && 'all' !== $post_status ? sanitize_key( $post_status ) : array( 'publish', 'draft', 'private', 'pending', 'future' ),
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'orderby'        => 'modified',
            'order'          => 'DESC',
        );

        if ( ! empty( $search ) ) {
            $args['s'] = sanitize_text_field( $search );
        }

        $query = new WP_Query( $args );
        $items = array();

        if ( $query->have_posts() ) {
            foreach ( $query->posts as $p ) {
                $p_id = $p->ID;

                // SmartCrawl / WDS / RankMath / Yoast focus keywords
                $wds_keywords = get_post_meta( $p_id, '_wds_focus-keywords', true );
                if ( empty( $wds_keywords ) ) {
                    $wds_keywords = get_post_meta( $p_id, '_wds_focus_keyword', true );
                }
                if ( empty( $wds_keywords ) ) {
                    $wds_keywords = get_post_meta( $p_id, 'rank_math_focus_keyword', true );
                }
                if ( empty( $wds_keywords ) ) {
                    $wds_keywords = get_post_meta( $p_id, '_yoast_wpseo_focuskw', true );
                }

                $primary_kw = '';
                $secondary_kws = array();
                if ( is_array( $wds_keywords ) ) {
                    $primary_kw    = $wds_keywords[0] ?? '';
                    $secondary_kws = array_slice( $wds_keywords, 1 );
                } elseif ( is_string( $wds_keywords ) ) {
                    $parts         = array_map( 'trim', explode( ',', $wds_keywords ) );
                    $primary_kw    = $parts[0] ?? '';
                    $secondary_kws = array_values( array_filter( array_slice( $parts, 1 ) ) );
                }

                // SEO & Readability score
                $seo_score = get_post_meta( $p_id, '_smartcrawl_seo_score', true );
                if ( '' === $seo_score || false === $seo_score ) {
                    $seo_score = get_post_meta( $p_id, '_wds_seo_score', true );
                }
                if ( '' === $seo_score || false === $seo_score ) {
                    $seo_score = get_post_meta( $p_id, '_wds_score', true );
                }
                if ( '' === $seo_score || false === $seo_score ) {
                    $seo_score = get_post_meta( $p_id, 'rank_math_seo_score', true );
                }

                $readability_score = get_post_meta( $p_id, '_smartcrawl_readability_score', true );
                if ( '' === $readability_score || false === $readability_score ) {
                    $readability_score = get_post_meta( $p_id, '_wds_readability_score', true );
                }

                // Content metrics
                $plain_content = wp_strip_all_tags( $p->post_content );
                $word_count    = ! empty( trim( $plain_content ) ) ? count( preg_split( '/\s+/', trim( $plain_content ) ) ) : 0;

                // Keyword occurrences in content & title
                $title_has_kw  = false;
                $content_count = 0;
                if ( ! empty( $primary_kw ) ) {
                    $kw_clean      = strtolower( $primary_kw );
                    $title_has_kw  = ( false !== stripos( $p->post_title, $primary_kw ) );
                    $content_count = substr_count( strtolower( $plain_content ), $kw_clean );
                }

                $pt_obj     = get_post_type_object( $p->post_type );
                $type_label = $pt_obj ? $pt_obj->labels->singular_name : ucfirst( $p->post_type );

                $items[] = array(
                    'id'                 => $p_id,
                    'title'              => html_entity_decode( $p->post_title ?: '(No title)' ),
                    'post_type'          => $p->post_type,
                    'post_type_label'    => $type_label,
                    'status'             => $p->post_status,
                    'permalink'          => get_permalink( $p_id ),
                    'edit_url'           => get_edit_post_link( $p_id, 'raw' ),
                    'focus_keyword'      => $primary_kw,
                    'secondary_keywords' => $secondary_kws,
                    'seo_score'          => ( '' !== $seo_score && false !== $seo_score ) ? (int) $seo_score : null,
                    'readability_score'  => ( '' !== $readability_score && false !== $readability_score ) ? (int) $readability_score : null,
                    'word_count'         => $word_count,
                    'in_title'           => $title_has_kw,
                    'occurrences'        => $content_count,
                    'modified_date'      => get_the_modified_date( 'Y-m-d H:i', $p ),
                );
            }
        }

        // Available post types for filtering
        $all_types = array();
        $pub_types = get_post_types( array( 'public' => true ), 'objects' );
        unset( $pub_types['attachment'] );
        foreach ( $pub_types as $slug => $obj ) {
            $count_obj = wp_count_posts( $slug );
            $pub_count = isset( $count_obj->publish ) ? (int) $count_obj->publish : 0;
            $drf_count = isset( $count_obj->draft ) ? (int) $count_obj->draft : 0;
            $all_types[] = array(
                'slug'  => $slug,
                'name'  => $obj->labels->name ?: ucfirst( $slug ),
                'count' => $pub_count + $drf_count,
            );
        }

        return new WP_REST_Response( array(
            'items'        => $items,
            'total'        => (int) $query->found_posts,
            'total_pages'  => (int) $query->max_num_pages,
            'current_page' => $page,
            'post_types'   => $all_types,
        ), 200 );
    }

    public function update_focus_keywords( $request ) {
        $params = $request->get_json_params();
        
        $items_to_update = array();
        if ( isset( $params['items'] ) && is_array( $params['items'] ) ) {
            $items_to_update = $params['items'];
        } elseif ( isset( $params['post_id'] ) ) {
            $items_to_update[] = array(
                'post_id'            => (int) $params['post_id'],
                'focus_keyword'      => $params['focus_keyword'] ?? '',
                'secondary_keywords' => $params['secondary_keywords'] ?? array(),
            );
        }

        if ( empty( $items_to_update ) ) {
            return new WP_Error( 'missing_data', 'No post or keyword updates provided.', array( 'status' => 400 ) );
        }

        $updated_count = 0;
        foreach ( $items_to_update as $item ) {
            $p_id = (int) ( $item['post_id'] ?? 0 );
            if ( ! $p_id || ! get_post( $p_id ) ) {
                continue;
            }

            $kw        = sanitize_text_field( $item['focus_keyword'] ?? '' );
            $secondary = array_map( 'sanitize_text_field', (array) ( $item['secondary_keywords'] ?? array() ) );
            $all_kws   = array_values( array_filter( array_merge( array( $kw ), $secondary ) ) );

            // SmartCrawl / WDS meta keys
            update_post_meta( $p_id, '_wds_focus-keywords', ! empty( $all_kws ) ? implode( ',', $all_kws ) : '' );
            update_post_meta( $p_id, '_wds_focus_keyword', $kw );

            // Secondary plugin meta compatibility
            update_post_meta( $p_id, 'rank_math_focus_keyword', implode( ', ', $all_kws ) );
            update_post_meta( $p_id, '_yoast_wpseo_focuskw', $kw );

            // Trigger SmartCrawl recalculation if installed
            if ( class_exists( '\SmartCrawl\Controllers\Analysis' ) ) {
                try {
                    $analyzer = method_exists( '\SmartCrawl\Controllers\Analysis', 'get' ) ? \SmartCrawl\Controllers\Analysis::get() : new \SmartCrawl\Controllers\Analysis();
                    if ( method_exists( $analyzer, 'maybe_analyze_post' ) ) {
                        $analyzer->maybe_analyze_post( $p_id );
                    }
                } catch ( Exception $e ) {
                    // Ignore analyzer exceptions
                }
            }

            $updated_count++;
        }

        return new WP_REST_Response( array(
            'success' => true,
            'updated' => $updated_count,
            'message' => "Successfully updated focus keywords for {$updated_count} post(s).",
        ), 200 );
    }

    public function get_keyword_opportunities( $request ) {
        global $wpdb;
        $meta_kws = $wpdb->get_col( "
            SELECT DISTINCT meta_value 
            FROM {$wpdb->postmeta} 
            WHERE meta_key IN ('_wds_focus-keywords', '_wds_focus_keyword', 'rank_math_focus_keyword', '_yoast_wpseo_focuskw')
              AND meta_value != ''
        " );

        $assigned_set = array();
        if ( ! empty( $meta_kws ) ) {
            foreach ( $meta_kws as $raw ) {
                $parts = explode( ',', $raw );
                foreach ( $parts as $p ) {
                    $trimmed = strtolower( trim( $p ) );
                    if ( ! empty( $trimmed ) ) {
                        $assigned_set[ $trimmed ] = true;
                    }
                }
            }
        }

        $lexicon_response = $this->get_content_lexicon( $request );
        $lexicon_data     = $lexicon_response->get_data();

        $untapped_opportunities = array();
        if ( is_array( $lexicon_data ) ) {
            foreach ( $lexicon_data as $entry ) {
                $term = strtolower( $entry['name'] );
                if ( ! isset( $assigned_set[ $term ] ) ) {
                    $untapped_opportunities[] = array(
                        'keyword'    => $term,
                        'frequency'  => (int) ( $entry['value'] / 10 ),
                        'importance' => $entry['value'] > 100 ? 'High' : ( $entry['value'] > 40 ? 'Medium' : 'Growth' ),
                        'status'     => 'Unassigned',
                    );
                }
            }
        }

        $missing_posts_query = new WP_Query( array(
            'post_type'      => array( 'post', 'page' ),
            'post_status'    => 'publish',
            'posts_per_page' => 20,
            'meta_query'     => array(
                'relation' => 'OR',
                array(
                    'key'     => '_wds_focus_keyword',
                    'compare' => 'NOT EXISTS',
                ),
                array(
                    'key'     => '_wds_focus_keyword',
                    'value'   => '',
                    'compare' => '=',
                ),
            ),
        ) );

        $missing_posts = array();
        if ( $missing_posts_query->have_posts() ) {
            foreach ( $missing_posts_query->posts as $p ) {
                $missing_posts[] = array(
                    'id'        => $p->ID,
                    'title'     => html_entity_decode( $p->post_title ?: '(No title)' ),
                    'post_type' => $p->post_type,
                    'permalink' => get_permalink( $p->ID ),
                    'date'      => get_the_date( 'Y-m-d', $p ),
                );
            }
        }

        return new WP_REST_Response( array(
            'untapped_keywords'   => array_slice( $untapped_opportunities, 0, 20 ),
            'missing_posts_count' => (int) $missing_posts_query->found_posts,
            'missing_posts'       => $missing_posts,
            'total_assigned'      => count( $assigned_set ),
        ), 200 );
    }
}
