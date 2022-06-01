<?php

// function that runs when shortcode is called
add_shortcode('counter', 'counter');
function counter() { ?>
<p id="customer_count" align="center" style="padding:10px; background:#F9F0E5; border:solid 2px rgb(82,46,31); border-radius:50px; font-size: 16px; font-weight: 500; color:rgb(82,46,31)"><span style="font-style:20px; font-weight: 900; padding-right:5px;"></span> utenti che lo osservano</p>
<?php }


class VisitorCounter {
		
    private static $instance;

    /**
     * @var string $file Relative path to the tracking file.
     */

    public $file = 'users/';

    /**
     * @var int $expires Automatically expire vistor after X amount of seconds.
     */
	
    public $expires = 3;

    /**
     * @return $this
     */
    public static function init() {
        if ( !static::$instance ) {
            static::$instance = new static( ...func_get_args() );
        }

        return static::$instance;
    }

    /**
     * Get the full database file path and create file if not exists.
     *
     * @return string|false
     */
    public function getPath() {

        $path = get_stylesheet_directory() . '/' . ltrim( $this->file, '/' ) . get_the_ID() . '.db';

        $exists = file_exists( $path );

        if ( !$exists ) {
            wp_mkdir_p( dirname( $path ) );
            $exists = touch( $path );
        }
        return $exists ? $path : true;
    }
    /**
     * Read the contents of the visitors database file as a JSON.
     *
     * @return array
     */
    public function getData() {
        if ( $path = $this->getPath() ) {
            if ( $contents = file_get_contents( $path ) ) {
                if ( $json = @json_decode( $contents, true ) ) {
                    return $this->cleanData( $json );
                }
            }
        }

        return [];
    }

    /**
     * Clean the visitor data by removing expired entries.
     *
     * @param array $input
     * @return array
     */
    private function cleanData( array $input ) {
        $now = time();

        foreach ( $input as $ip => $timestamp ) {
            if ( $timestamp + $this->expires < $now ) {
                unset( $input[ $ip ] );
            }
        }

        return $input;
    }

    /**
     * Add visitor count data.
     *
     * @param string $ip
     * @param int $timestamp
     * @return array
     */
    public function logUser() {
		
        // Get current data.
        $data = $this->getData();

        // Add new entry.
        if ( $ip = $this->getUserIp() ) {
            $data[ $ip ] = time();
        }

        // Clean data before saving.
        $data = $this->cleanData( $data );

        // Encode and save data.
        file_put_contents( $this->getPath(), wp_json_encode( $data ) );

        // Return data.
        return $data;
	}

    /**
     * Get the current users IP address.
     *
     * @return string|null
     */
    public function getUserIp() {

        // In order of preference, with the best ones for this purpose first.
        $address_headers = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        $client_ip = null;

        foreach ( $address_headers as $header ) {
            if ( !empty( $_SERVER[ $header ] ) ) {
                /*
                 * HTTP_X_FORWARDED_FOR can contain a chain of comma-separated
                 * addresses. The first one is the original client. It can't be
                 * trusted for authenticity, but we don't need to for this purpose.
                 */
                $address_chain = explode( ',', $_SERVER[ $header ] );
                $client_ip = trim( $address_chain[ 0 ] );
                break;
            }
        }

        return $client_ip;
    }
}

/**
 * Helper function for visitor counter class.
 *
 * @return VisitorCounter
 */
function visitor_counter() {
    return VisitorCounter::init();
}


/**
 * Register an ajax request handler.
 */
add_action( 'wp_ajax_active_visitor', 'handle_visitor_activity' );
add_action( 'wp_ajax_nopriv_active_visitor', 'handle_visitor_activity' );
function handle_visitor_activity() {
	$post_id = $_POST["post_id"];
    $controller = visitor_counter();
    $controller->logUser();
    wp_send_json_success( count( $controller->getData() ) );
}


/**
 * Load our javascript file on the frontend data.
 */
add_action( 'wp_enqueue_scripts', function() {
if ( is_product()) {
    // Load the counter javascript file after `jquery` in the footer.
    wp_enqueue_script( 'visitor-counter', get_stylesheet_directory_uri() . '/js/visitor-counter.js', [ 'jquery' ], '1.0.0', true );

    // Load php data that can be accessed in javascript.
    wp_localize_script( 'visitor-counter', 'VisitorCounterVars', [
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'ajaxAction' => 'active_visitor'
    ] );
}
} );
