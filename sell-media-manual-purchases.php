<?php
/**
 * Plugin Name: Sell Media Paystack gateway
 *
 * Description: An extension for Sell Media that allows to paystack gateway.
 * Version: 1.0.3
 * Author: Metric Internet
 * Author URI: http://www.metricinternet.com
 * Author Email: tumininu.ogunsola@metricinternet.com
 * License: GPL
 * Text Domain: sell-media-paystack
 *
 * @package  sell-media
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'SELL_MEDIA_MANUAL_PURCHASES_VERSION', '1.0.3' );
define( 'SELL_MEDIA_MANUAL_PURCHASES_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Sell Media Manual Purchases class.
 */

add_action( 'plugins_loaded', array( 'SellMediaManualPurchases', 'get_instance' ) );
register_activation_hook( __FILE__, array( 'SellMediaManualPurchases', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'SellMediaManualPurchases', 'deactivate' ) );

function sell_media_currencies_naira($currencies) {
    $naira_currencies = array(
    "NGN" => array(
        'name' => 'NGN',
        'title' => __('Naira (&#8358;)','sell_media'),
        'symbol' => "&#8358;"
        )
    );
	$currencies = array_merge($naira_currencies, $currencies);
	return $currencies;
}
add_filter( 'sell_media_currencies','sell_media_currencies_naira');



function sell_media_settings_paystack_payment_gateway(){
    $paystack_gateways = array(
        array(
            'name' => 'Paystack',
            'title' => __('Paystack','sell_media')
            )
        );
    $gateway =array_merge($paystack_gateway, $gateway);
    return apply_filters('sell_media_payment_gateway','sell_media_settings_paystack_payment_gateway', $gateways);
}

class SellMediaManualPurchases {

    private static $instance = null;

	private $settings;

    /**
     * Instance of class.
     * @return object
     */
	public static function get_instance() {
		if ( ! isset( self::$instance ) )
			self::$instance = new self;

		return self::$instance;
	}

    /**
     * Class constructor.
     */
	private function __construct() {
        add_action( 'admin_init', array( $this, 'check_dependency' ) );

        if( !function_exists( 'sell_media_get_plugin_options' ) ){
            return;
        }

        $this->settings = sell_media_get_plugin_options();
		add_action( 'sell_media_payment_gateway_fields', array( &$this, 'form' ) );
		add_action( 'sell_media_before_payment_process', array( $this, 'process' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'assets' ) );
        add_action( 'sell_media_thanks_hook', array( $this, 'thankyou_message' ) );
        add_filter( 'sell_media_options', array( &$this, 'settings' ) );
        add_filter( 'sell_media_payment_tab', array( &$this, 'tab' ) );

	}

    /**
     * Check requirements.
     */
    public function check_dependency() {
        $plugin = plugin_basename( __FILE__ );
        $plugin_data = get_plugin_data( __FILE__, false );

        if ( ! class_exists( 'SellMedia' ) ) {
            if ( is_plugin_active( $plugin ) ) {
                deactivate_plugins( $plugin );
                wp_die( '<strong>' . $plugin_data['Name'] . '</strong> requires the Sell Media plugin to work. Please activate it first. <br /><br />Back to the WordPress <a href="' . get_admin_url( null, 'plugins.php' ) . '">Plugins page</a>.' );
            }
        }
    }

    public function buyer_name(){  ;
    $current_user = wp_get_current_user();
    return $current_user;
    }

    public function buyer_email(){  ;
        $email = $current_user->user_email;
        return $email;
    }

    public function buyer_fullname(){  ;
        $full_name = $current_user->user_firstname. " ". $current_user->user_lastname;
            return $full_name;
    }

    
    

	/**
	 * Add manual purchase field.
	 * @return void
	 */
    public function form(){
        // print_pre( $this->get_contributers_info());
        $settings       = sell_media_get_plugin_options();
        $current_user = wp_get_current_user();
        $email = $current_user->user_email;
        $full_name = $current_user->user_firstname. " ". $current_user->user_lastname;
        
        ?>
         <script src="https://js.paystack.co/v1/inline.js"></script>
        <label for="manual_purchase"><input type="radio" name="gateway" id="manual_purchase" value="manual_purchase" checked><?php echo apply_filters( 'sell_media_manual_purchases_text', __( 'Paystack', 'sell_media' ) ); ?></label><br />
        <div class="sell-media-manual-purchase-meta">
            <p><?php _e( 'Please fill out the fields correctly because the download link will be sent to the email.  Thanks', 'sell_media' ); ?></p>
            <label for="manual_purchase_email" class="sell-media-manual-purchase-label"><?php _e( 'Your email', 'sell_media' ); ?> <input type="email" name="manual_purchase_email" id="manual_purchase_email" ></label>
            <label for="manual_purchase_full_name" class="sell-media-manual-purchase-label"><?php _e( 'Your name', 'sell_media' ); ?> <input type="text" name="manual_purchase_full_name" id="manual_purchase_full_name" ></label>
            <label for="manual_purchase_note" class="sell-media-manual-purchase-label"><?php _e( 'Your message', 'sell_media' ); ?> <textarea name="manual_purchase_note" id="manual_purchase_note" placeholder="<?php _e( 'Type your message to the seller here and click Checkout Now.', 'sell_media' ); ?>">Payment through Paystack for an image</textarea></label>
            <div style="clear: both;"></div>
        </div>

    <?php }

    /**
     * Payment process method.
     * @param  string $gateway Gateway type.
     */
    public function process( $gateway ){

    	if( !isset( $_POST['gateway'] ) || 'manual_purchase' !== $_POST['gateway'] ){
    		return false;
    	}

        $settings       = sell_media_get_plugin_options();

        $current_user = wp_get_current_user();
        $b_email = $current_user->user_email;
        $b_full_name = $current_user->user_firstname. " ". $current_user->user_lastname;

        $email = ( isset( $_POST['manual_purchase_email'] ) && '' != $_POST['manual_purchase_email'] ) ? sanitize_email( $_POST['manual_purchase_email'] ) : '';
        $full_name = ( isset( $_POST['manual_purchase_full_name'] ) && '' != $_POST['manual_purchase_full_name'] ) ? esc_attr( $_POST['manual_purchase_full_name'] ) : '';
        $note = ( isset( $_POST['manual_purchase_note'] ) && '' != $_POST['manual_purchase_note'] ) ? esc_html( $_POST['manual_purchase_note'] ) : '';
        $discount_id    = isset( $_POST['discount-id'] ) ? $_POST['discount-id'] : "";

        $tax_amount = "";

    	if( '' == $email ){
    		wp_die( __( 'Please enter your email address.', 'sell_media' ) );
    	}

        if( '' == $full_name ){
            wp_die( __( 'Please enter your full name.', 'sell_media' ) );
        }

        global $sm_cart;
        $items = $sm_cart->getItems();
        $cart = array();

        $total_print_qty = 0;
        $print_ship_flag = 0;
        foreach( $items as $c ){
            $cart[] = $c;
            if ( 'print' == $c['item_type'] ) {
                $print_ship_flag = 1;
                $total_print_qty+= $c['qty'];
            }
        }

        // Verify total
        $total = $this->verify_total( $cart, $discount_id );


        // If tax is enabled, tax the order
        if (
            isset( $settings->tax[0] ) &&
            'yes' == $settings->tax[0] &&
            ( empty( $settings->tax_display ) ||
            'exclusive' == $settings->tax_display )
        ) {
            $tax_amount = ( $settings->tax_rate * $total );
            $total = $tax_amount + $total;
        }

        /**
         * Add shipping only if purchase contains prints
         *
         * @since version 1.1
         */
        if ( 1 == $print_ship_flag ) {
            switch( $settings->reprints_shipping ){
                case 'shippingFlatRate':
                    $shipping_amount = $settings->reprints_shipping_flat_rate;
                    break;
                case 'shippingQuantityRate':
                    $shipping_amount = $total_print_qty * $settings->reprints_shipping_quantity_rate;
                    break;
                case 'shippingTotalRate':
                    $shipping_amount = round( $total * $settings->reprints_shipping_total_rate, 2 );
                    break;
                default:
                    $shipping_amount = 0;
                    break;
            }
        } else {
            $shipping_amount = 0;
        }

        // Calculate shipping amount
        $total = $shipping_amount + $total;

        $payment_id = wp_insert_post( array(
                'post_title' => $email,
                'post_status' => 'publish',
                'post_type' => 'sell_media_payment'
            ) );

        if ( !is_wp_error( $payment_id ) ){
            $args['email'] = $email;
            $args['full_name'] = $full_name;
            $args['note'] = $note;
            $args['number_products'] = count( $cart );
            $args['total'] = $total;
            $args['tax'] = $tax_amount;
            $args['shipping'] = $shipping_amount;
            $args['discount'] = $discount_id;
            $this->copy_args( $payment_id, $args );
        }

        $customer_info['customer_email'] = $email;
        $customer_info['customer_full_name'] = $full_name;
        $customer_info['customer_note'] = $note;

        $this->send_purchase_email('admin', $customer_info );
        $this->send_purchase_email( 'customer', $customer_info );
        $this->send_purchase_email( 'contributor', $customer_info );

        $sm_cart->clear();


        $location = ( isset( $this->settings->thanks_page ) && '' != $this->settings->thanks_page )? add_query_arg( 'manual', 'true', esc_url( get_permalink( $this->settings->thanks_page ) ) ) : esc_url( home_url( '/' ) );
        wp_redirect( $location );
        exit;

    }

    function copy_args( $payment_id, $args = array() ){

        $tmp = array();
        $p = new SellMediaProducts;
        $settings = sell_media_get_plugin_options();
        $tmp = array(
            'email' => $args['email'],
            'first_name' => $args['full_name'],
            'note' => $args['note'],
            'number_products' => $args['number_products'],
            'gateway' => 'manual',
            'total' => $args['total'],
            'tax' => $args['tax'],
            'shipping' => $args['shipping'],
            'discount' => $args['discount']
        );
        global $sm_cart;
        $products = $sm_cart->getItems();

        if( empty( $products ) )
            return false;
        foreach( $products as $product ){
            $taxonomy = ( 'download' == $product['item_type'] ) ? 'price-group' : 'reprints-price-group';
            $pgroup = ( 'original' == $product['item_pgroup'] ) ? 'original' : $product['item_pgroup'];

            if ( empty( $product['item_license'] ) ){
                $license_desc = null;
                $license_name = null;
                $amount = $p->verify_the_price( $product[ 'item_id' ], $product['item_pgroup'] );
            } else {
                $term_obj = get_term_by( 'id', $product['item_license'], 'licenses' );
                $license_desc = empty( $term_obj ) ? null : $term_obj->description;
                $license_name = empty( $term_obj ) ? null : $term_obj->name;
                $amount = $p->verify_the_price( $product[ 'item_id' ], $product['item_pgroup'] ) + $p->markup_amount( $product['item_id'], $product['item_pgroup'], $product['item_license'] );
            }

            if ( $product['qty'] > 1 ){
                $total = $amount * $product['qty'];
            } else {
                $total = $amount;
            }

            // Old purchase links didn't have attachment_id set
            // So we derive the attachment_id from the product's post_meta

            $product['attachment'] = ( ! empty( $product['attachment'] ) ) ? $product['attachment'] : sell_media_get_attachment_id( $product['item_id'] );

            $tmp_products = array(
                'name' => get_the_title( $product[ 'item_id' ] ),
                'id' => $product[ 'item_id' ],
                'attachment' => $product['attachment'],
                'type' => $product['item_type'],
                'size' => array(
                    'name' => $product['item_size'],
                    'id' => $product['item_pgroup'],
                    'amount' => $amount,
                    'description' => null
                    ),
                'license' => array(
                    'name' => $license_name,
                    'id' => empty( $product['item_license'] ) ? null : $product['item_license'],
                    'description' => null,
                    'markup' => empty( $product['item_license'] ) ? null : str_replace( '%', '', get_term_meta( $product['item_license'], 'markup', true ) )
                    ),
                'qty' => $product[ 'qty' ],
                'total' => $total,
                // 'shipping' => $product[ 'shipping_amount' ]
            );

            $tmp['products'][] = $tmp_products;

        }

        return update_post_meta( $payment_id, '_sell_media_payment_meta', $tmp );
    }

    /**
     * Verify the product totals
     * @param  $products
     * @return $total
     */

    public function verify_total( $products=null, $discount_id ){

        $total = 0;
        $p = new SellMediaProducts;

        foreach( $products as $product ){

            $product_id = $product['item_id'];
            $license_id = empty( $product['item_license'] ) ? null : $product['item_license'];
            $price_id = empty( $product['item_pgroup'] ) ? null : $product['item_pgroup'];

            // this is a download with an assigned license, so add license markup
            if ( '' !== $license_id ) {
                $price = $p->verify_the_price( $product_id, $price_id );
                $markup = $p->markup_amount( $product_id, $price_id, $license_id );
                $amount = $price + $markup;
            } else {
                // this is either a download without a license or a print, so just verify the price
                $amount = $p->verify_the_price( $product_id, $price_id );
            }

            // support for quantities
            if ( $product['qty'] > 1 ){
                $amount = $amount * $product['qty'];
            }

            // If taxes are enabled, subtract taxes from item
            // if ( $settings->tax ) {
            //     $tax_amount = ( $settings->tax_rate * $amount );
            //     $amount = $amount - $tax_amount;
            // }

            // Apply discount
            $amount = apply_filters( 'sell_media_price_filter', $amount, $discount_id, 1 );

            // If taxes are enabled, add taxes onto newly discounted amount
            // if ( $settings->tax ) {
            //     $tax_amount = ( $settings->tax_rate * $amount );
            //     $amount = $amount + $tax_amount;
            // }

            $total += $amount;
        }

        return number_format( ( float ) $total, 2, '.', '' );
    }

   

    /**
     * Send purchase email
     * @param  string $type Type of email reciver
     * @param  array  $info Customer infromation
     * @return string
     */
    function send_purchase_email( $type = 'admin', $info = array() ) {

        $products = $this->formated_product_list( true );

        $message['from_name'] = get_bloginfo( 'name' );
        $message['from_email'] = $email = $this->settings->from_email;

        // send admins and buyers different email subject and body
        if ( 'admin' == $type ) {

            $message['subject'] = __( 'New sale notification (Manual)', 'sell_media' );

            $message['body']  = apply_filters( 'sell_media_email_admin_receipt_message_intro', '<p style="margin: 10px 0;">Congrats! You just made a sale!</p>' );

            if( isset( $info['customer_full_name'] ) ){
                $message['body'] .= '<p style="margin: 10px 0;">' . __( 'Customer', 'sell_media' ) . ': ' . esc_attr( $info['customer_full_name'] ) . '</p>';
            }
            if( isset( $info['customer_email'] ) ){
                $message['body'] .= '<p style="margin: 10px 0;">' . __( 'Email', 'sell_media' ) . ': ' . sanitize_email( $info['customer_email'] ) . '</p>';
            }

            if( isset( $info['customer_note'] ) ){
                $message['body'] .= '<p style="margin: 10px 0;">' . __( 'Note', 'sell_media' ) . ': ' . esc_html( $info['customer_note'] ) . '</p>';
            }

            $message['body'] .= $products;

            return $this->send_email( $email, $message );

        }
        else if( 'customer' == $type ) {

            $email = sanitize_email( $info['customer_email'] );
            $message['subject'] = $this->settings->success_email_subject;
            $message['body'] = $this->settings->success_email_body;
            $tags = array(
                '{first_name}'      => esc_attr( $info['customer_full_name'] ),
                '{last_name}'       => '',
                '{email}'           => $info['customer_email'],
                '{download_links}'  => empty( $products ) ? null : $products,
            );
            $message['body'] = str_replace( array_keys( $tags ), $tags, nl2br( $message['body'] ) );

            return $this->send_email( $email, $message );
        }
        else if( 'contributor' == $type ){
            $contributors = $this->get_contributers_info();
            if( !empty( $contributors ) ){
                foreach ($contributors as $key => $contributor) {
                    $email = sanitize_email( $contributor->data->user_email );
                    $message['subject'] = __( 'New sale notification (Manual)', 'sell_media' );

                    $message['body']  = apply_filters( 'sell_media_email_admin_receipt_message_intro', '<p style="margin: 10px 0;">Congrats! Your photo just made a sale!</p>' );

                    if( isset( $info['customer_full_name'] ) ){
                        $message['body'] .= '<p style="margin: 10px 0;">' . __( 'Customer', 'sell_media' ) . ': ' . esc_attr( $info['customer_full_name'] ) . '</p>';
                    }
                    if( isset( $info['customer_email'] ) ){
                        $message['body'] .= '<p style="margin: 10px 0;">' . __( 'Email', 'sell_media' ) . ': ' . sanitize_email( $info['customer_email'] ) . '</p>';
                    }

                    if( isset( $info['customer_note'] ) ){
                        $message['body'] .= '<p style="margin: 10px 0;">' . __( 'Note', 'sell_media' ) . ': ' . esc_html( $info['customer_note'] ) . '</p>';
                    }

                    $message['body'] .= $products;
                    $this->send_email( $email, $message );
                }

            }
        }
    }

    /**
     * Send email method.
     * @param  string $to   Email address to send email.
     * @param  array  $args Arguments for the email.
     * @return string
     */
    function send_email( $to, $args = array() ){
        if( empty( $args ) ){
            return false;
        }

        $headers = "From: " . stripslashes_deep( html_entity_decode( $args['from_name'], ENT_COMPAT, 'UTF-8' ) ) . " <{$args['from_email']}>\r\n";
        $headers .= "Reply-To: ". $args['from_email'] . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=utf-8\r\n";

        /**
         * Check if we have additional test emails, if so we concatenate them
         */
        if ( ! empty( $this->settings->paypal_additional_test_email ) ){
            $to = $to . ', ' . $this->settings->paypal_additional_test_email;
        }

        // Send the email to buyer
        $r = wp_mail( $to, $args['subject'], html_entity_decode( $args['body'] ), $headers );

        return ( $r ) ? "Sent to: {$to}" : "Failed to send to: {$to}";
    }

    /**
     * Get all the cart item contributers.
     * @return array
     */
    function get_contributers_info(){
        global $sm_cart;
        $items = $sm_cart->getItems();
        $contributors = array();
        if ( $items ) {
            foreach ($items as $key => $item) {
                $commission = get_post_meta( $item['item_id'], '_sell_media_commissions_meta', true );
                if( isset( $commission[ 'holder' ] ) && '' != $commission[ 'holder' ] ){
                    $contributors[] = get_userdata( $commission[ 'holder' ] );
                }
            }
        }

        return $contributors;
    }


    public function get_meta( $post_id = null ) {
		$meta = get_post_meta( $post_id, '_sell_media_payment_meta', true );
		if ( ! empty( $meta ) ) {
			return $unserilaized_meta = maybe_unserialize( $meta );
		}
	}

	/**
	* Get specific key data associated with a payment
	*
	* @param $post_id (int) The post_id for a post of post type "sell_media_payment"
	* @param $key = first_name, last_name, email, gateway, transaction_id, products, total
	*
	* @return Array
	*/
	public function get_meta_key( $post_id = null, $key = null ) {
		$meta = $this->get_meta( $post_id );
		if ( is_array( $meta ) && array_key_exists( $key, $meta ) ) {
			return $meta[ $key ];
		}
    }
  
    /**
     * Formated list of the cart items.
     * @param  boolean $inline_css Is inline css.
     * @return string              List of cart items.
     */
    
    public function formated_product_list( $inline_css=false  ){
        global $sm_cart;
        
    	$css = ( $inline_css ) ? 'border-bottom: 1px solid #ccc; padding: 0.5rem; text-align: left;' : '';
    	$style = apply_filters( 'sell_media_products_table_style', $css );
    	$items = $sm_cart->getItems();
        $tax_rate = ( isset( $this->settings->tax[0] ) && 'yes' == $this->settings->tax[0] && 0<= $this->settings->tax_rate )? $this->settings->tax_rate:0;
        $discount_id = (isset( $_POST['discount-id'] ) && '' != $_POST['discount-id'] )?absint( $_POST['discount-id'] ):0;
    	if ( $items ) {
            $html = null;
            $html .= '<table class="sell-media-products" border="0" width="100%" style="border-collapse:collapse">';
            $html .= '<thead>';
            $html .= '<tr>';
            $html .= '<th style="' . $style . '  font-weight: bold;">' . __( 'Product', 'sell_media' ) . '</th>';
            $html .= '<th style="' . $style . '  font-weight: bold;">' . __( 'Size', 'sell_media' ) . '</th>';
            $html .= '<th style="' . $style . '  font-weight: bold;">' . __( 'License', 'sell_media' ) . '</th>';
            $html .= '<th style="' . $style . '  font-weight: bold;">' . __( 'Download', 'sell_media' ) . '</th>';
            $html .= '<th class="text-center" style="' . $style . ' text-align: center; font-weight: bold;">' . __( 'Qty', 'sell_media' ) . '</th>';
            $html .= '<th class="sell-media-product-subtotal" style="' . $style . ' text-align: right; font-weight: bold;">' . __( 'Subtotal', 'sell_media' ) . '</th>';
            $html .= '</tr>';
            $html .= '</thead>';
            $html .= '<tbody>';

            foreach ( $items as $item ) {

                $item['attachment'] = ( ! empty( $item['item_attachment'] ) ) ? $item['item_attachment'] : sell_media_get_attachment_id( $item['item_id'] );

                if ( ! empty( $item['item_id'] ) ) {

                    $html .= '<tr class="sell-media-product sell-media-product-' . $item['item_id'] . '">';

                    // Product name.
                    $html .= '<td class="sell-media-product-id" style="' . $style . '">';
                    $filename = wp_get_attachment_image_src( $item['attachment'], 'full' );
                    $filename = basename( $filename[0] );
                    if ( isset ( $item['item_id'] ) && ! is_array( $item['item_id'] ) ) {
                        $html .= '<p>#' . $item['item_id'] . ', ' . $item['item_name'] . ', File name: '. $filename . ', Item Type: ' . $item['item_type'] . '</p>';
                        $html .= '<p>' . sell_media_item_icon( $item['attachment'], array(100,100), false ) . '</p>';
                    }
                    $html .= '</td>';

                    // Product size.
                    $html .= '<td class="sell-media-product-size" style="' . $style . '">';
                    if ( isset ( $item['item_pgroup'] ) && ! is_array( $item['item_pgroup'] ) ){
                        if( 'original' == $item['item_pgroup'] ){
                            $html .= $item['item_pgroup'];
                        }
                        else{

                            $size = get_term( (int) $item['item_pgroup'], 'price-group', ARRAY_A );
                            if( !is_wp_error( $size ) ){

                                $html .= $size['name'];
                                $product_width = get_term_meta( (int) $size['term_id'], 'width', true );
                                if( $product_width ){
                                    $html .= "<p>Width: " . $product_width . "</p>";
                                }
                                $product_height = get_term_meta( (int) $size['term_id'], 'height', true );
                                if( $product_height){
                                    $html .= "<p>Height: " . $product_height . "</p>";
                                }

                            }
                        }
                    }
                    $html .= '</td>';

                    // Product license.
                    $html .= '<td class="sell-media-product-license" style="' . $style . '">';
                    if ( isset ( $item['item_license'] ) && ! is_array( $item['item_license'] ) ){
                        $license = get_term( (int) $item['item_license'], 'licenses', ARRAY_A );
                        if( !is_wp_error( $license ) ){
                            $html .= $license['name'] . '<br>' .$license['description'];
                        }
                    }
                    $html .= '</td>';

                    // downloadlink 
                    $attachment_id = sell_media_get_attachment_id( $item['item_id'] );
                    // $link = sprintf( '%s?download=free&product_id=%d&attachment_id=%d&payment_id=free', home_url(), $item['item_id'], $attachment_id );
	                // $html .= '<a href="' . $link . '" title="' . $text . '" data-product-id="' . esc_attr( $post_id ) . '" data-attachment-id="' . esc_attr( $attachment_id ) . '" class="' . $classes . '">' . $text . '</a>';
	
                    $html .= '<td class="sell-media-product-qty text-center" style="' . $style . ' text-align: center;"><a href="'. home_url() . '?download=free&product_id='.$item['item_id'].'&attachment_id='.sell_media_get_attachment_id( $item['item_id'] ).'&payment_id=free">download</a></td>';

                    // Product quantity.
                    $html .= '<td class="sell-media-product-qty text-center" style="' . $style . ' text-align: center;">';
                    if ( isset ( $item['qty'] ) && ! is_array( $item['qty'] ) )
                        $html .= $item['qty'];
                    $html .= '</td>';

                    // Product subtotal.
                    $html .= '<td class="sell-media-product-total" style="' . $style . ' text-align: right;">';
                    if ( isset ( $item['price'] ) && ! is_array( $item['price'] ) ){
                        $html .= sell_media_get_currency_symbol() . sprintf( "%0.2f", $item['price'] * $item['qty'] );
                    }
                    $html .= '</td>';

                    $html .= '</tr>';
                }
            }
            $html .= '</tbody>';
            $html .= '<tfoot>';
            $html .= '<tr>';
            $html .= '<td>&nbsp;</td>';
            $html .= '<td>&nbsp;</td>';
            $html .= '<td>&nbsp;</td>';
            $html .= '<td>&nbsp;</td>';
            $html .= '<td class="sell-media-products-grandtotal" style="border-bottom: 3px solid #ccc; padding: 0.5rem; text-align: right;">';
            $tax = 0;
            $cart_subtotal = $sm_cart->getSubtotal( false );
            if ( $tax_rate ) {
                $tax = $this->calc_tax( $cart_subtotal, $tax_rate, $discount_id );
                $html .= '<p>' . __( 'TAX', 'sell_media' ) . ': ' . sell_media_get_currency_symbol() . number_format( $tax, 2, '.', ',' ) . '</p>';
            }

            $shipping = $this->calc_shipping();
            if ( $shipping ) {
                $html .= '<p>' . __( 'SHIPPING', 'sell_media' ) . ': ' . sell_media_get_currency_symbol() . number_format( $shipping, 2, '.', ',' ) . '</p>';
            }

            if ( $discount_id ) {
                $html .= '<p>' . __( 'DISCOUNT', 'sell_media' ) . ': -' . sell_media_get_currency_symbol() . $this->calc_discount( $cart_subtotal, $discount_id ) . '</p>';
            }

            $total = $this->calc_total( $cart_subtotal, $tax_rate, $discount_id );
            if ( $total ) {
                $html .= '<strong>' . __( 'TOTAL', 'sell_media' ) . ': ' . sell_media_get_currency_symbol() . number_format( $total, 2, '.', ',' ) . '</strong>';
            }
            $html .= '</td>';
            $html .= '</tr>';
            $html .= '</table>';
            return $html;
        }
    }


     /**
     * Thank you message after purchase
     * @return string
     */
    function thankyou_message(){
        global $sm_cart;
        
        if( isset( $_GET['manual'] ) && 'true' == $_GET['manual'] ){
            // global $sm_cart;
            // // $down_link = '';
            // // $items = $sm_cart->getItems();
            // //  if($item){
            // //     foreach($items as $item){
            // //         $down_link .= '<a href="'. home_url() . '?download=free&product_id='.$item['item_id'].'&attachment_id='.sell_media_get_attachment_id( $item['item_id'] ).'&payment_id=free">link</a>';
            // //     }
            // //  }else{
            // //     $down_link = "Thank";
            // //  }

            $products_link = $this->formated_product_list( true );
          _e( 'Thank you for your purchase. We have received your purchase information. Please go to the entered email address for your download link'.$products_link, 'sell_media' );
        }
    }
    
    /**
     * Calculate tax.
     * @param  int  $total       Total amount on cart.
     * @param  int $tax_rate    Tax rate.
     * @param  int  $discount_id Discount post id.
     * @return int
     */
    private function calc_tax( $total, $tax_rate = 0, $discount_id = NULL ){
        $total = $total - $this->calc_discount( $total, $discount_id );
        $total_tax = ( $total * $tax_rate );
        return $total_tax;
    }

    /**
     * Calculate shipping cost.
     * @return int
     */
    private function calc_shipping(){
        return apply_filters( 'sell_media_payment_gateway_handling_cart', 0 );
    }

    /**
     * Calculate discount amount.
     * @param  int $subtotal    Cart subtotal.
     * @param  int $discount_id Discount post id.
     * @return int
     */
    private function calc_discount( $subtotal, $discount_id ){
        return $subtotal - apply_filters( 'sell_media_price_filter', $subtotal, $discount_id );
    }

    /**
     * Calculate grand total.
     * @param  init  $total       Cart subtotal.
     * @param  int $tax_rate    Tax rate.
     * @param  int  $discount_id Discount post id.
     * @return init
     */
    private function calc_total( $total, $tax_rate = 0, $discount_id = NULL ){
        $total = $total + $this->calc_tax( $total, $tax_rate, $discount_id ) - $this->calc_discount( $total, $discount_id );
        $total = $total + $this->calc_shipping();
        return $total;
    }

    /**
     * Enqueue scripts and styles.
     */
    
    public function assets(){

        $settings = sell_media_get_plugin_options();
        $current_user = wp_get_current_user(); 

        if ( is_page( $settings->checkout_page ) ) {

            wp_enqueue_script( 'sell_media_paystack', plugin_dir_url( __FILE__ ) . 'js/sell-media-manual-purchases.js', array( 'jquery', 'sell_media' ), '2.1.4', true );
            wp_enqueue_script( 'sell_media_paystack_checkout_js', 'https://js.paystack.co/v1/inline.js', array( 'jquery' ), '', true );

            // set javascript variables from settings
            wp_localize_script( 'sell_media_paystack', 'sell_media_paystack', array(
                'key' => $this->keys( 'public' ),
                'run_func' => $this->process( 'manual-purchase' ),
                'email'=> $current_user->user_email,
                'name' =>  $current_user->user_firstname. " ". $current_user->user_lastname,
                'buy_text' => __( 'Buy', 'sell_media' ),
                'item_text' => __( 'items', 'sell_media' )
            ) );
        }
    }


    /**
     * Checks if the site is in test mode and returns the correct
     * keys as needed
     *
     * @param $key (string) private | public
     * @return Returns either the test or live key based on the general setting "test_mode"
     *
     */
    public function keys( $key=null ){

        $settings = sell_media_get_plugin_options();

        $keys = array();

        if ( $settings->test_mode == 1 ) {
            $keys = array(
                'private' => $settings->paystack_test_secret_key,
                'public' => $settings->paystack_test_public_key
            );
        } else {
            $keys = array(
                'private' => $settings->paystack_live_secret_key,
                'public' => $settings->paystack_live_public_key
            );
        }

        return $keys[ $key ];
    }


      /**
     * Set Paystack API Keys
     */
    public function set_api_key(){

        $settings = sell_media_get_plugin_options();

        $paystack = array(
            "secret_key"      => $this->keys( 'private' ),
            "public_key" => $this->keys( 'public' )
        );
    }


    /**
     * Creates an array of settings for Paystack
     *
     * @param $options (array) multi-dimensional array of current Sell Media settings.
     * @return The merged array of current Sell Media settings and Paystack settings
     */
    public function settings( $options ){

        $tab = 'sell_media_payment_settings';
        $section = 'paystack_payment_section';

        $additional_options = array(
            'paystack_test_secret_key' => array(
                'tab' => $tab,
                'name' => 'paystack_test_secret_key',
                'title' => __('Paystack Test Secret Key','sell_media'),
                'description' => __(' ','sell_media'),
                'section' => $section,
                'since' => '1.0',
                'id' => $section,
                'default' => '',
                'sanitize' => 'html',
                'type' => 'password'
                ),
            'paystack_test_public_key' => array(
                'tab' => $tab,
                'name' => 'paystack_test_public_key',
                'title' => __('Paystack Test Public Key','sell_media'),
                'description' => __(' ','sell_media'),
                'section' => $section,
                'since' => '1.0',
                'id' => $section,
                'default' => '',
                'sanitize' => 'html',
                'type' => 'password'
                ),
            'paystack_live_secret_key' => array(
                'tab' => $tab,
                'name' => 'paystack_live_secret_key',
                'title' => __('Paystack Live Secret Key','sell_media'),
                'description' => __(' ','sell_media'),
                'section' => $section,
                'since' => '1.0',
                'id' => $section,
                'default' => '',
                'sanitize' => 'html',
                'type' => 'password'
                ),
            'paystack_live_public_key' => array(
                'tab' => $tab,
                'name' => 'paystack_live_public_key',
                'title' => __('Paystack Live Public Key','sell_media'),
                'description' => __(' ','sell_media'),
                'section' => $section,
                'since' => '1.0',
                'id' => $section,
                'default' => '',
                'sanitize' => 'html',
                'type' => 'password'
                )
            );

        return wp_parse_args( $options, $additional_options );
    }


    /**
     * Adds the additional section to the payment tab
     */
    public function tab( $payment_tab ){

        $payment_tab['sections']['paystack_payment_section'] = array(
            'name' => 'paystack_payment_section',
            'title' => __( 'Paystack Settings', 'sell_media' ),
            'description' => sprintf(
                __( 'You must add your <a href="https://dashboard.paystack.com/#/settings/developer" target="_blank">Paystack API Keys</a> to process transactions.', 'sell_media' )
                )
            );

        return $payment_tab;
    }

	public static function activate() {

	}

	public static function deactivate() {

	}

/*
	public static function uninstall() {
		if ( __FILE__ != WP_UNINSTALL_PLUGIN )
			return;
	}
*/
}
