<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/scheduler.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    verify_csrf();
    if ($id = input_int($_POST, 'id')) {
        db()->prepare('DELETE FROM schedules WHERE id = ?')->execute([$id]);
        flash('success', 'Đã xóa lịch dạy.');
    }
    redirect('schedules.php?' . http_build_query(array_filter([
        'branch_id' => $_POST['branch_id'] ?? '', 'teacher_id' => $_POST['teacher_id'] ?? '', 'day' => $_POST['day'] ?? '',
    ])));
}

$f = [
    'branch_id'  => input_int($_GET, 'branch_id'),
    'teacher_id' => input_int($_GET, 'teacher_id'),
    'day'        => input_int($_GET, 'day'),
    'q'          => trim($_GET['q'] ?? ''),
];
$rows = fetch_schedules($f);
$issues = [];
foreach (audit_schedules($rows) as $i) {
    $issues[$i['row']['id']][] = $i;
}

$title = 'Lịch dạy';
require __DIR__ . '/includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Lịch dạy <span class="text-muted fs-6">(<?= count($rows) ?> lớp)</span></h4>
    <a href="schedule_form.php" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Phân lịch mới</a>
</div>

<form class="card card-body mb-3" method="get">
    <div class="row g-2">
        <div class="col-md-3"><select name="branch_id" class="form-select"><option value="">— Tất cả cơ sở —</option>
            <?php foreach (branches() as $b): ?><option value="<?= $b['id'] ?>" <?= $f['branch_id'] == $b['id'] ? 'selected' : '' ?>><?= h($b['branch_name']) ?></option><?php endforeach; ?>
        </select></div>
        <div class="col-md-3"><select name="teacher_id" class="form-select"><option value="">— Tất cả giảng viên —</option>
            <?php foreach (teachers() as $t): ?><option value="<?= $t['id'] ?>" <?= $f['teacher_id'] == $t['id'] ? 'selected' : '' ?>><?= h($t['full_name']) ?></option><?php endforeach; ?>
        </select></div>
        <div class="col-md-2"><select name="day" class="form-select"><option value="">— Mọi ngày —</option>
            <?php foreach (DAYS as $k => $v): ?><option value="<?= $k ?>" <?= $f['day'] == $k ? 'selected' : '' ?>><?= $v ?></option><?php endforeach; ?>
        </select></div>
        <div class="col-md-2"><input name="q" class="form-control" placeholder="Môn, phòng, GV..." value="<?= h($f['q']) ?>"></div>
        <div class="col-md-2 d-flex gap-2"><button class="btn btn-outline-primary flex-fill"><i class="bi bi-funnel"></i> Lọc</button>
            <a href="schedules.php" class="btn btn-light">Xóa lọc</a></div>
    </div>
</form>

<div class="card">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0 small">
            <thead><tr><th>Thứ</th><th>Tiết / Giờ</th><th>Học phần</th><th>Giảng viên</th><th>Cơ sở</th><th>Phòng</th><th>Kiểm tra</th><th></th></tr></thead>
            <tbody>
            <?php if (!$rows): ?><tr><td colspan="8" class="text-center text-muted py-4">Chưa có lịch dạy phù hợp.</td></tr><?php endif; ?>
            <?php foreach ($rows as $r): $is = $issues[$r['id']] ?? []; ?>
                <tr>
                    <td class="fw-semibold"><?= DAYS[$r['day_of_week']] ?? $r['day_of_week'] ?></td>
                    <td><?= h(periods_label($r['shift_or_periods'])) ?><div class="text-muted"><?= SESSIONS[$r['session']][2] ?></div></td>
                    <td class="fw-semibold"><?= h($r['subject_name']) ?></td>
                    <td><a href="portal.php?teacher_id=<?= $r['teacher_id'] ?>"><?= h($r['full_name']) ?></a></td>
                    <td><span class="legend-dot" style="--c:<?= branch_color((int) $r['branch_id']) ?>"></span><?= h($r['branch_name']) ?></td>
                    <td><?= h($r['room']) ?></td>
                    <td>
                        <?php if (!$is): ?><span class="badge text-bg-success"><i class="bi bi-check2"></i> Hợp lệ</span><?php endif; ?>
                        <?php foreach ($is as $i): ?>
                            <span class="badge text-bg-<?= $i['type'] ?> text-wrap text-start d-block mb-1" style="max-width:280px"><?= h($i['msg']) ?></span>
                        <?php endforeach; ?>
                    </td>
                    <td class="text-end text-nowrap">
                        <a class="btn btn-sm btn-outline-primary" href="schedule_form.php?id=<?= $r['id'] ?>"><i class="bi bi-pencil"></i></a>
                        <form method="post" class="d-inline" onsubmit="return confirm('Xóa lịch dạy này?')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>">
                            <input type="hidden" name="branch_id" value="<?= h($f['branch_id']) ?>"><input type="hidden" name="teacher_id" value="<?= h($f['teacher_id']) ?>"><input type="hidden" name="day" value="<?= h($f['day']) ?>">
                            <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
