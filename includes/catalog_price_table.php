<?php
/**
 * includes/catalog_price_table.php
 * -------------------------------------------------
 * 공유 상품/가격 테이블 partial.
 * 사용처 3곳: ① admin 수정모드(effective_is_admin=true) ② admin 업체 미리보기(effective_is_admin=false, is_previewing=true)
 *            ③ client 고객 조회(effective_is_admin=false, is_previewing=false) — 다음 단계에서 연결 예정
 *
 * ⚠️ admin/price_manage.php 원본에서 한 글자도 바꾸지 않고 그대로 옮긴 코드입니다.
 * 디자인/동작이 3곳에서 100% 동일해야 하므로, 함수로 감싸거나 파라미터를 넘기지 않고
 * PHP include()로 그대로 불러 씁니다 (호출부의 변수 스코프를 그대로 공유하는 방식).
 *
 * [필수 변수] 이 파일을 include하기 전에 호출부에서 반드시 아래를 준비해야 합니다:
 *   $products             - 상품 목록 배열 (SELECT 결과: cash_price_a/b/c, cost_price, purchase_price 등 포함)
 *   $effective_is_admin   - true=관리자 수정모드 테이블, false=고객용 읽기전용 테이블
 *   $effective_grade      - 고객 등급 소문자 'a'/'b'/'c' (effective_is_admin=false일 때 사용)
 *   $is_searching         - 다중검색 모드 여부 (구분 컬럼 표시 여부)
 *   $has_brand            - 브랜드 컬럼 표시 여부
 *   $has_description      - 설명 컬럼 표시 여부
 *   $is_previewing        - 관리자 업체 미리보기 중인지 (true면 상태토글/태그편집 숨김)
 *   $tags_by_product      - [product_id => [tag, ...]]
 *   $brands_by_category   - [category_id => [브랜드 배열]]
 *   $formulas_by_category - [category_id => ['base_grade'=>.., 'rules'=>[...]]] (수정모드 자물쇠 표시용)
 *   $selected_month_full  - 'YYYY-MM-01' 형식
 *   $selected_category_id - 현재 선택된 카테고리 ID
 *   $current_user         - get_current_login_user() 결과 (워터마크 텍스트용)
 *   calc_card_price()     - includes/catalog_helpers.php에 정의됨, 미리 require 필요
 *
 * 이 안에서 쓰이는 JS 함수(toggleLock, toggleRowHighlight, handleBrandSelectChange, toggleActive,
 * showTagInput, removeTag)는 admin/price_manage.php의 <script> 블록에 정의되어 있습니다.
 * client 쪽에서는 해당 버튼들이 is_superadmin() 조건으로 애초에 렌더링되지 않으므로
 * 이 JS 함수들이 없어도 문제없습니다.
 */
?>
<!-- 가격 테이블 -->
<?php if (empty($products)): ?>
<div class="price-table-wrap">
    <div class="price-table-scroll">
        <div class="no-data"><?php echo $is_searching ? '검색 결과가 없습니다.' : '해당 월의 가격 정보가 없습니다.'; ?></div>
    </div>
</div>
<?php else: ?>

<form id="price-form" method="POST" action="<?php echo BASE_URL; ?>/api/price/save.php">
    <input type="hidden" name="csrf_token"  value="<?php echo generate_csrf_token(); ?>">
    <input type="hidden" name="price_month" value="<?php echo $selected_month_full; ?>">
    <input type="hidden" name="category_id" value="<?php echo $selected_category_id; ?>">

    <div class="price-table-wrap">
        <div class="price-table-scroll">
        <table class="price-table" id="price-table">
            <thead>
                <?php if ($effective_is_admin): ?>
                <tr>
                    <th rowspan="2" class="del-col" style="display:none;width:36px"></th>
                    <th rowspan="2">제품번호</th>
                    <?php if ($is_searching): ?><th rowspan="2">구분</th><?php endif; ?>
                    <?php if ($has_brand): ?><th rowspan="2">브랜드</th><?php endif; ?>
                    <th rowspan="2">제품명</th>
                    <th rowspan="2">매입가</th>
                    <th rowspan="2">원가</th>
                    <th colspan="2" class="group-header">A등급</th>
                    <th colspan="2" class="group-header">B등급</th>
                    <th colspan="2" class="group-header">C등급</th>
                    <?php if ($has_description): ?><th rowspan="2">설명</th><?php endif; ?>
                    <th rowspan="2" class="toggle-col">상태</th>
                </tr>
                <tr>
                    <th>현금가</th><th>이익률</th>
                    <th>현금가</th><th>이익률</th>
                    <th>현금가</th><th>이익률</th>
                </tr>
                <?php else: ?>
                <tr>
                    <th>제품번호</th>
                    <?php if ($is_searching): ?><th>구분</th><?php endif; ?>
                    <?php if ($has_brand): ?><th>브랜드</th><?php endif; ?>
                    <th>제품명</th>
                    <th>현금가 (부가세별도)</th>
                    <th>카드가</th>
                    <?php if ($has_description): ?><th>설명</th><?php endif; ?>
                </tr>
                <?php endif; ?>
            </thead>
            <tbody>
            <?php foreach ($products as $product):
                $pid      = $product['id'];
                $tags_str = implode(', ', $tags_by_product[$pid] ?? []);
                $row_brands = $brands_by_category[$product['category_id']] ?? [];
                if (!$effective_is_admin) {
                    $my_cash = $product['cash_price_' . $effective_grade] ?? null;
                    $my_card = calc_card_price($my_cash);
                }
            ?>
            <tr id="row-<?php echo $pid; ?>" data-category-id="<?php echo $product['category_id']; ?>" <?php echo !$product['is_active'] ? 'class="inactive-row"' : ''; ?>>
                <?php if ($effective_is_admin): ?>
                <td class="del-col" style="display:none;text-align:center">
                    <input type="checkbox" class="row-checkbox" value="<?php echo $pid; ?>" onchange="toggleRowHighlight(this)">
                </td>
                <?php endif; ?>

                <!-- 제품번호 (편집 불가) -->
                <td><?php echo h($product['product_number']); ?></td>
                
                <?php if ($is_searching): ?>
                <td><?php echo h($product['category_name']); ?></td>
                <?php endif; ?>

                <!-- 브랜드 -->
                <?php if ($has_brand): ?>
                <td class="editable brand-cell">
                    <span class="cell-display"><?php echo h($product['brand_name'] ?? '-'); ?></span>
                    <div class="brand-edit" style="display:none">
                        <select name="products[<?php echo $pid; ?>][brand_id]" class="brand-select" onchange="handleBrandSelectChange(this)">
                            <option value="">-- 선택 안함 --</option>
                            <?php foreach ($row_brands as $b): ?>
                            <option value="<?php echo $b['id']; ?>" <?php echo $b['id'] == $product['brand_id'] ? 'selected' : ''; ?>>
                                <?php echo h($b['brand_name']); ?>
                            </option>
                            <?php endforeach; ?>
                            <option value="new">+ 직접 입력</option>
                        </select>
                        <input type="text" name="products[<?= $pid ?>][brand_name_new]" class="brand-new-input" placeholder="새 브랜드명"
                                style="display:none;margin-top:4px;width:100%;padding:5px 8px; border:1px solid #5A6778;border-radius:3px;font-size:13px;box-sizing:border-box;">
                    </div>
                </td>
                <?php endif; ?>

                <!-- 제품명 -->
                <td class="editable">
                    <span class="cell-display"><?php echo h($product['product_name']); ?></span>
                    <input type="text" name="products[<?php echo $pid; ?>][product_name]"
                            value="<?php echo h($product['product_name']); ?>"
                            style="display:none" required>
                </td>

                <!-- 가격 -->
                <?php if ($effective_is_admin): ?>
                    <!-- 매입가 -->
                    <td class="num editable">
                        <span class="cell-display">
                            <?php echo $product['purchase_price'] !== null ? number_format($product['purchase_price']) . '원' : '-'; ?>
                        </span>
                        <?php if (is_superadmin()): ?>
                        <input type="number" name="prices[<?php echo $pid; ?>][purchase_price]"
                            value="<?php echo $product['purchase_price'] ?? ''; ?>"
                            style="display:none" min="0" step="1">
                        <?php endif; ?>
                    </td>
                    <!-- 원가 -->
                    <td class="num editable">
                        <span class="cell-display">
                            <?php echo $product['cost_price'] !== null ? number_format($product['cost_price']) . '원' : '-'; ?>
                        </span>
                        <?php if (is_superadmin()): ?>
                        <input type="number" name="prices[<?php echo $pid; ?>][cost_price]"
                            value="<?php echo $product['cost_price'] ?? ''; ?>"
                            style="display:none" min="0" step="1">
                        <?php endif; ?>
                    </td>
                    <?php foreach (['cash_price_a' => 'A', 'cash_price_b' => 'B', 'cash_price_c' => 'C'] as $col => $grade):
                        $cost   = $product['cost_price'];
                        $cash   = $product[$col];
                        $margin = ($cost > 0 && $cash !== null) ? round(($cash - $cost) / $cash * 100, 1) : null;

                        $manual_col  = $col . '_manual';
                        $manual_val  = (int)($product[$manual_col] ?? 0);
                        $cat_formula = $formulas_by_category[$product['category_id']] ?? null;
                        $is_base     = $cat_formula && $cat_formula['base_grade'] === $grade;
                        $show_lock   = $cat_formula && !$is_base;
                    ?>
                    <td class="num editable">
                        <span class="cell-display">
                            <?php echo $cash !== null ? number_format($cash) . '원' : '-'; ?>
                        </span>
                        <?php if (is_superadmin()): ?>
                            <?php if ($show_lock): ?>
                            <div class="price-input-wrap" style="display:none">
                                <input type="number" name="prices[<?php echo $pid; ?>][<?php echo $col; ?>]"
                                    value="<?php echo $cash ?? ''; ?>"
                                    class="price-input" data-grade="<?php echo strtolower($grade); ?>"
                                    min="0" step="1"
                                    <?php echo $manual_val ? '' : 'readonly'; ?>>
                                <button type="button"
                                        class="btn-lock <?php echo $manual_val ? 'locked' : 'unlocked'; ?>"
                                        onclick="toggleLock(this)"
                                        title="<?php echo $manual_val ? '자동계산으로 전환' : '수동입력으로 고정'; ?>">
                                    <?php echo $manual_val ? '🔒' : '🔓'; ?>
                                </button>
                                <input type="hidden" name="prices[<?php echo $pid; ?>][<?php echo $manual_col; ?>]"
                                        value="<?php echo $manual_val; ?>" class="manual-flag-input">
                            </div>
                            <?php else: ?>
                            <input type="number" name="prices[<?php echo $pid; ?>][<?php echo $col; ?>]"
                                value="<?php echo $cash ?? ''; ?>"
                                style="display:none" min="0" step="1"
                                class="price-input" data-grade="<?php echo strtolower($grade); ?>">
                            <?php if ($cat_formula): // 기준등급 칸도 manual=1로 명시 저장 ?>
                            <input type="hidden" name="prices[<?php echo $pid; ?>][<?php echo $manual_col; ?>]" value="1">
                            <?php endif; ?>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <td class="num margin-cell" style="<?php echo $margin !== null && $margin < 0 ? 'color:#e74c3c' : ''; ?>">
                        <?php echo $margin !== null ? $margin . '%' : '-'; ?>
                    </td>
                    <?php endforeach; ?>
                <?php else: ?>
                    <td class="num"><?php echo $my_cash !== null ? number_format($my_cash) . '원' : '-'; ?></td>
                    <td class="num"><?php echo $my_card !== null ? number_format($my_card) . '원' : '-'; ?></td>
                <?php endif; ?>

                <!-- 설명 -->
                <?php if ($has_description): ?>
                <td class="editable desc-cell">
                    <span class="cell-display"><?php echo h($product['description'] ?? '-'); ?></span>
                    <textarea name="products[<?php echo $pid; ?>][description]"
                            style="display:none"
                            rows="2"><?php echo h($product['description'] ?? ''); ?></textarea>
                </td>
                <?php endif; ?>
                <?php if (is_superadmin() && !$is_previewing): ?>
                <td class="toggle-col">
                    <button type="button"
                        class="btn-toggle <?php echo $product['is_active'] ? 'active' : 'inactive'; ?>"
                        onclick="toggleActive(<?php echo $pid; ?>, <?php echo $product['is_active'] ? 0 : 1; ?>, this)">
                        <?php echo $product['is_active'] ? '활성' : '비활성'; ?>
                    </button>
                </td>
                <?php endif; ?>
            </tr>
                <?php if (is_superadmin() && !$is_previewing): ?>
                <tr class="tag-subrow" id="tag-row-<?php echo $pid; ?>" style="display:none">
                    <td colspan="20">
                        <div class="tag-edit-row" id="tag-edit-<?php echo $pid; ?>">
                            <?php foreach ($tags_by_product[$pid] ?? [] as $tag): ?>
                            <span class="tag-chip">
                                <span># <?php echo h($tag); ?></span>
                                <button type="button" onclick="removeTag(this, <?php echo $pid; ?>)">×</button>
                            </span>
                            <?php endforeach; ?>
                            <button type="button" class="tag-add-btn" onclick="showTagInput(<?php echo $pid; ?>)">＋ 태그 추가</button>
                            <input type="hidden" class="tags-hidden"
                                name="products[<?php echo $pid; ?>][tags]"
                                value="<?php echo h($tags_str); ?>">
                        </div>
                    </td>
                </tr>
                <?php endif; ?>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
    </div>
</form>

<!-- 워터마크 -->
<?php
    $wm_text = '원카피 가격관리  ·  ' . $current_user['company_name'] . '  ·  ' . ($_SESSION['login_time'] ?? date('Y-m-d H:i'));
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="600" height="200">
        <text x="50%" y="50%" text-anchor="middle" dominant-baseline="middle"
                font-size="18" font-family="Malgun Gothic, sans-serif" font-weight="600"
                fill="rgba(90,103,120,0.10)"
                transform="rotate(-25,300,100)">' . htmlspecialchars($wm_text) . '</text>
    </svg>';
?>
<style>
.price-table-wrap::after {
    content:''; position:absolute; inset:0;
    background-image:url('data:image/svg+xml;base64,<?php echo base64_encode($svg); ?>');
    background-repeat:repeat; background-size:600px 200px;
    pointer-events:none; z-index:10;
}
</style>

<?php endif; // empty products ?>