<?php
$hosts = ['127.0.0.1', 'localhost'];
$port = 3306;
$user = 'root';
$pass = '';

foreach ($hosts as $host) {
    echo "Testing $host:$port...\n";
    try {
        $pdo = new PDO("mysql:host=$host;port=$port", $user, $pass, [
            PDO::ATTR_TIMEOUT => 2,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
        echo "SUCCESS: Connected to $host\n";
        $stmt = $pdo->query("SHOW DATABASES LIKE 'sigva_db'");
        if ($stmt->fetch()) {
            echo "DATABASE sigva_db EXISTS\n";
        } else {
            echo "DATABASE sigva_db DOES NOT EXIST\n";
        }
        exit(0);
    } catch (PDOException $e) {
        echo "FAILED: " . $e->getMessage() . "\n";
    }
}

echo "\nMySQL is likely NOT running or port is different.\n";
