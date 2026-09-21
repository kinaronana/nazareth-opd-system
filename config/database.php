<?php
// Secure database configuration initialization using strict PDO abstractions
$host    = 'localhost';
$db      = 'nazareth_hospital_db';
$user    = 'root';
$pass    = ''; // Leave blank if using default XAMPP, otherwise enter your root password
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // Enforces explicit error containment mapping
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // Returns records as clean associative arrays
    PDO::ATTR_EMULATE_PREPARES   => false,                  // Enforces true native prepared statements for security
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // Terminate process execution without leaking sensitive database structure strings or credentials
    die("Database Infrastructure Connection Failure: The platform could not interface with the relational layer safely.");
}
?>
