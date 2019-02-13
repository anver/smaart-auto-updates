<?php

namespace SmaartWeb\AutoUpdates;

class Locator {

    /**
     * Gets the absolute path from the 
     * @access public
     */
    public function get_plugins_absolute_path( $plugin_path ) {
        //return trailingslashit( substr_replace( __FILE__, PLUGINDIR, strpos( __FILE__, PLUGINDIR ) ) ) . $plugin_path;
        return WP_PLUGIN_DIR . '/' . $plugin_path;
    }

}
