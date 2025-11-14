<?php
/*
 * config.php
 * KitchCo: Cloud Kitchen Master Configuration File
 * Version 1.0
 *
 * This file is included at the top of almost all other PHP files.
 * It handles:
 * 1. Starting the PHP session
 * 2. Connecting to the MySQL database
 * 3. Loading all store settings from the `site_settings` table
 * 4. Setting the default timezone
 */

// --- 1. SESSION ---
// Start the session (must be called before any HTML output)
// This is used for admin login status and the customer's shopping cart.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// --- 2. DATABASE CONNECTION (IMPORTANT!) ---
// Fill in these details for your local WAMP server
define('DB_HOST', 'localhost');
define('DB_USER', 'root');      // Default for WAMP/XAMPP
define('DB_PASS', '');          // Default for WAMP/XAMPP is empty
define('DB_NAME', 'cloud_kitchen'); // The database name you created

// Create connection using MySQLi (a modern, secure method)
$db = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($db->connect_error) {
    // If we can't connect, stop the entire site from loading.
    die("Connection failed: " . $db->connect_error);
}

// Set the character set to utf8mb4 for full emoji and language support
$db->set_charset("utf8mb4");

// --- 3. LOAD SITE SETTINGS ---
/*
 * This is a smart function that loads ALL settings from your `site_settings`
 * table and puts them into a single, easy-to-use array called $settings.
 *
 * Instead of writing `SELECT * FROM site_settings WHERE setting_key = 'timezone'`,
 * you can just write `$settings['timezone']`.
 */
$settings = [];
$settings_query = $db->query("SELECT setting_key, setting_value FROM site_settings");

if ($settings_query) {
    while ($row = $settings_query->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} else {
    // This should never happen if the database.sql script ran correctly
    die("Error: Could not load site settings from database.");
}

// --- 4. SET TIMEZONE ---
// Use the timezone setting *from the database* to make sure all
// timestamps (like the Night Surcharge check) are accurate.
if (!empty($settings['timezone'])) {
    date_default_timezone_set($settings['timezone']);
} else {
    // Fallback just in case
    date_default_timezone_set('UTC');
}

// --- 5. GLOBAL VARIABLES & HELPERS (Optional but useful) ---
// This makes it easy to find your upload/asset paths
define('BASE_URL', 'http://localhost/your_project_folder_name'); // CHANGE THIS
define('UPLOADS_PATH', __DIR__ . '/uploads/');
define('ASSETS_PATH', BASE_URL . '/assets/');


// --- (Helper function for security - we will use this later) ---
/**
 * A simple helper function to sanitize output and prevent XSS attacks.
 * @param string $data The data to sanitize.
 * @return string The sanitized data.
 */
function e($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

?>