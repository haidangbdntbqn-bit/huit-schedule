<?php
/**
 * API JSON cho trang phân lịch
 *   ?action=check     -> kiểm tra trùng lịch / di chuyển / trống chết
 *   ?action=teachers  -> gợi ý giảng viên phù hợp chuyên môn & rảnh
 *   ?action=slots     -> gợi ý tiết trống tối ưu cho 1 giảng viên
 */
require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/scheduler.php';

if (!is_admin()) {
    json_out(['error' => 'Không có quyền truy cập'], 403);
}

$exclude = input_int($_GET, 'exclude_id');
$d = [
    'teacher_id'   => input_int($_GET, 'teacher_id'),
    'branch_id'    => input_int($_GET, 'branch_id'),
    'room'         => trim($_GET['room'] ?? ''),
    'day_of_week'  => input_int($_GET, 'day_of_week'),
    'p_start'      => input_int($_GET, 'p_start'),
    'p_end'        => input_int($_GET, 'p_end'),
    'subject_name' => trim($_GET['subject_name'] ?? ''),
];

switch ($_GET['action'] ?? '') {
    case 'check':
        if (!$d['branch_id'] || !$d['day_of_week'] || !$d['p_start'] || !$d['p_end']) {
            json_out(['errors' => [], 'warnings' => [], 'incomplete' => true]);
        }
        json_out(check_schedule($d, $exclude));

    case 'teachers':
        if ($d['subject_name'] === '' || !$d['branch_id'] || !$d['day_of_week'] || !$d['p_start'] || !$d['p_end']) {
            json_out(['error' => 'Nhập Tên học phần, Cơ sở, Thứ và Tiết học trước khi gợi ý.'], 422);
        }
        json_out(['items' => suggest_teachers($d, $exclude)]);

    case 'slots':
        $len = input_int($_GET, 'length', 3);
        if (!$d['teacher_id'] || !$d['branch_id'] || $len < 1 || $len > 6) {
            json_out(['error' => 'Chọn Giảng viên, Cơ sở và số tiết (1–6) trước khi gợi ý khung giờ.'], 422);
        }
        json_out(['items' => suggest_slots($d['teacher_id'], $d['branch_id'], $len, $d['room'], $exclude)]);

    default:
        json_out(['error' => 'Hành động không hợp lệ'], 400);
}
