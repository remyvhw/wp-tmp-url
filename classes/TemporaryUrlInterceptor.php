<?php

    
class TemporaryUrlInterceptor {

    public function getCookieName() {
        return "wp_tempurl_cookie";
    }

    public function getCookieHash($time, $salt) {
        $hashContent = $time . $salt;
        $hash = hash_hmac("sha256", $hashContent, get_option('temporary_url_secret_key'));
        $cookieContent = [$time, $salt, $hash];
        return implode('_' , $cookieContent);
    }

    public function setAsAuthorized() {
        setcookie($this->getCookieName(), $this->getCookieHash(time(), bin2hex(random_bytes(10))), time()+31556926, '/');
    }

    public function getIsAuthorizedByCookie() : bool {
        $cookieContent = $_COOKIE[$this->getCookieName()] ?? null;
        
        if (!$cookieContent){
            return false;
        }

        $cookieBites = explode('_', $cookieContent);
        $expectedHmac = $this->getCookieHash($cookieBites[0] ?? '', $cookieBites[1] ?? '');
        
        return $expectedHmac === $cookieContent;
    }
    
    public function intercept_request() {

        // Ignore jetpack urls
        if (strpos($_SERVER['REQUEST_URI'], "jetpack") !== false) {
            return;
        }

        // Ignore any logged in users
        if (is_user_logged_in()) {
            return;
        }

        // Do not intercept on login or admin pages so we avoid logging someone out permanently
        if (is_admin() || $GLOBALS['pagenow'] === 'wp-login.php') {
            return;
        }
        
        // Lastly, check if a session authorization has been set.
        if ($this->getIsAuthorizedByCookie()) {
            return;
        }

        /**
         * Check if we're currently attempting to use a temporary url
         */
        if ($this->attemptTemporaryUrlAuthorization()) {
            $this->setAsAuthorized();
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
