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
use CoinsnapBIF\Admin\CoinsnapBIF_Admin_Logs_Page as LogsPage;
use CoinsnapBIF\Rest\CoinsnapBIF_Rest_Routes as RestRoutes;
use CoinsnapBIF\Util\CoinsnapBIF_Util_Provider_Factory;
use CoinsnapBIF\Util\CoinsnapBIF_Logger;
use CoinsnapBIF\CoinsnapBIF_Constants;
use CoinsnapBIF\Services\CoinsnapBIF_Security;

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
            add_action('admin_post_coinsnapbif_register_webhook', [$this, 'registerWebhookAction']);
        }
        // Saving credentials (options.php checks capability and nonce) registers the webhook.
        add_action('add_option_' . AdminSettings::OPTION_KEY, [$this, 'credentialsAdded'], 10, 2);
        add_action('update_option_' . AdminSettings::OPTION_KEY, [$this, 'credentialsChanged'], 10, 2);
	
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
            $coinsnap_provider = ($this->getPaymentProvider() === 'btcpay')? 'BTCPay server' : 'Coinsnap';
                
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
                
            // No API call and no webhook change on a page view: the connection check runs
            // through the AJAX handler, the webhook is (re)registered only by an admin action.
            if(current_user_can('manage_options') && !empty($coinsnap_url) && !empty($coinsnap_api_key) && !empty($coinsnap_store_id)
                && !CoinsnapBIF_Security::is_connected($this->getPaymentProvider())){
                $url = wp_nonce_url(admin_url('admin-post.php?action=coinsnapbif_register_webhook'), 'coinsnapbif_register_webhook');
                printf('<div class="notice notice-warning"><p>%s <a class="button" href="%s">%s</a></p></div>',
                    esc_html__('Bitcoin Invoice Form: payments are not confirmed by webhook until the webhook is connected.', 'coinsnap-bitcoin-invoice-form'),
                    esc_url($url),
                    esc_html__('Connect webhook', 'coinsnap-bitcoin-invoice-form'));
            }
            $webhook_result = filter_input(INPUT_GET,'coinsnapbif_webhook',FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            if($webhook_result === 'ok' || $webhook_result === 'failed'){
                echo '<div class="notice notice-' . ($webhook_result === 'ok' ? 'success' : 'error') . '"><p>';
                echo ($webhook_result === 'ok')
                    ? esc_html__('Bitcoin Invoice Form: webhook connected with a new secret.', 'coinsnap-bitcoin-invoice-form')
                    : esc_html__('Bitcoin Invoice Form: unable to connect the webhook. Check the connection settings.', 'coinsnap-bitcoin-invoice-form');
                echo '</p></div>';
            }
        }
    }

    /** Admin action: capability and nonce before any API call (6.5). */
    public function registerWebhookAction(){
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Forbidden', 'coinsnap-bitcoin-invoice-form'), '', ['response' => 403]);
        }
        check_admin_referer('coinsnapbif_register_webhook');
        $ok = CoinsnapBIF_Security::register_all_webhooks();
        wp_safe_redirect(add_query_arg('coinsnapbif_webhook', $ok ? 'ok' : 'failed', admin_url('admin.php?page=coinsnapbif-settings')));
        exit;
    }

    public function credentialsAdded($option, $value){
        $this->credentialsChanged([], $value);
    }

    /** Settings saved through options.php: register the webhook if connection settings changed. */
    public function credentialsChanged($old, $new){
        if (!current_user_can('manage_options')) {
            return;
        }
        $keys = [
            'coinsnap' => ['coinsnap_store_id', 'coinsnap_api_key'],
            'btcpay'   => ['btcpay_host', 'btcpay_store_id', 'btcpay_api_key'],
        ];
        $old = (array) $old;
        $new = (array) $new;
        foreach ($keys as $provider => $fields) {
            foreach ($fields as $k) {
                if ((string) ($old[$k] ?? '') !== (string) ($new[$k] ?? '')) {
                    CoinsnapBIF_Security::register_webhook($provider);
                    break;
                }
            }
        }
    }
        
    public function btcpayApiUrlHandler(){
            if ( !current_user_can( 'manage_options' ) ) {
                wp_die('Unauthorized!', '', ['response' => 403]);
            }
            $_nonce = filter_input(INPUT_POST,'apiNonce',FILTER_SANITIZE_FULL_SPECIAL_CHARS);
            if ( !wp_verify_nonce( $_nonce, 'coinsnap-ajax-nonce' ) ) {
                wp_die('Unauthorized!', '', ['response' => 401]);
            }

            $host = filter_var(trim((string) filter_input(INPUT_POST,'host',FILTER_UNSAFE_RAW)), FILTER_VALIDATE_URL);
            if ($host === false || (substr( $host, 0, 7 ) !== "http://" && substr( $host, 0, 8 ) !== "https://")) {
                wp_send_json_error("Error validating BTCPayServer URL.");
            }
            $host = rtrim(esc_url_raw($host), '/');

            try {
                $state = CoinsnapBIF_Security::btcpay_start_state();
                // Create the redirect url to BTCPay instance.
                $url = $this->getAuthorizeUrl(
                    $host,
                    CoinsnapBIF_Security::BTCPAY_PERMISSIONS,
                    'CoinsnapBIF',
                    true,
                    true,
                    add_query_arg(['coinsnapBIF-settings-callback' => '1', 'state' => $state], home_url('/')),
                    'coinsnap-bitcoin-invoice-form'
                );

                // A key belongs to one server: a new server URL drops the stored key (6.14).
                $stored = AdminSettings::get_settings();
                $update = ['btcpay_host' => $host];
                if (rtrim((string) $stored['btcpay_host'], '/') !== $host) {
                    $update['btcpay_api_key'] = '';
                    $update['btcpay_store_id'] = '';
                }
                coinsnap_settings_update(AdminSettings::OPTION_KEY, $update);

                // Return the redirect url.
                wp_send_json_success(['url' => $url]);
            }
            catch (\Throwable $e) {
                wp_send_json_error("Error processing Ajax request.");
            }
    }
    
    public function getAuthorizeUrl(string $baseUrl, array $permissions, ?string $applicationName, ?bool $strict, ?bool $selectiveStores, ?string $redirectToUrlAfterCreation, ?string $applicationIdentifier): string {
        $url = rtrim($baseUrl, '/') . '/api-keys/authorize';

        $params = [];
        $params['permissions'] = $permissions;
        $params['applicationName'] = $applicationName;
        $params['strict'] = $strict;
        $params['selectiveStores'] = $selectiveStores;
        $params['redirect'] = $redirectToUrlAfterCreation;
        $params['applicationIdentifier'] = $applicationIdentifier;

        // Take out NULL values
        $params = array_filter($params, function ($value) {
            return $value !== null;
        });

        $queryParams = [];

        foreach ($params as $param => $value) {
            if ($value === true) {
                $value = 'true';
            }
            if ($value === false) {
                $value = 'false';
            }

            if (is_array($value)) {
                foreach ($value as $item) {
                    if ($item === true) {
                        $item = 'true';
                    }
                    if ($item === false) {
                        $item = 'false';
                    }
                    $queryParams[] = $param . '=' . urlencode((string)$item);
                }
            } else {
                $queryParams[] = $param . '=' . urlencode((string)$value);
            }
        }

        $url .= '?' . implode("&", $queryParams);
        return $url;
    }
        
    public function coinsnapConnectionHandler(){
        if ( !current_user_can( 'manage_options' ) ) {
            wp_die('Unauthorized!', '', ['response' => 403]);
        }
        $_nonce = filter_input(INPUT_POST,'apiNonce',FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        if ( !wp_verify_nonce( $_nonce, 'coinsnap-ajax-nonce' ) ) {
            wp_die('Unauthorized!', '', ['response' => 401]);
        }
        
        $this->post_id = (int) filter_input(INPUT_POST,'apiPost',FILTER_VALIDATE_INT);
        if($this->post_id > 0){
            $meta_payment = get_post_meta($this->post_id, '_coinsnapbif_payment', true);
            $currency = $meta_payment['currency'];
        }
        else {
            $currency = 'EUR';
        }
        
        $coinsnap_url = $this->getApiUrl();
        $coinsnap_api_key = $this->getApiKey();
        $coinsnap_store_id = $this->getStoreId();
        $coinsnap_provider = ($this->getPaymentProvider() === 'btcpay')? 'BTCPay server' : 'Coinsnap';
                
        if(!isset($coinsnap_url) || empty($coinsnap_url)){
                    
            $response = [
                'result' => false,
                'message' => __('Bitcoin Invoice Form: Server URL is not set', 'coinsnap-bitcoin-invoice-form')
            ];
            $this->sendJsonResponse($response);
                    
        }

        if(!isset($coinsnap_store_id) || empty($coinsnap_store_id)){
            
            $response = [
                'result' => false,
                'message' => __('Bitcoin Invoice Form: Store ID is not set', 'coinsnap-bitcoin-invoice-form')
            ];
            $this->sendJsonResponse($response);
            
        }

        if(!isset($coinsnap_api_key) || empty($coinsnap_api_key)){
            
            $response = [
                'result' => false,
                'message' => __('Bitcoin Invoice Form: API Key is not set', 'coinsnap-bitcoin-invoice-form')
            ];
            $this->sendJsonResponse($response);
        }
                
        if(!empty($coinsnap_url) && !empty($coinsnap_api_key) && !empty($coinsnap_store_id)){
                
            // Create payment provider and get store data
            $payment_provider = CoinsnapBIF_Util_Provider_Factory::payment_for_form( $this->post_id );
            $store = $payment_provider->get_store();
                
            try {
                    if (isset($store['code']) && $store['code'] === 200){
                        // Report only; the webhook is registered by an admin action, not on page views.
                        $connected = CoinsnapBIF_Security::is_connected($this->getPaymentProvider());
                        $response = [
                            'result' => $connected,
                            'message' => __('Bitcoin Invoice Form: Connection to server is established.', 'coinsnap-bitcoin-invoice-form') . ' ' . esc_html($coinsnap_provider) . '<br/>'
                                . ($connected ? esc_html__('Webhook: connected', 'coinsnap-bitcoin-invoice-form') : esc_html__('Webhook: not connected', 'coinsnap-bitcoin-invoice-form'))
                        ];
                        $this->sendJsonResponse($response);
                    }
                    else {
                        $response = [
                            'result' => false,
                            'message' => __('Coinsnap Bitcoin Invoice Form: Store is not available - error ', 'coinsnap-bitcoin-invoice-form') . (int) ($store['code'] ?? 0)
                        ];
                        $this->sendJsonResponse($response);
        
                    }
            }
            catch (\Exception $e) {
                
                $response = [
                    'result' => false,
                    'message' => __('Bitcoin Invoice Form: API connection is not established', 'coinsnap-bitcoin-invoice-form')
                ];
                $this->sendJsonResponse($response);
            }
        }
        
        
        /*
        
        $client = new Coinsnap_Paywall_Client();
        
        if($_provider === 'btcpay'){
            try {
                
                $storePaymentMethods = $client->getStorePaymentMethods($this->getApiUrl(), $this->getApiKey(), $this->getStoreId());

                if ($storePaymentMethods['code'] === 200) {
                    if($storePaymentMethods['result']['onchain'] && !$storePaymentMethods['result']['lightning']){
                        $checkInvoice = $client->checkPaymentData(0,$currency,'bitcoin','calculation');
                    }
                    elseif($storePaymentMethods['result']['lightning']){
                        $checkInvoice = $client->checkPaymentData(0,$currency,'lightning','calculation');
                    }
                }
            }
            catch (\Exception $e) {
                $response = [
                        'result' => false,
                        'message' => __('Coinsnap Bitcoin Paywall: API connection is not established', 'coinsnap-bitcoin-invoice-form')
                ];
                $this->sendJsonResponse($response);
            }
        }
        else {
            $checkInvoice = $client->checkPaymentData(0,$currency,'coinsnap','calculation');
        }
        
        if(isset($checkInvoice) && $checkInvoice['result']){
            $connectionData = __('Min order amount is', 'coinsnap-bitcoin-invoice-form') .' '. $checkInvoice['min_value'].' '.$currency;
        }
        else {
            $connectionData = __('No payment method is configured', 'coinsnap-bitcoin-invoice-form');
        }
        
        $_message_disconnected = ($_provider !== 'btcpay')? 
            __('Coinsnap Bitcoin Paywall: Coinsnap server is disconnected', 'coinsnap-bitcoin-invoice-form') :
            __('Coinsnap Bitcoin Paywall: BTCPay server is disconnected', 'coinsnap-bitcoin-invoice-form');
        $_message_connected = ($_provider !== 'btcpay')?
            __('Coinsnap Bitcoin Paywall: Coinsnap server is connected', 'coinsnap-bitcoin-invoice-form') : 
            __('Coinsnap Bitcoin Paywall: BTCPay server is connected', 'coinsnap-bitcoin-invoice-form');
        
        if( wp_verify_nonce($_nonce,'coinsnap-ajax-nonce') ){
            $response = ['result' => false,'message' => $_message_disconnected];

            try {
                $this_store = $client->getStore($this->getApiUrl(), $this->getApiKey(), $this->getStoreId());
                
                if ($this_store['code'] !== 200) {
                    $this->sendJsonResponse($response);
                }
                
                else {
                    $response = ['result' => true,'message' => $_message_connected.' ('.$connectionData.')'];
                    $this->sendJsonResponse($response);
                }
            }
            catch (\Exception $e) {
                $response['message'] =  __('Coinsnap Bitcoin Paywall: API connection is not established', 'coinsnap-bitcoin-invoice-form');
            }

            $this->sendJsonResponse($response);
        }    
        
         */   
        $this->sendJsonResponse($response);
    }
    
    public function sendJsonResponse(array $response): void {
        echo wp_json_encode($response);
        exit();
    }
        
    private function getPaymentProvider() {
        $coinsnapbif_options = AdminSettings::get_settings();
        //$this->post_id = (filter_input(INPUT_GET,'post',FILTER_SANITIZE_FULL_SPECIAL_CHARS ))? filter_input(INPUT_GET,'post',FILTER_SANITIZE_FULL_SPECIAL_CHARS ) : 0;
        
        if($this->post_id > 0){
            $payment  = get_post_meta( $this->post_id, '_coinsnapbif_payment', true );
            $override = (is_array( $payment ) && ! empty( $payment['provider_override'] )) ? $payment['provider_override'] : '';
        }
		
	$provider = (isset($override) && !empty($override)) ? $override : (($coinsnapbif_options['payment_provider'] === 'btcpay')? 'btcpay' : 'coinsnap');
        return $provider;
    }

    private function getApiKey() {
        $coinsnapbif_options = AdminSettings::get_settings();
        return ($this->getPaymentProvider() === 'btcpay')? $coinsnapbif_options['btcpay_api_key']  : $coinsnapbif_options['coinsnap_api_key'];
    }
    
    private function getStoreId() {
	$coinsnapbif_options = AdminSettings::get_settings();
        return ($this->getPaymentProvider() === 'btcpay')? $coinsnapbif_options['btcpay_store_id'] : $coinsnapbif_options['coinsnap_store_id'];
    }
    
    public function getApiUrl() {
        $coinsnapbif_options = AdminSettings::get_settings();
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

		wp_register_style( 'coinsnapbif-admin', COINSNAPBIF_PLUGIN_URL . 'assets/css/admin.css', array(), COINSNAPBIF_VERSION );
		wp_register_script( 'coinsnapbif-admin', COINSNAPBIF_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), COINSNAPBIF_VERSION, true );

		// Localize script with REST API data
		wp_localize_script(
			'coinsnapbif-admin',
			'CoinsnapBIFRestUrl',
			array(
				'restUrl' => esc_url_raw( get_rest_url( null, CoinsnapBIF_Constants::REST_NAMESPACE . '/' ) ),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
			)
		);
                
                wp_localize_script('coinsnapbif-admin', 'coinsnapbif_ajax', array(
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce'  => wp_create_nonce( 'coinsnap-ajax-nonce' ),
                    'post' => $this->post_id
                ));

		wp_enqueue_style( 'coinsnapbif-admin' );
		wp_enqueue_script( 'coinsnapbif-admin' );
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
    }
    return $vars;
});

if(!function_exists('coinsnap_settings_update')){
    function coinsnap_settings_update($option,$data){
        
        $form_data = get_option($option, []);
        if (!is_array($form_data)) {
            $form_data = [];
        }
        
        foreach($data as $key => $value){
            $form_data[$key] = $value;
        }
        
        update_option($option,$form_data);
    }
}

// BTCPay posts the new API key here from its own site. The URL is public and the
// admin's login cookie is usually not sent, so the proof is the one-time state
// created when the admin clicked "Generate API key". Every outcome ends the request.
add_action( 'template_redirect', function(){
    
    global $wp_query;
            
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- routing only
    if (!isset($wp_query->query_vars['coinsnapBIF-settings-callback']) && !isset($_GET['coinsnapBIF-settings-callback'])) {
        return;
    }

    $settingsUrl = admin_url('admin.php?page=coinsnapbif-settings');

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the one-time state is the token
    $state = isset($_GET['state']) ? sanitize_text_field(wp_unslash($_GET['state'])) : '';
    if (!CoinsnapBIF_Security::btcpay_consume_state($state)) {
        wp_safe_redirect($settingsUrl);
        exit;
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- checked by the state above
    $apiKey = isset($_POST['apiKey']) ? sanitize_text_field(wp_unslash($_POST['apiKey'])) : '';
    $btcpayUrl = (string) AdminSettings::get_settings()['btcpay_host']; // from settings, never from the request

    // Ask BTCPay what the key can do; permissions and store ID in the POST are ignored.
    $permissions = CoinsnapBIF_Security::btcpay_key_permissions($btcpayUrl, $apiKey);
    if ($permissions === null) {
        wp_safe_redirect($settingsUrl);
        exit;
    }
    $storeId = CoinsnapBIF_Security::btcpay_key_store($permissions);
    if (!CoinsnapBIF_Security::btcpay_key_is_acceptable($permissions, $storeId)) {
        wp_safe_redirect($settingsUrl);
        exit;
    }

    coinsnap_settings_update(AdminSettings::OPTION_KEY, [
        'btcpay_api_key'   => $apiKey,
        'btcpay_store_id'  => $storeId,
        'payment_provider' => 'btcpay',
    ]);
    // The callback has no admin session, so register the webhook here.
    CoinsnapBIF_Security::register_webhook('btcpay');

    wp_safe_redirect($settingsUrl);
    exit;
});
