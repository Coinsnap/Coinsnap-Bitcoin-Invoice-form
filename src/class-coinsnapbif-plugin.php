<?php
/**
 * Plugin core bootstrap.
 *
 * @package bitcoin-invoice-form
 */

declare(strict_types=1);

namespace CoinsnapBIF;

use CoinsnapBIF\CPT\CoinsnapBIF_CPT_Invoice_Form_Post_Type as InvoiceFormPostType;
use CoinsnapBIF\Shortcode\CoinsnapBIF_Shortcode_Invoice_Form_Shortcode as InvoiceFormShortcode;
use CoinsnapBIF\Admin\CoinsnapBIF_Admin_Settings as AdminSettings;
use CoinsnapBIF\Admin\CoinsnapBIF_Admin_Transactions_Page as TransactionsPage;
use CoinsnapCore\Admin\AjaxHandlers as CoreAjaxHandlers;
use CoinsnapBIF\Admin\CoinsnapBIF_Admin_Logs_Page as LogsPage;
use CoinsnapBIF\Rest\CoinsnapBIF_Rest_Routes as RestRoutes;
use CoinsnapBIF\Util\CoinsnapBIF_Util_Provider_Factory;
use CoinsnapBIF\Util\CoinsnapBIF_Logger;
use CoinsnapBIF\CoinsnapBIF_Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin bootstrap.
 */
class CoinsnapBIF_Plugin {
    /**
     * Singleton instance.
     *
     * @var CoinsnapBIF_Plugin|null
     */
    private static $instance;

    /**
     * Get singleton instance.
     *
     * @return CoinsnapBIF_Plugin
     */
    public static function instance(): CoinsnapBIF_Plugin {
        if ( ! self::$instance ) {
            self::$instance = new self();
	}
        return self::$instance;
    }
    
    public $post_id = 0;

    /**
     * Register hooks on load.
     */
    public function boot(): void {
        // Initialize logger first.
	CoinsnapBIF_Logger::init();

	add_action( 'init', array( InvoiceFormPostType::class, 'register' ) );
	add_action( 'init', array( InvoiceFormShortcode::class, 'register' ) );
        
        if (is_admin()) {
            add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
            add_action('admin_notices', array($this, 'coinsnapbif_notice'));
            add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin' ) );
            add_action('wp_ajax_coinsnapbif_btcpay_apiurl_handler', [$this, 'btcpayApiUrlHandler']);
            add_action('wp_ajax_coinsnapbif_connection_handler', [$this, 'coinsnapConnectionHandler']);
            // Register webhook only when settings are saved, not on every page load.
            add_action( 'update_option_' . AdminSettings::OPTION_KEY, array( $this, 'maybe_register_webhook_on_save' ), 10, 2 );
        }
	
	AdminSettings::register();
	TransactionsPage::register();
	LogsPage::register();
	add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend' ) );
	
    }    
    
    public function coinsnapbif_notice(){
        
        $this->post_id = (filter_input(INPUT_GET,'post',FILTER_SANITIZE_FULL_SPECIAL_CHARS ))? filter_input(INPUT_GET,'post',FILTER_SANITIZE_FULL_SPECIAL_CHARS ) : 0;
        $post_type = (filter_input(INPUT_GET,'post_type',FILTER_SANITIZE_FULL_SPECIAL_CHARS ))? filter_input(INPUT_GET,'post_type',FILTER_SANITIZE_FULL_SPECIAL_CHARS ) : 
            ( ($this->post_id > 0)? get_post_type($this->post_id) : '');
        $page = (filter_input(INPUT_GET,'page',FILTER_SANITIZE_FULL_SPECIAL_CHARS ))? filter_input(INPUT_GET,'page',FILTER_SANITIZE_FULL_SPECIAL_CHARS ) : '';
        
        if(stripos($page,'coinsnapbif') !== false || stripos($post_type,'coinsnapbif') !== false){
        
            $coinsnap_url = $this->getApiUrl();
            $coinsnap_api_key = $this->getApiKey();
            $coinsnap_store_id = $this->getStoreId();
                
            if(!isset($coinsnap_url) || empty($coinsnap_url)){
                    echo '<div class="notice notice-error"><p>';
                    esc_html_e('Bitcoin Invoice Form: Server URL is not set', 'coinsnap-bitcoin-invoice-form');
                    echo '</p></div>';
            }

            if(!isset($coinsnap_store_id) || empty($coinsnap_store_id)){
                    echo '<div class="notice notice-error"><p>';
                    esc_html_e('Bitcoin Invoice Form: Store ID is not set', 'coinsnap-bitcoin-invoice-form');
                    echo '</p></div>';
            }

            if(!isset($coinsnap_api_key) || empty($coinsnap_api_key)){
                    echo '<div class="notice notice-error"><p>';
                    esc_html_e('Bitcoin Invoice Form: API Key is not set', 'coinsnap-bitcoin-invoice-form');
                    echo '</p></div>';
            }

            if(!empty($coinsnap_url) && !empty($coinsnap_api_key) && !empty($coinsnap_store_id)){
                $webhookOption = get_option( AdminSettings::WEBHOOK_KEY );
                $provider      = $this->getPaymentProvider();
                if ( ! is_array( $webhookOption ) || empty( $webhookOption[ $provider ] ) ) {
                    echo '<div class="notice notice-warning"><p>';
                    esc_html_e('Bitcoin Invoice Form: Webhook is not registered. Save your settings to register it automatically.', 'coinsnap-bitcoin-invoice-form');
                    echo '</p></div>';
                }
            }
        }
    }

    /**
     * Register webhook when plugin settings are saved.
     * Fires only via update_option hook — not on every page load.
     *
     * @param mixed $old_value Previous option value.
     * @param mixed $new_value New option value.
     * @return void
     */
    public function maybe_register_webhook_on_save( $old_value, $new_value ): void {
        if ( ! is_array( $new_value ) ) {
            return;
        }

        $provider_name = $new_value['payment_provider'] ?? 'coinsnap';

        if ( 'btcpay' === $provider_name ) {
            $has_creds = ! empty( $new_value['btcpay_api_key'] )
                && ! empty( $new_value['btcpay_store_id'] )
                && ! empty( $new_value['btcpay_host'] );
        } else {
            $has_creds = ! empty( $new_value['coinsnap_api_key'] )
                && ! empty( $new_value['coinsnap_store_id'] );
        }

        if ( ! $has_creds ) {
            return;
        }

        try {
            $payment_provider = CoinsnapBIF_Util_Provider_Factory::create();

            if ( $payment_provider->check_webhook() ) {
                return;
            }

            $result = $payment_provider->register_webhook();

            if ( ! isset( $result['error'] ) && isset( $result['result'] ) ) {
                $stored_webhook                   = get_option( AdminSettings::WEBHOOK_KEY, array() );
                $stored_webhook[ $provider_name ] = array(
                    'id'     => $result['result']['id'],
                    'secret' => $result['result']['secret'],
                    'url'    => $result['result']['url'],
                );
                update_option( AdminSettings::WEBHOOK_KEY, $stored_webhook );
            }
        } catch ( \Exception $e ) {
            // Silently fail — user can test connection manually via the settings page button.
        }
    }
        
    public function btcpayApiUrlHandler(): void {
        $nonce = filter_input( INPUT_POST, 'apiNonce', FILTER_SANITIZE_FULL_SPECIAL_CHARS );
        if ( ! wp_verify_nonce( $nonce, 'coinsnap-ajax-nonce' ) ) {
            wp_die( 'Unauthorized!', '', array( 'response' => 401 ) );
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Insufficient permissions.' );
        }

        $host = filter_var(
            filter_input( INPUT_POST, 'host', FILTER_SANITIZE_FULL_SPECIAL_CHARS ),
            FILTER_VALIDATE_URL
        );

        if ( false === $host
            || ( substr( $host, 0, 7 ) !== 'http://'
                && substr( $host, 0, 8 ) !== 'https://' ) ) {
            wp_send_json_error( 'Error validating BTCPay Server URL.' );
        }

        $instance = CoinsnapBIF_Util_Provider_Factory::make_instance();
        $settings = AdminSettings::get_settings();

        // Build redirect URL. Priority: ngrok > HTTPS > popup (HTTP local dev).
        // The popup flow opens BTCPay in a small popup window; after the user
        // approves (and clicks "Send anyway" inside the popup), our callback sends
        // the key back to the opener via postMessage and closes the popup.
        $popup_mode = false;
        if ( ! empty( $settings['ngrok_url'] ) ) {
            $redirect_url = rtrim( $settings['ngrok_url'], '/' ) . '/?' . $instance->get( 'btcpay_callback_endpoint' );
        } elseif ( is_ssl() ) {
            $redirect_url = home_url( '/?' . $instance->get( 'btcpay_callback_endpoint' ) );
        } else {
            // HTTP local dev without ngrok: use popup + postMessage flow.
            $redirect_url = home_url( '/?' . $instance->get( 'btcpay_callback_endpoint' ) . '&popup=1' );
            $popup_mode   = true;
        }

        $permissions = array_merge(
            \CoinsnapCore\Auth\BTCPayAuthorizer::REQUIRED_PERMISSIONS,
            \CoinsnapCore\Auth\BTCPayAuthorizer::OPTIONAL_PERMISSIONS
        );

        try {
            $url = \CoinsnapCore\Auth\BTCPayAuthorizer::get_authorize_url(
                $host,
                $permissions,
                $instance->get( 'btcpay_app_name', 'Coinsnap' ),
                true,
                true,
                $redirect_url,
                null
            );

            \CoinsnapCore\Auth\BTCPayAuthorizer::update_settings(
                $instance->option_key(),
                array( 'btcpay_host' => $host )
            );

            wp_send_json_success( array(
                'url'   => $url,
                'popup' => $popup_mode,
            ) );
        } catch ( \Throwable $e ) {
            wp_send_json_error( 'Error processing request.' );
        }
    }
        
    public function coinsnapConnectionHandler(): void {
        CoreAjaxHandlers::handle_connection_check( CoinsnapBIF_Util_Provider_Factory::make_instance() );
    }
        
    public function sendJsonResponse(array $response): void {
        echo wp_json_encode($response);
        exit();
    }
        
    private function getPaymentProvider() {
        $coinsnapbif_options = get_option('bif_settings', []);
        //$this->post_id = (filter_input(INPUT_GET,'post',FILTER_SANITIZE_FULL_SPECIAL_CHARS ))? filter_input(INPUT_GET,'post',FILTER_SANITIZE_FULL_SPECIAL_CHARS ) : 0;
        
        if($this->post_id > 0){
            $payment  = get_post_meta( $this->post_id, '_coinsnapbif_payment', true );
            $override = (is_array( $payment ) && ! empty( $payment['provider_override'] )) ? $payment['provider_override'] : '';
        }
		
	$provider = (isset($override) && !empty($override)) ? $override : (($coinsnapbif_options['payment_provider'] === 'btcpay')? 'btcpay' : 'coinsnap');
        return $provider;
    }

    private function getApiKey() {
        $coinsnapbif_options = get_option('bif_settings', []);
        return ($this->getPaymentProvider() === 'btcpay')? $coinsnapbif_options['btcpay_api_key']  : $coinsnapbif_options['coinsnap_api_key'];
    }
    
    private function getStoreId() {
	$coinsnapbif_options = get_option('bif_settings', []);
        return ($this->getPaymentProvider() === 'btcpay')? $coinsnapbif_options['btcpay_store_id'] : $coinsnapbif_options['coinsnap_store_id'];
    }
    
    public function getApiUrl() {
        $coinsnapbif_options = get_option('bif_settings', []);
        return ($this->getPaymentProvider() === 'btcpay')? $coinsnapbif_options['btcpay_host'] : COINSNAP_SERVER_URL;
    }

    /**
     * Register admin menus and submenus.
     */
    public function register_admin_menu(): void {
		add_menu_page(
			__( 'Bitcoin Invoice Forms', 'coinsnap-bitcoin-invoice-form' ),
			__( 'Bitcoin Invoice Forms', 'coinsnap-bitcoin-invoice-form' ),
			'manage_options',
			'coinsnapbif-transactions',
			array( TransactionsPage::class, 'render_page' ),
			'dashicons-money-alt',
			56
		);

		// Add explicit submenu for transactions (same as main page).
		add_submenu_page(
			'coinsnapbif-transactions',
			__( 'Transactions', 'coinsnap-bitcoin-invoice-form' ),
			__( 'Transactions', 'coinsnap-bitcoin-invoice-form' ),
			'manage_options',
			'coinsnapbif-transactions',
			array( TransactionsPage::class, 'render_page' )
		);

		add_submenu_page(
			'coinsnapbif-transactions',
			__( 'Settings', 'coinsnap-bitcoin-invoice-form' ),
			__( 'Settings', 'coinsnap-bitcoin-invoice-form' ),
			'manage_options',
			'coinsnapbif-settings',
			array( AdminSettings::class, 'render_page' )
		);

		add_submenu_page(
			'coinsnapbif-transactions',
			__( 'Logs', 'coinsnap-bitcoin-invoice-form' ),
			__( 'Logs', 'coinsnap-bitcoin-invoice-form' ),
			'manage_options',
			'coinsnapbif-logs',
			array( LogsPage::class, 'render_page' )
		);
	}

	/**
	 * Register REST API routes.
	 */
	public function register_rest_routes(): void {
		RestRoutes::register();
	}

    /**
     * Enqueue frontend assets.
     */
    public function enqueue_frontend(): void {
        global $post;
    
        if ( is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'coinsnap_invoice_form') ) {
                wp_register_style( 'coinsnapbif-frontend', COINSNAPBIF_PLUGIN_URL . 'assets/css/frontend.css', array(), COINSNAPBIF_VERSION );
		wp_register_script( 'coinsnapbif-frontend', COINSNAPBIF_PLUGIN_URL . 'assets/js/frontend.js', array( 'jquery' ), COINSNAPBIF_VERSION, true );

		wp_localize_script(
			'coinsnapbif-frontend',
			'CoinsnapBIF',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'restUrl' => esc_url_raw( get_rest_url( null, CoinsnapBIF_Constants::REST_NAMESPACE . '/' ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
			)
		);

		wp_enqueue_style( 'coinsnapbif-frontend' );
		wp_enqueue_script( 'coinsnapbif-frontend' );
	}
    }

    /**
     * Enqueue admin assets.
     */
    public function enqueue_admin( string $hook ): void {
            
        $this->post_id = (filter_input(INPUT_GET,'post',FILTER_SANITIZE_FULL_SPECIAL_CHARS ))? filter_input(INPUT_GET,'post',FILTER_SANITIZE_FULL_SPECIAL_CHARS ) : 0;
        $post_type = (filter_input(INPUT_GET,'post_type',FILTER_SANITIZE_FULL_SPECIAL_CHARS ))? filter_input(INPUT_GET,'post_type',FILTER_SANITIZE_FULL_SPECIAL_CHARS ) : 
            (($this->post_id > 0)? get_post_type($this->post_id) : '');
        $page = (filter_input(INPUT_GET,'page',FILTER_SANITIZE_FULL_SPECIAL_CHARS ))? filter_input(INPUT_GET,'page',FILTER_SANITIZE_FULL_SPECIAL_CHARS ) : '';
        
        if(stripos($page,'coinsnapbif') !== false || stripos($post_type,'coinsnapbif') !== false){

            // Always load the plugin's own admin CSS (transactions, logs, CPT styles).
            wp_register_style( 'coinsnapbif-admin', COINSNAPBIF_PLUGIN_URL . 'assets/css/admin.css', array(), COINSNAPBIF_VERSION );
            wp_enqueue_style( 'coinsnapbif-admin' );

            if ( 'coinsnapbif-settings' === $page && defined( 'COINSNAP_CORE_PLUGIN_URL' ) ) {
                // Settings page: use the vendor's csc-* design system.
                $core_version = defined( 'COINSNAP_CORE_VERSION' ) ? COINSNAP_CORE_VERSION : '1.0.0';

                wp_register_style( 'coinsnap-core-admin', COINSNAP_CORE_PLUGIN_URL . 'assets/css/admin.css', array(), $core_version );
                wp_register_script( 'coinsnap-core-admin', COINSNAP_CORE_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), $core_version, true );

                wp_localize_script( 'coinsnap-core-admin', 'CoinsnapCoreAdmin', array(
                    'option_key'        => AdminSettings::OPTION_KEY,
                    'ajax_url'          => admin_url( 'admin-ajax.php' ),
                    'nonce'             => wp_create_nonce( 'coinsnap-ajax-nonce' ),
                    'connection_action' => 'coinsnapbif_connection_handler',
                    'btcpay_action'     => 'coinsnapbif_btcpay_apiurl_handler',
                    'webhook_action'    => '',
                    'post'              => $this->post_id,
                ) );

                wp_enqueue_style( 'coinsnap-core-admin' );
                wp_enqueue_script( 'coinsnap-core-admin' );
            } else {
                // All other plugin pages: use the plugin's own admin JS.
                wp_register_script( 'coinsnapbif-admin', COINSNAPBIF_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), COINSNAPBIF_VERSION, true );

                wp_localize_script(
                    'coinsnapbif-admin',
                    'CoinsnapBIFRestUrl',
                    array(
                        'restUrl' => esc_url_raw( get_rest_url( null, CoinsnapBIF_Constants::REST_NAMESPACE . '/' ) ),
                        'nonce'   => wp_create_nonce( 'wp_rest' ),
                    )
                );

                wp_localize_script( 'coinsnapbif-admin', 'coinsnapbif_ajax', array(
                    'ajax_url' => admin_url( 'admin-ajax.php' ),
                    'nonce'    => wp_create_nonce( 'coinsnap-ajax-nonce' ),
                    'post'     => $this->post_id,
                ) );

                wp_enqueue_script( 'coinsnapbif-admin' );
            }
        }
    }
}

add_action('init', function() {
    // Setting up and handling custom endpoint for api key redirect from BTCPay Server.
    add_rewrite_endpoint('coinsnapBIF-settings-callback', EP_ROOT);
});

// To be able to use the endpoint without appended url segments we need to do this.
add_filter('request', function($vars){
    if (isset($vars['coinsnapBIF-settings-callback'])) {
        $vars['coinsnapBIF-settings-callback'] = true;
        $vars['coinsnapBIF-nonce'] = wp_create_nonce('coinsnap-bitcoin-invoice-form-btcpay-nonce');
    }
    return $vars;
});

if ( ! function_exists( 'coinsnap_settings_update' ) ) {
	function coinsnap_settings_update( $option, $data ) {
		$form_data = get_option( $option, array() );
		foreach ( $data as $key => $value ) {
			$form_data[ $key ] = $value;
		}
		update_option( $option, $form_data );
	}
}

/**
 * Task 2: Placeholder function for saving the BTCPay API key.
 * Replace the body with your framework-specific storage logic.
 *
 * @param string $api_key Sanitized BTCPay API key.
 */
if ( ! function_exists( 'save_btcpay_api_key' ) ) {
	function save_btcpay_api_key( string $api_key ): void {
		// TODO: Insert your custom storage logic here, e.g.:
		// update_option( 'bif_btcpay_api_key', $api_key );
	}
}

/**
 * Task 3: Fetch the first available store from a BTCPay Server instance via cURL.
 *
 * Uses the Greenfield API GET /api/v1/stores endpoint with Bearer authorization.
 * Returns the id and name of the first store, or null on failure.
 *
 * @param string $btcpay_host BTCPay Server base URL (e.g. https://btcpay.example.com).
 * @param string $api_key     API key for Authorization header.
 * @return array{id: string, name: string}|null First store data, or null on failure.
 */
if ( ! function_exists( 'coinsnapbif_fetch_btcpay_store_id' ) ) {
	function coinsnapbif_fetch_btcpay_store_id( string $btcpay_host, string $api_key ): ?array {
		$url = rtrim( $btcpay_host, '/' ) . '/api/v1/stores';

		$response = wp_remote_get(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'token ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'timeout' => 20,
			)
		);

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$status = wp_remote_retrieve_response_code( $response );
		$body   = wp_remote_retrieve_body( $response );

		if ( $status < 200 || $status >= 300 ) {
			return null;
		}

		$stores = json_decode( $body, true );

		if ( ! is_array( $stores ) || count( $stores ) < 1 ) {
			return null;
		}

		return array(
			'id'   => $stores[0]['id']   ?? '',
			'name' => $stores[0]['name'] ?? '',
		);
	}
}

// Adding template redirect handling for coinsnapBIF-settings-callback.
add_action( 'template_redirect', function () {

	global $wp_query;

	// Only continue on a coinsnapBIF-settings-callback request.
	if ( ! isset( $wp_query->query_vars['coinsnapBIF-settings-callback'] ) ) {
		return;
	}

	if ( ! isset( $wp_query->query_vars['coinsnapBIF-nonce'] )
		|| ! wp_verify_nonce( $wp_query->query_vars['coinsnapBIF-nonce'], 'coinsnap-bitcoin-invoice-form-btcpay-nonce' ) ) {
		return;
	}

	$settings_url = admin_url( '/admin.php?page=coinsnapbif-settings' );
	$form_data    = get_option( AdminSettings::OPTION_KEY, array() );
	$btcpay_host  = $form_data['btcpay_host'] ?? '';

	// Task 2: BTCPay POSTs the API key back via a form submission to the redirect URL.
	$btcpay_api_key = filter_input( INPUT_POST, 'apiKey', FILTER_SANITIZE_FULL_SPECIAL_CHARS );

	if ( empty( $btcpay_api_key ) || empty( $btcpay_host ) ) {
		wp_safe_redirect( $settings_url );
		exit();
	}

	// Task 2: Read and sanitize permissions array from POST body.
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified above via query_vars.
	$raw_permissions = isset( $_POST['permissions'] ) && is_array( $_POST['permissions'] )
		? array_map( 'sanitize_text_field', wp_unslash( $_POST['permissions'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Missing
		: array();

	if ( empty( $raw_permissions ) ) {
		wp_safe_redirect( $settings_url );
		exit();
	}

	$btcpay_server_permissions = $raw_permissions;

	// Task 2: Check whether the user granted the btcpay.store.canmodifyofferings permission
	// (required for Crowdfund app types; informational for invoice forms).
	$flat_permissions = array_map(
		static function ( string $p ) {
			return explode( ':', $p )[0];
		},
		$btcpay_server_permissions
	);
	$has_modify_offerings = in_array( 'btcpay.store.canmodifyofferings', $flat_permissions, true );

	// Required and optional permissions for the invoice form plugin.
	$required_permissions = array(
		'btcpay.store.canviewinvoices',
		'btcpay.store.cancreateinvoice',
		'btcpay.store.canviewstoresettings',
		'btcpay.store.canmodifyinvoices',
	);
	$optional_permissions = array(
		'btcpay.store.cancreatenonapprovedpullpayments',
		'btcpay.store.webhooks.canmodifywebhooks',
		'btcpay.store.canmodifyofferings',
	);

	// Reduce to base permission names (strip :storeId suffix) and remove optional ones.
	$base_permissions = array_reduce(
		$btcpay_server_permissions,
		static function ( array $carry, string $permission ) {
			return array_merge( $carry, array( explode( ':', $permission )[0] ) );
		},
		array()
	);
	$base_permissions = array_diff( $base_permissions, $optional_permissions );

	$has_required_permissions = empty(
		array_merge(
			array_diff( $required_permissions, $base_permissions ),
			array_diff( $base_permissions, $required_permissions )
		)
	);

	// Determine whether all permissions belong to a single store.
	$has_single_store = true;
	$store_id         = null;
	foreach ( $btcpay_server_permissions as $perms ) {
		$exploded = explode( ':', $perms );
		if ( 2 !== count( $exploded ) ) {
			wp_safe_redirect( $settings_url );
			exit();
		}
		$received_store_id = $exploded[1] ?? null;
		if ( null === $received_store_id ) {
			$has_single_store = false;
		}
		if ( $store_id === $received_store_id ) {
			continue;
		}
		if ( null === $store_id ) {
			$store_id = $received_store_id;
			continue;
		}
		$has_single_store = false;
	}

	if ( $has_single_store && $has_required_permissions ) {
		// Task 2: Sanitize API key and pass to placeholder save function.
		$clean_api_key = sanitize_text_field( $btcpay_api_key );
		save_btcpay_api_key( $clean_api_key );

		$store_id_from_perm = explode( ':', $btcpay_server_permissions[0] )[1] ?? '';

		// Task 3: If the store ID was not embedded in the permissions, fetch it from the API.
		if ( empty( $store_id_from_perm ) ) {
			$fetched = coinsnapbif_fetch_btcpay_store_id( $btcpay_host, $clean_api_key );
			if ( $fetched && ! empty( $fetched['id'] ) ) {
				$store_id_from_perm = $fetched['id'];
			}
		}

		coinsnap_settings_update(
			AdminSettings::OPTION_KEY,
			array(
				'btcpay_api_key'   => $clean_api_key,
				'btcpay_store_id'  => $store_id_from_perm,
				'payment_provider' => 'btcpay',
			)
		);

		// Popup mode: send credentials back to opener via postMessage, then close.
		// Security: postMessage target is restricted to home_url() — same origin.
		if ( '1' === filter_input( INPUT_GET, 'popup', FILTER_SANITIZE_FULL_SPECIAL_CHARS ) ) {
			// wp_json_encode() is the correct escaping function for embedding data
			// in a <script> block; phpcs:ignore is required because PHPCS does not
			// recognise it as an escaping function for OutputNotEscaped.
			$js_data     = wp_json_encode( array(
				'type'    => 'coinsnapbif_btcpay_auth',
				'apiKey'  => $clean_api_key,
				'storeId' => $store_id_from_perm,
			) );
			$js_origin   = wp_json_encode( home_url() );
			$js_fallback = wp_json_encode( $settings_url );
			header( 'Content-Type: text/html; charset=utf-8' );
			echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Authorizing...</title>';
			echo '<script>(function(){';
			echo 'var d=' . $js_data . ';'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo 'if(window.opener&&!window.opener.closed){';
			echo 'window.opener.postMessage(d,' . $js_origin . ');'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo 'window.close();';
			echo '}else{window.location.href=' . $js_fallback . ';}'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			echo '})();</script></head><body></body></html>';
			exit();
		}
	}

	wp_safe_redirect( $settings_url );
	exit();
} );
