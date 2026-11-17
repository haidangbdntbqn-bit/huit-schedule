<?php
declare(strict_types=1);

/** Escape HTML */
function h(mixed $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $message];
}

function take_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . h(csrf_token()) . '">';
}

function verify_csrf(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', (string) ($_POST['_token'] ?? ''))) {
        http_response_code(419);
        die('Phiên làm việc đã hết hạn (CSRF). Vui lòng tải lại trang.');
    }
}

function input_int(array $src, string $key, ?int $default = null): ?int
{
    if (!isset($src[$key]) || $src[$key] === '') {
        return $default;
    }
    return filter_var($src[$key], FILTER_VALIDATE_INT) !== false ? (int) $src[$key] : $default;
}

/** Bỏ dấu tiếng Việt + chữ thường, dùng để so khớp chuyên môn */
function vn_normalize(string $s): string
{
    $s = mb_strtolower($s, 'UTF-8');
    $map = [
        'a' => 'àáạảãâầấậẩẫăằắặẳẵ', 'e' => 'èéẹẻẽêềếệểễ', 'i' => 'ìíịỉĩ',
        'o' => 'òóọỏõôồốộổỗơờớợởỡ', 'u' => 'ùúụủũưừứựửữ', 'y' => 'ỳýỵỷỹ', 'd' => 'đ',
    ];
    foreach ($map as $plain => $chars) {
        $s = str_replace(mb_str_split($chars), $plain, $s);
    }
    $s = preg_replace('/[^a-z0-9#+]+/', ' ', $s);
    return trim(preg_replace('/\s+/', ' ', $s));
}

function json_out(array $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function branch_color(int $id): string
{
    $palette = ['#0d6efd', '#198754', '#d63384', '#fd7e14', '#6f42c1', '#20c997', '#dc3545', '#0dcaf0'];
    return $palette[($id - 1) % count($palette)];
}
