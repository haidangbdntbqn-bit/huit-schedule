<?php
/** @var string $title */
$user = current_user();
$page = basename($_SERVER['SCRIPT_NAME'] ?? '');
$nav = is_admin() ? [
    'index.php'      => 'Tổng quan',
    'schedules.php'  => 'Lịch dạy',
    'free_slots.php' => 'Tiết trống',
    'teachers.php'   => 'Giảng viên',
    'branches.php'   => 'Cơ sở',
    'portal.php'     => 'Cổng giảng viên',
] : ['portal.php' => 'Lịch của tôi'];
?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($title ?? 'HUIT Schedule Hub') ?> · HUIT Schedule Hub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="assets/style.css" rel="stylesheet">
</head>
<body>
<?php if ($user): ?>
<nav class="navbar navbar-expand-lg navbar-dark bg-huit shadow-sm">
    <div class="container-fluid">
        <a class="navbar-brand fw-bold" href="<?= is_admin() ? 'index.php' : 'portal.php' ?>">
            <i class="bi bi-calendar3-week"></i> HUIT Schedule Hub
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav"><span class="navbar-toggler-icon"></span></button>
        <div class="collapse navbar-collapse" id="nav">
            <ul class="navbar-nav me-auto">
                <?php foreach ($nav as $file => $label): ?>
                    <li class="nav-item"><a class="nav-link <?= $page === $file || ($page === 'schedule_form.php' && $file === 'schedules.php') ? 'active' : '' ?>" href="<?= $file ?>"><?= h($label) ?></a></li>
                <?php endforeach; ?>
            </ul>
            <span class="navbar-text me-3 small">
                <i class="bi bi-person-circle"></i> <?= h($user['name']) ?>
                <span class="badge bg-light text-dark ms-1"><?= $user['role'] === 'admin' ? 'Quản trị' : 'Giảng viên' ?></span>
            </span>
            <a class="btn btn-sm btn-outline-light" href="logout.php"><i class="bi bi-box-arrow-right"></i> Đăng xuất</a>
        </div>
    </div>
</nav>
<?php endif; ?>
<main class="container-fluid py-4 px-lg-4">
<?php foreach ($user ? take_flashes() : [] as $fl): ?>
    <div class="alert alert-<?= h($fl['type']) ?> alert-dismissible fade show"><?= $fl['msg'] ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endforeach; ?>
