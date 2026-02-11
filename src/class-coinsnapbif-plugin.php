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
        }
	
	AdminSettings::register();
	TransactionsPage::register();
	LogsPage::register();
	add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
	add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend' ) );
	
    }
    
    public function coinsnapbif_notice(string $hook){
        
        $post_id = (filter_input(INPUT_GET,'post',FILTER_SANITIZE_FULL_SPECIAL_CHARS ))? filter_input(INPUT_GET,'post',FILTER_SANITIZE_FULL_SPECIAL_CHARS ) : 0;
        $post_type = (filter_input(INPUT_GET,'post_type',FILTER_SANITIZE_FULL_SPECIAL_CHARS ))? filter_input(INPUT_GET,'post_type',FILTER_SANITIZE_FULL_SPECIAL_CHARS ) : 
            ((!empty($post_id))? get_post_type($post_id) : '');
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
                
            if(!empty($coinsnap_url) && !empty($coinsnap_api_key) && !empty($coinsnap_store_id)){
                
                // Create payment provider and get store data
                $payment_provider = CoinsnapBIF_Util_Provider_Factory::payment_for_form( $form_id );
                $store = $payment_provider->get_store();
                
                try {
                    if ($store['code'] === 200){
                        echo '<div class="notice notice-success"><p>';
                        esc_html_e('Bitcoin Invoice Form: Established connection to ', 'coinsnap-bitcoin-invoice-form');
                        echo esc_html($coinsnap_provider);
                        echo '</p></div>';

                        if ( !$payment_provider->check_webhook() ) {

                            $register_webhook_result = $payment_provider->register_webhook();

                            if (isset($register_webhook_result['error'])) {
                                    echo '<div class="notice notice-error"><p>';
                                    esc_html_e('Bitcoin Invoice Form: Unable to create webhook on ', 'coinsnap-bitcoin-invoice-form');
                                    echo esc_html($coinsnap_provider);
                                    echo '</p></div>';
                            }
                            else {
                                $stored_webhook = get_option(AdminSettings::WEBHOOK_KEY, []);
                                $stored_webhook[$this->getPaymentProvider()] = [
                                        'id' => $register_webhook_result['result']['id'],
                                        'secret' => $register_webhook_result['result']['secret'],
                                        'url' => $register_webhook_result['result']['url']
                                    ];

                                update_option(AdminSettings::WEBHOOK_KEY,$stored_webhook);

                                echo '<div class="notice notice-success"><p>';
                                    esc_html_e('Bitcoin Invoice Form: Successfully registered a new webhook on ', 'coinsnap-bitcoin-invoice-form');
                                    echo esc_html($coinsnap_provider);
                                    echo '</p></div>';
                            }
                        }
                        else {
                                echo '<div class="notice notice-info"><p>';
                                esc_html_e('Bitcoin Invoice Form: Webhook already exists, skipping webhook creation', 'coinsnap-bitcoin-invoice-form');
                                echo '</p></div>';
                        }
                    }
                }
                catch (\Exception $e) {
                    echo '<div class="notice notice-error"><p>';
                    esc_html_e('Bitcoin Invoice Form: API connection is not established', 'coinsnap-bitcoin-invoice-form');
                    echo '</p></div>';
                }
            }
        }
    }
        
    public function btcpayApiUrlHandler(){
            
    }
        
    public function coinsnapConnectionHandler(){
        
        $_nonce = filter_input(INPUT_POST,'apiNonce',FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        if ( !wp_verify_nonce( $_nonce, 'coinsnap-ajax-nonce' ) ) {
            wp_die('Unauthorized!', '', ['response' => 401]);
        }
        
        $response = [
            'result' => false,
            'message' => __('Coinsnap Bitcoin Invoice Form: Empty gateway URL or API Key', 'coinsnap-bitcoin-invoice-form')
        ];
        
        
        $_provider = $this->getPaymentProvider();
        $post_id = ('' !== filter_input(INPUT_POST,'apiPost',FILTER_SANITIZE_FULL_SPECIAL_CHARS))? filter_input(INPUT_POST,'apiPost',FILTER_SANITIZE_FULL_SPECIAL_CHARS) : 0;
        
        if($post_id > 0){
            $meta_payment = get_post_meta($post_id, '_coinsnapbif_payment', true);
            $currency = $meta_payment['currency'];
        }
        else {
            $currency = 'EUR';
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
        $coinsnapbif_options = get_option('bif_settings', []);
        $form_id = (filter_input(INPUT_GET,'post',FILTER_SANITIZE_FULL_SPECIAL_CHARS ))? filter_input(INPUT_GET,'post',FILTER_SANITIZE_FULL_SPECIAL_CHARS ) : 0;
        
        if($form_id > 0){
            $payment  = get_post_meta( $form_id, '_coinsnapbif_payment', true );
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

	/**
	 * Enqueue admin assets.
	 */
	public function enqueue_admin( string $hook ): void {
		// Only enqueue on our plugin pages
		if ( strpos( $hook, 'coinsnapbif-' ) === false && strpos( $hook, 'coinsnapbif_invoice_form' ) === false ) {
			return;
		}

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
                
                $post_id = (filter_input(INPUT_GET,'post',FILTER_SANITIZE_FULL_SPECIAL_CHARS ))? filter_input(INPUT_GET,'post',FILTER_SANITIZE_FULL_SPECIAL_CHARS ) : '';
                
                wp_localize_script('coinsnapbif-admin', 'coinsnapbif_ajax', array(
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce'  => wp_create_nonce( 'coinsnap-ajax-nonce' ),
                    'post' => $post_id
                ));

		wp_enqueue_style( 'coinsnapbif-admin' );
		wp_enqueue_script( 'coinsnapbif-admin' );
	}
}
