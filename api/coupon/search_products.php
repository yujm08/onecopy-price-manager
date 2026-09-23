<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/config.php';

require_admin();

header('Content-Type: application/json');

$q = trim($_GET['q'] ?? '');
if ($q === '') {
    echo json_encode(['success' => true, 'products' => []]);
    exit;
}

try {
    $like = '%' . $q . '%';
    $stmt = $pdo->prepare("
        SELECT id, product_number, product_name
        FROM products
        WHERE (product_name LIKE ? OR product_number LIKE ?) AND is_active = 1
        ORDER BY product_name
        LIMIT 20
    ");
    $stmt->execute([$like, $like]);
    echo json_encode(['success' => true, 'products' => $stmt->fetchAll()]);
} catch (PDOException $e) {
    error_log('coupon/search_products error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '검색 중 오류가 발생했습니다.']);
}