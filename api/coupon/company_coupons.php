<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/config.php';

require_admin();

header('Content-Type: application/json');

$company_id = (int)($_GET['company_id'] ?? 0);
if (!$company_id) {
    echo json_encode(['success' => false, 'message' => '잘못된 요청입니다.']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT
            ca.id AS allocation_id,
            ca.coupon_id,
            ca.issued_qty,
            ca.used_qty,
            c.coupon_name,
            c.discount_type,
            c.discount_value,
            c.valid_from,
            c.valid_to,
            p.product_name
        FROM coupon_allocations ca
        JOIN coupons c  ON c.id = ca.coupon_id
        JOIN products p ON p.id = c.product_id
        WHERE ca.company_id = ?
        ORDER BY c.valid_to DESC, ca.id DESC
    ");
    $stmt->execute([$company_id]);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$r) {
        $r['remaining_qty'] = (int)$r['issued_qty'] - (int)$r['used_qty'];
        // 쿠폰명이 상품명과 동일하면(발행 시 이름 미입력) 커스텀 이름이 아닌 것으로 간주
        $r['is_custom_name'] = ($r['coupon_name'] !== null && $r['coupon_name'] !== $r['product_name']);
    }
    unset($r);

    echo json_encode(['success' => true, 'coupons' => $rows]);
} catch (PDOException $e) {
    error_log('coupon/company_coupons error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '조회 중 오류가 발생했습니다.']);
}