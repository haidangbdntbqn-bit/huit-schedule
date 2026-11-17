<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/scheduler.php';
require_admin();

$mode = ($_GET['mode'] ?? 'teacher') === 'room' ? 'room' : 'teacher';
$teacherId = input_int($_GET, 'teacher_id');
$branchId = input_int($_GET, 'branch_id');
$room = trim($_GET['room'] ?? '');

$rows = [];
$teacher = null;
if ($mode === 'teacher' && $teacherId) {
    $rows = fetch_schedules(['teacher_id' => $teacherId]);
    foreach (teachers() as $t) {
        if ((int) $t['id'] === $teacherId) { $teacher = $t; }
    }
} elseif ($mode === 'room' && $branchId && $room !== '') {
    $rows = array_values(array_filter(fetch_schedules(['branch_id' => $branchId]),
        fn($r) => vn_normalize($r['room']) === vn_normalize($room)));
}
$rooms = [];
foreach (fetch_schedules($branchId ? ['branch_id' => $branchId] : []) as $r) {
    $rooms[$r['room']] = true;
}
ksort($rooms);

// Tổng hợp các khối tiết trống liên tục theo từng buổi
$freeBlocks = [];
if ($rows) {
    $busy = [];
    foreach ($rows as $r) {
        for ($p = $r['p_start']; $p <= $r['p_end']; $p++) { $busy[$r['day_of_week']][$p] = true; }
    }
    foreach (DAYS as $d => $label) {
        foreach (SESSIONS as [$a, $b, $sl]) {
            $start = null;
            for ($p = $a; $p <= $b + 1; $p++) {
                $free = $p <= $b && empty($busy[$d][$p]);
                if ($free && $start === null) { $start = $p; }
                if (!$free && $start !== null) {
                    $freeBlocks[$d][] = "$sl: tiết " . format_periods($start, $p - 1);
                    $start = null;
                }
            }
        }
    }
}

$link = function (int $d, int $p) use ($mode, $teacherId, $branchId) {
    return 'schedule_form.php?' . http_build_query(array_filter([
        'day' => $d, 'p' => $p,
        'teacher_id' => $mode === 'teacher' ? $teacherId : null,
        'branch_id' => $mode === 'room' ? $branchId : null,
    ]));
};

$title = 'Tiết trống';
require __DIR__ . '/includes/header.php';
?>
<h4 class="mb-3">Tra cứu tiết trống & phân lịch theo tiết</h4>
<ul class="nav nav-tabs mb-3">
    <li class="nav-item"><a class="nav-link <?= $mode === 'teacher' ? 'active' : '' ?>" href="?mode=teacher">Theo giảng viên</a></li>
    <li class="nav-item"><a class="nav-link <?= $mode === 'room' ? 'active' : '' ?>" href="?mode=room">Theo phòng học</a></li>
</ul>
<form class="card card-body mb-3" method="get">
    <input type="hidden" name="mode" value="<?= $mode ?>">
    <div class="row g-2">
        <?php if ($mode === 'teacher'): ?>
            <div class="col-md-6"><select name="teacher_id" class="form-select" onchange="this.form.submit()">
                <option value="">— Chọn giảng viên —</option>
                <?php foreach (teachers() as $t): ?><option value="<?= $t['id'] ?>" <?= $teacherId == $t['id'] ? 'selected' : '' ?>><?= h($t['full_name']) ?> — <?= h($t['expertise']) ?></option><?php endforeach; ?>
            </select></div>
        <?php else: ?>
            <div class="col-md-4"><select name="branch_id" class="form-select" onchange="this.form.submit()">
                <option value="">— Chọn cơ sở —</option>
                <?php foreach (branches() as $b): ?><option value="<?= $b['id'] ?>" <?= $branchId == $b['id'] ? 'selected' : '' ?>><?= h($b['branch_name']) ?></option><?php endforeach; ?>
            </select></div>
            <div class="col-md-3"><input name="room" class="form-control" list="roomList" placeholder="Phòng (VD: A402)" value="<?= h($room) ?>">
                <datalist id="roomList"><?php foreach (array_keys($rooms) as $rm): ?><option value="<?= h($rm) ?>"><?php endforeach; ?></datalist></div>
            <div class="col-md-2"><button class="btn btn-primary w-100">Xem</button></div>
        <?php endif; ?>
    </div>
</form>

<?php if (($mode === 'teacher' && $teacherId) || ($mode === 'room' && $branchId && $room !== '')): ?>
<div class="row g-4">
    <div class="col-xl-9">
        <div class="card">
            <div class="card-header">
                <?= $mode === 'teacher' ? 'Thời khóa biểu: ' . h($teacher['full_name'] ?? '') : 'Phòng ' . h($room) ?>
                <span class="text-muted small fw-normal">— bấm dấu <b>+</b> ở ô trống để phân lịch vào tiết đó</span>
            </div>
            <div class="card-body p-0"><?= render_period_grid($rows, $link) ?></div>
        </div>
    </div>
    <div class="col-xl-3">
        <div class="card">
            <div class="card-header">Khối tiết trống liên tục</div>
            <div class="card-body small">
                <?php foreach (DAYS as $d => $label): ?>
                    <div class="mb-2"><b><?= $label ?>:</b>
                        <?php if (empty($freeBlocks)): ?>Trống cả ngày<?php else: ?>
                            <?= h(implode(' · ', $freeBlocks[$d] ?? ['Kín lịch'])) ?>
                        <?php endif; ?></div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php else: ?>
    <div class="text-muted">Chọn <?= $mode === 'teacher' ? 'giảng viên' : 'cơ sở và phòng học' ?> để xem lưới tiết trống.</div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
