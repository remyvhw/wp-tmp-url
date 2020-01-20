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

require_once( plugin_dir_path( __FILE__ ) . 'classes/TemporaryUrlPluginSettings.php' );

class TemporaryUrlPlugin {
    


    public function __construct() {
        new TemporaryUrlPluginSettings();
    }

}
new TemporaryUrlPlugin();