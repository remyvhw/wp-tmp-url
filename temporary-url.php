<?php
/**
 * Temporary URLs
 *
 * @package           WP-TemporaryURLs
 * @author            Rémy Vanherweghem
 * @copyright         2020 Rémy Vanherweghem
 * @license           MIT
 *
 * @wordpress-plugin
 * Plugin Name:       Temporary URLs
 * Description:       Prevent access to a Wordpress Site unless guest access it through a temporary URL.
 * Version:           1.0.0
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Rémy Vanherweghem
 * Author URI:        https://example.com
 * Text Domain:       plugin-slug
 * License:           MIT
 * License URI:       https://opensource.org/licenses/MIT
 */
require_once( plugin_dir_path( __FILE__ ) . 'classes/TemporaryUrlSessionInstantiator.php');
require_once( plugin_dir_path( __FILE__ ) . 'classes/TemporaryUrlPluginSettings.php' );
require_once( plugin_dir_path( __FILE__ ) . 'classes/TemporaryUrlInterceptor.php');

class TemporaryUrlPlugin {
    
    public function check_for_plugin() {
        
        if ( ! is_plugin_active( 'wp-session-manager/wp-session-manager.php' ) and current_user_can( 'activate_plugins' ) ) {
            wp_die('Sorry, but this plugin requires "ericmann/wp-session-manager" to be installed and active. <br><a href="' . admin_url( 'plugins.php' ) . '">&laquo; Return to Plugins</a>');
        }
    }

    public function __construct() {
        register_activation_hook( __FILE__, array( $this, 'check_for_plugin') );
        new TemporaryUrlSessionInstantiator();
        new TemporaryUrlPluginSettings();
        new TemporaryUrlInterceptor();
    }

}
new TemporaryUrlPlugin();