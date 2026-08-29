<?php
/**
 * Copy this file to site/config.php and update values for your environment.
 * Do not commit site/config.php with real credentials.
 */

$hostname = 'localhost';
$username = 'db_user';
$password = 'db_password';
$database = 'bcoem_db';

// Optional: override when your MySQL port is not default.
$database_port = ini_get('mysqli.default_port');

$connection = new mysqli($hostname, $username, $password, $database, $database_port);
mysqli_set_charset($connection, 'utf8mb4');
mysqli_query($connection, "SET NAMES 'utf8mb4';");
mysqli_query($connection, "SET CHARACTER SET 'utf8mb4';");
mysqli_query($connection, "SET COLLATION_CONNECTION = 'utf8mb4_unicode_ci';");

$brewing = $connection;

/**
 * Table prefix.
 * Use empty string when this database is dedicated to one installation.
 * Use a unique prefix (ending with "_") when sharing one DB across installs.
 */
$prefix = '';

/**
 * Installation ID.
 * Set to any unique short string/integer per installation.
 */
$installation_id = '10001';

// Session timeout in minutes.
$session_expire_after = 60;

/**
 * Set to TRUE only while running setup.php, then switch back to FALSE.
 */
$setup_free_access = FALSE;

/**
 * Subdirectory where this app is hosted.
 * Examples:
 *   ''       for domain root
 *   '/bcoem' for https://example.com/bcoem
 */
$sub_directory = '';

$base_url = 'http://';
if (is_https()) $base_url = 'https://';
$base_url .= $_SERVER['SERVER_NAME'].$sub_directory.'/';

$server_root = $_SERVER['DOCUMENT_ROOT'];
//$server_root = $_SERVER['SUBDOMAIN_DOCUMENT_ROOT'];

?>
