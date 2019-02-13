<?php

namespace SmaartWeb\AutoUpdates;

class PluginsRow extends AutoUpdates {

    /**
     * initialization method
     * @access public
     */
    public function init() {
        add_filter( 'plugin_row_meta', [$this, 'render_view_details_link'], 10, 3 );
        add_filter( 'plugin_row_meta', [$this, 'render_check_for_updates_link'], 10, 3 );
        add_action( 'after_plugin_row_' . $this->plugin_path, [$this, 'show_notice'] );
    }

    /**
     * Add view details link in the plugin row
     * @access public
     */
    public function render_view_details_link( $plugin_meta, $plugin_file, $plugin_data = [] ) {

        if ( $this->plugin_path != $plugin_file || isset( $plugin_data['slug'] ) ) {
            return $plugin_meta;
        }

        $link_index_visit_plugin_site = count( $plugin_meta ) - 1;

        if ( $plugin_data['PluginURI'] ) {
            $plugin_uri = esc_url( $plugin_data['PluginURI'] );
            foreach ( $plugin_meta as $index => $plugin_link ) {
                if ( strpos( $plugin_link, $plugin_uri ) !== FALSE ) {
                    $link_index_visit_plugin_site = $index;
                    break;
                }
            }
        }

        $viewDetailsLink = sprintf( '<a href="%s" class="thickbox open-plugin-details-modal" aria-label="%s" data-title="%s">%s</a>'
                , esc_url( network_admin_url( 'plugin-install.php?tab=plugin-information&plugin=' . urlencode( $this->plugin_slug ) . '&TB_iframe=true&width=600&height=550' ) )
                , esc_attr( sprintf( __( 'More information about %s' ), $plugin_data['Name'] ) ), esc_attr( $plugin_data['Name'] ), __( 'View details' ) );
        $plugin_meta[$link_index_visit_plugin_site] = $viewDetailsLink;
        return $plugin_meta;
    }

    /**
     * Add a "Check for updates" link to the plugin row in the "Plugins" page. 
     * @param array $plugin_meta Array of meta links.
     * @param string $plugin_file
     * @return array
     */
    public function render_check_for_updates_link( $plugin_meta, $plugin_file ) {
        if ( $this->plugin_path != $plugin_file ) {
            return $plugin_meta;
        }

        $linkUrl = wp_nonce_url(
                add_query_arg( ['licensor_check_for_updates' => 1, 'licensor_slug' => $this->plugin_slug]
                        , self_admin_url( 'plugins.php' ) ), 'licensor_check_for_updates' );

        $plugin_meta[] = sprintf( '<a href="%s">%s</a>', esc_attr( $linkUrl ), 'Check for updates' );
        return $plugin_meta;
    }

    /**
     * Print notice error message below the plugin row
     * @access public
     */
    public function show_notice() {
        $messages = [
            'none' => 'License is not set yet. Please enter your license key to enable automatic updates.',
            'expired' => 'Your access to updates has expired. You can continue using the plugin, but you\'ll need to renew your license to receive updates and bug fixes.',
            'invalid' => 'The current license key or site token is invalid. Please enter your license key to enable automatic updates.',
            'deactive' => 'The current license is inactive.',
            'suspended' => 'The current license is suspended.',
            'pending' => 'The current license is pending activation.',
            'wrong_site' => 'Please re-enter your license key. This is necessary because the site URL has changed.'
        ];
        ?>
        <tr class="plugin-update-tr-active">
            <td class="plugin-update colspanchange" colspan="3">
                <div class="expired">
                    <?php echo $messages['expired']; ?>
                </div>
            </td>
        </tr>
        <?php
    }

}
