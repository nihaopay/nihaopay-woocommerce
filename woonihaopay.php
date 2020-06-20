<?php
/**
 * Plugin Name: NihaoPay Gateway for WooCommerce
 * Description: Allows you to use UnionPay, AliPay and WechatPay through NihaoPay Gateway
 * Version: 1.0.2
 * Author: nihaopay
 * Author URI: https://nihaopay.com
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
	        $this->wechatpay_icon     		= apply_filters( 'woocommerce_nihaopay_wechatpay_icon', ''.$plugin_dir.'/wechatpay_logo.png' );
	        $this->alipay_icon     		= apply_filters( 'woocommerce_nihaopay_alipay_icon', ''.$plugin_dir.'/alipay_logo.png' );
	        $this->unionpay_icon     		= apply_filters( 'woocommerce_nihaopay_unionpay_icon', ''.$plugin_dir.'/unionpay_logo.png' );
	        $this->has_fields       = true;

	        $this->init_form_fields();
	        $this->init_settings();
			$this->is_mobile       = $this->isMobile();
			$this->is_weixin       = $this->isWeixin();

	        // variables
	        $this->title            = $this->settings['title'];
			$this->token			= $this->settings['token'];
			$this->mode             = $this->settings['mode'];
			$this->currency         = $this->settings['currency'];
	        	$this->notify_url   	= add_query_arg('wc-api', 'wc_nihaopay', home_url('/'));

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
			if ( $this->wechatpay_icon ) {
				$icon.= '<img src="' . $this->force_ssl( $this->wechatpay_icon ) . '" alt="' . $this->title . '" width="29" height="26" />';
			}
			if ( $this->alipay_icon ) {
				$icon.= '<img src="' . $this->force_ssl( $this->alipay_icon ) . '" alt="' . $this->title . '" width="26" height="26" />';
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
		function isMobile()
    	{
    	    $useragent = $_SERVER['HTTP_USER_AGENT'];
    	    return (bool)(
    	        preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i',
    	            $useragent)
    	        || preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i',
    	            substr($useragent, 0, 4))
    	    );
    	}
		function isWeixin(){
        	if ( strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false ) {
            	return true;
        	}
        	return false;
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
	        $nhp_arg['reference']=$orderid;
	        $nhp_arg['vendor']=$_POST['vendor'];
			if ($_POST['vendor'] === 'wechatpay' ){
	        	if ($this->is_weixin) {
	        		$nhp_arg['terminal']= 'WAP';
	        	}else{
	        		$nhp_arg['terminal'] = 'ONLINE';
	        	}

	        }else{
	        	 if($this->is_mobile){
	        		$nhp_arg['terminal'] = 'WAP';
	        	}else{
	        		$nhp_arg['terminal'] = 'ONLINE';
	        	}
	        }
	        
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
			$res=gzdeflate(base64_encode(esc_attr($resp)),9);
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
						<label for="nihaopay_pay_method_wechatpay"> WeChatPay </label>
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
