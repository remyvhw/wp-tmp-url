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
        
        $authorizedUntil = intval( $_SESSION['temporary_url_authorized'] ) ?? false;
        if ($authorizedUntil && (time() < intval($authorizedUntil))) {
            return;
        }

        /**
         * Check if we're currently attempting to use a temporary url
         */
        if ($this->attemptTemporaryUrlAuthorization()) {
            $_SESSION['temporary_url_authorized'] = intval(time()) + 6*3600;
            return;
        }


        /**
         * Handle redirection
         */
        $this->handleRedirection();
        
        
    }


    public function attemptTemporaryUrlAuthorization() : bool {

        // Some parameters are missing
        if (!$_GET["tmpurl_hash"] || !$_GET["tmpurl_expiration"] || !$_GET["tmpurl_salt"]) {
            return false;
        }

        // Authorization has expired
        $expiration = intval($_GET["tmpurl_expiration"]);
        
        if ($expiration < time()) {
            return false;
        }
        $salt = $_GET["tmpurl_salt"];

        if (strlen($salt) < 12) {
            return false;
        }

        $comparisonString = $expiration . $salt;

        $expectedHmac = hash_hmac("sha256", $comparisonString, get_option('temporary_url_secret_key'));
        return $expectedHmac === $_GET["tmpurl_hash"];

    }

    protected function handleRedirection() {
         // If no redirection URL is provided, just redirect every guests to the login page.
         if (!get_option('temporary_url_redirection_url') || get_option('temporary_url_redirection_url') === '') {
            return auth_redirect();
        }

        $redirectionUrl = get_option('temporary_url_redirection_url');
        if (strpos($redirectionUrl, '?') === false) {
            $redirectionUrl .= "?";
        } else {
            $redirectionUrl .= "&";
        }

        $redirectionUrl .= http_build_query(["tmpurl_query"=>$_SERVER['REQUEST_URI']]);

        header("Location: " . $redirectionUrl);
        exit();
    }

    public function __construct() {
        add_action("init", array( $this, 'intercept_request' ));
    }

}
