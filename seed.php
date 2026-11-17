<?php
/**
 * Sinh dữ liệu mẫu bằng fakerphp/faker.
 *   CLI : php seed.php [số_giảng_viên]
 *   Web : đăng nhập quản trị rồi mở /seed.php
 * CẢNH BÁO: lệnh này XÓA toàn bộ dữ liệu cũ trong 3 bảng branches, teachers, schedules.
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/seeder.php';

$isCli = PHP_SAPI === 'cli';
if (!$isCli) {
    require_admin();
}

if (!class_exists(\Faker\Factory::class)) {
    $msg = 'Chưa cài fakerphp/faker. Hãy chạy: composer install';
    if ($isCli) {
        fwrite(STDERR, $msg . PHP_EOL);
        exit(1);
    }
    flash('danger', $msg);
    redirect('index.php');
}

if (!$isCli && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $title = 'Sinh dữ liệu mẫu';
    require __DIR__ . '/includes/header.php'; ?>
    <div class="card card-body" style="max-width:560px">
        <h5>Sinh dữ liệu mẫu bằng Faker</h5>
        <p class="text-danger small">Thao tác này sẽ xóa toàn bộ cơ sở, giảng viên và lịch dạy hiện có.</p>
        <form method="post"><?= csrf_field() ?>
            <label class="form-label">Số giảng viên</label>
            <input type="number" name="n" value="12" min="3" max="60" class="form-control mb-3">
            <button class="btn btn-danger">Xóa & sinh dữ liệu mới</button>
        </form>
    </div>
    <?php require __DIR__ . '/includes/footer.php';
    exit;
}

if (!$isCli) {
    verify_csrf();
}
$n = $isCli ? (int) ($argv[1] ?? 12) : (int) ($_POST['n'] ?? 12);
$n = max(3, min(60, $n));

$faker = \Faker\Factory::create('vi_VN');
$result = seed_database(db(), $faker, $n, random_int(1, 99999));
$text = "Đã sinh {$result['branches']} cơ sở, {$result['teachers']} giảng viên, {$result['schedules']} lịch dạy. "
      . 'Mật khẩu giảng viên: ' . env('TEACHER_DEFAULT_PASSWORD', '123456');

if ($isCli) {
    echo $text . PHP_EOL;
} else {
    flash('success', h($text));
    redirect('index.php');
}
