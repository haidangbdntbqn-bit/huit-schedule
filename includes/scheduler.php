<?php
/**
 * LÕI XẾP LỊCH THÔNG MINH
 *  - Chuẩn hóa tiết/buổi học (cột shift_or_periods lưu dạng "1-3" = tiết 1 đến tiết 3)
 *  - Kiểm tra trùng lịch giảng viên, trùng phòng
 *  - Ràng buộc di chuyển giữa các cơ sở + phát hiện "khoảng trống chết"
 *  - Gợi ý giảng viên phù hợp chuyên môn & đang rảnh
 *  - Gợi ý khung giờ (tiết trống) tối ưu cho một giảng viên
 */
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

const DAYS = [2 => 'Thứ 2', 3 => 'Thứ 3', 4 => 'Thứ 4', 5 => 'Thứ 5', 6 => 'Thứ 6', 7 => 'Thứ 7', 8 => 'Chủ nhật'];

/** Buổi học: [tiết bắt đầu, tiết kết thúc, nhãn] */
const SESSIONS = [
    'sang'  => [1, 6, 'Sáng'],
    'chieu' => [7, 12, 'Chiều'],
    'toi'   => [13, 15, 'Tối'],
];

/** Giờ bắt đầu từng tiết (mỗi tiết 45 phút) */
const PERIOD_START = [
    1 => '06:45', 2 => '07:30', 3 => '08:15', 4 => '09:20', 5 => '10:05', 6 => '10:50',
    7 => '12:45', 8 => '13:30', 9 => '14:15', 10 => '15:20', 11 => '16:05', 12 => '16:50',
    13 => '18:00', 14 => '18:45', 15 => '19:30',
];

function travel_gap(): int
{
    return (int) env('TRAVEL_GAP_PERIODS', 1);   // số tiết tối thiểu để di chuyển giữa 2 cơ sở
}
function dead_gap(): int
{
    return (int) env('DEAD_GAP_PERIODS', 3);     // khoảng trống >= N tiết trong cùng buổi = "trống chết"
}
function max_periods_per_day(): int
{
    return (int) env('MAX_PERIODS_PER_DAY', 10);
}

function session_of(int $p): string
{
    foreach (SESSIONS as $key => [$a, $b]) {
        if ($p >= $a && $p <= $b) {
            return $key;
        }
    }
    return 'toi';
}

function period_end_time(int $p): string
{
    return date('H:i', strtotime(PERIOD_START[$p]) + 45 * 60);
}

/** "1-3", "Tiết 1-3", "Tiết 4", "Sáng", "chieu" => [start, end] */
function parse_periods(?string $s): ?array
{
    $s = trim((string) $s);
    $n = vn_normalize($s);
    foreach (SESSIONS as $key => [$a, $b, $label]) {
        if ($n === $key || $n === vn_normalize($label) || $n === 'buoi ' . vn_normalize($label)) {
            return [$a, $b];
        }
    }
    if (preg_match('/(\d{1,2})\s*(?:-|–|den|đến)?\s*(\d{1,2})?/u', $s, $m)) {
        $a = (int) $m[1];
        $b = isset($m[2]) && $m[2] !== '' ? (int) $m[2] : $a;
        if ($a >= 1 && $b <= 15 && $a <= $b) {
            return [$a, $b];
        }
    }
    return null;
}

function format_periods(int $a, int $b): string
{
    return $a === $b ? (string) $a : "{$a}-{$b}";
}

function periods_label(string $s, bool $withTime = true): string
{
    $p = parse_periods($s);
    if (!$p) {
        return $s;
    }
    [$a, $b] = $p;
    $label = 'Tiết ' . format_periods($a, $b);
    if ($withTime) {
        $label .= ' (' . PERIOD_START[$a] . '–' . period_end_time($b) . ')';
    }
    return $label;
}

/** Lấy lịch kèm tên GV, cơ sở; đã tách sẵn p_start/p_end */
function fetch_schedules(array $f = []): array
{
    $sql = 'SELECT s.*, t.full_name, t.email, b.branch_name, b.address
            FROM schedules s
            JOIN teachers t ON t.id = s.teacher_id
            JOIN branches b ON b.id = s.branch_id WHERE 1=1';
    $args = [];
    if (!empty($f['teacher_id'])) { $sql .= ' AND s.teacher_id = ?'; $args[] = $f['teacher_id']; }
    if (!empty($f['branch_id']))  { $sql .= ' AND s.branch_id = ?';  $args[] = $f['branch_id']; }
    if (!empty($f['day']))        { $sql .= ' AND s.day_of_week = ?'; $args[] = $f['day']; }
    if (!empty($f['q'])) {
        $sql .= ' AND (s.subject_name LIKE ? OR s.room LIKE ? OR t.full_name LIKE ?)';
        $like = '%' . $f['q'] . '%';
        array_push($args, $like, $like, $like);
    }
    $sql .= ' ORDER BY s.day_of_week, s.id';
    $st = db()->prepare($sql);
    $st->execute($args);
    $rows = [];
    foreach ($st->fetchAll() as $r) {
        [$r['p_start'], $r['p_end']] = parse_periods($r['shift_or_periods']) ?? [0, 0];
        $r['session'] = session_of((int) $r['p_start']);
        $rows[] = $r;
    }
    usort($rows, fn($x, $y) => [$x['day_of_week'], $x['p_start']] <=> [$y['day_of_week'], $y['p_start']]);
    return $rows;
}

function branches(): array
{
    return db()->query('SELECT * FROM branches ORDER BY id')->fetchAll();
}

function teachers(): array
{
    return db()->query('SELECT * FROM teachers ORDER BY full_name')->fetchAll();
}

function find_branch(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM branches WHERE id = ?');
    $st->execute([$id]);
    return $st->fetch() ?: null;
}

/**
 * Kiểm tra một lịch dự kiến.
 * $d = [teacher_id, branch_id, room, day_of_week, p_start, p_end]
 * Trả về ['errors' => [...], 'warnings' => [...]] — errors chặn lưu, warnings chỉ cảnh báo.
 */
function check_schedule(array $d, ?int $excludeId = null, ?array $all = null): array
{
    $errors = $warnings = [];
    $day = (int) $d['day_of_week'];
    $s = (int) $d['p_start'];
    $e = (int) $d['p_end'];

    if (!isset(DAYS[$day])) {
        $errors[] = 'Thứ trong tuần không hợp lệ.';
    }
    if ($s < 1 || $e > 15 || $s > $e) {
        $errors[] = 'Tiết học không hợp lệ (1–15, tiết bắt đầu ≤ tiết kết thúc).';
        return compact('errors', 'warnings');
    }
    if (session_of($s) !== session_of($e)) {
        $errors[] = 'Một buổi dạy phải nằm gọn trong cùng một buổi (Sáng 1–6, Chiều 7–12, Tối 13–15).';
    }

    $all ??= fetch_schedules(['day' => $day]);
    $sameDay = array_filter($all, fn($r) => (int) $r['day_of_week'] === $day && (int) $r['id'] !== (int) $excludeId);
    $branchNames = array_column(branches(), 'branch_name', 'id');
    $myBranch = $branchNames[$d['branch_id']] ?? 'cơ sở đã chọn';

    $teacherLoad = $e - $s + 1;
    foreach ($sameDay as $r) {
        $overlap = !($e < $r['p_start'] || $s > $r['p_end']);

        // 1. Trùng phòng
        if (!empty($d['room']) && (int) $r['branch_id'] === (int) $d['branch_id']
            && vn_normalize($r['room']) === vn_normalize((string) $d['room']) && $overlap) {
            $errors[] = "Phòng {$r['room']} ({$r['branch_name']}) đã có lớp {$r['subject_name']} – "
                . periods_label($r['shift_or_periods'], false) . " ({$r['full_name']}).";
        }

        if (empty($d['teacher_id']) || (int) $r['teacher_id'] !== (int) $d['teacher_id']) {
            continue;
        }
        $teacherLoad += $r['p_end'] - $r['p_start'] + 1;

        // 2. Trùng lịch giảng viên
        if ($overlap) {
            $errors[] = "Giảng viên đã có lịch {$r['subject_name']} – " . periods_label($r['shift_or_periods'], false)
                . " tại {$r['branch_name']}.";
            continue;
        }

        // Khoảng cách (số tiết trống) giữa 2 lớp
        $gap = $r['p_end'] < $s ? $s - $r['p_end'] - 1 : $r['p_start'] - $e - 1;
        $sameSession = $r['session'] === session_of($s);

        // 3. Di chuyển giữa các cơ sở
        if ((int) $r['branch_id'] !== (int) $d['branch_id'] && $sameSession && $gap < travel_gap()) {
            $errors[] = "Không đủ thời gian di chuyển: lớp {$r['subject_name']} tại {$r['branch_name']} ("
                . periods_label($r['shift_or_periods'], false) . ") sát lớp mới tại {$myBranch}. Cần cách ít nhất "
                . travel_gap() . ' tiết.';
        } elseif ((int) $r['branch_id'] !== (int) $d['branch_id']) {
            $warnings[] = "Trong ngày giảng viên phải di chuyển giữa {$r['branch_name']} và {$myBranch}.";
        }

        // 4. Khoảng trống chết trong cùng buổi
        if ($sameSession && $gap >= dead_gap()) {
            $warnings[] = "Tạo khoảng trống chết {$gap} tiết giữa lớp mới và lớp {$r['subject_name']} ("
                . periods_label($r['shift_or_periods'], false) . ').';
        }
    }

    if ($teacherLoad > max_periods_per_day()) {
        $warnings[] = "Giảng viên sẽ dạy {$teacherLoad} tiết trong ngày (vượt mức " . max_periods_per_day() . ' tiết).';
    }

    return ['errors' => array_values(array_unique($errors)), 'warnings' => array_values(array_unique($warnings))];
}

/** Mức độ khớp chuyên môn (0 → 1) giữa tên học phần và chuyên môn giảng viên */
function expertise_score(string $subject, string $expertise): float
{
    $stop = ['va', 'cua', 'nhap', 'mon', 'thuc', 'hanh', 'ly', 'thuyet', 'do', 'an', 'chuyen', 'nganh',
             'co', 'ban', 'nang', 'cao', 'i', 'ii', 'iii', '1', '2', '3', 'cac', 'trong', 'cho', 'voi'];
    $subj = vn_normalize($subject);
    if ($subj === '') {
        return 0.0;
    }
    $best = 0.0;
    foreach (preg_split('/[,;\/|]+/', $expertise) as $item) {
        $item = vn_normalize($item);
        if ($item === '') {
            continue;
        }
        if (str_contains(" $subj ", " $item ") || str_contains(" $item ", " $subj ")) {
            return 1.0;
        }
        $a = array_diff(explode(' ', $subj), $stop);
        $b = array_diff(explode(' ', $item), $stop);
        if (!$a || !$b) {
            continue;
        }
        $hit = count(array_intersect($a, $b));
        $best = max($best, $hit / count($a));   // tỉ lệ từ khóa của học phần xuất hiện trong chuyên môn
    }
    return round($best, 2);
}

/** Tổng số tiết/tuần của từng giảng viên */
function weekly_load(?array $all = null): array
{
    $all ??= fetch_schedules();
    $load = [];
    foreach ($all as $r) {
        $load[$r['teacher_id']] = ($load[$r['teacher_id']] ?? 0) + ($r['p_end'] - $r['p_start'] + 1);
    }
    return $load;
}

/**
 * Gợi ý giảng viên cho một lớp mới.
 * Trả về danh sách đã chấm điểm: phù hợp chuyên môn + rảnh + tối ưu di chuyển.
 */
function suggest_teachers(array $d, ?int $excludeId = null): array
{
    $all  = fetch_schedules();
    $load = weekly_load($all);
    $day  = (int) $d['day_of_week'];
    $out  = [];

    foreach (teachers() as $t) {
        $match = expertise_score((string) $d['subject_name'], (string) $t['expertise']);
        $check = check_schedule(['teacher_id' => $t['id']] + $d, $excludeId, $all);
        $reasons = [];
        $score = $match * 60;
        if ($match >= 1) {
            $reasons[] = 'Đúng chuyên môn';
        } elseif ($match >= 0.5) {
            $reasons[] = 'Chuyên môn liên quan';
        }

        $mine = array_filter($all, fn($r) => (int) $r['teacher_id'] === (int) $t['id']
            && (int) $r['day_of_week'] === $day && (int) $r['id'] !== (int) $excludeId);
        foreach ($mine as $r) {
            if ((int) $r['branch_id'] === (int) $d['branch_id']) {
                $score += 15;
                $reasons[] = 'Đã có lịch cùng cơ sở trong ngày (không phải đi thêm)';
                if ($r['p_end'] + 1 === (int) $d['p_start'] || (int) $d['p_end'] + 1 === $r['p_start']) {
                    $score += 10;
                    $reasons[] = 'Nối tiếp liền mạch với lớp ' . $r['subject_name'];
                }
            } else {
                $score -= 15;
            }
        }
        $score -= count($check['warnings']) * 8;
        $score -= ($load[$t['id']] ?? 0) * 0.8;   // cân bằng tải
        $reasons[] = 'Đang dạy ' . ($load[$t['id']] ?? 0) . ' tiết/tuần';

        $out[] = [
            'id'        => (int) $t['id'],
            'full_name' => $t['full_name'],
            'expertise' => $t['expertise'],
            'match'     => $match,
            'available' => empty($check['errors']),
            'score'     => round($score, 1),
            'reasons'   => array_values(array_unique($reasons)),
            'errors'    => $check['errors'],
            'warnings'  => $check['warnings'],
        ];
    }

    usort($out, fn($a, $b) => [$b['available'], $b['match'] >= 0.5, $b['score']] <=> [$a['available'], $a['match'] >= 0.5, $a['score']]);
    return $out;
}

/**
 * Gợi ý khung giờ (tiết trống) tối ưu cho giảng viên tại một cơ sở.
 * Ưu tiên: ngày đã dạy ở cùng cơ sở, xếp nối tiếp liền mạch, tránh di chuyển và tránh trống chết.
 */
function suggest_slots(int $teacherId, int $branchId, int $length, string $room = '', ?int $excludeId = null, int $limit = 8): array
{
    $all = fetch_schedules();
    $includeSunday = env('ALLOW_SUNDAY', 'false') === 'true';
    $out = [];

    foreach (DAYS as $day => $dayLabel) {
        if ($day === 8 && !$includeSunday) {
            continue;
        }
        $mine = array_filter($all, fn($r) => (int) $r['teacher_id'] === $teacherId
            && (int) $r['day_of_week'] === $day && (int) $r['id'] !== (int) $excludeId);

        foreach (SESSIONS as $sessKey => [$a, $b, $sessLabel]) {
            for ($s = $a; $s + $length - 1 <= $b; $s++) {
                $e = $s + $length - 1;
                $d = ['teacher_id' => $teacherId, 'branch_id' => $branchId, 'room' => $room,
                      'day_of_week' => $day, 'p_start' => $s, 'p_end' => $e];
                $check = check_schedule($d, $excludeId, $all);
                if ($check['errors']) {
                    continue;
                }
                $score = 50;
                $why = [];
                foreach ($mine as $r) {
                    if ((int) $r['branch_id'] === $branchId) {
                        $score += 20;
                        $why[] = 'Cùng cơ sở với lớp ' . $r['subject_name'];
                        if ($r['p_end'] + 1 === $s || $e + 1 === $r['p_start']) {
                            $score += 15;
                            $why[] = 'Nối tiếp liền mạch';
                        }
                    } else {
                        $score -= 20;
                        $why[] = 'Phải di chuyển từ ' . $r['branch_name'];
                    }
                }
                if (!$mine) {
                    $why[] = 'Ngày trống hoàn toàn';
                }
                $score -= count($check['warnings']) * 10;
                if ($sessKey === 'toi') {
                    $score -= 5;
                }
                if ($s === $a || $e === $b) {
                    $score += 3;     // bám đầu/cuối buổi -> không tạo lỗ hổng giữa buổi
                }
                $out[] = [
                    'day_of_week' => $day,
                    'day_label'   => $dayLabel,
                    'session'     => $sessLabel,
                    'p_start'     => $s,
                    'p_end'       => $e,
                    'label'       => $dayLabel . ' · ' . periods_label(format_periods($s, $e)),
                    'score'       => $score,
                    'reasons'     => array_values(array_unique($why)),
                    'warnings'    => $check['warnings'],
                ];
            }
        }
    }
    usort($out, fn($x, $y) => [$y['score'], $x['day_of_week'], $x['p_start']] <=> [$x['score'], $y['day_of_week'], $y['p_start']]);
    return array_slice($out, 0, $limit);
}

/** Rà soát toàn bộ lịch hiện có, trả về danh sách vấn đề */
function audit_schedules(array $rows): array
{
    $all = fetch_schedules();
    $issues = [];
    foreach ($rows as $r) {
        $c = check_schedule($r, (int) $r['id'], $all);
        foreach ($c['errors'] as $m) {
            $issues[] = ['type' => 'danger', 'row' => $r, 'msg' => $m];
        }
        foreach ($c['warnings'] as $m) {
            $issues[] = ['type' => 'warning', 'row' => $r, 'msg' => $m];
        }
    }
    return $issues;
}

/**
 * Lưới thời khóa biểu theo TIẾT (dòng = tiết 1..15, cột = thứ) cho 1 giảng viên.
 * $freeLink: callback(day, period) -> url để thêm lịch vào ô trống (null = không link)
 */
function render_period_grid(array $rows, ?callable $freeLink = null, array $dates = []): string
{
    $map = [];
    foreach ($rows as $r) {
        for ($p = $r['p_start']; $p <= $r['p_end']; $p++) {
            $map[$r['day_of_week']][$p][] = $r;
        }
    }
    $html = '<div class="table-responsive"><table class="table table-bordered grid-table align-middle mb-0"><thead><tr><th class="text-center">Tiết</th>';
    foreach (DAYS as $d => $label) {
        $html .= '<th class="text-center">' . h($label) . (isset($dates[$d]) ? '<div class="small text-muted fw-normal">' . h($dates[$d]) . '</div>' : '') . '</th>';
    }
    $html .= '</tr></thead><tbody>';
    $skip = [];
    for ($p = 1; $p <= 15; $p++) {
        $sessStart = in_array($p, [1, 7, 13], true);
        $html .= '<tr class="' . ($sessStart ? 'session-start' : '') . '"><th class="text-center small text-nowrap">'
            . ($sessStart ? '<span class="badge text-bg-light d-block mb-1">' . h(SESSIONS[session_of($p)][2]) . '</span>' : '')
            . 'Tiết ' . $p . '<div class="text-muted fw-normal">' . PERIOD_START[$p] . '</div></th>';
        foreach (array_keys(DAYS) as $d) {
            if (!empty($skip[$d][$p])) {
                continue;
            }
            $cell = $map[$d][$p] ?? [];
            if ($cell) {
                $r = $cell[0];
                $span = $r['p_end'] - $p + 1;
                for ($k = $p + 1; $k <= $r['p_end']; $k++) {
                    $skip[$d][$k] = true;
                }
                $conflict = count($cell) > 1 ? ' <span class="badge text-bg-danger">Trùng</span>' : '';
                $html .= '<td rowspan="' . $span . '" class="busy" style="--c:' . branch_color((int) $r['branch_id']) . '">'
                    . '<div class="fw-semibold">' . h($r['subject_name']) . $conflict . '</div>'
                    . '<div class="small">' . h($r['branch_name']) . ' · P.' . h($r['room']) . '</div>'
                    . '<div class="small text-muted">' . h(periods_label($r['shift_or_periods'])) . '</div></td>';
                continue;
            }
            $html .= '<td class="free">' . ($freeLink ? '<a href="' . h($freeLink($d, $p)) . '" title="Thêm lịch vào tiết trống">+</a>' : '') . '</td>';
        }
        $html .= '</tr>';
    }
    return $html . '</tbody></table></div>';
}

/** Lưới tổng quan theo BUỔI (nhiều giảng viên) dùng cho trang tổng quan */
function render_session_grid(array $rows): string
{
    $html = '<div class="table-responsive"><table class="table table-bordered align-top session-grid mb-0"><thead><tr><th>Buổi</th>';
    foreach (DAYS as $label) {
        $html .= '<th class="text-center">' . h($label) . '</th>';
    }
    $html .= '</tr></thead><tbody>';
    foreach (SESSIONS as $key => [, , $label]) {
        $html .= '<tr><th>' . h($label) . '</th>';
        foreach (array_keys(DAYS) as $d) {
            $html .= '<td>';
            foreach ($rows as $r) {
                if ((int) $r['day_of_week'] === $d && $r['session'] === $key) {
                    $html .= '<div class="chip" style="--c:' . branch_color((int) $r['branch_id']) . '">'
                        . '<b>T' . h(format_periods((int) $r['p_start'], (int) $r['p_end'])) . '</b> ' . h($r['subject_name'])
                        . '<div class="small text-muted">' . h($r['full_name']) . ' · ' . h($r['branch_name']) . ' · P.' . h($r['room']) . '</div></div>';
                }
            }
            $html .= '</td>';
        }
        $html .= '</tr>';
    }
    return $html . '</tbody></table></div>';
}
