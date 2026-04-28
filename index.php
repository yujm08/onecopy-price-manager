<?php
// index.php
// 메인 진입점 - 로그인 상태에 따라 적절한 페이지로 리다이렉트

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/config.php';

// 로그인 안 되어 있으면 로그인 페이지로
if (!is_logged_in()) {
    header('Location: ' . BASE_URL . '/login.php');
    exit;
}

// 로그인 되어 있으면 가격 관리 페이지로
// (관리자든 일반 업체든 같은 페이지, 권한에 따라 기능만 다름)
header('Location: ' . BASE_URL . '/admin/price_manage.php');
exit;
?>