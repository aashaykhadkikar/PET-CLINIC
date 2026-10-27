<?php
// =====================================================
// Database connection (PDO + MySQL)
// Add this file to .gitignore - do NOT push real passwords.
// Include it in every API file with: require_once __DIR__ . '/../config/db.php';
// =====================================================

$DB_HOST = 'localhost';
$DB_NAME = 'petclinic';
$DB_USER = 'root';   // XAMPP/WAMP default
$DB_PASS = '';       // XAMPP default is empty; set your own if you changed it

try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,  // real prepared statements
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database connection failed']);
    // For debugging only: error_log($e->getMessage());
    exit;
}