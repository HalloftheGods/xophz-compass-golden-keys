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
}
