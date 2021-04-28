<?php
   /*
   Plugin Name: GWS Technologies - Mauritius Shipping Regions for WooCommerce
   Plugin URI: https://www.gws-technologies.com/
   description: Adds Shipping Regions for Mauritius
   Version: 1.0.1
   Author: GWS Technologies
   Author URI: https://www.gws-technologies.com/
   Forked From: https://github.com/Boufel/gws-mru-shipping-regions-woocommerce_plugin_version
   */

   
/**
 * Die if accessed directly
 */
defined( 'ABSPATH' ) or die( __('You can not access this file directly!', 'gws-mru-shipping-regions-woocommerce') );

/**
 * Check if WooCommerce is active
 */
if(in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {

    class WC_States_Places {

        private $version = '1.0.1';
        private $states;
        private $places;

        /**
         * Construct class
         */
        public function __construct() {
            add_action( 'plugins_loaded', array( $this, 'init') );
        }

        /**
         * WC init
         */
        public function init() {
            $this->init_fields();
            $this->init_states();
            $this->init_places();

            if (get_site_option('gws-mru-shipping-regions-woocommerce_plugin_version') != $this->version) {
                $this->setup();
                update_option("gws-mru-shipping-regions-woocommerce_plugin_version", $this->version);
            }
        }

        public static function setup(){
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
           
            $sql = file_get_contents(plugin_dir_path(__FILE__) . "/sql/gws_postcodes.sql");
        
            dbDelta($sql);
        }

        /**
         * WC Fields init
         */
        public function init_fields() {
            add_filter('woocommerce_default_address_fields', array($this, 'wc_change_state_and_city_order'));
        }

        /**
         * WC States init
         */
        public function init_states() {
            add_filter('woocommerce_states', array($this, 'wc_states'));
        }

        /**
         * WC Places init
         */
        public function init_places() {
            add_filter( 'woocommerce_billing_fields', array( $this, 'wc_billing_fields' ), 10, 2 );
            add_filter( 'woocommerce_shipping_fields', array( $this, 'wc_shipping_fields' ), 10, 2 );
            add_filter( 'woocommerce_form_field_city', array( $this, 'wc_form_field_city' ), 10, 4 );

            add_action( 'wp_enqueue_scripts', array( $this, 'load_scripts' ) );            
        }

        /**
         * Change the order of State and City fields to have more sense with the steps of form
         * @param mixed $fields
         * @return mixed
         */         
        public function wc_change_state_and_city_order($fields) {
            $fields['state']['priority'] = 70;
            $fields['city']['priority'] = 80;
            /* translators: Translate it to the name of the State level territory division, e.g. "State", "Province",  "Department" */
            $fields['state']['label'] = __('Region', 'gws-mru-shipping-regions-woocommerce_plugin_version');
            /* translators: Translate it to the name of the City level territory division, e.g. "City, "Municipality", "District" */
            $fields['city']['label'] = __('Locality', 'gws-mru-shipping-regions-woocommerce_plugin_version');             

            return $fields;
        }
            

        /**
         * Implement WC States
         * @param mixed $states
         * @return mixed
         */
        public function  wc_states($states) {
            //get countries allowed by store owner
            $allowed = $this->get_store_allowed_countries();

            if (!empty( $allowed ) ) {
                foreach ($allowed as $code => $country) {
                    if (! isset( $states[$code] ) ) {
                        global $wpdb;
                        $states_results = $wpdb->get_results( "SELECT DISTINCT(state_code),state FROM gws_postcodes ORDER BY 'state'" );
                        foreach($states_results as $state){
                            $states['MU'][$state->state_code] = $state->state;
                        }
                    }
                }
            }

            return $states;
        }

        /**
         * Modify billing field
         * @param mixed $fields
         * @param mixed $country
         * @return mixed
         */
        public function wc_billing_fields( $fields, $country ) {
            $fields['billing_city']['type'] = 'city';

            return $fields;
        }

        /**
         * Modify shipping field
         * @param mixed $fields
         * @param mixed $country
         * @return mixed
         */
        public function wc_shipping_fields( $fields, $country ) {
            $fields['shipping_city']['type'] = 'city';

            return $fields;
        }

        /**
         * Implement places/city field
         * @param mixed $field
         * @param string $key
         * @param mixed $args
         * @param string $value
         * @return mixed
         */
        public function wc_form_field_city($field, $key, $args, $value ) {
            // Do we need a clear div?
            if ( ( ! empty( $args['clear'] ) ) ) {
                $after = '<div class="clear"></div>';
            } else {
                $after = '';
            }

            // Required markup
            if ( $args['required'] ) {
                $args['class'][] = 'validate-required';
                $required = ' <abbr class="required" title="' . esc_attr__( 'required', 'woocommerce'  ) . '">*</abbr>';
            } else {
                $required = '';
            }

            // Custom attribute handling
            $custom_attributes = array();

            if ( ! empty( $args['custom_attributes'] ) && is_array( $args['custom_attributes'] ) ) {
                foreach ( $args['custom_attributes'] as $attribute => $attribute_value ) {
                    $custom_attributes[] = esc_attr( $attribute ) . '="' . esc_attr( $attribute_value ) . '"';
                }
            }

            // Validate classes
            if ( ! empty( $args['validate'] ) ) {
                foreach( $args['validate'] as $validate ) {
                    $args['class'][] = 'validate-' . $validate;
                }
            }

            // field p and label
            $field  = '<p class="form-row ' . esc_attr( implode( ' ', $args['class'] ) ) .'" id="' . esc_attr( $args['id'] ) . '_field">';
            if ( $args['label'] ) {
                $field .= '<label for="' . esc_attr( $args['id'] ) . '" class="' . esc_attr( implode( ' ', $args['label_class'] ) ) .'">' . $args['label']. $required . '</label>';
            }

            // Get Country
            $country_key = $key == 'billing_city' ? 'billing_country' : 'shipping_country';
            $current_cc  = WC()->checkout->get_value( $country_key );

            $state_key = $key == 'billing_city' ? 'billing_state' : 'shipping_state';
            $current_sc  = WC()->checkout->get_value( $state_key );

            // Get country places
            $places = $this->get_places( $current_cc );

            if ( is_array( $places ) ) {

                $field .= '<select name="' . esc_attr( $key ) . '" id="' . esc_attr( $args['id'] ) . '" class="city_select ' . esc_attr( implode( ' ', $args['input_class'] ) ) .'" ' . implode( ' ', $custom_attributes ) . ' placeholder="' . esc_attr( $args['placeholder'] ) . '">';

                $field .= '<option value="">'. __( 'Select an option&hellip;', 'woocommerce' ) .'</option>';

                if ( $current_sc && array_key_exists( $current_sc, $places ) ) {
                    $dropdown_places = $places[ $current_sc ];
                } else if ( is_array($places) &&  isset($places[0])) {
                    $dropdown_places = array_reduce( $places, 'array_merge', array() );
                    sort( $dropdown_places );
                } else {
                    $dropdown_places = $places;
                }

                foreach ( $dropdown_places as $city_name ) {
                    if(!is_array($city_name)) {
                        $field .= '<option value="' . esc_attr( $city_name ) . '" '.selected( $value, $city_name, false ) . '>' . $city_name .'</option>';
                    }
                }

                $field .= '</select>';

            } else {

                $field .= '<input type="text" class="input-text ' . esc_attr( implode( ' ', $args['input_class'] ) ) .'" value="' . esc_attr( $value ) . '"  placeholder="' . esc_attr( $args['placeholder'] ) . '" name="' . esc_attr( $key ) . '" id="' . esc_attr( $args['id'] ) . '" ' . implode( ' ', $custom_attributes ) . ' />';
            }

            // field description and close wrapper
            if ( $args['description'] ) {
                $field .= '<span class="description">' . esc_attr( $args['description'] ) . '</span>';
            }

            $field .= '</p>' . $after;

            return $field;
        }
        /**
         * Get places
         * @param string $p_code(default:)
         * @return mixed
         */
        public function get_places( $p_code = null ) {
            if ( empty( $this->places ) ) {
                $this->load_country_places();
            }

            if ( ! is_null( $p_code ) ) {
                return isset( $this->places[ $p_code ] ) ? $this->places[ $p_code ] : false;
            } else {
                return $this->places;
            }
        }
        /**
         * Get country places
         * @return mixed
         */
        public function load_country_places() {
            global $places;

            $allowed =  $this->get_store_allowed_countries();

            if ( $allowed ) {
                foreach ( $allowed as $code => $country ) {
                    if ( ! isset( $places[ $code ] ) ) {
                        global $wpdb;
                        $states_results = $wpdb->get_results( "SELECT * FROM gws_postcodes ORDER BY 'place'" );
                        foreach($states_results as $state){
                            $places['MU'][$state->state_code][] = $state->place;
                        }
                    }
                }
            }

            $this->places = $places;
        }

        /**
         * Load scripts
         */
        public function load_scripts() {
            if ( is_cart() || is_checkout() || is_wc_endpoint_url( 'edit-address' ) ) {

                $city_select_path = $this->get_plugin_url() . 'js/place-select.js';
                wp_enqueue_script( 'wc-city-select', $city_select_path, array( 'jquery', 'woocommerce' ), $this->version, true );

                $places = json_encode( $this->get_places() );
                wp_localize_script( 'wc-city-select', 'wc_city_select_params', array(
                    'cities' => $places,
                    'i18n_select_city_text' => esc_attr__( 'Select an option&hellip;', 'woocommerce' )
                ) );
            }
        }

        /**
         * Get plugin root path
         * @return mixed
         */
        private function get_plugin_path() {
            if (isset($this->plugin_path)) {
                return $this->plugin_path;
            }
            $path = $this->plugin_path = plugin_dir_path( __FILE__ );

            return untrailingslashit($path);
        }

        /**
         * Get Store allowed countries
         * @return mixed
         */
        private function get_store_allowed_countries() {
            return array_merge( WC()->countries->get_allowed_countries(), WC()->countries->get_shipping_countries() );
        }

        /**
         * Get plugin url
         * @return mixed
         */
        public function get_plugin_url() {

            if (isset($this->plugin_url)) {
                return $this->plugin_url;
            }

            return $this->plugin_url = plugin_dir_url( __FILE__ );
        }
    }
    /**
     * Instantiate class
     */
    $GLOBALS['wc_states_places'] = new WC_States_Places();


    /**
     * Need to move to it's own plugin
     */
    

}
?>
