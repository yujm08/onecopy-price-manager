<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/catalog_helpers.php';

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

    $coupons_by_product = get_active_coupons_for_company($pdo, $company_id);

    $total          = 0;
    $total_discount = 0;

    foreach ($items as &$item) {
        $item['unit_price'] = $item['unit_price'] !== null ? (float)$item['unit_price'] : null;
        $item['line_total'] = $item['unit_price'] !== null
            ? round($item['unit_price'] * $item['quantity'], 2)
            : null;

        // 이 상품에 적용 가능한 쿠폰들 — 할인액 큰 순 → 만료임박 순으로 정렬해서 후보 목록 제공
        $candidates = $coupons_by_product[$item['product_id']] ?? [];
        $scored = [];
        if ($item['unit_price'] !== null) {
            foreach ($candidates as $c) {
                $amount = $c['discount_type'] === 'percent'
                    ? round($item['unit_price'] * $c['discount_value'] / 100)
                    : (float)$c['discount_value'];
                $scored[] = [
                    'allocation_id'    => (int)$c['allocation_id'],
                    'coupon_name'      => $c['is_custom_name'] ? $c['coupon_name'] : $c['product_name'],
                    'discount_type'    => $c['discount_type'],
                    'discount_value'   => (float)$c['discount_value'],
                    'remaining_qty'    => $c['remaining_qty'],
                    'valid_to'         => $c['valid_to'],
                    'effective_amount' => $amount,
                ];
            }
            usort($scored, function ($a, $b) {
                if ($a['effective_amount'] != $b['effective_amount']) {
                    return $b['effective_amount'] <=> $a['effective_amount'];
                }
                return strtotime($a['valid_to']) <=> strtotime($b['valid_to']);
            });
        }
        $item['available_coupons'] = $scored;

        // 기본값: 가장 유리한 쿠폰 자동 적용 (잔여수량만큼만 할인, 나머지 수량은 정가)
        if (!empty($scored)) {
            $best = $scored[0];
            $applied_qty = min($item['quantity'], $best['remaining_qty']);
            $item['applied_coupon_allocation_id'] = $best['allocation_id'];
            $item['coupon_applied_qty']           = $applied_qty;
            $item['line_discount']                = round($best['effective_amount'] * $applied_qty, 2);
        } else {
            $item['applied_coupon_allocation_id'] = null;
            $item['coupon_applied_qty']           = 0;
            $item['line_discount']                = 0;
        }

        $item['line_net'] = $item['line_total'] !== null ? $item['line_total'] - $item['line_discount'] : null;

        if ($item['line_total'] !== null) $total += $item['line_total'];
        $total_discount += $item['line_discount'];
    }
    unset($item);

    $total_net = $total - $total_discount;

    echo json_encode([
        'success'        => true,
        'items'          => $items,
        'total'          => $total,
        'total_discount' => $total_discount,
        'total_net'      => $total_net,
    ]);

} catch (PDOException $e) {
    error_log('cart/list error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => '조회 중 오류가 발생했습니다.']);
}