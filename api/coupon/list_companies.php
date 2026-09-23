<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/config.php';

require_admin();

header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
// only_with_coupons=1 이면 쿠폰이 하나라도 할당된 업체만 반환 (쿠폰 관리 메인 목록용).
// 없거나 0이면 전체 업체 대상으로 검색 (쿠폰 발행 모달의 업체 검색/전체선택용).
$only_with_coupons = ($_GET['only_with_coupons'] ?? '') === '1';

try {
    $where  = "WHERE c.role = 'user'";
    $params = [];

    if ($q !== '') {
        $where   .= " AND c.company_name LIKE ?";
        $params[] = '%' . $q . '%';
    }

    if ($only_with_coupons) {
        $sql = "
            SELECT DISTINCT c.id, c.company_name, c.grade
            FROM companies c
            JOIN coupon_allocations ca ON ca.company_id = c.id
            $where
            ORDER BY c.company_name
        ";
    } else {
        $sql = "
            SELECT c.id, c.company_name, c.grade
            FROM companies c
            $where
            ORDER BY c.company_name
        ";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    echo json_encode(['success' => true, 'companies' => $stmt->fetchAll()]);
} catch (PDOException $e) {
    error_log('coupon/list_companies error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '조회 중 오류가 발생했습니다.']);
}