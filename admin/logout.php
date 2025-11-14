<?php
/*
 * admin/logout.php
 * KitchCo: Cloud Kitchen Logout Script
 * Version 1.0
 *
 * This script destroys the user's session and redirects to the login page.
 */

// 1. We must start the session to be able to access and destroy it.
require_once('../config.php');

// 2. Unset all of the session variables.
$_SESSION = array();

// 3. Destroy the session.
session_destroy();

// 4. Redirect to login page.
header('Location: login.php');
exit;
?>