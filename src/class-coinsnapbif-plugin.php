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
        CoreAjaxHandlers::handle_btcpay_url( CoinsnapBIF_Util_Provider_Factory::make_instance() );
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

if(!function_exists('coinsnap_settings_update')){
    function coinsnap_settings_update($option,$data){
        
        $form_data = get_option($option, []);
        
        foreach($data as $key => $value){
            $form_data[$key] = $value;
        }
        
        update_option($option,$form_data);
    }
}

// Adding template redirect handling for coinsnapBIF-settings-callback.
add_action( 'template_redirect', function(){
    
    global $wp_query;
            
    // Only continue on a coinsnapBIF-settings-callback request.    
    if (!isset( $wp_query->query_vars['coinsnapBIF-settings-callback'])) {
        return;
    }
    
    if(!isset($wp_query->query_vars['coinsnapBIF-nonce']) || !wp_verify_nonce($wp_query->query_vars['coinsnapBIF-nonce'],'coinsnap-bitcoin-invoice-form-btcpay-nonce')){
        return;
    }

    $CoinsnapBTCPaySettingsUrl = admin_url('/admin.php?page=coinsnapbif-settings');
    
    $rawData = file_get_contents('php://input');
    $form_data = get_option(AdminSettings::OPTION_KEY, []);

    $btcpay_server_url = $form_data['btcpay_host'];
    $btcpay_api_key  = filter_input(INPUT_POST,'apiKey',FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    $request_url = $btcpay_server_url.'/api/v1/stores';
    $args = array(
                'method'  => 'GET',
                'headers' => array(
                    'Authorization' => 'token ' . $btcpay_api_key,
                    'Content-Type'  => 'application/json',
                ),
                'timeout' => 20
    );

    $res = wp_remote_request( $request_url, $args );
    $code = wp_remote_retrieve_response_code( $res );
    $getstores = json_decode( wp_remote_retrieve_body( $res ), true );
            
            if($code >= 200 && $code < 300 && is_array($getstores)){
                if (count($getstores) < 1) {
                    //$messageAbort = __('Error on verifiying redirected API Key with stored BTCPay Server url. Aborting API wizard. Please try again or continue with manual setup.', 'coinsnap-bitcoin-invoice-form');
                    wp_safe_redirect($CoinsnapBTCPaySettingsUrl);
                }
            }
                        
            // Data does get submitted with url-encoded payload, so parse $_POST here.
            if (!empty($_POST)) {
                $data['apiKey'] = filter_input(INPUT_POST,'apiKey',FILTER_SANITIZE_FULL_SPECIAL_CHARS) ?? null;
                if(isset($_POST['permissions'])){
                    $permissions = array_map('sanitize_text_field', wp_unslash($_POST['permissions']));
                    if(is_array($permissions)){
                        foreach ($permissions as $key => $value) {
                            $data['permissions'][$key] = sanitize_text_field($permissions[$key] ?? null);
                        }
                    }
                }
            }
    
            if (isset($data['apiKey']) && isset($data['permissions'])) {

                $REQUIRED_PERMISSIONS = [
                    'btcpay.store.canviewinvoices',
                    'btcpay.store.cancreateinvoice',
                    'btcpay.store.canviewstoresettings',
                    'btcpay.store.canmodifyinvoices'
                ];
                $OPTIONAL_PERMISSIONS = [
                    'btcpay.store.cancreatenonapprovedpullpayments',
                    'btcpay.store.webhooks.canmodifywebhooks',
                ];
                
                $btcpay_server_permissions = $data['permissions'];
                
                $permissions = array_reduce($btcpay_server_permissions, static function (array $carry, string $permission) {
			return array_merge($carry, [explode(':', $permission)[0]]);
		}, []);

		// Remove optional permissions so that only required ones are left.
		$permissions = array_diff($permissions, $OPTIONAL_PERMISSIONS);

		$hasRequiredPermissions = (empty(array_merge(array_diff($REQUIRED_PERMISSIONS, $permissions), array_diff($permissions, $REQUIRED_PERMISSIONS))))? true : false;
                
                $hasSingleStore = true;
                $storeId = null;
		foreach ($btcpay_server_permissions as $perms) {
                    if (2 !== count($exploded = explode(':', $perms))) { return false; }
                    if (null === ($receivedStoreId = $exploded[1])) { $hasSingleStore = false; }
                    if ($storeId === $receivedStoreId) { continue; }
                    if (null === $storeId) { $storeId = $receivedStoreId; continue; }
                    $hasSingleStore = false;
		}
                
                if ($hasSingleStore && $hasRequiredPermissions) {

                    coinsnap_settings_update(
                        AdminSettings::OPTION_KEY,[
                        'btcpay_api_key' => $data['apiKey'],
                        'btcpay_store_id' => explode(':', $btcpay_server_permissions[0])[1],
                        'payment_provider' => 'btcpay'
                        ]);
                    
                    wp_safe_redirect($CoinsnapBTCPaySettingsUrl);
                    exit();
                }
                else {
                    wp_safe_redirect($CoinsnapBTCPaySettingsUrl);
                    exit();
                }
            }

    wp_safe_redirect($CoinsnapBTCPaySettingsUrl);
    exit();
});
