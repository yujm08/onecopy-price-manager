<?php
/**
 * includes/catalog/catalog_price_cards.php
 * -------------------------------------------------
 * 고객(일반 업체) 전용 모바일 카드형 상품 목록.
 * ⚠️ client/catalog.php에서만 include합니다.
 *    관리자 화면(admin/price_manage.php 수정모드·업체 미리보기)은 이 파일을 쓰지 않으므로
 *    관리자 모바일 화면은 기존 테이블(catalog_price_table.php) 그대로 유지됩니다.
 *
 * 표시/숨김은 client/catalog.php의 CSS(768px 기준)가 담당합니다.
 *   PC  : .catalog-desktop(테이블) 표시, .catalog-mobile(이 카드) 숨김
 *   모바일: 반대
 *
 * [필수 변수] (catalog_price_table.php와 같은 스코프 공유 방식)
 *   $products            - 상품 목록 (product_number, brand_name, product_name, description,
 *                           category_name, cash_price_a/b/c 포함)
 *   $effective_grade     - 'a' / 'b' / 'c'
 * 
 * 
 * 
 *   $coupons_by_product  - get_active_coupons_for_company() 결과
 *   $is_searching        - 다중검색 모드 여부 (카드에 구분명 표시)
 *   $current_user        - 워터마크 텍스트용
 *   calc_card_price(), pick_best_coupon() - includes/catalog/catalog_helpers.php
 */
?>
<style>
.catalog-cards-wrap {
    position: relative;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.08);
    padding: 4px 14px;
    user-select: none;
    -webkit-user-select: none;
}
.pc-card { padding: 14px 0; border-bottom: 1px solid #e3e7eb; }
.pc-card:last-child { border-bottom: none; }

.pc-meta { font-size: 11px; color: #888; display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.pc-cat { background: #eef1f4; color: #5A6778; padding: 1px 7px; border-radius: 10px; font-size: 11px; }

/* 제품명 + 설명: 이름이 길면 설명이 자연스럽게 아랫줄로 내려감 */
.pc-head { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 6px; align-items: stretch; }
.pc-name {
    flex: 0 1 auto; max-width: 100%;
    background: #f1f3f5; border-radius: 8px; padding: 8px 12px;
    font-size: 17px; font-weight: 700; color: #2c3e50;
    word-break: break-all; display: flex; align-items: center;
}
.pc-desc {
    flex: 1 1 140px;
    border: 1px solid #d5dbe0; border-radius: 6px; padding: 6px 10px;
    font-size: 12px; color: #555; line-height: 1.5; word-break: break-all;
}

.pc-foot { display: flex; align-items: flex-end; justify-content: space-between; gap: 10px; margin-top: 10px; }
.pc-prices { flex: 1; min-width: 0; }
.pc-coupon-label { font-size: 11px; color: #2980b9; font-weight: 700; margin-bottom: 2px; }
.pc-price-row { display: flex; flex-wrap: wrap; gap: 4px 18px; }
.pc-price { display: flex; flex-direction: column; font-variant-numeric: tabular-nums; }
.pc-price-original { font-size: 11px; color: #999; text-decoration: line-through; }
.pc-price-value { font-size: 17px; font-weight: 700; color: #2c3e50; }
.pc-price-cash .pc-price-value { color: #e53935; }
.pc-price-label { font-size: 10px; color: #888; margin-top: 1px; }
.pc-no-price { font-size: 13px; color: #e67e22; }

.pc-cart-btn {
    position: relative; flex-shrink: 0;
    width: 44px; height: 44px; border: none; border-radius: 10px;
    background: #eef1f4; color: #2c3e50; cursor: pointer;
    display: flex; align-items: center; justify-content: center;
}
.pc-cart-btn:active { background: #dde3e8; }
.pc-cart-btn:disabled { color: #bbb; background: #f5f5f5; cursor: not-allowed; }
.pc-cart-btn svg { width: 24px; height: 24px; }
.pc-cart-badge {
    display: none; position: absolute; top: -5px; right: -5px;
    min-width: 18px; height: 18px; padding: 0 4px; border-radius: 9px;
    background: #e74c3c; color: white; font-size: 11px; font-weight: 700;
    align-items: center; justify-content: center;
}
.pc-cart-badge.show { display: flex; }
</style>

<div class="catalog-cards-wrap">
<?php if (empty($products)): ?>
    <div class="no-data"><?php echo $is_searching ? '검색 결과가 없습니다.' : '해당 월의 가격 정보가 없습니다.'; ?></div>
<?php else: ?>
    <?php foreach ($products as $product):
        $pid     = (int)$product['id'];
        $my_cash = $product['cash_price_' . $effective_grade] ?? null;
        $my_card = calc_card_price($my_cash);

        $best        = pick_best_coupon($coupons_by_product[$pid] ?? [], $my_cash);
        $coupon_cash = $best ? $my_cash - $best['_effective_amount'] : null;
        $coupon_card = $coupon_cash !== null ? calc_card_price($coupon_cash) : null;
    ?>
    <div class="pc-card">
        <div class="pc-meta">
            <span>
                <?php echo h($product['product_number']); ?>
                <?php if (!empty($product['brand_name'])): ?> | <?php echo h($product['brand_name']); ?><?php endif; ?>
            </span>
            <?php if ($is_searching): ?>
            <span class="pc-cat"><?php echo h($product['category_name']); ?></span>
            <?php endif; ?>
        </div>

        <div class="pc-head">
            <div class="pc-name"><?php echo h($product['product_name']); ?></div>
            <?php if (!empty($product['description'])): ?>
            <div class="pc-desc"><?php echo h($product['description']); ?></div>
            <?php endif; ?>
        </div>

        <div class="pc-foot">
            <div class="pc-prices">
                <?php if ($my_cash === null): ?>
                    <div class="pc-no-price">이번 달 가격 미등록</div>
                <?php else: ?>
                    <?php if ($best): ?><div class="pc-coupon-label">쿠폰 적용가</div><?php endif; ?>
                    <div class="pc-price-row">
                        <div class="pc-price pc-price-cash">
                            <?php if ($best): ?><span class="pc-price-original"><?php echo number_format($my_cash); ?>원</span><?php endif; ?>
                            <span class="pc-price-value"><?php echo number_format($best ? $coupon_cash : $my_cash); ?>원</span>
                            <span class="pc-price-label">현금가(부가세별도)</span>
                        </div>
                        <div class="pc-price pc-price-card">
                            <?php if ($best): ?><span class="pc-price-original"><?php echo number_format($my_card ?? 0); ?>원</span><?php endif; ?>
                            <span class="pc-price-value"><?php echo number_format(($best ? $coupon_card : $my_card) ?? 0); ?>원</span>
                            <span class="pc-price-label">카드가</span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <button type="button" class="pc-cart-btn"
                <?php if ($my_cash === null): ?>
                    disabled title="이번 달 가격이 없어 담을 수 없습니다"
                <?php else: ?>
                    onclick="openCartModal(<?php echo $pid; ?>, <?php echo h(json_encode($product['product_name'])); ?>)"
                <?php endif; ?>
                aria-label="장바구니 담기">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="9" cy="20" r="1.4"/><circle cx="18" cy="20" r="1.4"/>
                    <path d="M2 3h3l2.4 11.2a1.5 1.5 0 0 0 1.5 1.2h8.6a1.5 1.5 0 0 0 1.5-1.1L21 7H6"/>
                    <path d="M13.5 8.5v4M11.5 10.5h4"/>
                </svg>
                <span class="pc-cart-badge" data-product-id="<?php echo $pid; ?>">0</span>
            </button>
        </div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>
</div>

<?php if (!empty($products)): ?>
<!-- 워터마크 (테이블과 동일한 문구) -->
<?php
    $__wm_text = '원카피 가격관리  ·  ' . $current_user['company_name'] . '  ·  ' . ($_SESSION['login_time'] ?? date('Y-m-d H:i'));
    $__wm_svg  = '<svg xmlns="http://www.w3.org/2000/svg" width="360" height="160">
        <text x="50%" y="50%" text-anchor="middle" dominant-baseline="middle"
                font-size="13" font-family="Malgun Gothic, sans-serif" font-weight="600"
                fill="rgba(90,103,120,0.10)"
                transform="rotate(-25,180,80)">' . htmlspecialchars($__wm_text) . '</text>
    </svg>';
?>
<style>
.catalog-cards-wrap::after {
    content:''; position:absolute; inset:0; border-radius:12px;
    background-image:url('data:image/svg+xml;base64,<?php echo base64_encode($__wm_svg); ?>');
    background-repeat:repeat; background-size:360px 160px;
    pointer-events:none; z-index:10;
}
</style>
<?php endif; ?>