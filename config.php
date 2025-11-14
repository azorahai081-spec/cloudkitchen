<?php
/*
 * config.php
 * KitchCo: Cloud Kitchen Master Configuration File
 * Version 1.1 - Added CSRF Protection
 *
 * This file is included at the top of almost all other PHP files.
 * It handles:
 * 1. Starting the PHP session
 * 2. Connecting to the MySQL database
 * 3. Loading all store settings from the `site_settings` table
 * 4. Setting the default timezone
 * 5. (NEW) CSRF Protection Functions
 */

// --- 1. SESSION ---
// Start the session (must be called before any HTML output)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- (NEW) CSRF PROTECTION ---

/**
 * Generates a new CSRF token and stores it in the session.
 * Call this once per request on pages with forms.
 */
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

/**
 * Validates a submitted CSRF token against the one in the session.
 * @return bool True if valid, false otherwise.
 */
function validate_csrf_token() {
    if (isset($_POST['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        return true;
    }
    // Also check for GET token (for logout/delete links)
    if (isset($_GET['csrf_token']) && hash_equals($_SESSION['csrf_token'], $_GET['csrf_token'])) {
        return true;
    }
    return false;
}

/**
 * Returns the current CSRF token from the session.
 * @return string The token.
 */
function get_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        generate_csrf_token();
    }
    return $_SESSION['csrf_token'];
}

// Generate a token for all admin pages
if (isset($_SESSION['user_id'])) {
    generate_csrf_token();
}
// --- (END NEW) ---


// --- 2. DATABASE CONNECTION (IMPORTANT!) ---
define('DB_HOST', 'localhost');
define('DB_USER', 'root');      // Default for WAMP/XAMPP
define('DB_PASS', '');          // Default for WAMP/XAMPP is empty
define('DB_NAME', 'cloud_kitchen'); // The database name you created

// Create connection using MySQLi
$db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($db->connect_error) {
    die("Connection failed: " . $db->connect_error);
}
$db->set_charset("utf8mb4");

// --- 3. LOAD SITE SETTINGS ---
$settings = [];
$settings_query = $db->query("SELECT setting_key, setting_value FROM site_settings");

if ($settings_query) {
    while ($row = $settings_query->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} else {
    die("Error: Could not load site settings from database.");
}

// --- 4. SET TIMEZONE ---
if (!empty($settings['timezone'])) {
    date_default_timezone_set($settings['timezone']);
} else {
    date_default_timezone_set('UTC');
}

// --- 5. GLOBAL VARIABLES & HELPERS ---
// (!!!) IMPORTANT: UPDATE THIS TO YOUR PROJECT'S URL
define('BASE_URL', 'http://localhost/cloudkitchen'); // CHANGE THIS
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