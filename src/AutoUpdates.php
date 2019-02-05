<?php

namespace SmaartWeb\AutoUpdates;

class AutoUpdates {

    /**
     * Set the api url
     * @access public
     */
    private $url;

    /**
     * License key
     * @access private
     */
    private $license_key;

    /**
     * Product id
     * @access private
     */
    private $product_id;

    /**
     * The plugin path
     * @access public
     */
    private $plugin_path;

    /**
     * The main plugin filename without the .php extenstion
     * eg: akismet
     * @access private
     */
    private $plugin_slug;

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
        return $this;
    }

    /**
     * Sets the plugin slug
     * The name of the main plugin file without the .php extension
     * For instance "akismet" for the akismet.php plugin
     * @access private
     */
    private function set_slug() {
        $this->plugin_slug = basename( $this->plugin_path, '.php' );
    }

    /**
     * Inits and adds all the actions
     * @access public
     */
    public function init() {
        $this->set_slug();
        add_filter( 'pre_set_site_transient_update_plugins', [$this, 'check_updates'] );
        add_filter( 'plugins_api', [$this, 'call_api'], 10, 3 );
        add_action( 'after_plugin_row_' . $this->plugin_path, [$this, 'show_notice'] );
    }

    /**
     * Check for latest updates
     * before wordpress updates the site transient this is called prior to that
     * we use this hook to pre populate the custom plugins information into the
     * transient
     * @access public
     * @return mixed Returns the modified transient variable which will be stored by WordPress
     */
    public function check_updates( $transient ) {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        $args = [
            'slug' => $this->plugin_slug,
            'version' => $transient->checked[$this->plugin_path],
            'product' => $this->product_id,
            'domain' => home_url()
        ];

        if ( $this->license_key ) {
            $args['license'] = $this->license_key;
        }

        $request_string = $this->prepare_request( 'check_update', $args );
        $raw_response = wp_remote_post( $this->url, $request_string );
        $response = null;

        if ( !is_wp_error( $raw_response ) && ($raw_response['response']['code'] == 200) ) {
            $response = unserialize( $raw_response['body'] );
        }

        if ( is_object( $response ) && !empty( $response ) ) {
            $transient->response[$this->plugin_path] = $response;
            return $transient;
        }

        // Check to make sure there is not a similarly named plugin in the wordpress.org repository
        if ( isset( $transient->response[$this->plugin_path] ) ) {
            if ( strpos( $transient->response[$this->plugin_path]->package, 'wordpress.org' ) !== false ) {
                unset( $transient->response[$this->plugin_path] );
            }
        }

        return $transient;
    }

    /**
     * This hook is called whenever the plugin update link is clicked by the
     * user or individual updates takes place
     * @param bool|object|array $resObj The result object or array default FALSE
     * @access public
     */
    public function call_api( $resObj, $action, $args ) {
        if ( !isset( $args->slug ) || $args->slug != $this->plugin_slug ) {
            return $resObj;
        }

        $plugin_info = get_site_transient( 'update_plugins' );
        $request_args = [
            'slug' => $this->plugin_slug,
            'version' => isset( $plugin_info->checked ) ? $plugin_info->checked[$this->plugin_path] : '1.0',
            'product' => $this->product_id,
            'domain' => home_url()
        ];

        if ( $this->license_key ) {
            $request_args['license'] = $this->license_key;
        }

        $request_string = $this->prepare_request( $action, $request_args );
        $raw_response = wp_remote_post( $this->url, $request_string );

        if ( is_wp_error( $raw_response ) ) {
            $response = new WP_Error( 'plugins_api_failed', __( 'An Unexpected HTTP Error occurred during the API request.</p> <p><a href="?" onclick="document.location.reload(); return false;">Try again</a>' ), $raw_response->get_error_message() );
        } else {
            $response = unserialize( $raw_response['body'] );
            if ( $response === FALSE ) {
                $response = new WP_Error( 'plugins_api_failed', __( 'An unknown error occurred' ), $raw_response['body'] );
            }
        }

        return $response;
    }

    /**
     * Print notice error message below the plugin row
     * @access public
     */
    public function show_notice() {
        $license = $this->wpls_authorize_action();

        if ( $license->info->status === 'Active' ) {
            return;
        }

        $messages = [
            'no_license_yet' => __( 'License is not set yet. Please enter your license key to enable automatic updates.', 'wpls' ),
            'Expired' => __( 'Your access to updates has expired. You can continue using the plugin, but you\'ll need to renew your license to receive updates and bug fixes.', 'wpls' ),
            'Invalid' => __( 'The current license key or site token is invalid. Please enter your license key to enable automatic updates.', 'wpls' ),
            'Deactive' => __( 'The current license is inactive.', 'wpls' ),
            'Suspended' => __( 'The current license is suspended.', 'wpls' ),
            'Pending' => __( 'The current license is pending activation.', 'wpls' ),
            'Wrong_site' => __( 'Please re-enter your license key. This is necessary because the site URL has changed.', 'wpls' ),
        ];

        if ( $this->license_key ) {
            $status = $license->info->status;
        } else {
            $status = 'no_license_yet';
        }

        $notice = isset( $messages[$status] ) ? $messages[$status] : __( 'The current license is invalid.', 'wpls' );
        $licenseLink = $this->makeLicenseLink( apply_filters( 'wpls_plugin_row_link_text-' . $this->plugin_slug, __( 'Enter License Key', 'wpls' ) ) );
        $showLicenseLink = $status !== 'expired';
    }

    /**
     * prepares the request
     * @access public
     */
    public function prepare_request( $action, $args ) {
        global $wp_version;
        $request = [
            'body' => ['action' => $action, 'request' => serialize( $args )],
            'user-agent' => 'WordPress/' . $wp_version . '; ' . home_url()
        ];
        return $request;
    }

}
