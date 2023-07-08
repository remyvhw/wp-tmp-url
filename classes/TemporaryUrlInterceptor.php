<?php


class TemporaryUrlInterceptor
{

    public function getCookieName()
    {
        return "wp_tempurl_authorization";
    }

    public function getIdentityCookieName()
    {
        return "wp_tempurl_identity";
    }

    public function getCookieHash($time, $salt)
    {
        $hashContent = $time . $salt;
        $hash = hash_hmac("sha256", $hashContent, get_option('temporary_url_secret_key'));
        $cookieContent = [$time, $salt, $hash];
        return implode('_', $cookieContent);
    }

    public function setAsAuthorized()
    {
        setcookie($this->getCookieName(), $this->getCookieHash(time(), bin2hex(random_bytes(10))), time() + 28800, '/');
        setcookie($this->getIdentityCookieName(), base64_encode(json_encode([
            'id' => $_GET["tmpurl_identity_display"] ?? null,
            'url' => $_GET['tmpurl_identity_manage'] ?? null,
        ])), time() + 28800, '/');
    }

    public function getIsAuthorizedByCookie(): bool
    {
        $cookieContent = $_COOKIE[$this->getCookieName()] ?? null;

        if (!$cookieContent) {
            return false;
        }

        $cookieBites = explode('_', $cookieContent);
        $expectedHmac = $this->getCookieHash($cookieBites[0] ?? '', $cookieBites[1] ?? '');

        return $expectedHmac === $cookieContent;
    }

    public function add_identity_banner()
    {

        $cookieContent = $_COOKIE[$this->getIdentityCookieName()] ?? null;

        if (!$cookieContent) {
            return;
        }

        try {
            $identityPayload = json_decode(base64_decode($cookieContent), true);
            $id = $identityPayload['id'];
            $url = $identityPayload['url'];
        } catch (\Exception $ex) {
            return;
        }

        if (!$id || !$url) {
            return;
        }

        echo "<div style='background-color: rgb(243, 244, 246);padding:8px;margin:0;display:flex;flex-direction:row;justify-content:flex-end;'>";

        echo "<a style='font-weight: bold;font-size: small;display: flex;flex-direction: row;gap: 0.5em;align-items: center;background-color: rgb(235, 248, 255);padding: 4px;border-radius: 4px;color: rgb(44, 82, 130);box-shadow: rgba(0, 0, 0, 0) 0px 0px 0px 0px, rgba(0, 0, 0, 0) 0px 0px 0px 0px, rgba(0, 0, 0, 0.1) 0px 1px 3px 0px, rgba(0, 0, 0, 0.1) 0px 1px 2px -1px;'";
        echo "target='_blank' href='" . $url . "'>";


        echo '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" style="height: 1.25em;width: 1.25em;"> <path fill-rule="evenodd" d="M10 2.5c-1.31 0-2.526.386-3.546 1.051a.75.75 0 01-.82-1.256A8 8 0 0118 9a22.47 22.47 0 01-1.228 7.351.75.75 0 11-1.417-.49A20.97 20.97 0 0016.5 9 6.5 6.5 0 0010 2.5zM4.333 4.416a.75.75 0 01.218 1.038A6.466 6.466 0 003.5 9a7.966 7.966 0 01-1.293 4.362.75.75 0 01-1.257-.819A6.466 6.466 0 002 9c0-1.61.476-3.11 1.295-4.365a.75.75 0 011.038-.219zM10 6.12a3 3 0 00-3.001 3.041 11.455 11.455 0 01-2.697 7.24.75.75 0 01-1.148-.965A9.957 9.957 0 005.5 9c0-.028.002-.055.004-.082a4.5 4.5 0 018.996.084V9.15l-.005.297a.75.75 0 11-1.5-.034c.003-.11.004-.219.005-.328a3 3 0 00-3-2.965zm0 2.13a.75.75 0 01.75.75c0 3.51-1.187 6.745-3.181 9.323a.75.75 0 11-1.186-.918A13.687 13.687 0 009.25 9a.75.75 0 01.75-.75zm3.529 3.698a.75.75 0 01.584.885 18.883 18.883 0 01-2.257 5.84.75.75 0 11-1.29-.764 17.386 17.386 0 002.078-5.377.75.75 0 01.885-.584z" clip-rule="evenodd"></path></svg>';
        echo "<span>" . $id . "</span>";

        echo "</a>";
        echo "</div>";
    }

    public function intercept_request()
    {

        // Ignore jetpack urls
        if (strpos($_SERVER['REQUEST_URI'], "jetpack") !== false) {
            return;
        }

        // Ignore rest api
        if (strpos($_SERVER['REQUEST_URI'], "/wp-json/") !== false) {
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


    public function attemptTemporaryUrlAuthorization(): bool
    {

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

    protected function handleRedirection()
    {
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

        $redirectionUrl .= http_build_query(["tmpurl_query" => $_SERVER['REQUEST_URI']]);

        header("Location: " . $redirectionUrl);
        exit();
    }

    public function __construct()
    {

        add_action("init", array($this, 'intercept_request'));
        add_action('generate_before_header', array($this, 'add_identity_banner'));



        add_filter('rest_authentication_errors', function ($result) {
            // If a previous authentication check was applied,
            // pass that result along without modification.
            if (true === $result || is_wp_error($result)) {
                return $result;
            }

            // No authentication has been performed yet.
            // Return an error if user is not logged in.
            if (!is_user_logged_in()) {
                return new WP_Error(
                    'rest_not_logged_in',
                    __('You are not currently logged in.'),
                    array('status' => 401)
                );
            }

            // Our custom authentication check should have no effect
            // on logged-in requests
            return $result;
        });
    }
}
