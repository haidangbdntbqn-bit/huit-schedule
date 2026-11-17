<?php
/**
 * Kết nối MySQL bằng PDO, thông số lấy từ file .env
 */
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function db(?PDO $override = null): PDO
{
    static $pdo = null;
    if ($override !== null) {
        $pdo = $override;           // dùng cho kiểm thử
    }
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $host = env('DB_HOST', 'localhost');
    $port = env('DB_PORT', '3306');
    $name = env('DB_NAME', 'huit_db');
    $dsn  = "mysql:host={$host};port={$port};dbname={$name};charset=utf8mb4";

    try {
        $pdo = new PDO($dsn, (string) env('DB_USER', 'root'), (string) env('DB_PASS', ''), [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        $msg = htmlspecialchars($e->getMessage());
        die("<div style='font-family:sans-serif;max-width:640px;margin:60px auto;padding:24px;border:1px solid #f5c2c7;background:#f8d7da;border-radius:8px'>
            <h3>Không kết nối được cơ sở dữ liệu</h3>
            <p>{$msg}</p>
            <p>Kiểm tra lại file <code>.env</code> (DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS) và chắc chắn đã import <code>database/huit_db.sql</code>.
            Với MAMP trên macOS: DB_PORT=8889, DB_USER=root, DB_PASS=root.</p></div>");
    }
    return $pdo;
}
