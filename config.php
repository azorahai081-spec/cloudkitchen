<?php
/*
 * config.php
 * KitchCo: Cloud Kitchen Master Configuration File
 * Version 1.6 - (FINAL FIX) Correct BASE_URL logic for WAMP/XAMPP
 *
 * This file is included at the top of almost all other PHP files.
 * It handles:
 * 1. Starting the PHP session
 * 2. Connecting to the MySQL database
 * 3. Loading all store settings from the `site_settings` table
 * 4. Setting the default timezone
 * 5. CSRF Protection Functions
 */

// --- 1. SESSION ---
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- 2. CSRF PROTECTION ---
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}
function validate_csrf_token() {
    if (isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        return true;
    }
    if (isset($_GET['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_GET['csrf_token'])) {
        return true;
    }
    return false;
}
function get_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        generate_csrf_token();
    }
    return $_SESSION['csrf_token'];
}
generate_csrf_token();


// --- 3. DATABASE CONNECTION ---
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'cloud_kitchen');

$db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}
$db->set_charset("utf8mb4");

// --- 4. LOAD SITE SETTINGS ---
$settings = [];
$settings_query = $db->query("SELECT setting_key, setting_value FROM site_settings");

if ($settings_query) {
    while ($row = $settings_query->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} else {
    die("Error: Could not load site settings from database.");
}

// --- 5. SET TIMEZONE ---
if (!empty($settings['timezone'])) {
    date_default_timezone_set($settings['timezone']);
} else {
    date_default_timezone_set('UTC');
}

// --- 6. GLOBAL VARIABLES & HELPERS ---

// (!!!) (FIXED) This logic correctly finds your "cloud" folder.
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https:" : "http:";
$host = $_SERVER['HTTP_HOST'];

// Get the server's filesystem path to this config file
// (e.g., "C:\wamp64\www\cloud")
$config_dir = dirname(__FILE__);

// Get the server's web root (e.g., "C:/wamp64/www")
$document_root = $_SERVER['DOCUMENT_ROOT'];

// Get the web path by removing the document root from the config file's path
// This will result in "/cloud"
$path = str_replace('\\', '/', substr($config_dir, strlen($document_root)));
$path = rtrim($path, '/\\'); // Clean up any trailing slashes

// Define the correct BASE_URL (e.g., http://localhost/cloud)
define('BASE_URL', $protocol . '//' . $host . $path);


define('UPLOADS_PATH', __DIR__ . '/uploads/');
define('ASSETS_PATH', BASE_URL . '/assets/');


/**
 * Helper function to sanitize output and prevent XSS attacks.
 * @param string $data The data to sanitize.
 * @return string The sanitized data.
 */
function e($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

?>