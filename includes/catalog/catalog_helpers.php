<?php
/**
 * includes/catalog/catalog_helpers.php
 * -------------------------------------------------
 * admin/price_manage.php 와 client/catalog.php(예정) 양쪽에서 공용으로 쓰는 함수 모음.
 * 여기 있는 함수들은 원본 price_manage.php에서 그대로(내용 변경 없이) 옮긴 것입니다.
 */

function calc_card_price($cash) {
    if (!$cash) return null;
    return (int)(ceil((float)$cash / 0.97 * 1.1 / 1000) * 1000);
}

/**
 * 특정 업체(company_id)가 지금 시점에 실제로 쓸 수 있는(유효기간 내 + 잔여수량>0) 쿠폰을
 * product_id별로 그룹핑해서 반환.
 * 반환: [ product_id => [ ['allocation_id','coupon_id','coupon_name','discount_type',
 *                          'discount_value','valid_from','valid_to','issued_qty','used_qty',
 *                          'remaining_qty','product_name','is_custom_name'], ... ] ]
 */
function get_active_coupons_for_company(PDO $pdo, int $company_id): array {
    $stmt = $pdo->prepare("
        SELECT
            ca.id AS allocation_id, ca.coupon_id, ca.issued_qty, ca.used_qty,
            c.product_id, c.coupon_name, c.discount_type, c.discount_value,
            c.valid_from, c.valid_to,
            p.product_name
        FROM coupon_allocations ca
        JOIN coupons c  ON c.id = ca.coupon_id
        JOIN products p ON p.id = c.product_id
        WHERE ca.company_id = ?
          AND (ca.issued_qty - ca.used_qty) > 0
          AND CURDATE() BETWEEN c.valid_from AND c.valid_to
    ");
    $stmt->execute([$company_id]);

    $by_product = [];
    foreach ($stmt->fetchAll() as $row) {
        $row['remaining_qty']  = (int)$row['issued_qty'] - (int)$row['used_qty'];
        $row['is_custom_name'] = ($row['coupon_name'] !== null && $row['coupon_name'] !== $row['product_name']);
        $by_product[$row['product_id']][] = $row;
    }
    return $by_product;
}

/**
 * 한 상품에 적용 가능한 쿠폰 후보들 중 "할인액이 가장 큰 것 → 동률이면 만료 임박한 것" 우선으로 하나 선택.
 * 정률(percent) 쿠폰은 unit_price 기준으로 실제 할인액을 계산해서 비교한다.
 * 반환값에 '_effective_amount'(실제 할인액, 원 단위)가 추가됨. 후보가 없거나 unit_price가 없으면 null.
 */
function pick_best_coupon(array $coupons, $unit_price) {
    if (empty($coupons) || $unit_price === null) return null;

    $scored = [];
    foreach ($coupons as $c) {
        $amount = $c['discount_type'] === 'percent'
            ? round($unit_price * $c['discount_value'] / 100)
            : (float)$c['discount_value'];
        $c['_effective_amount'] = $amount;
        $scored[] = $c;
    }

    usort($scored, function ($a, $b) {
        if ($a['_effective_amount'] != $b['_effective_amount']) {
            return $b['_effective_amount'] <=> $a['_effective_amount']; // 할인액 큰 순
        }
        return strtotime($a['valid_to']) <=> strtotime($b['valid_to']); // 만료 임박한 순
    });

    return $scored[0];
}