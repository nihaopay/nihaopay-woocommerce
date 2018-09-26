<?php
/**
 * Plugin Name: NihaoPay Gateway for WooCommerce
 * Plugin Name:
 * Description: Allows you to use UnionPay, AliPay and WechatPay through NihaoPay Gateway
 * Version: 1.0.1
 * Author: nihaopay
 * Author URI: https://www.nihaopay.com
 *
 * @package NihaoPay Gateway for WooCommerce
 * @author nihaopay
 */

add_action('plugins_loaded', 'init_woocommerce_nihaopay', 0);

function init_woocommerce_nihaopay() {

    if ( ! class_exists( 'WC_Payment_Gateway' ) ) { return; }

	class woocommerce_nihaopay extends WC_Payment_Gateway{

		public function __construct() {

			global $woocommerce;

			$plugin_dir = plugin_dir_url(__FILE__);

	        $this->id               = 'nihaopay';
	        $this->wechat_pay_icon     		= apply_filters( 'woocommerce_nihaopay_wechat_pay_icon', ''.$plugin_dir.'/WeChat-Pay.png' );
	        $this->alipay_icon     		= apply_filters( 'woocommerce_nihaopay_alipay_icon', ''.$plugin_dir.'/AliPay.png' );
	        $this->unionpay_icon     		= apply_filters( 'woocommerce_nihaopay_unionpay_icon', ''.$plugin_dir.'/UnionPay.png' );
	        $this->has_fields       = true;

	        $this->init_form_fields();
	        $this->init_settings();

	        // variables
	        $this->title            = $this->settings['title'];
			$this->token			= $this->settings['token'];
			$this->mode             = $this->settings['mode'];
			$this->currency         = $this->settings['currency'];
	        $this->notify_url   	= str_replace( 'https:', 'http:', add_query_arg( 'wc-api', 'wc_nihaopay', home_url( '/' ) ) );

			if( $this->mode == 'test' ){
				$this->gateway_url = 'https://apitest.nihaopay.com/v1.2/transactions/securepay';
			}else if( $this->mode == 'live' ){
				$this->gateway_url = 'https://api.nihaopay.com/v1.2/transactions/securepay';
			}

	        // actions
			add_action( 'woocommerce_receipt_nihaopay', array( $this, 'receipt_page' ) );
	        add_action( 'woocommerce_update_options_payment_gateways', array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_api_wc_nihaopay', array( $this, 'check_ipn_response' ) );

			if ( !$this->is_valid_for_use() ) {
				$this->enabled = false;
			}
		}

		/**
		 * get_icon function.
		 *
		 * @access public
		 * @return string
		 */
		function get_icon() {
			global $woocommerce;
			$icon = '';
			if ( $this->wechat_pay_icon ) {
				$icon.= '<img src="' . $this->force_ssl( $this->wechat_pay_icon ) . '" alt="' . $this->title . '" width="82" height="26" />';
			}
			if ( $this->alipay_icon ) {
				$icon.= '<img src="' . $this->force_ssl( $this->alipay_icon ) . '" alt="' . $this->title . '" width="74" height="26" />';
			}
			if ( $this->unionpay_icon ) {
				$icon.= '<img src="' . $this->force_ssl( $this->unionpay_icon ) . '" alt="' . $this->title . '" width="42" height="26" />';
			}
			return apply_filters( 'woocommerce_gateway_icons', $icon, $this->id );
		}

	     /**
	     * Check if this gateway is enabled and available in the user's country
	     */
	    function is_valid_for_use() {
	        if (!in_array(get_option('woocommerce_currency'), array('USD','GBP','HKD','JPY','EUR','CAD','CNY')))
	        	return false;

	        return true;
	    }

	    /**
	    * Admin Panel Options
	    **/
	    public function admin_options()
	    {
			?>
	        <h3><?php _e('NihaoPay', 'woocommerce'); ?></h3>
	        <p><?php _e('NihaoPay Gateway supports AliPay, WeChatPay and UnionPay.', 'woocommerce'); ?></p>
			<table class="form-table">
	        <?php
	    		if ( $this->is_valid_for_use() ) :

	    			// Generate the HTML For the settings form.
	    			$this->generate_settings_html();

	    		else :

	    			?>
	            		<div class="inline error"><p><strong><?php _e( 'Gateway Disabled', 'woothemes' ); ?></strong>: <?php _e( 'NihaoPay does not support your store currency.', 'woothemes' ); ?></p></div>
	        		<?php

	    		endif;
	        ?>
	        </table><!--/.form-table-->
	        <?php
		}

	    /**
	    * Initialise NihaoPay Settings Form Fields
	    */
	    public function init_form_fields() {

			//  array to generate admin form
	        $this->form_fields = array(
	        	'enabled' => array(
	            				'title' => __( 'Enable/Disable', 'woocommerce' ),
			                    'type' => 'checkbox',
			                    'label' => __( 'Enable NihaoPay', 'woocommerce' ),
			                    'default' => 'yes'
							),
				'title' => array(
			                    'title' => __( 'Title', 'woocommerce' ),
			                    'type' => 'text',
			                    'description' => __('This is the title displayed to the user during checkout.', 'woocommerce' ),
			                    'default' => __( 'NihaoPay', 'patsatech-woo-nihaopay-server' )
			                ),
				'token' => array(
								'title' => __( 'API Token', 'woocommerce' ),
								'type' => 'text',
								'description' => __( 'API Token', 'woocommerce' ),
								'default' => ''
				),
				'currency' => array(
								'title' => __( 'Settle Currency', 'woocommerce' ),
								'type' => 'select',
								'options' => array(
													'USD' => 'USD',
													'JPY' => 'JPY',
													'HKD' => 'HKD',
													'EUR' => 'EUR',
													'GBP' => 'GBP',
													'CAD' => 'CAD'
													),
								'description' => __( 'Settlement Currency from NihaoPay', 'woocommerce' ),
								'default' => 'USD'
				),
				'mode' => array(
								'title' => __('Mode', 'woocommerce'),
			                    'type' => 'select',
			                    'options' => array(
													'test' => 'Test',
													'live' => 'Live'
													),
			                    'default' => 'live',
								'description' => __( 'Test or Live', 'woocommerce' )
							)
				);
		}

		/**
		 * Generate the nihaopayserver button link
		 **/
	    public function generate_nihaopay_form( $order_id ) {
			global $woocommerce;
	        $order = new WC_Order( $order_id );

			wc_enqueue_js('
					jQuery("body").block({
							message: "<img src=\"'.esc_url( $woocommerce->plugin_url() ).'/assets/images/ajax-loader.gif\" alt=\"Redirecting...\" style=\"float:left; margin-right: 10px;\" />'.__('Thank you for your order. We are now redirecting you to verify your card.', 'woothemes').'",
							overlayCSS:
							{
								background: "#fff",
								opacity: 0.6
							},
							css: {
						        padding:        20,
						        textAlign:      "center",
						        color:          "#555",
						        border:         "3px solid #aaa",
						        backgroundColor:"#fff",
						        cursor:         "wait",
						        lineHeight:		"32px"
						    }
						});
					jQuery("#submit_nihaopay_payment_form").click();
				');

				return '<form action="'.esc_url( get_transient('nihaopay_next_url') ).'" method="post" id="nihaopay_payment_form">
						<input type="submit" class="button alt" id="submit_nihaopay_payment_form" value="'.__('Submit', 'woothemes').'" /> <a class="button cancel" href="'.esc_url( $order->get_cancel_order_url() ).'">'.__('Cancel order', 'woothemes').'</a>
					</form>';

		}

		/**
		*
	    * process payment
	    *
	    */
	    function process_payment( $order_id ) {
			global $woocommerce;

	        $order = new WC_Order( $order_id );

	        $time_stamp = date("YmdHis");
	        $orderid = $time_stamp . "-" . $order_id;

	        $nhp_arg[]=array();

			$mark_currency = get_option('woocommerce_currency');

	        $nhp_arg['currency']=$this->currency;

	        if($mark_currency =='CNY'){
	        	$nhp_arg['rmb_amount']=$order->order_total * 100;
	        }else{
	        	if($mark_currency != 'JPY') {
					$nhp_arg['amount']=$order->order_total * 100;
				} else {
					$nhp_arg['amount']=$order->order_total;
				}
	        }

	        $nhp_arg['ipn_url']=$this->notify_url;
	        $nhp_arg['callback_url']=$order->get_checkout_order_received_url();
	        $nhp_arg['show_url']=$order->get_cancel_order_url();
	        $nhp_arg['reference']=$orderid;
	        $nhp_arg['vendor']=$_POST['vendor'];
	        $nhp_arg['terminal']=$this->terminal;
	        $nhp_arg['note']=$order_id;


	        $post_values = "";
	        foreach( $nhp_arg as $key => $value ) {
	            $post_values .= "$key=" . $value . "&";
	        }
	        $post_values = rtrim( $post_values, "& " );

	        $response = wp_remote_post($this->gateway_url, array(
											'body' => $post_values,
											'method' => 'POST',
	                						'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded', 'Authorization' => 'Bearer '.$this->token ),
											'sslverify' => FALSE
											));

			if (!is_wp_error($response)) {
	        	$resp=$response['body'];
			$res=gzcompress(base64_encode(esc_attr($resp)));
	        	$redirect = $this->force_ssl( WP_PLUGIN_URL ."/" . plugin_basename( dirname(__FILE__) ) . '/redirect.php').'?res='. urlencode($res);
				return array(
					'result' 	=> 'success',
					'redirect'	=> $redirect
				);
	        }else{
	        	$woocommerce->add_error( __('Gateway Error.', 'woocommerce') );
	        }
		}
		/**
		 * Payment form on checkout page
		 */
		function payment_fields() {
				global $woocommerce;
				?>
				<?php if ($this->description) : ?><p><?php echo $this->description; ?></p><?php endif; ?>
				<fieldset>
				<legend><label>Method of payment<span class="required">*</span></label></legend>
				<ul class="wc_payment_methods payment_methods methods">
					<li class="wc_payment_method">
						<input id="nihaopay_pay_method_alipay" class="input-radio" name="vendor" checked="checked" value="alipay" data-order_button_text="" type="radio" required>
						<label for="nihaopay_pay_method_alipay"> AliPay </label>
					</li>
					<li class="wc_payment_method">
						<input id="nihaopay_pay_method_wechatpay" class="input-radio" name="vendor" value="wechatpay" data-order_button_text="" type="radio" required>
						<label for="nihaopay_pay_method_wechatpay"> WechatPay </label>
					</li>
					<li class="wc_payment_method">
						<input id="nihaopay_pay_method_unionpay" class="input-radio" name="vendor" value="unionpay" data-order_button_text="" type="radio" required>
						<label for="nihaopay_pay_method_unionpay"> UnionPay </label>
					</li>
				</ul>
				<div class="clear"></div>
				</fieldset>
				<?php
		 }
		/**
		 * receipt_page
		 **/
		function receipt_page( $order ) {
			global $woocommerce;
			echo '<p>'.__('Thank you for your order.', 'woothemes').'</p>';

			echo $this->generate_nihaopay_form( $order );

		}

		private function force_ssl($url){
			if ( 'yes' == get_option( 'woocommerce_force_ssl_checkout' ) ) {
				$url = str_replace( 'http:', 'https:', $url );
			}
			return $url;
		}

		function check_ipn_response() {
            global $woocommerce;
            @ob_clean();
            $note = $_REQUEST['note'];
            $status=$_REQUEST['status'];

            $wc_order   = new WC_Order( absint( $note ) );

            if($status == 'success'){
            	$wc_order->payment_complete();
            	$woocommerce->cart->empty_cart();
            	wp_redirect( $this->get_return_url( $wc_order ) );
                exit;
            }else{
            	wp_die( "Payment failed. Please try again." );
            }
        }
	}

	/**
	 * Add the gateway to WooCommerce
	 **/
	function add_nihaopay_gateway( $methods )
	{
	    $methods[] = 'woocommerce_nihaopay';
	    return $methods;
	}
	add_filter('woocommerce_payment_gateways', 'add_nihaopay_gateway' );
}
