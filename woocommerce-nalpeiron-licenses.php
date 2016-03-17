<?php
/**
 * Plugin Name: WooCommerce Nalpeiron Licenses
 * Description: WooCommerce add-on that requests a license from Nalpeiron for each product purchased.
 * Version: 0.5
 * Author: Cinnamon Toast
 * Author URI: http://cinnamontoast.ca
 */

class Nalpeiron_Licenses {

	/**
    *   Stores current Order ID 
    */
    public $orderID;
    
    /**
    *   Nalpeiron Settings 
    */
    private $options;


    /**
    *   Initialize Plugin and Add Hooks 
    */

    public function __construct() {
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        if (is_admin()) {
            add_action('init', array($this, 'handleAdminRequests'));
            add_action('admin_init', array($this, 'action_add_metaboxes'));
            add_action('admin_menu', array($this, 'add_plugin_page'));
            add_action('admin_init', array($this, 'page_init'));
            add_action('admin_notices', array($this, 'nalpeiron_admin_notice'));
            add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_action_links'));
        } 
        add_action('woocommerce_product_options_general_product_data', array($this, 'nalpeiron_product_id_field'));
        add_action('woocommerce_process_product_meta', array($this, 'nalpeiron_product_id_field_save'));
        add_action('woocommerce_before_my_account', array($this, 'product_license_codes'));
        add_action('woocommerce_order_details_after_order_table', array($this, 'order_details_license_codes'));

        if ($this->check_credentials() === true) {
            add_action('woocommerce_order_status_changed', array($this, 'action_woocommerce_order_status_completed'));
            add_action('scheduled_subscription_payment', array($this, 'subscription_renewal_payment'), 1, 2);
        }
    }

    public function activate() {
        global $wpdb;
        $query =   "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}woocommerce_licenses
                    (
                        license_id integer auto_increment primary key,
                        product_id integer not null,
                        order_id integer not null,
                        license_code varchar(50),
                        license_status varchar(50),
                        subscription_period integer not null,
                        creation_date datetime,
                        activation_date datetime,
                        latestrenewal_date datetime,
                        expiration_date datetime
                    )";
        $wpdb->query($query);
    }

    public function deactivate() {
        
    }

    public function check_credentials() {
        $options = get_option('nalpeiron');
        if ($options['username'] != null && $options['password'] != null && $options['customer_id'] != null) {
            return true;
        } else {
            return false;
        }
    }

    public function nalpeiron_admin_notice() { 
        if ($this->check_credentials() === false) : ?>
        <div class="error">
            <p><?php _e('You must enter your Nalpeiron login credentials in order to request license codes.'); ?>&nbsp;&nbsp; <a href="<?php echo admin_url( 'options-general.php?page=nalpeiron-settings' ); ?>"><?php _e('Nalpeiron Settings'); ?></a></p>
        </div>
        <?php endif;
    }

    public function add_action_links ( $links ) {
        $mylinks = array('<a href="' . admin_url( 'options-general.php?page=nalpeiron-settings' ) . '">'.__('Settings').'</a>');
        return array_merge( $mylinks, $links );
    }


    /**
    *   Add Nalpeiron Product ID Meta Field to Products
    */

    function nalpeiron_product_id_field() {
        global $woocommerce, $post;
        echo '<div class="options_group">';
        woocommerce_wp_text_input( array( 
                'id'                => '_nalpeiron_product_id', 
                'label'             => __( 'Nalpeiron Product ID', 'woocommerce' ), 
                'placeholder'       => '', 
                'description'       => __( 'Enter the ID value from Nalpeiron that corresponds with this product', 'woocommerce' )
            ) );
        echo '</div>';
    }

    function nalpeiron_product_id_field_save( $post_id ) { 
        $woocommerce_nalpeiron_product_id = $_POST['_nalpeiron_product_id'];
        update_post_meta( $post_id, '_nalpeiron_product_id', esc_attr( $woocommerce_nalpeiron_product_id ) );
    }


    /**
    *   Add Metaboxes to Wordpress backend
    */

    public function action_add_metaboxes() {    
        if ($this->check_credentials() === true) {
            add_meta_box('product-license-metabox', __('Nalpeiron Licenses'), array($this, 'product_licenses_metabox'), 'product', 'normal', 'core');  
            add_meta_box('order-license-metabox', __('Nalpeiron Licenses'), array($this, 'order_licenses_metabox'), 'shop_order', 'normal', 'core');  
        }
    }

    public function product_licenses_metabox( $post ) {
        global $wpdb;
        $product_id = $post->ID;
        $query = "SELECT * FROM {$wpdb->prefix}woocommerce_licenses WHERE product_id = $product_id AND license_status = 'assigned'";
        $licenses = $wpdb->get_results($query);
        require_once dirname(__FILE__) . '/templates/product-metabox.php';
    }

    public function order_licenses_metabox( $post ) {
        global $wpdb;
        $order_id = $post->ID;
        $query = "SELECT * FROM {$wpdb->prefix}woocommerce_licenses WHERE order_id = $order_id AND license_status = 'assigned'";
        $licenses = $wpdb->get_results($query);
        require_once dirname(__FILE__) . '/templates/order-metabox.php';
    }

    public function product_license_codes() {
        global $wpdb;
        $customer_orders = get_posts( apply_filters( 'woocommerce_my_account_my_orders_query', array(
            'numberposts' => -1,
            'meta_key'    => '_customer_user',
            'meta_value'  => get_current_user_id(),
            'post_type'   => wc_get_order_types( 'view-orders' ),
            'post_status' => array_keys( wc_get_order_statuses() )
        ) ) );

        if (!empty($customer_orders)) {
            $query = "SELECT * FROM {$wpdb->prefix}woocommerce_licenses WHERE license_status = 'assigned' AND (";
            $i = 1;
            foreach ($customer_orders as $o) {
                $query .= "order_id = $o->ID";
                if ($i < count($customer_orders)) {
                    $query .= " OR ";
                }
                $i++;
            }
            $query .= ")";
        }
         
        $licenses = $wpdb->get_results($query);
        if (!empty($licenses)) {
            require_once dirname(__FILE__) . '/templates/myaccount-licenses.php';
        }
    }

    public function order_details_license_codes( $order ) {
        global $wpdb;

        $query = "SELECT * FROM {$wpdb->prefix}woocommerce_licenses WHERE order_id = $order->id AND license_status = 'assigned'";
        $licenses = $wpdb->get_results($query);
        if (!empty($licenses)) {
            require_once dirname(__FILE__) . '/templates/order-details-licenses.php';
        }
    }


    /**
    *   Delete Licenses from Wordpress backend
    */

    public function handleAdminRequests() {
        global $wpdb;

        $task = isset($_REQUEST['task']) ? $_REQUEST['task'] : null;
        if (!$task)
            return false;
        if ($task == 'delete_license') {
            global $wpdb;
            $post_id = (int) $_REQUEST['post'];
            $wpdb->delete($wpdb->prefix . 'woocommerce_licenses', array('license_id' => (int) $_REQUEST['id']));
            $link = admin_url('post.php?post=' . $post_id . '&action=edit&message=4');
            wp_redirect($link);
            die();
        }
    }


    /**
    *   Add Nalpeiron Licenses Order Meta
    */

    public function action_woocommerce_order_status_completed( $order_id ) {
        if (is_object($order_id)) {
            $order_id = $order_id->id;
        }
        $this->orderID = $order_id;
        $order = new WC_Order($order_id);
        if ( $order->status == 'completed' ) {
            $items = $order->get_items();
            foreach ( $items as $key => $value ) {
                if ( empty($value['nalpeiron_license']) ) {
                    $this->action_woocommerce_add_order_item_meta($key, array('quantity' => $value['item_meta']['_qty'][0], 'product_id' => $value['item_meta']['_product_id'][0]));
                }
            }
        }
    }


    /**
    *   Add WooCommerce Order Notes for Order
    */

    public function add_order_note($content) {
        $current_user = wp_get_current_user();
        $commentdata = array(
            'comment_post_ID' => $this->orderID,
            'comment_author' => $current_user->display_name,
            'comment_author_email' => $current_user->user_email,
            'comment_author_url' => '',
            'comment_content' => $content,
            'comment_type' => 'order_note',
            'comment_parent' => '0',
            'user_id' => '0',
            'comment_author_IP' => '',
            'comment_agent' => 'WooCommerce',
            'comment_approved' => 1
        );
        wp_insert_comment($commentdata);
    }


    /**
    *   Get Licenses from Nalpeiron and Store in Database
    */

    public function action_woocommerce_add_order_item_meta($item_id, $values) {
        global $wpdb;

        $license_type = $wpdb->get_var('SELECT meta_value FROM ' . $wpdb->prefix . 'woocommerce_order_itemmeta WHERE meta_key = \'license-type\' and order_item_id = ' . $item_id );
        $subscription_period = ( false !== strpos( $license_type , 'Annual Commitment' ) ) ? 365 : 31;

        $product_no = (int) $values['product_id'];
        $qty_no = (int) $values['quantity'];
        
        $nalpeiron_id = get_post_meta($product_no, '_nalpeiron_product_id', true);
        if ($nalpeiron_id == null || $nalpeiron_id == '') {
            $this->add_order_note('Skipping Nalpeiron license request: Nalpeiron Product ID was not found');
            return false;
        }

        $codes = array();

        // Add Order Note //
        if ($qty_no >= 1) { $plurral = 's'; } else { $plurral = null; }
        $this->add_order_note('Request sent to Nalpeiron for '.$qty_no.' license'.$plurral.' expiring in '.$subscription_period.' days');
        
        $nalp_keys = $this->get_nalpeiron_key( $qty_no, $product_no, $subscription_period );
        if ( empty($nalp_keys) ) {
            $this->add_order_note('There was an error with the response from Nalpeiron, no licenses were obtained.');
            return false;
        }
        
        for ( $x = 0 ; $x < $qty_no; $x++ ) {
            $wpdb->insert( "{$wpdb->prefix}woocommerce_licenses" , array(
                'product_id' => $product_no ,
                'license_code' => $nalp_keys[$x],
                'license_status' => 'assigned',
                'creation_date' => current_time('mysql'),
            ));
            $codes[] = array( 'license_id' => $wpdb->insert_id , 'license_code' => $nalp_keys[$x] );
        }
        $license_id = array();
        $licenseString = "";
        $i = 1;

        $codeString = '';

        foreach ($codes as $code) {
            $license_id[] = $code['license_id'];
            $licenseString =  $code['license_code'];
            $licenses[] = $licenseString;
            if ( !empty( $codeString ) ) {
                $codeString .= "\n";
            }
            $codeString .= $licenseString;
            woocommerce_add_order_item_meta($item_id, '_nalpeiron_license', $licenseString);
        }

        wp_update_post(array(
            'ID' => $this->orderID,
            'post_excerpt' => "License Codes:\n" . $codeString
        ));

        if (!empty($licenses) && count($licenses) > 0) {
            $this->add_order_note(count($codes). " Licenses received from Nalpeiron: \n".implode("\n", $licenses));
        } else {

        }
      
        $product_name = '';
        $order = new WC_Order( $this->orderID );
        if ( count( $order->get_items() ) > 0 ) {
            foreach( $order->get_items() as $item ) {
                if ( 'line_item' == $item['type'] ) {
                    $_product = $order->get_product_from_item( $item );
                    if ( isset( $_product->product_custom_fields ) ) {
                        if ( !empty( $product_name ) ) {
                            $product_name .= ' , ';
                        }
                        if ( isset( $_product->product_custom_fields['attribute_billing-period'] ) ) {
                            $product_name .= $_product->product_custom_fields['attribute_billing-period'][0];
                        }
                        if ( isset( $_product->product_custom_fields['attribute_product-type'] ) ) {
                            $product_name .= ' - ' . $_product->product_custom_fields['attribute_product-type'][0];
                        }
                    }
                }
            }
        }        

        $this->_setLicenseCodeStatus(implode($license_id, ','), 'assigned', $subscription_period);
        $this->_setLicenseCodeOrder(implode($license_id, ','), $this->orderID);     
    }


    /**
    *   Update License Information on Renewal Payment
    */

    function subscription_renewal_payment($user_id, $subscription_key) {
        global $wpdb;
        $options = get_option('nalpeiron');
        $subscription = WC_Subscriptions_Manager::get_subscription($subscription_key);
        $product_id = $subscription['product_id'];
        $nalpeiron_id = get_post_meta($product_id, '_nalpeiron_product_id', true);
        $order_id = $subscription['order_id'];
        $query = "SELECT * FROM {$wpdb->prefix}woocommerce_licenses WHERE order_id = $order_id AND license_status = 'assigned'";
        $licenses = $wpdb->get_results($query);
        foreach ($licenses as $license) {
            if ( empty( $license ) ) return false;
            $auth = '<auth><username>'.$options['username'].'</username><password>'.$options['password'].'</password><customerid>'.$options['customer_id'].'</customerid></auth>';
            $data = '<data>'
                    .'<productid>'.$nalpeiron_id.'</productid>'
                    .'<licensecode>' . $license->license_code . '</licensecode>'
                    .'<enabledforuse>1</enabledforuse>'
                    .'<subscriptionperiod>' . $license->subscription_period . '</subscriptionperiod>'
                    .'<subscriptionenddate></subscriptionenddate>'
                    .'<clientsallowed>1</clientsallowed>'
                    .'</data>';
            $xml = $this->get_nalpeiron_response( $auth , $data , 'UpdateLicenseCode' );
            $this->_setLicenseRenewal($license->license_id, $license->subscription_period);
            $this->add_order_note('Renewing Nalpeiron license for '.$license->subscription_period.' days');
            if ( empty( $xml ) or false === strpos( $xml , 'OK' ) ) {
                error_log('Error: Empty Nalpeiron Response. Nalpeiron Request Sent: '.$data);
                return false;
            }
        }
        return true;
    }

    /**
    *   Update License Information in Database
    */

    protected function _setLicenseCodeStatus($license_id, $status, $subscription_period) {
        global $wpdb;
        $wpdb->query("UPDATE {$wpdb->prefix}woocommerce_licenses SET license_status = '$status' WHERE license_id in ($license_id)");
        if ($status == 'assigned') {
            $activation_date = date('Y-m-d H:i:s');
            $expiration_date = date('Y-m-d H:i:s', strtotime("+ ".$subscription_period." days"));
            $wpdb->query("UPDATE {$wpdb->prefix}woocommerce_licenses SET activation_date = '$activation_date', subscription_period = '$subscription_period', expiration_date = '$expiration_date' WHERE license_id in ($license_id)");            
        }
    }

    protected function _setLicenseCodeOrder($license_id, $order_id) {
        global $wpdb;
        $wpdb->query("UPDATE {$wpdb->prefix}woocommerce_licenses SET order_id = $order_id WHERE license_id in ($license_id)");
    }    

    protected function _setLicenseRenewal($license_id, $subscription_period) {
        global $wpdb;

        $licenses = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}woocommerce_licenses WHERE license_id = $license_id");
        $license = array_shift(array_values($licenses));
        $latestrenewal_date = date('Y-m-d H:i:s'); 
        $expiration_date = date('Y-m-d H:i:s', strtotime("+ ".$subscription_period." days"));
        $wpdb->query("UPDATE {$wpdb->prefix}woocommerce_licenses SET latestrenewal_date = '$latestrenewal_date', expiration_date = '$expiration_date' WHERE license_id in ($license_id)");
    }


    /**
    *   Build and Make Request to Nalpeiron
    */

    function get_nalpeiron_key( $quantity, $product_id, $subscription_period = 31 ) {
        global $post;

        $options = get_option('nalpeiron');
        $nalpeiron_id = get_post_meta($product_id, '_nalpeiron_product_id', true);
        $auth = '<auth><username>'.$options['username'].'</username><password>'.$options['password'].'</password><customerid>'.$options['customer_id'].'</customerid></auth>';
        $data = '<data><productid>'.$nalpeiron_id.'</productid><amount>'.$quantity.'</amount><profilename></profilename></data>';

        $xml = $this->get_nalpeiron_response( $auth , $data , 'GetNextLicenseCode' );
        if ( empty( $xml ) ) {
            error_log('Error: Empty Nalpeiron Response. Nalpeiron Request Sent: '.$auth.$data);
            return false;
        }
        $array = json_decode(json_encode($xml), true);
        if ( empty( $array ) or !is_array( $array ) ) {
            error_log('Error: Empty Nalpeiron Response. Nalpeiron Request Sent: '.$auth.$data);
            return false;
        }

        if (strpos($array[0], ',')) {
            $licenses = explode(',', $array[0]);
        } else {
            $licenses[0] = $array[0];
        }

        foreach ($licenses as $license) {
            if ( empty( $license ) or !is_numeric( $license ) ) return false;
            $data = '<data>'
                .'<productid>'.$nalpeiron_id.'</productid>'
                .'<licensecode>' . $license . '</licensecode>'
                .'<enabledforuse>1</enabledforuse>'
                .'<subscriptionperiod>' . $subscription_period . '</subscriptionperiod>'
                .'<subscriptionenddate></subscriptionenddate>'
                .'<clientsallowed>1</clientsallowed>'
                .'</data>';

            $xml = $this->get_nalpeiron_response( $auth , $data , 'UpdateLicenseCode' );
            if ( empty( $xml ) or false === strpos( $xml , 'OK' ) ) {
                error_log('Error: Empty Nalpeiron Response. Nalpeiron Request Sent: '.$data);
                return false;
            }
        }
        
        return $licenses;
    }


    /**
    *   Return and Parse Response from Nalpeiron
    */
    
    function get_nalpeiron_response( $auth , $data , $call ) {
        $url = 'https://my.nalpeiron.com/shaferws.asmx/' . $call . '?Auth=' . urlencode( $auth ) . '&Data=' . urlencode( $data );
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 300);
        $data = curl_exec($ch);
        error_log('Nalpeiron Response: '.$data);
        curl_close($ch);
        try {
            $xml = simplexml_load_string( $data ,'SimpleXMLElement', LIBXML_NOCDATA );
            if ( false !== strpos( $xml[0] , '<' ) ) {
                $doc = new DOMDocument();
                $doc->loadXML( $xml[0] );
                $xml = simplexml_import_dom($doc);
            }           
        } catch (Exception $e) {
            return null;
        }
        return $xml;
    }


    /**
    *   Settings Page for Nalpeiron Login Credentials
    */

    public function add_plugin_page() {
        add_options_page(
            __('Nalpeiron Settings'), __('Nalpeiron Settings'), 'manage_options', 'nalpeiron-settings', array($this, 'create_admin_page')
        );
    }

    public function page_init() {
        register_setting(
            'nalpeiron_credentials_group', 
            'nalpeiron',
            array($this, 'sanitize') 
        );

        add_settings_section(
            'setting_section_id',
            '',
            array($this, 'print_section_info'),
            'nalpeiron_credentials'
        );

        add_settings_field('username', __('Nalpeiron Username'), array($this, 'username_callback'), 'nalpeiron_credentials', 'setting_section_id');
        add_settings_field('password', __('Nalpeiron Password'), array($this, 'password_callback'), 'nalpeiron_credentials', 'setting_section_id');
        add_settings_field('customer_id', __('Nalpeiron Customer ID'), array($this, 'customer_callback'), 'nalpeiron_credentials', 'setting_section_id');
    }

    public function create_admin_page() {
        $this->options = get_option('nalpeiron');
        ?>
        <div class="wrap">
            <h2><?php _e('Nalpeiron License Settings'); ?></h2>           
            <form method="post" action="options.php">
                <?php
                settings_fields('nalpeiron_credentials_group');
                do_settings_sections('nalpeiron_credentials');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }
    
    public function sanitize($input) {
        $new_input = array();
        if (isset($input['username']))
            $new_input['username'] = strip_tags(trim($input['username']));

        if (isset($input['password']))
            $new_input['password'] = strip_tags(trim($input['password']));

        if (isset($input['customer_id']))
            $new_input['customer_id'] = strip_tags(trim($input['customer_id']));

        return $new_input;
    }

    public function print_section_info() {
        _e('Please enter the login credentials below for your Nalpeiron account. The plugin will remain disabled until the credentials are entered.');
    }

    public function username_callback() {
        printf('<input type="text" name="nalpeiron[username]" value="%s">', !empty($this->options['username']) ? $this->options['username'] : '');
    }

    public function password_callback() {
        printf('<input type="password" name="nalpeiron[password]" value="%s">', !empty($this->options['password']) ? $this->options['password'] : '');
    }

    public function customer_callback() {
        printf('<input type="number" name="nalpeiron[customer_id]" value="%s">', !empty($this->options['customer_id']) ? $this->options['customer_id'] : '');
    }
}

$wc_licenses = new Nalpeiron_Licenses();
?>