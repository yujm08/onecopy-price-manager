<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/config.php';

require_login();

header('Content-Type: application/json');

$company_id = $_SESSION['user_id'];
$grade      = $_SESSION['grade'] ?? null;

if (!in_array($grade, ['A', 'B', 'C'], true)) {
    echo json_encode(['success' => false, 'message' => '등급 정보가 없어 조회할 수 없습니다.']);
    exit;
}

// $grade는 로그인 시 DB(companies.grade ENUM)에서 세팅된 값만 들어오므로
// A/B/C 화이트리스트 체크 후에는 컬럼명 직접 조립에 사용해도 안전함
$price_col  = 'cash_price_' . strtolower($grade);
$this_month = date('Y-m-01');

try {
    $stmt = $pdo->prepare("
        SELECT
            ci.id AS cart_item_id,
            ci.product_id,
            ci.quantity,
            p.product_name,
            p.product_number,
            pr.$price_col AS unit_price
        FROM cart_items ci
        JOIN products p ON p.id = ci.product_id
        LEFT JOIN prices pr ON pr.product_id = ci.product_id AND pr.price_month = ?
        WHERE ci.company_id = ?
        ORDER BY ci.id DESC
    ");
    $stmt->execute([$this_month, $company_id]);
    $items = $stmt->fetchAll();

    $total = 0;
    foreach ($items as &$item) {
        $item['unit_price'] = $item['unit_price'] !== null ? (float)$item['unit_price'] : null;
        $item['line_total'] = $item['unit_price'] !== null
            ? round($item['unit_price'] * $item['quantity'], 2)
            : null;
        if ($item['line_total'] !== null) {
            $total += $item['line_total'];
        }
    }
    unset($item);

    echo json_encode(['success' => true, 'items' => $items, 'total' => $total]);

} catch (PDOException $e) {
    error_log('cart/list error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '조회 중 오류가 발생했습니다.']);
}