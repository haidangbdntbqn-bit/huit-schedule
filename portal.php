<?php
/**
 * Cổng thông tin Giảng viên: xem lịch dạy cá nhân theo tuần / tháng và các cơ sở cần đến.
 * Truy cập: đăng nhập email/mật khẩu hoặc đường dẫn riêng portal.php?token=...
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/scheduler.php';

// Đăng nhập bằng đường dẫn riêng
if (!empty($_GET['token'])) {
    $st = db()->prepare('SELECT id, full_name, email FROM teachers WHERE access_token = ?');
    $st->execute([(string) $_GET['token']]);
    if ($t = $st->fetch()) {
        login_teacher($t);
        redirect('portal.php');
    }
    flash('danger', 'Đường dẫn không hợp lệ hoặc đã bị thu hồi.');
    redirect('login.php');
}

$user = require_login();
if ($user['role'] === 'admin') {
    $teacherId = input_int($_GET, 'teacher_id') ?? (int) (teachers()[0]['id'] ?? 0);
} else {
    $teacherId = (int) $user['id'];   // giảng viên chỉ xem được lịch của chính mình
}
$st = db()->prepare('SELECT * FROM teachers WHERE id = ?');
$st->execute([$teacherId]);
$teacher = $st->fetch();
if (!$teacher) {
    flash('warning', 'Chưa có giảng viên nào trong hệ thống.');
    redirect(is_admin() ? 'teachers.php' : 'logout.php');
}

$view = ($_GET['view'] ?? 'week') === 'month' ? 'month' : 'week';
$rows = fetch_schedules(['teacher_id' => $teacherId]);
$byDay = [];
foreach ($rows as $r) {
    $byDay[$r['day_of_week']][] = $r;
}
$totalPeriods = array_sum(array_map(fn($r) => $r['p_end'] - $r['p_start'] + 1, $rows));

// Các cơ sở cần đến
$branchVisits = [];
foreach ($rows as $r) {
    $branchVisits[$r['branch_id']]['name'] = $r['branch_name'];
    $branchVisits[$r['branch_id']]['address'] = $r['address'];
    $branchVisits[$r['branch_id']]['days'][$r['day_of_week']] = DAYS[$r['day_of_week']];
}

/** day_of_week theo quy ước VN từ ngày dương lịch (Thứ 2 = 2 ... CN = 8) */
$dowOf = fn(DateTimeInterface $dt) => (int) $dt->format('N') + 1;

// Tuần đang xem
$weekRef = new DateTimeImmutable($_GET['week'] ?? 'today');
$monday = $weekRef->modify('monday this week');
$dates = [];
for ($i = 0; $i < 7; $i++) {
    $dates[$i + 2] = $monday->modify("+$i day")->format('d/m');
}
// Tháng đang xem
$monthRef = DateTimeImmutable::createFromFormat('Y-m-d', ($_GET['month'] ?? date('Y-m')) . '-01') ?: new DateTimeImmutable('first day of this month');
$teachDays = 0;
for ($d = $monthRef; $d->format('m') === $monthRef->format('m'); $d = $d->modify('+1 day')) {
    if (!empty($byDay[$dowOf($d)])) { $teachDays++; }
}

$title = 'Lịch dạy cá nhân';
require __DIR__ . '/includes/header.php';
$qs = fn(array $extra) => '?' . http_build_query(array_merge(is_admin() ? ['teacher_id' => $teacherId] : [], ['view' => $view], $extra));
?>
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <h4 class="mb-0"><i class="bi bi-person-badge"></i> <?= h($teacher['full_name']) ?></h4>
        <div class="text-muted small"><?= h($teacher['email']) ?> · <?= h($teacher['phone']) ?> · Chuyên môn: <?= h($teacher['expertise']) ?></div>
    </div>
    <div class="d-flex gap-2 no-print">
        <?php if (is_admin()): ?>
            <form method="get"><input type="hidden" name="view" value="<?= $view ?>">
                <select name="teacher_id" class="form-select" onchange="this.form.submit()">
                    <?php foreach (teachers() as $t): ?><option value="<?= $t['id'] ?>" <?= $t['id'] == $teacherId ? 'selected' : '' ?>><?= h($t['full_name']) ?></option><?php endforeach; ?>
                </select></form>
        <?php endif; ?>
        <div class="btn-group">
            <a class="btn btn-<?= $view === 'week' ? 'primary' : 'outline-primary' ?>" href="<?= $qs(['view' => 'week']) ?>">Tuần</a>
            <a class="btn btn-<?= $view === 'month' ? 'primary' : 'outline-primary' ?>" href="<?= $qs(['view' => 'month']) ?>">Tháng</a>
        </div>
        <button class="btn btn-outline-secondary" onclick="window.print()"><i class="bi bi-printer"></i></button>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-md-3"><div class="card card-body stat"><div class="text-muted small">Số lớp / tuần</div><div class="num"><?= count($rows) ?></div></div></div>
    <div class="col-md-3"><div class="card card-body stat"><div class="text-muted small">Số tiết / tuần</div><div class="num"><?= $totalPeriods ?></div></div></div>
    <div class="col-md-3"><div class="card card-body stat"><div class="text-muted small">Số cơ sở phải đến</div><div class="num"><?= count($branchVisits) ?></div></div></div>
    <div class="col-md-3"><div class="card card-body stat"><div class="text-muted small">Ngày lên lớp trong tháng <?= $monthRef->format('m/Y') ?></div><div class="num"><?= $teachDays ?></div></div></div>
</div>

<div class="row g-4">
    <div class="col-xl-9">
        <?php if ($view === 'week'): ?>
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center no-print-flex">
                    <a class="btn btn-sm btn-light" href="<?= $qs(['week' => $monday->modify('-7 day')->format('Y-m-d')]) ?>"><i class="bi bi-chevron-left"></i> Tuần trước</a>
                    <span>Tuần <?= $monday->format('d/m') ?> – <?= $monday->modify('+6 day')->format('d/m/Y') ?></span>
                    <a class="btn btn-sm btn-light" href="<?= $qs(['week' => $monday->modify('+7 day')->format('Y-m-d')]) ?>">Tuần sau <i class="bi bi-chevron-right"></i></a>
                </div>
                <div class="card-body p-0"><?= render_period_grid($rows, null, $dates) ?></div>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <a class="btn btn-sm btn-light" href="<?= $qs(['month' => $monthRef->modify('-1 month')->format('Y-m')]) ?>"><i class="bi bi-chevron-left"></i></a>
                    <span>Tháng <?= $monthRef->format('m/Y') ?></span>
                    <a class="btn btn-sm btn-light" href="<?= $qs(['month' => $monthRef->modify('+1 month')->format('Y-m')]) ?>"><i class="bi bi-chevron-right"></i></a>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered month-table mb-0">
                        <thead><tr><?php foreach (DAYS as $l): ?><th class="text-center"><?= $l ?></th><?php endforeach; ?></tr></thead>
                        <tbody>
                        <?php
                        $cur = $monthRef->modify('monday this week');
                        $end = $monthRef->modify('last day of this month')->modify('sunday this week');
                        $today = date('Y-m-d');
                        while ($cur <= $end):
                            echo '<tr>';
                            for ($i = 0; $i < 7; $i++, $cur = $cur->modify('+1 day')):
                                $other = $cur->format('m') !== $monthRef->format('m');
                                $cls = ($other ? 'other ' : '') . ($cur->format('Y-m-d') === $today ? 'today' : '');
                                echo '<td class="' . $cls . '"><div class="day-num">' . $cur->format('j') . '</div>';
                                if (!$other) {
                                    foreach ($byDay[$dowOf($cur)] ?? [] as $r) {
                                        echo '<div class="chip" style="--c:' . branch_color((int) $r['branch_id']) . '"><b>T' . h(format_periods($r['p_start'], $r['p_end'])) . '</b> '
                                            . h($r['subject_name']) . '<div class="text-muted">' . h($r['branch_name']) . ' · P.' . h($r['room']) . '</div></div>';
                                    }
                                }
                                echo '</td>';
                            endfor;
                            echo '</tr>';
                        endwhile;
                        ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>
    </div>
    <div class="col-xl-3">
        <div class="card mb-3">
            <div class="card-header"><i class="bi bi-geo-alt"></i> Cơ sở cần đến dạy</div>
            <div class="card-body small">
                <?php if (!$branchVisits): ?><div class="text-muted">Chưa có lịch dạy.</div><?php endif; ?>
                <?php foreach ($branchVisits as $bid => $b): ?>
                    <div class="mb-3">
                        <div class="fw-semibold"><span class="legend-dot" style="--c:<?= branch_color((int) $bid) ?>"></span><?= h($b['name']) ?></div>
                        <div class="text-muted"><?= h($b['address']) ?></div>
                        <div><?= h(implode(', ', $b['days'])) ?></div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><i class="bi bi-signpost-split"></i> Lộ trình theo ngày</div>
            <div class="card-body small">
                <?php foreach (DAYS as $d => $label): if (empty($byDay[$d])) continue; ?>
                    <div class="mb-2"><b><?= $label ?>:</b>
                        <?php
                        $steps = [];
                        foreach ($byDay[$d] as $r) {
                            $steps[] = h($r['branch_name']) . ' <span class="text-muted">(T' . h(format_periods($r['p_start'], $r['p_end'])) . ', P.' . h($r['room']) . ')</span>';
                        }
                        echo implode(' → ', $steps);
                        ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
