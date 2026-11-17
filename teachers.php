<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/scheduler.php';
require_admin();

$pdo = db();
$defaultPass = (string) env('TEACHER_DEFAULT_PASSWORD', '123456');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? 'save';
    $id = input_int($_POST, 'id');

    if ($action === 'delete' && $id) {
        $cnt = $pdo->prepare('SELECT COUNT(*) FROM schedules WHERE teacher_id = ?');
        $cnt->execute([$id]);
        if ($cnt->fetchColumn() > 0) {
            flash('danger', 'Không thể xóa: giảng viên đang có lịch dạy. Hãy phân lại lịch trước.');
        } else {
            $pdo->prepare('DELETE FROM teachers WHERE id = ?')->execute([$id]);
            flash('success', 'Đã xóa giảng viên.');
        }
        redirect('teachers.php');
    }
    if ($action === 'reset' && $id) {
        $pdo->prepare('UPDATE teachers SET password_hash = ?, access_token = ? WHERE id = ?')
            ->execute([password_hash($defaultPass, PASSWORD_DEFAULT), bin2hex(random_bytes(16)), $id]);
        flash('success', "Đã đặt lại mật khẩu về <b>" . h($defaultPass) . "</b> và cấp đường dẫn cá nhân mới.");
        redirect('teachers.php');
    }

    $data = [
        'full_name' => trim($_POST['full_name'] ?? ''),
        'email'     => mb_strtolower(trim($_POST['email'] ?? '')),
        'phone'     => trim($_POST['phone'] ?? ''),
        'expertise' => trim($_POST['expertise'] ?? ''),
    ];
    if ($data['full_name'] === '' || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        flash('danger', 'Vui lòng nhập họ tên và email hợp lệ.');
        redirect('teachers.php' . ($id ? "?edit=$id" : ''));
    }
    $dup = $pdo->prepare('SELECT id FROM teachers WHERE LOWER(email) = ? AND id <> ?');
    $dup->execute([$data['email'], $id ?? 0]);
    if ($dup->fetch()) {
        flash('danger', 'Email đã tồn tại.');
        redirect('teachers.php' . ($id ? "?edit=$id" : ''));
    }
    if ($id) {
        $pdo->prepare('UPDATE teachers SET full_name = ?, email = ?, phone = ?, expertise = ? WHERE id = ?')
            ->execute([...array_values($data), $id]);
        flash('success', 'Đã cập nhật giảng viên.');
    } else {
        $pdo->prepare('INSERT INTO teachers (full_name, email, phone, expertise, password_hash, access_token) VALUES (?, ?, ?, ?, ?, ?)')
            ->execute([...array_values($data), password_hash($defaultPass, PASSWORD_DEFAULT), bin2hex(random_bytes(16))]);
        flash('success', 'Đã thêm giảng viên (mật khẩu mặc định: <b>' . h($defaultPass) . '</b>).');
    }
    redirect('teachers.php');
}

$edit = null;
if ($eid = input_int($_GET, 'edit')) {
    $st = $pdo->prepare('SELECT * FROM teachers WHERE id = ?');
    $st->execute([$eid]);
    $edit = $st->fetch() ?: null;
}
$q = trim($_GET['q'] ?? '');
$sql = 'SELECT * FROM teachers';
$args = [];
if ($q !== '') {
    $sql .= ' WHERE full_name LIKE ? OR email LIKE ? OR expertise LIKE ?';
    $args = array_fill(0, 3, "%$q%");
}
$st = $pdo->prepare($sql . ' ORDER BY full_name');
$st->execute($args);
$rows = $st->fetchAll();
$all = fetch_schedules();
$load = weekly_load($all);
$branchCount = [];
foreach ($all as $r) {
    $branchCount[$r['teacher_id']][$r['branch_id']] = true;
}
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$baseUrl = $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/') . '/';

$title = 'Giảng viên';
require __DIR__ . '/includes/header.php';
?>
<div class="row g-4">
    <div class="col-xl-4">
        <div class="card">
            <div class="card-header"><?= $edit ? 'Sửa giảng viên' : 'Thêm giảng viên' ?></div>
            <div class="card-body">
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= h($edit['id'] ?? '') ?>">
                    <div class="mb-2"><label class="form-label">Họ tên *</label>
                        <input name="full_name" class="form-control" required value="<?= h($edit['full_name'] ?? '') ?>"></div>
                    <div class="mb-2"><label class="form-label">Email *</label>
                        <input type="email" name="email" class="form-control" required value="<?= h($edit['email'] ?? '') ?>"></div>
                    <div class="mb-2"><label class="form-label">Điện thoại</label>
                        <input name="phone" class="form-control" value="<?= h($edit['phone'] ?? '') ?>"></div>
                    <div class="mb-3"><label class="form-label">Chuyên môn chính</label>
                        <input name="expertise" class="form-control" placeholder="VD: Lập trình web, PHP, Mã nguồn mở" value="<?= h($edit['expertise'] ?? '') ?>">
                        <div class="form-text">Nhiều chuyên môn cách nhau bằng dấu phẩy — dùng cho chức năng gợi ý giảng viên.</div></div>
                    <button class="btn btn-primary"><?= $edit ? 'Cập nhật' : 'Thêm mới' ?></button>
                    <?php if ($edit): ?><a href="teachers.php" class="btn btn-light">Hủy</a><?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    <div class="col-xl-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Danh sách giảng viên (<?= count($rows) ?>)</span>
                <form class="d-flex gap-2" method="get">
                    <input name="q" class="form-control form-control-sm" placeholder="Tìm tên, email, chuyên môn" value="<?= h($q) ?>">
                    <button class="btn btn-sm btn-outline-primary"><i class="bi bi-search"></i></button>
                </form>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle small">
                    <thead><tr><th>Họ tên</th><th>Liên hệ</th><th>Chuyên môn</th><th class="text-center">Tiết/tuần</th><th class="text-center">Số CS</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $t): ?>
                        <tr>
                            <td class="fw-semibold"><?= h($t['full_name']) ?></td>
                            <td><?= h($t['email']) ?><div class="text-muted"><?= h($t['phone']) ?></div></td>
                            <td><?= h($t['expertise']) ?></td>
                            <td class="text-center"><span class="badge text-bg-<?= ($load[$t['id']] ?? 0) > 24 ? 'danger' : 'primary' ?>"><?= $load[$t['id']] ?? 0 ?></span></td>
                            <td class="text-center"><?= count($branchCount[$t['id']] ?? []) ?></td>
                            <td class="text-end text-nowrap">
                                <a class="btn btn-sm btn-outline-success" href="portal.php?teacher_id=<?= $t['id'] ?>" title="Xem lịch"><i class="bi bi-calendar-week"></i></a>
                                <button type="button" class="btn btn-sm btn-outline-secondary" title="Sao chép đường dẫn cá nhân"
                                        data-link="<?= h($baseUrl . 'portal.php?token=' . $t['access_token']) ?>" onclick="copyLink(this)">
                                    <i class="bi bi-link-45deg"></i></button>
                                <a class="btn btn-sm btn-outline-primary" href="?edit=<?= $t['id'] ?>"><i class="bi bi-pencil"></i></a>
                                <form method="post" class="d-inline" onsubmit="return confirm('Đặt lại mật khẩu và cấp link mới?')">
                                    <?= csrf_field() ?><input type="hidden" name="action" value="reset"><input type="hidden" name="id" value="<?= $t['id'] ?>">
                                    <button class="btn btn-sm btn-outline-warning" title="Đặt lại mật khẩu"><i class="bi bi-key"></i></button>
                                </form>
                                <form method="post" class="d-inline" onsubmit="return confirm('Xóa giảng viên này?')">
                                    <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $t['id'] ?>">
                                    <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
