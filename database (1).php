<?php
/**
 * Database configuration and connection (same style as config/database.php)
 */
define("DB_HOST", "localhost");
define("DB_NAME", "educational_chatbot");
define("DB_USER", "root");
define("DB_PASS", "");
define("DB_CHARSET", "utf8mb4");

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$conn->set_charset(DB_CHARSET);
?>