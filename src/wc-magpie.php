<?php
/**
* Plugin Name: Magpie Payment for WooCommerce
* Plugin URI: https://magpie.im
* Description: Pay using Visa, MasterCard, JCB, PayMaya, GCash and online banking.
* Version: 1.0.1
* Author: Magpie.IM Inc.
* Author URI: https://github.com/flairlabs
* License: GPL2
*/

defined( 'ABSPATH' ) or exit;

// Make sure WooCommerce is active
if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    return;
}

/**
 * Add the gateway to WC Available Gateways
 * 
 * @since 1.0.0
 * @param array $gateways all available WC gateways
 * @return array $gateways all WC gateways + magpie gateway
 */
function magpie_add_to_gateways( $gateways ) {
    $gateways[] = 'Checkout_Magpie';
    return $gateways;
}
add_filter( 'woocommerce_payment_gateways', 'magpie_add_to_gateways' );

/**
 * Adds plugin page links
 * 
 * @since 1.0.0
 * @param array $links all plugin links
 * @return array $links all plugin links + our custom links (i.e., "Settings")
 */
function magpie_checkout_plugin_links( $links ) {

    $plugin_links = array(
        '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=magpie_checkout' ) . '">' . __( 'Configure', 'wc-checkout-magpie' ) . '</a>'
    );

    return array_merge( $plugin_links, $links );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'magpie_checkout_plugin_links' );

/**
 * Magpie Payment Gateway
 *
 * Provides an Magpie Payment Gateway; mainly for testing purposes.
 * We load it later to ensure WC is loaded first since we're extending it.
 *
 * @class       Checkout_Magpie
 * @extends     WC_Payment_Gateway
 * @version     1.0.0
 * @package     WooCommerce/Classes/Payment
 * @author      Magpie
 */
add_action( 'plugins_loaded', 'magpie_checkout_init', 11 );

function magpie_checkout_init() {

    class Checkout_Magpie extends WC_Payment_Gateway {

        /**
         * Constructor for the gateway
         */
        public function __construct() {
            
            require_once dirname(__FILE__) . '/wc-magpie-post.php';

            $this->id                 = 'magpie_checkout';
            $this->icon               = apply_filters('woocommerce_offline_icon', '');
            $this->has_fields         = false;
            $this->method_title       = __( 'Magpie Payment', 'wc-checkout-magpie' );
            $this->method_description = __( 'Lets you accept Magpie payments via Checkout. Your customer is redirected to the secure hosted payment page.', 'wc-checkout-magpie' );
            
            // Load the settings.
            $this->magpie_init_form_fields();
            $this->init_settings();

            $this->title        = $this->get_option( 'title' );
            $this->description  = $this->get_option( 'description' );
            $this->instructions = $this->get_option( 'instructions', $this->description );

            // Actions
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) );
        }
    
    
        /**
         * Initialize Gateway Settings Form Fields
         */
        public function magpie_init_form_fields() {
      
            $this->form_fields = apply_filters( 'wc_offline_form_fields', array(
                
                'title' => array(
                    'title'       => __( 'Title', 'wc-checkout-magpie' ),
                    'type'        => 'text',
                    'description' => __( 'Title name for the payment', 'wc-checkout-magpie' ),
                    'default'     => __( 'Magpie Checkout', 'wc-checkout-magpie' ),
                    'desc_tip'    => true,
                ),
                
                'description' => array(
                    'title'       => __( 'Description', 'wc-checkout-magpie' ),
                    'type'        => 'textarea',
                    'description' => __( 'Payment method description that the customer will see on your checkout.', 'wc-checkout-magpie' ),
                    'default'     => __( 'Pay using Visa, MasterCard, JCB, PayMaya, GCash and online banking.', 'wc-checkout-magpie' ),
                    'desc_tip'    => true,
                ),

                'p_key' => array(
                    'title'   => __( 'Secret Key', 'wc-checkout-magpie' ),
                    'type'    => 'password',
                    'label'   => __( 'Secret key to access Magpie API', 'wc-checkout-magpie' ),
                    'description' => __( 'Get the secret key from your Magpie Dashboard.', 'wc-checkout-magpie' ),
                    'default' => '',
                    'placeholder' => 'sk_live_xxxxxxxxxx'
                ),

                'client_reference' => array(
                    'title'   => __( 'Client Reference ID', 'wc-checkout-magpie' ),
                    'type'    => 'text',
                    'label' => __('Client Reference ID', 'wc-checkout-magpie'),
                    'description' => __( 'A string to prefix your order IDs, so that Checkout and WooCommerce can track each unique order.', 'wc-checkout-magpie' ),
                    'default' => 'woocommerce-magpie',
                    'placeholder' => ''
                ),

                'cancel_url' => array(
                    'title'   => __( 'Cancel URL', 'wc-checkout-magpie' ),
                    'type'    => 'text',
                    'description' => __( 'URL endpoint if transaction is cancelled', 'wc-checkout-magpie' ),
                    'label'   => __( 'Magpie Checkout calls this URL if the transaction is canceled.', 'wc-checkout-magpie' ),
                    'placeholder' => 'https://example.com/cancelled'
                ),

                'branding_title' => array(
                    'title'   => __( '<h2>Branding</h2>', 'wc-checkout-magpie' ),
                    'type'    => 'hidden',
                    "style" => ""
                ),

                'logo_url' => array(
                    'title'   => __( 'Logo URL', 'wc-checkout-magpie' ),
                    'type'    => 'text',
                    'description' => __( 'The URL of your logo. Optional: if not supplied, Checkout gets it from Dashboard branding settings. If supplied, overrides your branding settings. (We recommend you set branding options in the Dashboard.)', 'wc-checkout-magpie' ),
                    'label'   => __( 'Web location of your logo', 'wc-checkout-magpie' ),
                    'placeholder' => 'https://example.com/images/example-logo.png'
                ),

                'icon_url' => array(
                    'title'   => __( 'Icon URL', 'wc-checkout-magpie' ),
                    'type'    => 'text',
                    'description' => __( 'The URL of your icon. Optional: if not supplied, Checkout gets it from Dashboard branding settings. If supplied, overrides your branding settings. (We recommend you set branding options in the Dashboard.)', 'wc-checkout-magpie' ),
                    'label'   => __( 'Web location of your icon', 'wc-checkout-magpie' ),
                    'placeholder' => 'https://example.com/images/example-icon.png'
                ),

                'icon_logo_toggle' => array(
                    'title'   => __( 'Use logo instead of icon', 'wc-checkout-magpie' ),
                    'type'    => 'checkbox'
                ),

                'brand_color' => array(
                    'title'   => __( 'Brand color', 'wc-checkout-magpie' ),
                    'type'    => 'color',
                    'default' => '#fefefe'
                ),

                'accent_color' => array(
                    'title'   => __( 'Accent color', 'wc-checkout-magpie' ),
                    'type'    => 'color',
                    'default' => '#45a0f1'
                ),

                'payment_method_title' => array(
                    'title'   => __( '<h2>Payment Methods</h2>', 'wc-checkout-magpie' ),
                    'type'    => 'hidden'
                ),

                'bank_payment_title' => array(
                    'title'   => __( 'Banks', 'wc-checkout-magpie' ),
                    'type'    => 'hidden'
                ),
                'bpi_toggle' => array(
                    'title'   => __( 'BPI', 'wc-checkout-magpie' ),
                    'type'    => 'checkbox',
                    'label' => ' ',
                    'default' => 'yes'
                ),
                //disable not yet available
                // 'bdo_toggle' => array(
                //     'title'   => __( 'BDO', 'wc-checkout-magpie' ),
                //     'type'    => 'checkbox',
                //     'label' => ' ',
                //     'default' => 'yes'
                // ),
                // 'metro_toggle' => array(
                //     'title'   => __( 'Metrobank', 'wc-checkout-magpie' ),
                //     'type'    => 'checkbox',
                //     'label' => ' ',
                //     'default' => 'yes'
                // ),
                // 'pnb_toggle' => array(
                //     'title'   => __( 'Philippine National Bank', 'wc-checkout-magpie' ),
                //     'type'    => 'checkbox',
                //     'label' => ' ',
                //     'default' => 'yes'
                // ),
                // 'rcbc_toggle' => array(
                //     'title'   => __( 'RCBC', 'wc-checkout-magpie' ),
                //     'type'    => 'checkbox',
                //     'label' => ' ',
                //     'default' => 'yes'
                // ),

                'ub_toggle' => array(
                    'title'   => __( 'UnionBank', 'wc-checkout-magpie' ),
                    'type'    => 'checkbox',
                    'label' => ' ',
                    'default' => 'yes'
                ),

                'wallet_payment_title' => array(
                    'title'   => __( 'E-Wallets', 'wc-checkout-magpie' ),
                    'type'    => 'hidden',
                    'css' => 'padding:0',
                    
                ),
                'gcash_toggle' => array(
                    'title'   => __( 'GCash', 'wc-checkout-magpie' ),
                    'type'    => 'checkbox',
                    'label' => ' ',
                    'default' => 'yes'
                ),
                'paymaya_toggle' => array(
                    'title'   => __( 'PayMaya', 'wc-checkout-magpie' ),
                    'type'    => 'checkbox',
                    'label' => ' ',
                    'default' => 'yes'
                ),
                // 'grab_toggle' => array(
                //  'title'   => __( 'Grab Pay', 'wc-checkout-magpie' ),
                //  'type'    => 'checkbox',
                //     'label' => ' '
                // ),
                // 'coins_toggle' => array(
                //  'title'   => __( 'Coins.ph', 'wc-checkout-magpie' ),
                //  'type'    => 'checkbox',
                //     'label' => ' '
                // ),
                'ali_toggle' => array(
                    'title'   => __( 'Alipay', 'wc-checkout-magpie' ),
                    'type'    => 'checkbox',
                    'label' => ' ',
                    'default' => 'yes'
                ),

                'union_toggle' => array(
                    'title'   => __( 'UnionPay', 'wc-checkout-magpie' ),
                    'type'    => 'checkbox',
                    'label' => ' ',
                    'default' => 'yes'
                ),

                'wechat_toggle' => array(
                    'title'   => __( 'WeChat Pay', 'wc-checkout-magpie' ),
                    'type'    => 'checkbox',
                    'label' => ' ',
                    'default' => 'yes'
                ),

                

                'mode_toggle'   => array(
                    'title'       => __( 'Payment Mode', 'wc-checkout-magpie' ),
                    'type'        => 'select',
                    'description' => __( 'For more information, check out <strong>authorize</strong> and <strong>capture</strong> <a href="https://www.360payments.com/capture-vs-authorization-heres-what-you-need-to-know/">here</a> and <a href="https://www.godaddy.com/garage/authorize-vs-authorize-and-capture/">here</a>', 'wc-checkout-magpie' ),
                    'options'     => array(
                        'payment' => 'Purchase - authorize and capture in one go',
                        'setup'  => 'Authorize only - you need to capture separately'
                    ),
                    'default' => 'payment'
                ),

            //     'billing_details' => array(
            //         'title' => 'Billing Details',
            //         'description' => 'Description for your option shown on the settings page',
            //         'type' => 'select',
            //         'default' => 1,
            //         'options' => array(
            //              '1' => 'Auto(default)',
            //              '2' => 'Required'
            //         ) // array of options for select/multiselects only
            //    )

            ) );
        }
    
        /**
         * Process the payment and return the result
         *
         * @param int $order_id
         * @return array
         */
        public function process_payment( $order_id ) {

            return $this->magpie_checkout_session($order_id);
            
        }

        
    public function magpie_checkout_session($order_id){

        // Get the user ID from an Order ID
        // $user_id = get_post_meta( $order_id, '_customer_user', true );
        $order = wc_get_order($order_id);
        // Get the WP_User instance Object
        $customer_id = $order->get_user_id();

        $customer = new WC_Customer($customer_id);

        $magpie_post = new Magpie_Post();
        
        $magpie_cust_id = "";

        $header = base64_encode($this->get_option( 'p_key' ). ':');

        if($customer->get_meta("magpie_cust_id") == null){
        //if empty create customer and save id to woocommerce meta data

                $customerObj = array(
                        "email" => $customer->get_email(),
                        "description" => $customer->get_first_name()." ".$customer->get_last_name(),
                        "metadata" => array()
                    );
                //create customer obj using magpie API
                $magpie_cust_id = $magpie_post->create_customer($header, $customerObj);
                //update user meta data on woocommerce database
                update_user_meta( $customer_id, 'magpie_cust_id', $magpie_cust_id );    
        }else{
              //if customer id is already created, get customer id from woocomerce database
              $magpie_cust_id = $customer->get_meta("magpie_cust_id");
        }


        $name = $order->get_billing_first_name().' '.$order->get_billing_last_name();
        $cnumber = get_post_meta( $order_id, '_billing_card_number', true );
        $exp_month = get_post_meta( $order_id, '_billing_expiry_month', true );
        $exp_year = get_post_meta( $order_id, '_billing_expiry_year', true );
        $cvc = get_post_meta( $order_id, '_billing_cvv', true );
        $address_line1 = $order->get_billing_address_1().' '.$order->get_billing_address_2();
        $address_city = $order->get_billing_city();
        $address_state = $order->get_billing_state();
        $address_zip = $order->get_billing_postcode();
        $address_line2email = $order->get_billing_email();
        $total_amount = $order->get_total();


        $use_logo = false;

        $paymethod_array = $this->get_payment_methods();

        if($this->get_option('mode_toggle') == "setup"){
            $paymethod_array = array("card");
        }

        if($this->get_option('icon_logo_toggle') == "yes"){
        $use_logo = true;
        }

        $sessionObj = array(
            "amount" => $total_amount,
            "billing_address_collection" => "auto",
            "branding" => array(
                "icon" => $this->get_option( 'icon_url' ),
                "logo" => $this->get_option( 'logo_url' ),
                "use_logo" => $use_logo,
                "primary_color"=> $this->get_option("brand_color"),
                "secondary_color"=> $this->get_option("accent_color")
            ),
            "cancel_url"=> $this->get_option( 'cancel_url' ),
            "client_reference_id"=> $this->get_option( 'client_reference' ).'-'.$order_id,
            "currency"=> "php",
            "customer"=> $magpie_cust_id,
            "customer_email"=> $customer->get_email(),
            "line_items" => $this->get_items($order),
            "locale"=> "en",
            "payment_method_types" => $paymethod_array,
            "shipping_address_collection"=>null,
            "submit_type" => "pay",
            "success_url" => $this->get_return_url( $order ),
            "mode" => $this->get_option('mode_toggle'),
        );

        return $magpie_post->checkout_session($header,$sessionObj);
    }

    public function get_payment_methods(){

        $array = array();
        array_push($array,"card");
        if($this->get_option('bpi_toggle') == "yes"){
            array_push($array,"bpi");
        }

        // if($this->get_option('bdo_toggle') == "yes"){array_push($array,"bdo");}
        // if($this->get_option('metro_toggle') == "yes"){array_push($array,"metrobank");}
        // if($this->get_option('pnb_toggle') == "yes"){array_push($array,"pnb");}
        // if($this->get_option('rcbc_toggle') == "yes"){array_push($array,"rcbc");}

        if($this->get_option('ub_toggle') == "yes"){array_push($array,"unionbank");}

        if($this->get_option('gcash_toggle') == "yes"){array_push($array,"gcash");}
        if($this->get_option('paymaya_toggle') == "yes"){array_push($array,"paymaya");}

        // if($this->get_option('grab_toggle') == "yes"){array_push($array,"grab");}
        // if($this->get_option('coins_toggle') == "yes"){array_push($array,"coins");}

        if($this->get_option('ali_toggle') == "yes"){array_push($array,"alipay");}
        if($this->get_option('union_toggle') == "yes"){array_push($array,"unionpay");}
        if($this->get_option('wechat_toggle') == "yes"){array_push($array,"wechat");}

        return $array;
    }

    
    public function get_items($order){
        $data_items = array();
        foreach( $order->get_items() as $item_id => $item ){
            //Get the product ID
            $product_id = $item->get_product_id();
        
            //Get the variation ID
            $variation_id = $item->get_variation_id();
        
            //Get the WC_Product object
            $product = $item->get_product();
        
            // The quantity
            $quantity = $item->get_quantity();
        
            // The product name
            $product_name = $item->get_name(); //   OR: $product->get_name();
        
            //Get the product SKU (using WC_Product method)
            $sku = $product->get_sku();
        
            // Get line item totals (non discounted)
            $total     = $item->get_subtotal(); // Total without tax (non discounted)
            $total_tax = $item->get_subtotal_tax(); // Total tax (non discounted)
            
            // Get line item totals (discounted when a coupon is applied)
            $total     = $item->get_total(); // Total without tax (discounted)
            $total_tax = $item->get_total_tax(); // Total tax (discounted)

            // DOM NOTE: Should we not get the values here from WooCommerce data?
            $image_id  = $product->get_image_id();
            $image_url = wp_get_attachment_image_url( $image_id, 'full' );

            $data = array(
                "name" => $product_name,
                "description" => $product->get_short_description(),
                "quantity" => $quantity,
                "amount" => (int) ((float) $product->get_price() * 100),
                "currency"=> "php",
                 "image"=> $image_url
            );

            array_push($data_items,$data);
        }

        return $data_items;
    }

  } // end \Checkout_Magpie class

}

?>