<?php
declare(strict_types=1);

/** Adds a fresh fictional appointment for the isolated Playwright run. */
$pdo = new PDO(
    'mysql:host=' . (getenv('OPD_TEST_DB_HOST') ?: '127.0.0.1') . ';dbname=' . (getenv('OPD_TEST_DB_NAME') ?: 'nazareth_opd_test') . ';charset=utf8mb4',
    getenv('OPD_TEST_DB_USER') ?: 'opd_test',
    getenv('OPD_TEST_DB_PASSWORD') ?: 'opd_test_password',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$pdo->exec("INSERT INTO appointments (patient_id,doctor_id,appointment_date,appointment_time,reason,status) VALUES (1,1,CURDATE(),'14:00:00','Fictional browser-test booking','Approved')");
echo "Seeded fictional browser-test appointment.\n";
