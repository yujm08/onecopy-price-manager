<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/config.php';

require_login();

header('Content-Type: application/json');

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => '보안 토큰이 유효하지 않습니다.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '잘못된 요청입니다.']);
    exit;
}

$company_id = $_SESSION['user_id'];

try {
    $pdo->prepare("DELETE FROM cart_items WHERE company_id = ?")->execute([$company_id]);
    echo json_encode(['success' => true, 'message' => '장바구니를 비웠습니다.']);
} catch (PDOException $e) {
    error_log('cart/clear error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '처리 중 오류가 발생했습니다.']);
}