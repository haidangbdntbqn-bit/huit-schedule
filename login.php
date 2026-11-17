<?php
require_once __DIR__ . '/includes/auth.php';

if (current_user()) {
    redirect(is_admin() ? 'index.php' : 'portal.php');
}

$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = trim($_POST['email'] ?? '');
    if (attempt_login($email, (string) ($_POST['password'] ?? ''))) {
        redirect(is_admin() ? 'index.php' : 'portal.php');
    }
    flash('danger', 'Email hoặc mật khẩu không đúng.');
}
$title = 'Đăng nhập';
require __DIR__ . '/includes/header.php';
?>
<div class="login-wrap position-fixed top-0 start-0 w-100">
    <div class="card p-4" style="width: 380px">
        <div class="text-center mb-3">
            <i class="bi bi-calendar3-week text-primary" style="font-size: 2.5rem"></i>
            <h4 class="fw-bold mb-0">HUIT Schedule Hub</h4>
            <div class="text-muted small">Quản lý & phân lịch giảng dạy đa cơ sở</div>
        </div>
        <?php foreach (take_flashes() as $f): ?>
            <div class="alert alert-<?= h($f['type']) ?> py-2"><?= $f['msg'] ?></div>
        <?php endforeach; ?>
        <form method="post">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" value="<?= h($email) ?>" required autofocus>
            </div>
            <div class="mb-3">
                <label class="form-label">Mật khẩu</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button class="btn btn-primary w-100">Đăng nhập</button>
        </form>
        <div class="small text-muted mt-3">
            Quản trị: tài khoản cấu hình trong <code>.env</code> (mặc định <code>admin@huit.edu.vn / admin123</code>).<br>
            Giảng viên: email giảng viên, mật khẩu mặc định <code>123456</code>, hoặc dùng đường dẫn riêng do Phòng Đào tạo cấp.
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
