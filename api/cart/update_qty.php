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

$company_id   = $_SESSION['user_id'];
$cart_item_id = (int)($_POST['cart_item_id'] ?? 0);
$quantity     = (int)($_POST['quantity'] ?? 0);

if ($cart_item_id <= 0 || $quantity <= 0) {
    echo json_encode(['success' => false, 'message' => '잘못된 요청입니다.']);
    exit;
}

try {
    // company_id까지 조건에 넣어 다른 회사 소유 항목을 수정하지 못하게 방지
    $stmt = $pdo->prepare("
        UPDATE cart_items SET quantity = ?
        WHERE id = ? AND company_id = ?
    ");
    $stmt->execute([$quantity, $cart_item_id, $company_id]);

    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => '해당 항목을 찾을 수 없습니다.']);
        exit;
    }

    echo json_encode(['success' => true, 'message' => '수량이 변경되었습니다.']);

} catch (PDOException $e) {
    error_log('cart/update_qty error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '처리 중 오류가 발생했습니다.']);
}