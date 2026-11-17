<?php
/**
 * Trang tổng quan: lọc theo Cơ sở / Giảng viên, biểu đồ phân bổ thời gian, rà soát chất lượng lịch.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/scheduler.php';

$user = require_login();
if ($user['role'] !== 'admin') {
    redirect('portal.php');
}

$f = ['branch_id' => input_int($_GET, 'branch_id'), 'teacher_id' => input_int($_GET, 'teacher_id')];
$rows = fetch_schedules($f);
$branches = branches();
$teachers = teachers();
$teacherNames = array_column($teachers, 'full_name', 'id');

// ---- Số liệu biểu đồ ----
$byDayBranch = [];   // [branch_id][day] = tiết
$byTeacher = [];     // [teacher] = tiết
$byBranch = [];      // [branch] = tiết
$bySession = ['sang' => 0, 'chieu' => 0, 'toi' => 0];
$teacherDays = [];   // để đếm số lần di chuyển liên cơ sở
foreach ($rows as $r) {
    $n = $r['p_end'] - $r['p_start'] + 1;
    $byDayBranch[$r['branch_id']][$r['day_of_week']] = ($byDayBranch[$r['branch_id']][$r['day_of_week']] ?? 0) + $n;
    $byTeacher[$r['full_name']] = ($byTeacher[$r['full_name']] ?? 0) + $n;
    $byBranch[$r['branch_name']] = ($byBranch[$r['branch_name']] ?? 0) + $n;
    $bySession[$r['session']] += $n;
    $teacherDays[$r['teacher_id']][$r['day_of_week']][$r['branch_id']] = true;
}
arsort($byTeacher);
$multiBranchDays = 0;
foreach ($teacherDays as $days) {
    foreach ($days as $bs) {
        if (count($bs) > 1) { $multiBranchDays++; }
    }
}
$totalPeriods = array_sum($byBranch);
$issues = audit_schedules($rows);
$nErr = count(array_filter($issues, fn($i) => $i['type'] === 'danger'));

$datasets = [];
foreach ($branches as $b) {
    if ($f['branch_id'] && $f['branch_id'] != $b['id']) continue;
    $datasets[] = [
        'label' => $b['branch_name'],
        'data' => array_map(fn($d) => $byDayBranch[$b['id']][$d] ?? 0, array_keys(DAYS)),
        'backgroundColor' => branch_color((int) $b['id']),
    ];
}
$chart = [
    'days' => array_values(DAYS),
    'dayDatasets' => $datasets,
    'teachers' => array_keys($byTeacher),
    'teacherData' => array_values($byTeacher),
    'branches' => array_keys($byBranch),
    'branchData' => array_values($byBranch),
    'branchColors' => array_map(fn($name) => branch_color((int) (array_column($branches, 'id', 'branch_name')[$name] ?? 1)), array_keys($byBranch)),
    'sessions' => ['Sáng', 'Chiều', 'Tối'],
    'sessionData' => array_values($bySession),
];

$title = 'Tổng quan';
$scripts = ['https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js', 'assets/dashboard.js'];
require __DIR__ . '/includes/header.php';
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h4 class="mb-0">Tổng quan phân lịch giảng dạy</h4>
    <form class="d-flex flex-wrap gap-2" method="get">
        <select name="branch_id" class="form-select" style="width:auto">
            <option value="">Tất cả cơ sở</option>
            <?php foreach ($branches as $b): ?><option value="<?= $b['id'] ?>" <?= $f['branch_id'] == $b['id'] ? 'selected' : '' ?>><?= h($b['branch_name']) ?></option><?php endforeach; ?>
        </select>
        <select name="teacher_id" class="form-select" style="width:auto">
            <option value="">Tất cả giảng viên</option>
            <?php foreach ($teachers as $t): ?><option value="<?= $t['id'] ?>" <?= $f['teacher_id'] == $t['id'] ? 'selected' : '' ?>><?= h($t['full_name']) ?></option><?php endforeach; ?>
        </select>
        <button class="btn btn-primary"><i class="bi bi-funnel"></i> Lọc</button>
        <a class="btn btn-light" href="index.php">Bỏ lọc</a>
    </form>
</div>

<div class="row g-3 mb-4">
    <div class="col-6 col-lg"><div class="card card-body stat d-flex flex-row justify-content-between"><div><div class="text-muted small">Lớp học phần</div><div class="num"><?= count($rows) ?></div></div><i class="bi bi-journal-text"></i></div></div>
    <div class="col-6 col-lg"><div class="card card-body stat d-flex flex-row justify-content-between"><div><div class="text-muted small">Tổng số tiết/tuần</div><div class="num"><?= $totalPeriods ?></div></div><i class="bi bi-clock"></i></div></div>
    <div class="col-6 col-lg"><div class="card card-body stat d-flex flex-row justify-content-between"><div><div class="text-muted small">Giảng viên có lịch</div><div class="num"><?= count($byTeacher) ?>/<?= count($teachers) ?></div></div><i class="bi bi-people"></i></div></div>
    <div class="col-6 col-lg"><div class="card card-body stat d-flex flex-row justify-content-between"><div><div class="text-muted small">Ngày GV đi nhiều cơ sở</div><div class="num"><?= $multiBranchDays ?></div></div><i class="bi bi-signpost-split"></i></div></div>
    <div class="col-6 col-lg"><div class="card card-body stat d-flex flex-row justify-content-between"><div><div class="text-muted small">Xung đột / Cảnh báo</div><div class="num <?= $nErr ? 'text-danger' : '' ?>"><?= $nErr ?>/<?= count($issues) - $nErr ?></div></div><i class="bi bi-shield-check"></i></div></div>
</div>

<div class="row g-4 mb-4">
    <div class="col-xl-8"><div class="card h-100"><div class="card-header">Phân bổ số tiết theo ngày trong tuần & cơ sở</div>
        <div class="card-body"><canvas id="chartDays" height="120"></canvas></div></div></div>
    <div class="col-xl-4"><div class="card h-100"><div class="card-header">Tỷ trọng tiết dạy theo cơ sở</div>
        <div class="card-body"><canvas id="chartBranches" height="200"></canvas></div></div></div>
    <div class="col-xl-8"><div class="card h-100"><div class="card-header">Khối lượng giảng dạy theo giảng viên (tiết/tuần)</div>
        <div class="card-body"><canvas id="chartTeachers" height="<?= max(120, count($byTeacher) * 9) ?>"></canvas></div></div></div>
    <div class="col-xl-4"><div class="card h-100"><div class="card-header">Phân bổ theo buổi</div>
        <div class="card-body"><canvas id="chartSessions" height="200"></canvas></div></div></div>
</div>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between">
        <span>Thời khóa biểu tổng hợp
            <?= $f['branch_id'] ? ' · ' . h(find_branch($f['branch_id'])['branch_name'] ?? '') : '' ?>
            <?= $f['teacher_id'] ? ' · ' . h($teacherNames[$f['teacher_id']] ?? '') : '' ?></span>
        <span class="small fw-normal"><?php foreach ($branches as $b): ?><span class="ms-2"><span class="legend-dot" style="--c:<?= branch_color((int) $b['id']) ?>"></span><?= h($b['branch_name']) ?></span><?php endforeach; ?></span>
    </div>
    <div class="card-body p-0"><?= render_session_grid($rows) ?></div>
</div>

<div class="card">
    <div class="card-header"><i class="bi bi-shield-check"></i> Rà soát chất lượng lịch (trùng lịch, di chuyển, khoảng trống chết)</div>
    <div class="card-body small">
        <?php if (!$issues): ?><div class="text-success"><i class="bi bi-check-circle"></i> Lịch hợp lệ: không trùng giảng viên/phòng, đủ thời gian di chuyển, không có khoảng trống chết.</div><?php endif; ?>
        <?php foreach ($issues as $i): $r = $i['row']; ?>
            <div class="alert alert-<?= $i['type'] ?> py-1 px-2 mb-1">
                <b><?= h($r['full_name']) ?></b> · <?= DAYS[$r['day_of_week']] ?> · <?= h(periods_label($r['shift_or_periods'], false)) ?> · <?= h($r['subject_name']) ?>:
                <?= h($i['msg']) ?> <a href="schedule_form.php?id=<?= $r['id'] ?>" class="alert-link">Sửa</a>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<script id="chartData" type="application/json"><?= json_encode($chart, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
<?php require __DIR__ . '/includes/footer.php'; ?>
