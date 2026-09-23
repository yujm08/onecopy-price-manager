<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/config.php';

require_superadmin();

header('Content-Type: application/json');

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => '보안 토큰이 유효하지 않습니다.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '잘못된 요청입니다.']);
    exit;
}

$product_id    = (int)($_POST['product_id'] ?? 0);
$coupon_name   = trim($_POST['coupon_name'] ?? '');
$discount_value = (int)($_POST['discount_value'] ?? 0);
$issued_qty    = (int)($_POST['issued_qty'] ?? 0);
$valid_from    = trim($_POST['valid_from'] ?? '');
$valid_to      = trim($_POST['valid_to'] ?? '');
$company_ids   = $_POST['company_ids'] ?? [];
// 카카오 알림톡 자동발송 체크박스는 UI 상태만 받고 실제 발송 로직은 아직 없음 (추후 연동)
// $send_alimtalk = isset($_POST['send_alimtalk']);

// 정수 배열로 정제
$company_ids = array_values(array_filter(array_map('intval', (array)$company_ids), fn($id) => $id > 0));

$errors = [];
if (!$product_id) $errors[] = '대상 상품을 선택해주세요.';
if ($discount_value <= 0) $errors[] = '할인 금액을 입력해주세요.';
if ($issued_qty <= 0) $errors[] = '발행 수량을 입력해주세요.';
if (!$valid_from || !$valid_to || strtotime($valid_from) === false || strtotime($valid_to) === false) {
    $errors[] = '사용 기한을 확인해주세요.';
} elseif (strtotime($valid_to) < strtotime($valid_from)) {
    $errors[] = '종료일이 시작일보다 빠를 수 없습니다.';
}
if (empty($company_ids)) $errors[] = '대상 업체를 1개 이상 선택해주세요.';

if (!empty($errors)) {
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
    exit;
}

try {
    // 상품 존재 확인 + 이름 조회(쿠폰명 기본값용)
    $pstmt = $pdo->prepare("SELECT product_name FROM products WHERE id = ?");
    $pstmt->execute([$product_id]);
    $product = $pstmt->fetch();
    if (!$product) {
        echo json_encode(['success' => false, 'message' => '존재하지 않는 상품입니다.']);
        exit;
    }

    // 선택된 업체가 실제 존재하는 일반 업체인지 확인
    $ph = implode(',', array_fill(0, count($company_ids), '?'));
    $cstmt = $pdo->prepare("SELECT id FROM companies WHERE id IN ($ph) AND role = 'user'");
    $cstmt->execute($company_ids);
    $valid_company_ids = $cstmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($valid_company_ids)) {
        echo json_encode(['success' => false, 'message' => '유효한 대상 업체가 없습니다.']);
        exit;
    }

    $final_coupon_name = $coupon_name !== '' ? $coupon_name : $product['product_name'];

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO coupons (product_id, coupon_name, discount_type, discount_value, valid_from, valid_to)
        VALUES (?, ?, 'fixed', ?, ?, ?)
    ");
    $stmt->execute([$product_id, $final_coupon_name, $discount_value, $valid_from, $valid_to]);
    $coupon_id = (int)$pdo->lastInsertId();

    $alloc_stmt = $pdo->prepare("
        INSERT INTO coupon_allocations (coupon_id, company_id, issued_qty)
        VALUES (?, ?, ?)
    ");
    foreach ($valid_company_ids as $cid) {
        $alloc_stmt->execute([$coupon_id, $cid, $issued_qty]);
    }

    $pdo->commit();

    $skipped = count($company_ids) - count($valid_company_ids);
    $message = count($valid_company_ids) . '개 업체에 쿠폰이 발행되었습니다.';
    if ($skipped > 0) $message .= " ({$skipped}개 업체는 유효하지 않아 제외됨)";

    echo json_encode(['success' => true, 'message' => $message, 'coupon_id' => $coupon_id]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('coupon/create error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '발행 중 오류가 발생했습니다.']);
}