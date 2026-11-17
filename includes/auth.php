<?php
/**
 * Phân quyền theo vai trò:
 *  - admin   : Phòng đào tạo (tài khoản cấu hình trong .env)
 *  - teacher : Giảng viên (đăng nhập bằng email + mật khẩu, hoặc đường dẫn riêng có token)
 */
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_admin(): bool
{
    return (current_user()['role'] ?? null) === 'admin';
}

function attempt_login(string $email, string $password): bool
{
    $email = trim(mb_strtolower($email));

    // 1) Tài khoản quản trị
    $adminEmail = mb_strtolower((string) env('ADMIN_EMAIL', 'admin@huit.edu.vn'));
    $adminPass  = (string) env('ADMIN_PASSWORD', 'admin123');
    if ($email === $adminEmail && hash_equals($adminPass, $password)) {
        session_regenerate_id(true);
        $_SESSION['user'] = ['role' => 'admin', 'id' => 0, 'name' => 'Phòng Đào tạo', 'email' => $adminEmail];
        return true;
    }

    // 2) Tài khoản giảng viên
    $st = db()->prepare('SELECT id, full_name, email, password_hash FROM teachers WHERE LOWER(email) = ?');
    $st->execute([$email]);
    $t = $st->fetch();
    if ($t && $t['password_hash'] && password_verify($password, $t['password_hash'])) {
        login_teacher($t);
        return true;
    }
    return false;
}

function login_teacher(array $t): void
{
    session_regenerate_id(true);
    $_SESSION['user'] = ['role' => 'teacher', 'id' => (int) $t['id'], 'name' => $t['full_name'], 'email' => $t['email']];
}

function require_login(): array
{
    $u = current_user();
    if (!$u) {
        flash('warning', 'Vui lòng đăng nhập.');
        redirect('login.php');
    }
    return $u;
}

function require_admin(): array
{
    $u = require_login();
    if ($u['role'] !== 'admin') {
        redirect('portal.php');
    }
    return $u;
}
