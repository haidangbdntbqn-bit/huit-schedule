<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/scheduler.php';
require_admin();

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $id = input_int($_POST, 'id');
    $name = trim($_POST['branch_name'] ?? '');
    $address = trim($_POST['address'] ?? '');

    if ($action === 'delete' && $id) {
        $cnt = $pdo->prepare('SELECT COUNT(*) FROM schedules WHERE branch_id = ?');
        $cnt->execute([$id]);
        if ($cnt->fetchColumn() > 0) {
            flash('danger', 'Không thể xóa: cơ sở này đang có lịch dạy. Hãy chuyển/xóa lịch trước.');
        } else {
            $pdo->prepare('DELETE FROM branches WHERE id = ?')->execute([$id]);
            flash('success', 'Đã xóa cơ sở.');
        }
        redirect('branches.php');
    }

    if ($name === '') {
        flash('danger', 'Tên cơ sở không được để trống.');
    } elseif ($id) {
        $pdo->prepare('UPDATE branches SET branch_name = ?, address = ? WHERE id = ?')->execute([$name, $address, $id]);
        flash('success', 'Đã cập nhật cơ sở.');
    } else {
        $pdo->prepare('INSERT INTO branches (branch_name, address) VALUES (?, ?)')->execute([$name, $address]);
        flash('success', 'Đã thêm cơ sở mới.');
    }
    redirect('branches.php');
}

$edit = null;
if ($eid = input_int($_GET, 'edit')) {
    $edit = find_branch($eid);
}
$rows = $pdo->query('SELECT b.*, (SELECT COUNT(*) FROM schedules s WHERE s.branch_id = b.id) AS n_classes,
                     (SELECT COUNT(DISTINCT s.teacher_id) FROM schedules s WHERE s.branch_id = b.id) AS n_teachers
                     FROM branches b ORDER BY b.id')->fetchAll();

$title = 'Cơ sở';
require __DIR__ . '/includes/header.php';
?>
<div class="row g-4">
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><?= $edit ? 'Sửa cơ sở' : 'Thêm cơ sở' ?></div>
            <div class="card-body">
                <form method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= h($edit['id'] ?? '') ?>">
                    <div class="mb-3">
                        <label class="form-label">Tên cơ sở *</label>
                        <input name="branch_name" class="form-control" required value="<?= h($edit['branch_name'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Địa chỉ</label>
                        <textarea name="address" class="form-control" rows="2"><?= h($edit['address'] ?? '') ?></textarea>
                    </div>
                    <button class="btn btn-primary"><?= $edit ? 'Cập nhật' : 'Thêm mới' ?></button>
                    <?php if ($edit): ?><a href="branches.php" class="btn btn-light">Hủy</a><?php endif; ?>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">Danh sách cơ sở</div>
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead><tr><th>#</th><th>Tên cơ sở</th><th>Địa chỉ</th><th class="text-center">Số lớp</th><th class="text-center">Số GV</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($rows as $r): ?>
                        <tr>
                            <td><span class="legend-dot" style="--c:<?= branch_color((int) $r['id']) ?>"></span><?= $r['id'] ?></td>
                            <td class="fw-semibold"><?= h($r['branch_name']) ?></td>
                            <td class="small"><?= h($r['address']) ?></td>
                            <td class="text-center"><?= $r['n_classes'] ?></td>
                            <td class="text-center"><?= $r['n_teachers'] ?></td>
                            <td class="text-end text-nowrap">
                                <a class="btn btn-sm btn-outline-secondary" href="index.php?branch_id=<?= $r['id'] ?>" title="Xem lịch"><i class="bi bi-bar-chart"></i></a>
                                <a class="btn btn-sm btn-outline-primary" href="?edit=<?= $r['id'] ?>"><i class="bi bi-pencil"></i></a>
                                <form method="post" class="d-inline" onsubmit="return confirm('Xóa cơ sở này?')">
                                    <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $r['id'] ?>">
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
