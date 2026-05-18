<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../config/config.php';

require_login();

$current_user = get_current_login_user();
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo h($page_title ?? '가격관리 시스템'); ?></title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
</head>
<body>
    <!-- 모바일 오버레이 -->
<div class="drawer-overlay" id="drawer-overlay" onclick="closeDrawer()"></div>

<!-- 사이드 드로어 -->
<div class="side-drawer" id="side-drawer">
    <div class="drawer-header">
        <h1><a href="<?php echo BASE_URL; ?>/admin/price_manage.php" style="text-decoration:none;color:inherit;">가격관리 시스템</a></h1>
    </div>
    <?php if ($current_user['is_admin']): ?>
    <nav class="drawer-nav">
        <a href="<?php echo BASE_URL; ?>/admin/price_manage.php"
            class="<?php echo basename($_SERVER['PHP_SELF']) === 'price_manage.php' ? 'active' : ''; ?>">
            조회
        </a>
        <a href="<?php echo BASE_URL; ?>/admin/company_manage.php"
            class="<?php echo basename($_SERVER['PHP_SELF']) === 'company_manage.php' ? 'active' : ''; ?>">
            업체 관리
        </a>
    </nav>
    <?php endif; ?>
    <div class="drawer-footer">
        <a href="<?php echo BASE_URL; ?>/change_password.php" class="drawer-link">비밀번호 변경</a>
        <span class="drawer-user-info">
            <?php echo h($current_user['company_name']); ?>
            <?php if ($current_user['is_admin']): ?>
                <span class="badge-admin">관리자</span>
            <?php else: ?>
                <span class="badge-normal">일반 업체</span>
            <?php endif; ?>
        </span>
    </div>
</div>

<header class="main-header">
    <!-- 모바일: 햄버거 -->
    <button class="btn-hamburger" onclick="toggleDrawer()">☰</button>

    <!-- 데스크탑: 로고 -->
    <div class="header-left">
        <h1><a href="<?php echo BASE_URL; ?>/admin/price_manage.php" style="text-decoration:none;color:inherit;">가격관리 시스템</a></h1>
    </div>

    <!-- 데스크탑: 네비 -->
    <?php if ($current_user['is_admin']): ?>
    <div class="header-center">
        <nav class="nav-tabs">
            <a href="<?php echo BASE_URL; ?>/admin/price_manage.php"
                class="<?php echo basename($_SERVER['PHP_SELF']) === 'price_manage.php' ? 'active' : ''; ?>">
                조회
            </a>
            <a href="<?php echo BASE_URL; ?>/admin/company_manage.php"
                class="<?php echo basename($_SERVER['PHP_SELF']) === 'company_manage.php' ? 'active' : ''; ?>">
                업체 관리
            </a>
        </nav>
    </div>
    <?php endif; ?>

    <!-- 데스크탑: 우측 / 모바일: 로그아웃만 -->
    <div class="header-right">
        <span class="user-info desktop-only">
            <?php echo h($current_user['company_name']); ?>
            <?php if ($current_user['is_admin']): ?>
                <span class="badge-admin">관리자</span>
            <?php endif; ?>
        </span>
        <a href="<?php echo BASE_URL; ?>/change_password.php" class="btn-change-pw desktop-only">비밀번호 변경</a>
        <a href="<?php echo BASE_URL; ?>/logout.php" class="btn-logout">로그아웃</a>
    </div>
</header>

<script>
function toggleDrawer() {
    document.getElementById('side-drawer').classList.toggle('open');
    document.getElementById('drawer-overlay').classList.toggle('active');
}
function closeDrawer() {
    document.getElementById('side-drawer').classList.remove('open');
    document.getElementById('drawer-overlay').classList.remove('active');
}
</script>