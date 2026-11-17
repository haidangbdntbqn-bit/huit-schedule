<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/scheduler.php';
require_admin();

$pdo = db();
$id = input_int($_GET, 'id') ?? input_int($_POST, 'id');
$item = [
    'id' => null, 'teacher_id' => input_int($_GET, 'teacher_id'), 'branch_id' => input_int($_GET, 'branch_id'),
    'subject_name' => '', 'room' => '', 'day_of_week' => input_int($_GET, 'day', 2),
    'p_start' => input_int($_GET, 'p', 1), 'p_end' => null,
];
if ($item['p_end'] === null) {
    // mặc định 3 tiết nhưng không vượt khỏi buổi
    $item['p_end'] = min($item['p_start'] + 2, SESSIONS[session_of($item['p_start'])][1]);
}

if ($id) {
    $st = $pdo->prepare('SELECT * FROM schedules WHERE id = ?');
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) {
        flash('danger', 'Không tìm thấy lịch dạy.');
        redirect('schedules.php');
    }
    [$row['p_start'], $row['p_end']] = parse_periods($row['shift_or_periods']) ?? [1, 3];
    $item = $row;
}

$check = ['errors' => [], 'warnings' => []];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $item = [
        'id'           => $id,
        'teacher_id'   => input_int($_POST, 'teacher_id'),
        'branch_id'    => input_int($_POST, 'branch_id'),
        'subject_name' => trim($_POST['subject_name'] ?? ''),
        'room'         => trim($_POST['room'] ?? ''),
        'day_of_week'  => input_int($_POST, 'day_of_week'),
        'p_start'      => input_int($_POST, 'p_start'),
        'p_end'        => input_int($_POST, 'p_end'),
    ];
    if (!$item['teacher_id'] || !$item['branch_id'] || $item['subject_name'] === '' || $item['room'] === '') {
        $check['errors'][] = 'Vui lòng nhập đầy đủ: Học phần, Cơ sở, Phòng, Giảng viên.';
    } else {
        $check = check_schedule($item, $id);
    }

    $accept = !empty($_POST['accept_warnings']);
    if (!$check['errors'] && (!$check['warnings'] || $accept)) {
        $vals = [$item['teacher_id'], $item['branch_id'], $item['subject_name'], $item['room'], $item['day_of_week'],
                 format_periods($item['p_start'], $item['p_end'])];
        if ($id) {
            $pdo->prepare('UPDATE schedules SET teacher_id=?, branch_id=?, subject_name=?, room=?, day_of_week=?, shift_or_periods=? WHERE id=?')
                ->execute([...$vals, $id]);
            flash('success', 'Đã cập nhật lịch dạy.');
        } else {
            $pdo->prepare('INSERT INTO schedules (teacher_id, branch_id, subject_name, room, day_of_week, shift_or_periods) VALUES (?,?,?,?,?,?)')
                ->execute($vals);
            flash('success', 'Đã phân lịch thành công.');
        }
        redirect('schedules.php?teacher_id=' . $item['teacher_id']);
    }
}

$title = $id ? 'Sửa lịch dạy' : 'Phân lịch mới';
$scripts = ['assets/schedule-form.js'];
require __DIR__ . '/includes/header.php';
?>
<h4 class="mb-3"><?= h($title) ?></h4>
<div class="row g-4">
    <div class="col-xl-5">
        <form method="post" class="card" id="scheduleForm">
            <div class="card-header">Thông tin lớp học phần</div>
            <div class="card-body">
                <?= csrf_field() ?>
                <input type="hidden" name="id" id="exclude_id" value="<?= h($id) ?>">

                <?php foreach ($check['errors'] as $e): ?><div class="alert alert-danger py-2 small"><i class="bi bi-x-octagon"></i> <?= h($e) ?></div><?php endforeach; ?>
                <?php if (!$check['errors'] && $check['warnings']): ?>
                    <div class="alert alert-warning small">
                        <?php foreach ($check['warnings'] as $w): ?><div><i class="bi bi-exclamation-triangle"></i> <?= h($w) ?></div><?php endforeach; ?>
                        <div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="accept_warnings" value="1" id="acc">
                            <label class="form-check-label fw-semibold" for="acc">Tôi đã xem cảnh báo và vẫn muốn lưu</label></div>
                    </div>
                <?php endif; ?>

                <div class="mb-3">
                    <label class="form-label">Tên học phần *</label>
                    <input name="subject_name" id="subject_name" class="form-control" required value="<?= h($item['subject_name']) ?>" placeholder="VD: Lập trình mã nguồn mở">
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-7">
                        <label class="form-label">Cơ sở *</label>
                        <select name="branch_id" id="branch_id" class="form-select" required>
                            <option value="">— Chọn cơ sở —</option>
                            <?php foreach (branches() as $b): ?><option value="<?= $b['id'] ?>" <?= $item['branch_id'] == $b['id'] ? 'selected' : '' ?>><?= h($b['branch_name']) ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-5">
                        <label class="form-label">Phòng học *</label>
                        <input name="room" id="room" class="form-control" required value="<?= h($item['room']) ?>" placeholder="VD: A402">
                    </div>
                </div>
                <div class="row g-2 mb-2">
                    <div class="col-4">
                        <label class="form-label">Thứ *</label>
                        <select name="day_of_week" id="day_of_week" class="form-select">
                            <?php foreach (DAYS as $k => $v): ?><option value="<?= $k ?>" <?= $item['day_of_week'] == $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-4">
                        <label class="form-label">Từ tiết</label>
                        <select name="p_start" id="p_start" class="form-select">
                            <?php foreach (PERIOD_START as $p => $t): ?><option value="<?= $p ?>" <?= $item['p_start'] == $p ? 'selected' : '' ?>>Tiết <?= $p ?> (<?= $t ?>)</option><?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-4">
                        <label class="form-label">Đến tiết</label>
                        <select name="p_end" id="p_end" class="form-select">
                            <?php foreach (PERIOD_START as $p => $t): ?><option value="<?= $p ?>" <?= $item['p_end'] == $p ? 'selected' : '' ?>>Tiết <?= $p ?> (→<?= period_end_time($p) ?>)</option><?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="mb-3 small">
                    Chọn nhanh buổi:
                    <button type="button" class="btn btn-sm btn-outline-secondary quick" data-s="1" data-e="3">Sáng 1–3</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary quick" data-s="4" data-e="6">Sáng 4–6</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary quick" data-s="7" data-e="9">Chiều 7–9</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary quick" data-s="10" data-e="12">Chiều 10–12</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary quick" data-s="13" data-e="15">Tối 13–15</button>
                </div>
                <div class="mb-3">
                    <label class="form-label">Giảng viên *</label>
                    <select name="teacher_id" id="teacher_id" class="form-select" required>
                        <option value="">— Chọn giảng viên (hoặc dùng Gợi ý) —</option>
                        <?php foreach (teachers() as $t): ?><option value="<?= $t['id'] ?>" <?= $item['teacher_id'] == $t['id'] ? 'selected' : '' ?>><?= h($t['full_name']) ?> — <?= h($t['expertise']) ?></option><?php endforeach; ?>
                    </select>
                </div>

                <div id="liveCheck" class="small mb-3"></div>

                <button class="btn btn-primary"><i class="bi bi-save"></i> <?= $id ? 'Cập nhật' : 'Lưu lịch dạy' ?></button>
                <a href="schedules.php" class="btn btn-light">Quay lại</a>
            </div>
        </form>
    </div>

    <div class="col-xl-7">
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-stars text-warning"></i> Gợi ý giảng viên phù hợp</span>
                <button type="button" class="btn btn-sm btn-warning" id="btnSuggestTeachers">Gợi ý</button>
            </div>
            <div class="card-body small" id="teacherSuggest">
                <div class="text-muted">Nhập tên học phần, chọn cơ sở, thứ, tiết rồi bấm <b>Gợi ý</b>. Hệ thống xếp hạng giảng viên theo
                    độ khớp chuyên môn, loại bỏ người bị trùng lịch / không kịp di chuyển, ưu tiên người đã có lớp cùng cơ sở trong ngày và đang ít tiết.</div>
            </div>
        </div>
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <span><i class="bi bi-clock-history text-success"></i> Gợi ý tiết trống tối ưu cho giảng viên</span>
                <div class="d-flex gap-2 align-items-center">
                    <label class="small text-nowrap">Số tiết</label>
                    <select id="slotLength" class="form-select form-select-sm" style="width:80px">
                        <?php for ($i = 1; $i <= 6; $i++): ?><option <?= $i === 3 ? 'selected' : '' ?>><?= $i ?></option><?php endfor; ?>
                    </select>
                    <button type="button" class="btn btn-sm btn-success" id="btnSuggestSlots">Tìm tiết trống</button>
                </div>
            </div>
            <div class="card-body small" id="slotSuggest">
                <div class="text-muted">Chọn giảng viên và cơ sở, hệ thống tìm các tiết trống không trùng lịch, không trùng phòng,
                    ưu tiên xếp nối tiếp lớp sẵn có tại cùng cơ sở để giảm di chuyển và tránh khoảng trống chết.</div>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
