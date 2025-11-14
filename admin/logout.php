<?php
/*
 * admin/logout.php
 * KitchCo: Cloud Kitchen Logout Script
 * Version 1.1 - Added CSRF Protection
 *
 * This script destroys the user's session and redirects to the login page.
 */

// 1. We must start the session to be able to access and destroy it.
require_once('../config.php');

// 2. (NEW) Validate CSRF Token
// This prevents logging out from a malicious link
if (!validate_csrf_token()) {
    die('Invalid logout request.');
}

// 3. Unset all of the session variables.
$_SESSION = array();

// 4. Destroy the session.
session_destroy();

// 5. Redirect to login page.
header('Location: login.php');
exit;
?>