<?php
/*
 * admin/index.php
 * KitchCo: Cloud Kitchen Admin Entry Point
 * Version 1.0
 *
 * This file prevents directory listing of the /admin folder.
 * It acts as a router:
 * 1. If user is logged in, redirect to the live dashboard.
 * 2. If user is not logged in, redirect to the login page.
 */

// 1. We must start the session to check the login status.
// We use ../config.php because this file is in the /admin folder.
require_once('../config.php');

// 2. Check for a valid session
if (isset($_SESSION['user_id'])) {
    // User is already logged in. Send them to the dashboard.
    header('Location: live_orders.php');
} else {
    // User is not logged in. Send them to the login page.
    header('Location: login.php');
}

// 3. Stop all further script execution
exit;
?>