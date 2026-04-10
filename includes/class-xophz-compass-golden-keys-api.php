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
