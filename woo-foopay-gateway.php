<?php

/**
 * Plugin Name:       WooCommerce FoPay Gateway
 * Plugin URI:        https://profiles.wordpress.org/creativehassan
 * Description:       Easily enable FoPay payment methods for WooCommerce.
 * Version:           1.0.0
 * Author:            Hassan Ali
 * Author URI:        https://hassanali.pro
 * License:           GNU General Public License v3.0
 * License URI:       http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       woo-fopay-gateway
 * Domain Path:       /languages
 * Requires at least: 3.8
 * Fopayed up to: 5.2.2
 * WC requires at least: 2.6
 * WC fopayed up to: 3.7.0
 */
if (!defined('WPINC')) {
    die;
}

add_filter( 'woocommerce_payment_gateways', 'fopay_gateway_add_gateway_class' );
function fopay_gateway_add_gateway_class( $gateways ) {
	$gateways[] = 'WC_FoPay_Gateway'; // your class name is here
	return $gateways;
}

function fopay_gateway_action_links($links){
	$plugin_links = array();

	if ( function_exists( 'WC' ) ) {
		if ( version_compare( WC()->version, '2.6', '>=' ) ) {
			$section_slug = 'fopay_gateway';
		} else {
			$section_slug = strtolower( 'WC_FoPay_Gateway' );
		}
		$setting_url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . $section_slug );
		$plugin_links[] = '<a href="' . esc_url( $setting_url ) . '">' . esc_html__( 'Settings', 'woo-fopay-gateway' ) . '</a>';
	}

	return array_merge( $plugin_links, $links );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'fopay_gateway_action_links' );


// Adding Meta container admin shop_order pages
add_action( 'add_meta_boxes', 'fopay_gateway_add_meta_boxes' );
if ( ! function_exists( 'fopay_gateway_add_meta_boxes' ) )
{
    function fopay_gateway_add_meta_boxes()
    {
        add_meta_box( 'fopay_gateway_other_fields', __('FoPay Invoice','woocommerce'), 'fopay_gateway_add_other_fields_for_packaging', 'shop_order', 'side', 'core' );
    }
}

// Adding Meta field in the meta container admin shop_order pages
if ( ! function_exists( 'fopay_gateway_add_other_fields_for_packaging' ) )
{
    function fopay_gateway_add_other_fields_for_packaging()
    {
        global $post;

		$invoice_number = get_post_meta($post->ID, "_foo_invoice_number", true);
		$invoice_id = get_post_meta($post->ID, "_foo_invoice_id", true);

		echo '<p><strong> Invoice Status : </strong> PAID </p>';
		echo '<p><strong> Invoice ID : </strong>'. $invoice_id . '</p>';
		echo '<p><strong> Invoice Number : </strong>'. $invoice_number . '</p>';
    }
}

/*
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action( 'plugins_loaded', 'fopay_gateway_init_gateway_class' );
function fopay_gateway_init_gateway_class() {

	class WC_FoPay_Gateway extends WC_Payment_Gateway {

 		/**
 		 * Class constructor, more about it in Step 3
 		 */
 		public function __construct() {
	 		$this->id = 'fopay_gateway';
			$this->icon = plugin_dir_url( __FILE__ ) . "images/fopay-small.png";
			$this->has_fields = true;
			$this->method_title = 'FoPay Gateway';
			$this->method_description = 'Description of FoPay payment gateway';

			$this->supports = array(
				'products'
			);

			// Method with all the options fields
			$this->init_form_fields();

			// Load the settings.
			$this->init_settings();
			$this->title = $this->get_option( 'title' );
			$this->description = $this->get_option( 'description' );
			$this->enabled = $this->get_option( 'enabled' );
			$this->fopaymode = 'yes' === $this->get_option( 'fopaymode' );
			$this->fopay_clientcodename = $this->get_option( 'fopay_clientcodename');
			$this->fopay_private_key = $this->get_option( 'fopay_private_key');
			$this->fopay_public_key = $this->get_option( 'fopay_public_key' );

			// This action hook saves the settings
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

			// We need custom JavaScript to obtain a token
			add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );

			// You can also register a webhook here
			add_action( 'woocommerce_api_fopay', array( $this, 'fopay_webhook' ) );
 		}

 		function process_admin_options(){
 			parent::process_admin_options();
		}

		/**
 		 * Plugin options, we deal with it in Step 3 too
 		 */
 		public function init_form_fields(){
			$this->form_fields = array(
				'enabled' => array(
					'title'       => 'Enable/Disable',
					'label'       => 'Enable FoPay Gateway',
					'type'        => 'checkbox',
					'description' => '',
					'default'     => 'no'
				),
				'title' => array(
					'title'       => 'Title',
					'type'        => 'text',
					'description' => 'This controls the title which the user sees during checkout.',
					'default'     => 'FoPay ',
					'desc_tip'    => true,
				),
				'description' => array(
					'title'       => 'Description',
					'type'        => 'textarea',
					'description' => 'This controls the description which the user sees during checkout.',
					'default'     => 'Pay with your FoPay payment gateway.',
				),
				'fopay_clientcodename' => array(
					'title'       => 'Fopay Client Code Name',
					'type'        => 'text',
				),
				'fopay_private_key' => array(
					'title'       => 'Fopay Private Key',
					'type'        => 'textarea',
				),
				'fopay_public_key' => array(
					'title'       => 'Fopay publicKey Key',
					'type'        => 'textarea'
				),
			);
	 	}

	 	public function payment_scripts() {
	 		// we need JavaScript to process a token only on cart/checkout pages, right?
			if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) ) {
				return;
			}

			// if our payment gateway is disabled, we do not have to enqueue JS too
			if ( 'no' === $this->enabled ) {
				return;
			}

			// no reason to enqueue JavaScript if API keys are not set
			if ( empty( $this->fopay_clientcodename ) || empty( $this->fopay_private_key ) || empty( $this->fopay_public_key ) ) {
				return;
			}

			if ( ! $this->fopaymode && ! is_ssl() ) {
				return;
			}

			// let's suppose it is our payment processor JavaScript that allows to obtain a token
			//wp_enqueue_script( 'fopay_js', 'https://www.fopaypayments.com/api/token.js' );

			// and this is our custom JS in your plugin directory that works with token.js
			wp_register_script( 'woocommerce_fopay', plugins_url( 'assets/js/fopay.js', __FILE__ ), array( 'jquery' ) );

			// in most payment processors you have to use PUBLIC KEY to obtain a token
			wp_localize_script( 'woocommerce_fopay', 'fopay_params', array(
				'publishableKey' => $this->fopay_public_key
			) );

			wp_enqueue_script( 'woocommerce_fopay' );

		}

		/*
		 * We're processing the payments here, everything about it is in Step 5
		 */
		public function process_payment( $order_id ) {

			global $woocommerce;
			// we need it to get any order detailes
			$order = wc_get_order( $order_id );

			include_once("gateway/FoPayAPI/vendor/autoload.php");
			include_once("gateway/FoPayAPI/FoPayAPI.php");

			$fopayAPI = new FoPayAPI($this->fopay_clientcodename, $this->fopay_private_key, $this->fopay_public_key);
			$createdInvoice = $fopayAPI->createInvoice([
// 			    "amount" => "0.01",
			    "amount" => $order->get_total(),
			    "currency" => get_woocommerce_currency(),
			    "returnUrl" => add_query_arg( array( 'id' => $order->get_id()), site_url( '/wc-api/fopay' ) ),
			]);

			if(!empty($createdInvoice) && $createdInvoice->data->status == "OPEN" ){
				add_post_meta( $order_id, '_foo_invoice_id', $createdInvoice->data->invoiceId );
				add_post_meta( $order_id, '_foo_invoice_number', $createdInvoice->data->invoiceNumber );

				$redirect_link = $createdInvoice->data->link;
				return array(
					'result' => 'success',
					'redirect' => $redirect_link
				);
			} else {
				wc_add_notice(  'FoPay API Connection error.', 'error' );
				return;
			}

	 	}
	 	public function fopay_webhook(){
		 	global $woocommerce;

		 	include_once("gateway/FoPayAPI/vendor/autoload.php");
			include_once("gateway/FoPayAPI/FoPayAPI.php");

			$fopayAPI = new FoPayAPI($this->fopay_clientcodename, $this->fopay_private_key, $this->fopay_public_key);

		 	$order_id = $_GET['id'];
		 	$order = wc_get_order( $_GET['id'] );

		 	$invoice_number = get_post_meta($order_id, "_foo_invoice_number", true);
		 	$invoice_id = get_post_meta($order_id, "_foo_invoice_id", true);

		 	$invoice = $fopayAPI->getInvoice([
			    "invoiceId" => $invoice_id
			]);

			if(!empty($invoice) && $invoice->data->status == "PAID"){
				$order->payment_complete();
				$order->reduce_order_stock();
				$order->add_order_note( 'Hey, your order is paid! Thank you!', true );

				// Empty cart
				$woocommerce->cart->empty_cart();
				wp_redirect( $this->get_return_url( $order ) );
				exit();
			} else {
				$url = get_permalink( get_option( 'woocommerce_checkout_page_id' ) );
				wc_add_notice(  'Please try again.', 'error' );
				wp_redirect( $url );
				exit();
			}
	 	}
 	}
}