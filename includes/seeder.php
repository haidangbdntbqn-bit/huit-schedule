<?php
/**
 * Sinh dữ liệu mẫu: cơ sở, giảng viên (fakerphp/faker) và lịch dạy.
 * Lịch dạy được xếp bằng chính thuật toán gợi ý tiết trống nên luôn hợp lệ.
 */
declare(strict_types=1);

require_once __DIR__ . '/scheduler.php';

const SAMPLE_EXPERTISE = [
    'Lập trình web, PHP, Mã nguồn mở',
    'Cơ sở dữ liệu, SQL, NoSQL',
    'Mạng máy tính, An ninh mạng',
    'Trí tuệ nhân tạo, Học máy',
    'Lập trình di động, Flutter',
    'Cấu trúc dữ liệu và giải thuật, Lập trình C++',
    'Hệ điều hành, Linux',
    'Công nghệ phần mềm, Phân tích thiết kế hệ thống',
    'Dữ liệu lớn, Khoa học dữ liệu',
    'Internet vạn vật, Hệ thống nhúng',
    'Kiểm thử phần mềm, Công nghệ phần mềm',
    'Điện toán đám mây, DevOps, Linux',
];

const SAMPLE_SUBJECTS = [
    'Lập trình mã nguồn mở', 'Lập trình web', 'Cơ sở dữ liệu', 'Hệ quản trị cơ sở dữ liệu', 'Cơ sở dữ liệu NoSQL',
    'Mạng máy tính', 'An ninh mạng', 'Trí tuệ nhân tạo', 'Học máy', 'Lập trình di động', 'Cấu trúc dữ liệu và giải thuật',
    'Hệ điều hành', 'Phân tích thiết kế hệ thống', 'Công nghệ phần mềm', 'Nhập môn dữ liệu lớn', 'Internet vạn vật',
    'Kiểm thử phần mềm', 'Điện toán đám mây',
];

const SAMPLE_BRANCHES = [
    ['Cơ sở chính – Tân Phú', '140 Lê Trọng Tấn, P. Tây Thạnh, Q. Tân Phú, TP.HCM', ['A101', 'A204', 'A306', 'B201', 'B305', 'C402']],
    ['Cơ sở 2 – Khu phòng máy', 'Khu thực hành phòng máy (địa chỉ mẫu), Q. Tân Phú, TP.HCM', ['PM01', 'PM02', 'PM03', 'PM04']],
    ['Trung tâm liên kết – Bình Tân', 'Trung tâm đào tạo liên kết (địa chỉ mẫu), Q. Bình Tân, TP.HCM', ['L201', 'L202', 'L305']],
];

/** Tên dự phòng khi chưa cài Faker (dùng để tạo file SQL mẫu tĩnh) */
const FALLBACK_NAMES = [
    'Nguyễn Văn An', 'Trần Thị Bích Ngọc', 'Lê Hoàng Minh', 'Phạm Thu Hà', 'Võ Quốc Bảo', 'Đặng Thị Mai',
    'Huỳnh Tấn Phát', 'Bùi Ngọc Lan', 'Đỗ Minh Khoa', 'Hồ Thị Thanh Tâm', 'Ngô Đức Thắng', 'Dương Mỹ Linh',
];

function seed_database(PDO $pdo, ?object $faker = null, int $nTeachers = 12, int $seed = 2026): array
{
    mt_srand($seed);
    db($pdo);
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

    // Xóa dữ liệu cũ
    if ($driver === 'mysql') {
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        foreach (['schedules', 'teachers', 'branches'] as $t) {
            $pdo->exec("TRUNCATE TABLE $t");
        }
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    } else {
        foreach (['schedules', 'teachers', 'branches'] as $t) {
            $pdo->exec("DELETE FROM $t");
        }
    }

    // 1. Cơ sở
    $rooms = [];
    $insB = $pdo->prepare('INSERT INTO branches (branch_name, address) VALUES (?, ?)');
    foreach (SAMPLE_BRANCHES as [$name, $addr, $r]) {
        $insB->execute([$name, $addr]);
        $rooms[(int) $pdo->lastInsertId()] = $r;
    }

    // 2. Giảng viên
    $insT = $pdo->prepare('INSERT INTO teachers (full_name, email, phone, expertise, password_hash, access_token) VALUES (?, ?, ?, ?, ?, ?)');
    $hash = password_hash((string) env('TEACHER_DEFAULT_PASSWORD', '123456'), PASSWORD_DEFAULT);
    $used = [];
    for ($i = 0; $i < $nTeachers; $i++) {
        $name = $faker ? $faker->name() : FALLBACK_NAMES[$i % count(FALLBACK_NAMES)];
        $parts = explode(' ', vn_normalize($name));
        $local = end($parts) . '.' . implode('', array_map(fn($p) => $p[0], array_slice($parts, 0, -1)));
        $email = $local . '@huit.edu.vn';
        for ($k = 2; isset($used[$email]); $k++) {
            $email = $local . $k . '@huit.edu.vn';
        }
        $used[$email] = true;
        $phone = $faker ? '09' . $faker->numerify('########') : sprintf('09%08d', mt_rand(10000000, 99999999));
        $expertise = SAMPLE_EXPERTISE[$i % count(SAMPLE_EXPERTISE)];
        $token = $faker ? md5($faker->unique()->uuid()) : md5("teacher-$seed-$i");
        $insT->execute([$name, $email, $phone, $expertise, $hash, $token]);
    }

    // 3. Lịch dạy — dùng thuật toán gợi ý để xếp hợp lệ
    $insS = $pdo->prepare('INSERT INTO schedules (teacher_id, branch_id, subject_name, room, day_of_week, shift_or_periods) VALUES (?, ?, ?, ?, ?, ?)');
    $branchIds = array_keys($rooms);
    $created = 0;
    foreach (SAMPLE_SUBJECTS as $subject) {
        $groups = mt_rand(1, 3);
        for ($g = 0; $g < $groups; $g++) {
            $bid = $branchIds[[0, 0, 0, 1, 1, 2][mt_rand(0, 5)] % count($branchIds)];
            $room = $rooms[$bid][mt_rand(0, count($rooms[$bid]) - 1)];
            $len = [3, 3, 3, 4, 2][mt_rand(0, 4)];

            // ứng viên: GV có chuyên môn phù hợp, ít tiết trước
            $load = weekly_load();
            $cands = array_filter(teachers(), fn($t) => expertise_score($subject, $t['expertise']) >= 0.5);
            usort($cands, fn($a, $b) => [-expertise_score($subject, $a['expertise']), $load[$a['id']] ?? 0]
                <=> [-expertise_score($subject, $b['expertise']), $load[$b['id']] ?? 0]);
            foreach ($cands as $t) {
                $slots = array_values(array_filter(suggest_slots((int) $t['id'], $bid, $len, $room, null, 60), fn($s) => !$s['warnings']));
                if (!$slots) {
                    continue;
                }
                // chọn ngẫu nhiên trong nhóm khung giờ có điểm cao nhất (rải đều các ngày)
                $top = array_values(array_filter($slots, fn($s) => $s['score'] >= $slots[0]['score'] - 5));
                $s = $top[mt_rand(0, count($top) - 1)];
                $insS->execute([$t['id'], $bid, $subject, $room, $s['day_of_week'], format_periods($s['p_start'], $s['p_end'])]);
                $created++;
                break;
            }
        }
    }

    return ['branches' => count($rooms), 'teachers' => $nTeachers, 'schedules' => $created];
}
