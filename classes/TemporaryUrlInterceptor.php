<?php

    
class TemporaryUrlInterceptor {
    
    public function intercept_request() {

        // Ignore any logged in users
        if (is_user_logged_in()) {
            return;
        }

        // Do not intercept on login or admin pages so we avoid logging someone out permanently
        if (is_admin() || $GLOBALS['pagenow'] === 'wp-login.php') {
            return;
        }

        // Lastly, check if a session authorization has been set.
        if ($_SESSION['temporary_url_authorized'] ?? false) {
            return;
        }

        /**
         * Handle redirection
         */

         // If no redirection URL is provided, just redirect every guests to the login page.
        if (!get_option('temporary_url_redirection_url') || get_option('temporary_url_redirection_url') === '') {
            return auth_redirect();
        }

        $redirectionUrl = get_option('temporary_url_redirection_url');
        if (strpos($redirectionUrl, '?') === false) {
            $redirectionUrl .= "?";
        }

        $redirectionUrl .= http_build_query(["uri"=>$_SERVER['REQUEST_URI']]);

        
        header("Location: " . $redirectionUrl);
        exit();
        
    }

    public function __construct() {
        add_action("init", array( $this, 'intercept_request' ));
    }

}
