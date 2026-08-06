<?php
// includes/db.php - Conexión a la base de datos

$dbfile = getenv('DB_FILE') ?: __DIR__ . '/../casalatina.db';

try {
    $pdo = new PDO("sqlite:$dbfile");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec("PRAGMA foreign_keys = ON;"); // SQLite has FK enforcement off by default
} catch (PDOException $e) {
    die("Error de conexion: " . $e->getMessage());
}
?>