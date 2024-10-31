<?php
/**
 * Plugin Name: Authorize.net for WooCommerce
 * Plugin URI: http://offshorent.com/extensions/os-woocommerce-authorizenet-aim/
 * Description: AuthorizeNet WooCommerce addon adds a payment option for customers to pay with their Credit Cards.
 * Version: 1.4
 * Author: Offshorent Solutions Pvt Ltd. | Jinesh.P.V
 * Author URI: http://offshorent.com/
 * Requires at least: 4.0
 * Tested up to: 4.7.4
**/

add_action('plugins_loaded', 'oswc_auth_woocommerce_plugin_init', 0);
function oswc_auth_woocommerce_plugin_init() {

	if ( !class_exists( 'WC_Payment_Gateway' ) ) 
	  return;
	
	/**
	* Authorize.net AIM Payment Gateway class
	*/
	class OSWC_AuthorizeAIM extends WC_Payment_Gateway {
		
		protected $msg = array();
      
		public function __construct(){

			$this->id               = 'authorizeaim';
			$this->method_title     = __( 'Authorize.net AIM', 'oswc-authorize-aim' );
			$this->icon             = WP_PLUGIN_URL . "/" . plugin_basename( dirname( __FILE__ ) ) . '/images/logo.gif';
			$this->has_fields       = true;
			$this->init_form_fields();
			$this->init_settings();
			$this->supports         = array( 'refunds' );
			$this->title            = $this->settings['title'];
			$this->description      = $this->settings['description'];
			$this->login            = $this->settings['login_id'];
			$this->mode             = $this->settings['working_mode'];
			$this->transaction_key  = $this->settings['transaction_key'];
			$this->success_message  = $this->settings['success_message'];
			$this->failed_message   = $this->settings['failed_message'];
			$this->liveurl          = 'https://secure.authorize.net/gateway/transact.dll';
			$this->testurl          = 'https://test.authorize.net/gateway/transact.dll';
			$this->msg['message']   = "";
			$this->msg['class']     = "";

			// Lets check for SSL
			add_action( 'admin_notices', array( $this,	'do_ssl_check' ) );
         
			if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
			} else {
				add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
			}

			add_action( 'woocommerce_receipt_first_data', array( &$this, 'oswc_auth_receipt_page' ) );
			add_action( 'woocommerce_credit_card_form_fields', array( &$this, 'oswc_auth_woocommerce_credit_card_form_fields' ) );
      }

		function init_form_fields() {

			$this->form_fields = array(
				'enabled'      => array(
					  'title'        => __('Enable/Disable', 'oswc-authorize-aim'),
					  'type'         => 'checkbox',
					  'label'        => __('Enable Authorize.net AIM Payment Module.', 'oswc-authorize-aim'),
					  'default'      => 'no'),
				'title'        => array(
					  'title'        => __('Title:', 'oswc-authorize-aim'),
					  'type'         => 'text',
					  'description'  => __('This controls the title which the user sees during checkout.', 'oswc-authorize-aim'),
					  'default'      => __('Authorize.net AIM', 'oswc-authorize-aim')),
				'description'  => array(
					  'title'        => __('Description:', 'oswc-authorize-aim'),
					  'type'         => 'textarea',
					  'description'  => __('This controls the description which the user sees during checkout.', 'oswc-authorize-aim'),
					  'default'      => __('Pay securely by Credit or Debit Card through Authorize.net AIM Secure Servers.', 'oswc-authorize-aim')),
				'login_id'     => array(
					  'title'        => __('Login ID', 'oswc-authorize-aim'),
					  'type'         => 'text',
					  'description'  => __('This is API Login ID')),
				'transaction_key' => array(
					  'title'        => __('Transaction Key', 'oswc-authorize-aim'),
					  'type'         => 'text',
					  'description'  =>  __('API Transaction Key', 'oswc-authorize-aim')),
				'success_message' => array(
					  'title'        => __('Transaction Success Message', 'oswc-authorize-aim'),
					  'type'         => 'textarea',
					  'description'=>  __('Message to be displayed on successful transaction.', 'oswc-authorize-aim'),
					  'default'      => __('Your payment has been procssed successfully.', 'oswc-authorize-aim')),
				'failed_message'  => array(
					  'title'        => __('Transaction Failed Message', 'oswc-authorize-aim'),
					  'type'         => 'textarea',
					  'description'  =>  __('Message to be displayed on failed transaction.', 'oswc-authorize-aim'),
					  'default'      => __('Your transaction has been declined.', 'oswc-authorize-aim')),
				'working_mode'    => array(
					  'title'        => __('API Mode'),
					  'type'         => 'select',
				'options'      => array('false'=>'Live Mode', 'true'=>'Test/Sandbox Mode'),
					  'description'  => "Live/Test Mode" )
			);
		}
      
		/**
		* Admin Panel Options
		* 
		**/
		public function admin_options() {
		  
			echo '<h3>'.__('Authorize.net AIM Payment Gateway', 'oswc-authorize-aim').'</h3>';
			echo '<p>'.__('Authorize.net AIM is most popular payment gateway for online payment processing').'</p>';
			echo '<table class="form-table">';
			$this->generate_settings_html();
			echo '</table>';
		}
		
		// Check if we are forcing SSL on checkout pages

		public function do_ssl_check() {
			if( $this->enabled == "yes" ) {
				if( get_option( 'woocommerce_force_ssl_checkout' ) == "no" ) {
					echo "<div class=\"error\"><p>". sprintf( __( "<strong>%s</strong> is enabled and WooCommerce is not forcing the SSL certificate on your checkout page. Please ensure that you have a valid SSL certificate and that you are <a href=\"%s\">forcing the checkout pages to be secured.</a>" ), $this->method_title, admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ) ."</p></div>";	
				}
			}	
		} 
		 
		/**
		*  There are no payment fields for Authorize.net, but want to show the description if set.
		**/
		
		public function payment_fields() {
			
			$month = $year = '';
			
			if ( $this->description ) 
				echo wpautop( wptexturize( $this->description ) );

			echo '<style>.woocommerce-checkout #payment div.payment_box label {font-family: inherit; font-weight: 300; text-transform: uppercase; display: block;} .woocommerce-checkout #payment div.payment_box input { border: 1px solid #ccc;border-radius: 3px; -o-border-radius: 3px; -ms-border-radius: 3px; -moz-border-radius: 3px; border-radius: 3px; font-size: 15px; line-height: 28px; padding: 0 10px; width: 100%;}.oswc_paymet_outer .oswc_row_left{ display: inline-block; width: 83%; }</style>';	
			
			$this->credit_card_form();
		}
		
      	/*
		* Filter hook for credit card form
		*/
		  
		public function oswc_auth_woocommerce_credit_card_form_fields( $default_fields, $form_id = null ) {

			$default_args = array(
				'fields_have_names' => true, // Some gateways like stripe don't need names as the form is tokenized
			);
			$args = wp_parse_args( $default_fields, apply_filters( 'woocommerce_credit_card_form_args', $default_args, $this->id ) );
			$default_fields['card-expiry-field'] = '<p class="form-row form-row-first">
														<label for="' . esc_attr( $this->id ) . '-card-expiry">' . __( 'Expiry (MM/YY)', 'woocommerce' ) . ' <span class="required">*</span></label>
														<input id="' . esc_attr( $this->id ) . '-card-expiry" class="input-text wc-credit-card-form-card-expiry" type="text" autocomplete="off" placeholder="' . esc_attr__( 'MM / YY', 'woocommerce' ) . '" name="' . ( $args['fields_have_names'] ? $this->id . '-card-expiry' : '' ) . '" maxlength="7" />
													</p>';

			return $default_fields;
		}
		
      /*
		* Basic Card validation
		*/
	  
		public function validate_fields() {
			
			global $woocommerce; 
			
			if ( !$this->is_empty_credit_card( $_POST['authorizeaim-card-number'] ) ) 
				wc_add_notice( '<strong>Credit Card Number</strong> ' . __( 'is a required field.', 'oswc_fistdata' ), 'error' );
				
			elseif ( !$this->is_valid_credit_card( $_POST['authorizeaim-card-number'] ) ) 
				wc_add_notice( '<strong>Credit Card Number</strong> ' . __( 'is not a valid credit card number.', 'oswc_fistdata' ), 'error' );
				
			if ( !$this->is_empty_expire_date( $_POST['authorizeaim-card-expiry'] ) )    
				wc_add_notice( '<strong>Card Expiry Date</strong> ' . __( 'is a required field.', 'oswc_fistdata' ), 'error' );
				
			elseif ( !$this->is_valid_expire_date( $_POST['authorizeaim-card-expiry'] ) ) 
				wc_add_notice( '<strong>Card Expiry Date</strong> ' . __( 'is not a valid expiry date.', 'oswc_fistdata' ), 'error' );
				
			if ( !$this->is_empty_ccv_nmber( $_POST['authorizeaim-card-cvc'] ) ) 
				wc_add_notice( '<strong>CCV Number</strong> ' . __( 'is a required field.', 'oswc_fistdata' ), 'error' );
		}
  
		/*
		* Check whether the card number number is empty
		*/

		private function is_empty_credit_card( $credit_card ) {
		    
			if ( empty( $credit_card ) )
				return false;
				
			return true; 	
		}
		/*
		* Check whether the card number number is valid
		*/

		private function is_valid_credit_card( $credit_card ) {
		    
			$credit_card = preg_replace( '/(?<=\d)\s+(?=\d)/', '', trim( $credit_card ) );
			
			$number = preg_replace( '/[^0-9]+/', '', $credit_card );
			$strlen = strlen( $number );
			$sum    = 0;
			
			if ( $strlen < 13 )
				return false; 
			
			for ( $i=0; $i < $strlen; $i++ ) {
				$digit = substr( $number, $strlen - $i - 1, 1 );
				
				if( $i % 2 == 1 ) {
					
					$sub_total = $digit * 2;
					
					if( $sub_total > 9 ) {
						$sub_total = 1 + ( $sub_total - 10 );
					}
				} else {
					$sub_total = $digit;
				}
				$sum += $sub_total;
			}
			
			if ( $sum > 0 AND $sum % 10 == 0 )
				return true; 
			
			return false; 
		}
		
		/*
		* Check expiry date is empty
		*/
		
		private function is_empty_expire_date( $ccexp_expiry ) {
			
			$ccexp_expiry = str_replace( ' / ', '', $ccexp_expiry );
			
			if ( is_numeric( $ccexp_expiry ) && ( strlen( $ccexp_expiry ) == 4 ) ){
				return true;
			}

			return false;
		}
		
		/*
		* Check expiry date is valid
		*/
		
		private function is_valid_expire_date( $ccexp_expiry ) {
			
			$month = $year = '';
			$month = substr( $ccexp_expiry , 0, 2 );
			$year = substr( $ccexp_expiry , 5, 7 );
			$year = '20'. $year;
			
			if( $month > 12 ) {
				return false;
			} 
			
			if ( date( "Y-m-d", strtotime( $year . "-" . $month . "-01" ) ) > date( "Y-m-d" ) ) {
				return true;
			}

			return false;
		}
		
		/*
		* Check whether the ccv number is empty
		*/
		
		private function is_empty_ccv_nmber( $ccv_number ) {
			
			$length = strlen( $ccv_number );
			
			return is_numeric( $ccv_number ) AND $length > 2 AND $length < 5;
		}
		
		/**
		* Receipt Page
		**/
		
		public function oswc_auth_receipt_page( $order ) {
			
			echo '<p>'.__( 'Thank you for your order.', 'oswc_fistdata' ).'</p>';
		}
      
		/**
		* Process the payment and return the result
		**/
		
		function process_payment( $order_id ) {
			
			global $woocommerce;
			$order = new WC_Order( $order_id );
			
			if( $this->mode == 'true' ){
				$process_url = $this->testurl;
			} else {
				$process_url = $this->liveurl;
			}
			
			$params = $this->oswc_generate_authorizeaim_params( $order ); 
			
			$post_string = "";
			foreach( $params as $key => $value ){ 
				$post_string .= "$key=" . urlencode( $value ) . "&"; 
			}
			$post_string = rtrim( $post_string, "& " );
			
			$request = curl_init( $process_url ); // initiate curl object
			curl_setopt( $request, CURLOPT_HEADER, 0 ); // set to 0 to eliminate header info from response
			curl_setopt( $request, CURLOPT_RETURNTRANSFER, 1 ); // Returns response data instead of TRUE(1)
			curl_setopt( $request, CURLOPT_POSTFIELDS, $post_string ); // use HTTP POST to send form data
			curl_setopt( $request, CURLOPT_SSL_VERIFYPEER, FALSE ); // uncomment this line if you get no gateway response.
			$post_response = curl_exec( $request ); // execute curl post and store results in $post_response
			curl_close ( $request );
			
			$response_array = explode( '|', $post_response );
			
			if ( count( $response_array ) > 1 ) {
			
				if( $response_array[0] == '1' ) {
				
					if ( $order->status != 'completed' ) {
						$order->payment_complete();
						$woocommerce->cart->empty_cart();
						
						$order->add_order_note( $this->success_message. $response_array[3] . 'Transaction ID: '. $response_array[6] );
						unset( $_SESSION['order_awaiting_payment'] );
					}
					
					return array(
									'result'   => 'success',
									'redirect'  => $order->get_checkout_order_received_url()
								);
				} else {
				
					$order->add_order_note($this->failed_message .$response_array[3] );
					wc_add_notice(__( '(Transaction Error) '. $response_array[3], 'oswc-authorize-aim' ) );
				}
			} else {
			
				$order->add_order_note( $this->failed_message );
				$order->update_status( 'failed' );
				wc_add_notice( __( '(Transaction Error) Error processing payment.', 'oswc-authorize-aim' ) ); 
			}
		}
      
		/**
		* Generate authorize.net AIM button link
		**/
	  
		public function oswc_generate_authorizeaim_params( $order ) {
			
			$credit_card = ( !empty( $_POST['authorizeaim-card-number'] ) ) ? strip_tags( str_replace( "'", "`", strip_tags( $_POST['authorizeaim-card-number'] ) ) ) : '';
			$credit_card = preg_replace( '/(?<=\d)\s+(?=\d)/', '', trim( $credit_card ) );
			$ccexp_expiry = ( !empty( $_POST['authorizeaim-card-expiry'] ) ) ? strip_tags( str_replace( "'", "`", strip_tags( $_POST['authorizeaim-card-expiry'] ) ) ) : '';
			$cc_expiry = str_replace( ' / ', '', $ccexp_expiry );
			$ccexp_cvc = ( !empty( $_POST['authorizeaim-card-cvc'] ) ) ? strip_tags( str_replace( "'", "`", strip_tags( $_POST['authorizeaim-card-cvc'] ) ) ) : '';
						
			$authorizeaim_args = array(
										'x_login'                  => $this->login,
										'x_tran_key'               => $this->transaction_key,
										'x_version'                => '3.1',
										'x_delim_data'             => 'TRUE',
										'x_delim_char'             => '|',
										'x_relay_response'         => 'FALSE',
										'x_type'                   => 'AUTH_CAPTURE',
										'x_method'                 => 'CC',
										'x_card_num'               => $credit_card,
										'x_exp_date'               => $cc_expiry,
										'x_invoice_num'            => $order->get_order_number(),
										'x_description'            => '',
										'x_amount'                 => $order->order_total,
										'x_first_name'             => $order->billing_first_name ,
										'x_last_name'              => $order->billing_last_name ,
										'x_company'                => $order->billing_company ,
										'x_address'                => $order->billing_address_1 . ' ' . $order->billing_address_2,
										'x_country'                => $order->billing_country,
										'x_phone'                  => $order->billing_phone,
										'x_state'                  => $order->billing_state,
										'x_city'                   => $order->billing_city,
										'x_zip'                    => $order->billing_postcode,
										'x_email'                  => $order->billing_email,
										'x_card_code'              => $ccexp_cvc, 
										'x_ship_to_first_name'     => $order->shipping_first_name,
										'x_ship_to_last_name'      => $order->shipping_last_name,
										'x_ship_to_address'        => $order->shipping_address_1,
										'x_ship_to_city'           => $order->shipping_city,
										'x_ship_to_zip'            => $order->shipping_postcode,
										'x_ship_to_state'          => $order->shipping_state
			
			);
			
			return $authorizeaim_args;
		}
		
	/**
	* Authorize.net AIM refund
	**/

	public function process_refund( $order_id, $amount = NULL, $reason = '' ) {}
	}
	
	/**
	* Add this Gateway to WooCommerce
	**/
	
	function woocommerce_add_authorize_aim_gateway( $methods ) {
		
		$methods[] = 'OSWC_AuthorizeAIM';
		return $methods;
	}
	
	add_filter( 'woocommerce_payment_gateways', 'woocommerce_add_authorize_aim_gateway' );
	
	function oswc_auth_woocommerce_addon_activate() {

		if( !function_exists( 'curl_exec' ) ) {
			 wp_die( '<pre>This plugin requires PHP CURL library installled in order to be activated </pre>' );
		}
	}
	register_activation_hook( __FILE__, 'oswc_auth_woocommerce_addon_activate' );
	
	/*Plugin Settings Link*/
	function oswc_auth_woocommerce_settings_link( $links ) {
		
		$settings_link = '<a href="admin.php?page=wc-settings&tab=checkout&section=oswc_authorizeaim">' . __( 'Settings' ) . '</a>';
		array_push( $links, $settings_link );
		return $links;
	}
	$plugin = plugin_basename( __FILE__ );
	add_filter( "plugin_action_links_$plugin", 'oswc_auth_woocommerce_settings_link' );
}