<?php

class TemporaryUrlPluginSettings {
    public $pluginPrefix = "tmp_url";

    public function plugin_settings_page_content() {
        ?>
    <div class="wrap">
        <h2>Temporary URL settings</h2>
        <form method="post" action="options.php">
            <?php
                settings_fields( 'tmp_url_fields' );
                do_settings_sections( 'tmp_url_fields' );
                submit_button();
            ?>
        </form>
    </div> <?php
    }

    public function create_plugin_settings_page() {
        // Add the menu item and page
        $page_title = 'Temporary URL plugin settings.';
        $menu_title = 'Temporary URL';
        $capability = 'manage_options';
        $slug = $this->pluginPrefix;
        $callback = array( $this, 'plugin_settings_page_content' );
        $icon = 'dashicons-admin-links';
        $position = 100;
        add_submenu_page('options-general.php', $page_title, $menu_title, $capability, $slug, $callback);
    }


    public function setup_sections() {
        add_settings_section(  'tmp_url_shared_secret', '', false, 'tmp_url_fields' );
        add_settings_section(  'tmp_url_redirection_url', '', false, 'tmp_url_fields' );
    }

    public function setup_fields() {
        $fields = [
            [
                'uid' => 'temporary_url_redirection_url',
                'label' => 'Guest redirection URL',
                'section' => 'tmp_url_redirection_url',
                'type' => 'text',
                'options' => false,
                'placeholder' => 'https://example.com/redirect-to-wordpress-temp-url',
                'helper' => false,
                'supplemental' => "We'll redirect guest to that URL when they visit the site without having first visited a temporary URL (we'll take note of the visit in their PHP session). We'll append a <code>?tmpurl_query=</code> parameter to that URL that you can use to redirect back to the intended page. If no URL is supplied, users will be redirect to the regular WP login page.",
                'default' => ""
            ],
            [
                'uid' => 'temporary_url_secret_key',
                'label' => 'Shared secret key',
                'section' => 'tmp_url_shared_secret',
                'type' => 'text',
                'options' => false,
                'placeholder' => '',
                'helper' => false,
                'supplemental' => "This secret key will be used to generate a hash parameter (<code>&tmpurl_hash=</code>) for the temporary URL. The hash will take the following string: <code>{secret}{expiration}{salt}</code>, where both expiration and salt are passed through a URL parameter (<code>&tmpurl_expiration=</code> and <code>&tmpurl_salt=</code>). We'll check that the expiration (expressed as a timestamp in seconds since Epoch) is not yet past, that the salt is longer than 12 characters and we'll use the following function to check the genrated string again'st the provided hash: <code>password_verify(\$string, \$hash);</code>. To generate a password, you could use the following function <code>password_hash(\$string, PASSWORD_DEFAULT);</code>. Note that, even if you are using <code>PASSWORD_BCRYPT</code> and this function can manually take a salt value, the salt must always be concatenated to the <code>\$string</code>.",
                'default' => bin2hex(openssl_random_pseudo_bytes(10))
            ]
        ];
        foreach( $fields as $field ){
            add_settings_field( $field['uid'], $field['label'], array( $this, 'field_callback' ), 'tmp_url_fields', $field['section'], $field );
            register_setting( 'tmp_url_fields', $field['uid'] );
        }
    }

    public function field_callback( $arguments ) {
        $value = get_option( $arguments['uid'] ); // Get the current value, if there is one
        if( ! $value ) { // If no value exists
            $value = $arguments['default']; // Set to our default
        }
    
        // Check which type of field we want
        switch( $arguments['type'] ){
            case 'text': // If it is a text field
                printf( '<input name="%1$s" id="%1$s" type="%2$s" placeholder="%3$s" value="%4$s" />', $arguments['uid'], $arguments['type'], $arguments['placeholder'], $value );
                break;
        }
    
        // If there is help text
        if( $helper = $arguments['helper'] ){
            printf( '<span class="helper"> %s</span>', $helper ); // Show it
        }
    
        // If there is supplemental text
        if( $supplimental = $arguments['supplemental'] ){
            printf( '<p class="description">%s</p>', $supplimental ); // Show it
        }
    }


    public function __construct() {
        add_action( 'admin_menu', array( $this, 'create_plugin_settings_page' ) );
        add_action( 'admin_init', array( $this, 'setup_sections' ) );
        add_action( 'admin_init', array( $this, 'setup_fields' ) );


    }

}
