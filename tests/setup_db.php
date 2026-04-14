<?php
/**
 * setup_db.php — Initializes the test database
 */

// We don't include config.php because we need to connect WITHOUT a DB name first
// to create the database if it doesn't exist.

$host = 'localhost';
$user = 'root';
$pass = '';
$dbName = 'test_feedbackiq';

echo "Connecting to MySQL server...\n";
$conn = new mysqli($host, $user, $pass);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error . "\n");
}

echo "Dropping old test database (if exists)...\n";
$conn->query("DROP DATABASE IF EXISTS `$dbName`");

echo "Creating test database...\n";
if ($conn->query("CREATE DATABASE `$dbName`") === TRUE) {
    echo "Database created successfully.\n";
} else {
    die("Error creating database: " . $conn->error . "\n");
}

$conn->select_db($dbName);

// Read schema.sql
$schemaPath = __DIR__ . '/../api/schema.sql';
if (!file_exists($schemaPath)) {
    die("Schema file not found at: $schemaPath\n");
}

$sql = file_get_contents($schemaPath);
// Remove CREATE DATABASE and USE statements from schema so it applies to test DB
$sql = preg_replace('/CREATE DATABASE IF NOT EXISTS [a-zA-Z0-9_]+[^;]*;/i', '', $sql);
$sql = preg_replace('/USE [a-zA-Z0-9_]+;/i', '', $sql);

echo "Importing schema...\n";
if ($conn->multi_query($sql)) {
    do {
        // Store first result set
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->more_results() && $conn->next_result());
    echo "Schema imported successfully.\n";
} else {
    die("Error importing schema: " . $conn->error . "\n");
}

$conn->close();
echo "Test database setup complete.\n";
