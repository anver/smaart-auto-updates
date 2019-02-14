<?php

namespace SmaartWeb\AutoUpdates;

use WP_Error;

class AutoUpdates {

    /**
     * Set the api url
     * @access protected
     */
    protected $url;

    /**
     * License key
     * @access protected
     */
    protected $license_key;

    /**
     * Product id
     * @access protected
     */
    protected $product_id;

    /**
     * The plugin path
     * @access protected
     */
    protected $plugin_path;

    /**
     * The main plugin filename without the .php extenstion
     * eg: akismet
     * @access protected
     */
    protected $plugin_slug;

    /**
     * The locator object
     * @access private
     * @var Locator The locator object
     */
    private $locator;

    /**
     * Set product id
     * @access public
     * @param int $id The product id
     */
    public function set_product( $id ) {
        $this->product_id = $id;
        return $this;
    }

    /**
     * Sets the license key
     * @access public
     * @param string $key The license key string
     */
    public function set_license( $key ) {
        $this->license_key = $key;
        return $this;
    }

    /**
     * Sets the api url
     * @access public
     * @param str $url The url string
     */
    public function set_url( $url ) {
        $this->url = $url;
        return $this;
    }

    /**
     * Sets the plugin path, the path has to be compliant with WordPress
     * Standards, eg:- "akismet/akismet.php" "google-font-manager/google-font-manager.php"
     * The format is Plugin Directory Name followed by a slash and the main
     * plugin file eg: akismet is the directory + a front slash + akismet.php
     * the main plugin file
     * @access public
     */
    public function set_path( $path ) {
        $this->plugin_path = $path;
        $this->plugin_slug = basename( $path, ".php" );
        return $this;
    }

    /**
     * Inits and adds all the actions
     * @access public
     */
    public function init() {
        ( new PluginsRow() )->set_path( $this->plugin_path )
                ->set_url( $this->url )
                ->set_license( $this->license_key )
                ->set_product( $this->product_id )
                ->init();
        $this->locator = new Locator();
        add_filter( 'pre_set_site_transient_update_plugins', [$this, 'check_updates'] );
        add_filter( 'plugins_api', [$this, 'get_plugin_information'], 10, 3 );
    }

    /**
     * This stores all the new version plugin information into a site transient
     * variable which will be used to download the updates
     * @access public
     * @return mixed Returns the modified transient variable which will be stored by WordPress
     */
    public function check_updates( $transient ) {

        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        $plugin_file = $this->locator->get_plugins_absolute_path( $this->plugin_path );
        $plugin_data = get_plugin_data( $plugin_file );
        $raw_response = $this->get_response( 'plugin_information', ['version' => $plugin_data['Version']] );

        if ( !is_wp_error( $raw_response ) && $raw_response['response']['code'] == 200 ) {
            $response = unserialize( $raw_response['body'] );
        }

        if ( is_object( $response[$this->plugin_path] ) && isset( $response[$this->plugin_path] ) && !empty( $response[$this->plugin_path] ) ) {
            if ( version_compare( $response[$this->plugin_path]->version, $plugin_data['Version'], ">" ) ) {
                $transient->response[$this->plugin_path] = $response[$this->plugin_path];
            }
        }

        return $transient;
    }

    /**
     * Get the plugin information from the remote api 
     * @param bool|object|array $result The result object or array default FALSE
     * If FALSE is returned as result then WordPress takes over
     * @param string $action The action passed by WordPress
     * @param mixed $name Description
     * @access public
     */
    public function get_plugin_information( $result, $action, $args ) {

        if ( $action != 'plugin_information' || !isset( $args->slug ) || $args->slug != $this->plugin_slug ) {
            return $result;
        }

        $plugin_file = $this->locator->get_plugins_absolute_path( $this->plugin_path );
        $plugin_data = get_plugin_data( $plugin_file );
        $raw_response = $this->get_response( 'plugin_information', ['version' => $plugin_data['Version']] );

        if ( is_wp_error( $raw_response ) ) {
            $response = new WP_Error( 'plugins_api_failed', __( 'An Unexpected HTTP Error occurred during the API request.</p> <p><a href="?" onclick="document.location.reload(); return false;">Try again</a>' ), $raw_response->get_error_message() );
        } else {
            $response = unserialize( $raw_response['body'] );

            if ( $response === FALSE ) {
                $response = new WP_Error( 'plugins_api_failed', __( 'An unknown error occurred' ), $raw_response['body'] );
            }
        }

        if ( is_object( $response[$this->plugin_path] ) && isset( $response[$this->plugin_path] ) && !empty( $response[$this->plugin_path] ) ) {
            return $response[$this->plugin_path];
        }

        return $response;
    }

    /**
     * Sends the request to the remote server and receives a response
     * @access public
     */
    public function get_response( $action, $args ) {
        global $wp_version;
        $defaults = [
            'slug' => $this->plugin_slug,
            'version' => '1.0',
            'path' => $this->plugin_path,
            'product' => $this->product_id,
            'domain' => home_url(),
            'action' => $action,
            'license_key' => $this->license_key ? $this->license_key : 'dummy'
        ];

        $request_args = wp_parse_args( $args, $defaults );
        $request_string = ['body' => $request_args, 'user-agent' => 'WordPress/' . $wp_version . '; ' . home_url(), 'timeout' => 600];
        return wp_remote_post( $this->url, $request_string );
    }

}
