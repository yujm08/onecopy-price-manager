<?php
$page_title = '가격 관리';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/catalog_helpers.php';
require_admin();

/* ── 업체별 가격 미리보기 ── */
$company_search      = trim($_GET['company_search'] ?? '');
$preview_company_id  = (int)($_GET['preview_company'] ?? 0);
$preview_company     = null;

if ($preview_company_id && is_admin()) {
    $pstmt = $pdo->prepare("
        SELECT id, company_name, representative_name, manager_name, grade
        FROM companies
        WHERE id = ? AND role = 'user'
    ");
    $pstmt->execute([$preview_company_id]);
    $preview_company = $pstmt->fetch();
}

$is_previewing        = (bool)$preview_company;
$effective_is_admin   = is_admin() && !$is_previewing;
$show_company_results = is_admin() && $company_search !== '' && !$is_previewing;

$company_search_results = [];
if ($show_company_results) {
    $cstmt = $pdo->prepare("
        SELECT id, company_name, representative_name, manager_name, grade
        FROM companies
        WHERE role = 'user' AND company_name LIKE ?
        ORDER BY company_name
        LIMIT 50
    ");
    $cstmt->execute(['%' . $company_search . '%']);
    $company_search_results = $cstmt->fetchAll();
}

/* ── 월 ── */
if ($effective_is_admin) {
    $selected_month = $_GET['month'] ?? date('Y-m');
} else {
    $selected_month = date('Y-m');
}
$selected_month_full = $selected_month . '-01';

/* ── 검색 파라미터 ── */
$search_query   = trim($_GET['q'] ?? '');
$cat_filter_str = trim($_GET['cat_filter'] ?? '');
$cat_filters    = array_values(array_filter(array_map('intval',
    $cat_filter_str !== '' ? explode(',', $cat_filter_str) : []
)));
$terms = $search_query !== ''
    ? array_values(array_filter(array_map('trim', explode(',', $search_query))))
    : [];
$is_searching = !empty($terms);

/* ── 카테고리 ── */
$all_categories = $pdo->query("SELECT * FROM categories ORDER BY id")->fetchAll();
$selected_category_id = (int)($_GET['category'] ?? ($all_categories[0]['id'] ?? 1));

/* ── 쿼리 공통 FROM ── */
$from_condition = $effective_is_admin ? "WHERE 1=1" : "WHERE p.is_active = 1";
$from = "FROM products p
    LEFT JOIN brands b     ON p.brand_id = b.id
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN prices pr    ON p.id = pr.product_id AND pr.price_month = ?
    LEFT JOIN product_tags pt ON p.id = pt.product_id
    {$from_condition}";

$term_sql    = '';
$term_params = [];
foreach ($terms as $t) {
    $like = '%' . $t . '%';
    $term_sql .= " AND (p.product_number LIKE ? OR b.brand_name LIKE ? OR p.product_name LIKE ? OR p.description LIKE ? OR pt.tag LIKE ?)";
    array_push($term_params, $like, $like, $like, $like, $like);
}

/* ── 메인 제품 조회 ── */
$params = [$selected_month_full];
if ($is_searching) {
    $extra  = $term_sql;
    $params = array_merge($params, $term_params);
    if (!empty($cat_filters)) {
        $ph     = implode(',', array_fill(0, count($cat_filters), '?'));
        $extra .= " AND p.category_id IN ($ph)";
        $params = array_merge($params, $cat_filters);
    }
} else {
    $extra    = " AND p.category_id = ?";
    $params[] = $selected_category_id;
}

$stmt = $pdo->prepare("
    SELECT DISTINCT
        p.id, p.product_number, p.product_name, p.description,
        p.category_id, p.brand_id, p.is_active,
        b.brand_name, c.category_name,
        pr.cost_price, pr.purchase_price, pr.cash_price_a, pr.cash_price_b, pr.cash_price_c, pr.updated_at,
        pr.cash_price_a_manual, pr.cash_price_b_manual, pr.cash_price_c_manual
    $from $extra
    ORDER BY c.id, p.is_active DESC, p.product_name
");
$stmt->execute($params);
$products = $stmt->fetchAll();

/* ── 카테고리 버블 ── */
$bubble_categories = [];
if ($is_searching) {
    $bstmt = $pdo->prepare("SELECT DISTINCT c.id, c.category_name $from $term_sql ORDER BY c.id");
    $bstmt->execute(array_merge([$selected_month_full], $term_params));
    $bubble_categories = $bstmt->fetchAll();
}

/* ── 태그 조회 ── */
$tags_by_product = [];
if (!empty($products)) {
    $pids = array_column($products, 'id');
    $ph   = implode(',', array_fill(0, count($pids), '?'));
    $ts   = $pdo->prepare("SELECT product_id, tag FROM product_tags WHERE product_id IN ($ph) ORDER BY id");
    $ts->execute($pids);
    foreach ($ts->fetchAll() as $r) $tags_by_product[$r['product_id']][] = $r['tag'];
}

/* ── 컬럼 표시 여부 ── */
$has_brand = $has_description = false;
foreach ($products as $p) {
    if (!empty($p['brand_name']))  $has_brand = true;
    if (!empty($p['description'])) $has_description = true;
    if ($has_brand && $has_description) break;
}

/* ── 결과에 포함된 카테고리의 브랜드 (수정 모드용) ── */
$brands_by_category = [];
if (!empty($products)) {
    $rel_cats = array_values(array_unique(array_column($products, 'category_id')));
    $ph = implode(',', array_fill(0, count($rel_cats), '?'));
    $bs = $pdo->prepare("SELECT id, brand_name, category_id FROM brands WHERE category_id IN ($ph) ORDER BY brand_name");
    $bs->execute($rel_cats);
    foreach ($bs->fetchAll() as $b) $brands_by_category[$b['category_id']][] = $b;
}

/* ── 행 추가 폼용 브랜드 (현재 카테고리) ── */
$category_brands = $brands_by_category[$selected_category_id] ?? [];

/* ── 선택된 카테고리 정보 ── */
$selected_category = null;
foreach ($all_categories as $c) {
    if ($c['id'] == $selected_category_id) { $selected_category = $c; break; }
}

/* ── 카테고리 수식 조회 ── */
$existing_formula = null;
$existing_rules_by_grade = [];
if (is_superadmin()) {
    $fstmt = $pdo->prepare("SELECT * FROM category_price_formulas WHERE category_id = ?");
    $fstmt->execute([$selected_category_id]);
    $existing_formula = $fstmt->fetch();

    if ($existing_formula) {
        $rstmt = $pdo->prepare("SELECT * FROM category_price_formula_rules WHERE formula_id = ?");
        $rstmt->execute([$existing_formula['id']]);
        foreach ($rstmt->fetchAll() as $r) {
            $existing_rules_by_grade[$r['target_grade']] = $r;
        }
    }
}

/* ── 화면에 표시된 제품들의 카테고리별 수식 (자물쇠/실시간 자동계산용) ── */
$formulas_by_category = [];
if (is_superadmin() && !empty($products)) {
    $prod_cat_ids = array_values(array_unique(array_column($products, 'category_id')));
    $ph2 = implode(',', array_fill(0, count($prod_cat_ids), '?'));
    $fstmt2 = $pdo->prepare("SELECT * FROM category_price_formulas WHERE category_id IN ($ph2)");
    $fstmt2->execute($prod_cat_ids);
    foreach ($fstmt2->fetchAll() as $f) {
        $rstmt2 = $pdo->prepare("SELECT * FROM category_price_formula_rules WHERE formula_id = ?");
        $rstmt2->execute([$f['id']]);
        $rules = [];
        foreach ($rstmt2->fetchAll() as $r) {
            $rules[$r['target_grade']] = ['calc_type' => $r['calc_type'], 'calc_value' => (float)$r['calc_value']];
        }
        $formulas_by_category[$f['category_id']] = [
            'base_grade' => $f['base_grade'],
            'rules'      => $rules,
        ];
    }
}

$user_grade      = strtolower($_SESSION['grade'] ?? '');
$effective_grade = $is_previewing ? strtolower($preview_company['grade']) : $user_grade;
?>

<?php
/* ── 쿠폰 적용가 표시용: 미리보기 중이면 그 업체 기준, 아니면(관리자 수정모드) 빈 값 ── */
$coupons_by_product = $is_previewing
    ? get_active_coupons_for_company($pdo, $preview_company_id)
    : [];
?>

<style>
.container { max-width: min(1800px, 96vw); width: 100%; margin: 0 auto; padding: 0 16px; box-sizing: border-box; }

/* 월 네비 */
.month-nav { display:flex; align-items:center; gap:12px; margin-bottom:20px; }
.month-nav button { padding:8px 14px; background:#5A6778; color:white; border:none; border-radius:4px; cursor:pointer; font-size:16px; }
.month-nav button:hover { background:#4B5563; }
.month-display { font-size:18px; font-weight:700; padding:8px 16px; border:2px solid #ddd; border-radius:4px; background:white; cursor:pointer; min-width:130px; text-align:center; }
.month-display:hover { background:#f8f9fa; }
.btn-snapshot { padding:8px 16px; background:#8e44ad; color:white; border:none; border-radius:4px; cursor:pointer; font-size:13px; }
.btn-snapshot:hover { background:#7d3c98; }

/* 상단 바 (월 네비 + 업체 검색) */
.top-bar { display:flex; justify-content:space-between; align-items:center; gap:16px; margin-bottom:20px; flex-wrap:wrap; }
.company-search-form { display:flex; align-items:center; gap:8px; }
.company-search-input { padding:8px 12px; border:1px solid #ddd; border-radius:4px; font-size:14px; min-width:220px; }
.btn-company-search { padding:8px 16px; background:#34495e; color:white; border:none; border-radius:4px; cursor:pointer; font-size:14px; white-space:nowrap; }
.btn-company-search:hover { background:#2c3e50; }
.company-chip { display:inline-flex; align-items:center; gap:8px; background:#eef1f4; border:1px solid #c7ccd3; border-radius:4px; padding:8px 12px; font-size:14px; color:#444; }
.company-chip .chip-close { color:#888; text-decoration:none; font-size:15px; cursor:pointer; }
.company-chip .chip-close:hover { color:#333; }

.company-results-wrap { display:flex; flex-direction:column; gap:10px; }
.company-result-row {
    display:flex; justify-content:space-between; align-items:center;
    background:white; padding:16px 20px; border-radius:8px;
    box-shadow:0 2px 4px rgba(0,0,0,0.08); text-decoration:none; color:#333; font-size:14px;
}
.company-result-row:hover { background:#f8f9fa; }
.grade-badge { padding:4px 12px; border-radius:20px; background:#d4edda; color:#155724; font-size:12px; font-weight:600; }

/* 검색 */
.search-section { background:white; padding:16px 20px; border-radius:8px; margin-bottom:12px; box-shadow:0 2px 4px rgba(0,0,0,0.08); }
.search-row { display:flex; gap:10px; align-items:center; }
.search-input { flex:1; padding:10px 14px; border:1px solid #ddd; border-radius:4px; font-size:14px; }
.search-input:focus { outline:none; border-color:#5A6778; }
.btn-search { padding:10px 20px; background:#5A6778; color:white; border:none; border-radius:4px; cursor:pointer; font-size:14px; }
.btn-search:hover { background:#4B5563; }
.btn-reset-search { padding:10px 14px; background:#95a5a6; color:white; text-decoration:none; border-radius:4px; font-size:14px; display:inline-block; }
.btn-reset-search:hover { background:#7f8c8d; }

/* 필터 칩 */
.filter-chips { display:flex; flex-wrap:wrap; gap:8px; margin-top:10px; min-height:4px; }
.filter-chip { display:inline-flex; align-items:center; gap:6px; background:#5A6778; color:white; padding:4px 12px; border-radius:20px; font-size:13px; }
.filter-chip button { background:none; border:none; color:white; cursor:pointer; font-size:15px; padding:0; line-height:1; }

/* 카테고리 버블 */
.bubble-section { margin-bottom:14px; }
.bubble-label { font-size:12px; color:#888; margin-bottom:6px; }
.bubble-list { display:flex; flex-wrap:wrap; gap:8px; }
.category-bubble { padding:5px 16px; border:2px solid #5A6778; border-radius:20px; background:white; color:#5A6778; font-size:13px; cursor:pointer; font-weight:500; transition:all 0.15s; }
.category-bubble:hover, .category-bubble.active { background:#5A6778; color:white; }

/* 카테고리 탭 바 */
.category-bar { background:white; border-radius:8px; padding:14px 20px; margin-bottom:14px; box-shadow:0 2px 4px rgba(0,0,0,0.08); display:flex; justify-content:space-between; align-items:center; gap:16px; }
.category-tabs { display:flex; gap:24px; align-items:center; flex-wrap:wrap; }
.category-tab { text-decoration:none; color:#555; font-size:14px; font-weight:500; padding:4px 0; border-bottom:2px solid transparent; transition:all 0.2s; }
.category-tab:hover { color:#5A6778; }
.category-tab.active { color:#5A6778; border-bottom-color:#5A6778; font-weight:700; }

/* 액션 버튼 */
.btn-edit     { background:#f39c12; }
.btn-edit:hover { background:#e67e22; }
.btn-save     { padding:8px 18px; background:#27ae60; color:white; border:none; border-radius:4px; cursor:pointer; font-size:14px; font-weight:500; }
.btn-save:hover { background:#229954; }
.btn-cancel   { padding:8px 18px; background:#95a5a6; color:white; border:none; border-radius:4px; cursor:pointer; font-size:14px; }
.btn-cancel:hover { background:#7f8c8d; }
.btn-delete   { padding:8px 18px; background:#e74c3c; color:white; border:none; border-radius:4px; cursor:pointer; font-size:14px; }
.btn-delete:hover { background:#c0392b; }
.btn-del-confirm { padding:8px 18px; background:#c0392b; color:white; border:none; border-radius:4px; cursor:pointer; font-size:14px; }
.btn-add-row  { background:#5A6778; }
.btn-add-row:hover { background:#4B5563; }
.hidden { display:none !important; }

/* 비활성 행 */
.inactive-row td { opacity: 0.38; }
.inactive-row { display: table-row; }
.edit-mode .inactive-row { visibility: visible; }

/* 상태 토글 열 */
.toggle-col { display: none; width: 80px; text-align: center; }
.edit-mode .toggle-col { display: table-cell; }

.btn-toggle {
    padding: 4px 10px; border: none; border-radius: 3px;
    font-size: 12px; cursor: pointer; white-space: nowrap;
}
.btn-toggle.active  { background: #27ae60; color: white; }
.btn-toggle.inactive { background: #bdc3c7; color: #555; }

/* 테이블 툴바 */
.table-toolbar { display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; }
.toolbar-left, .toolbar-right { display:flex; gap:8px; }

/* 자물쇠 (수동/자동 토글) */
.edit-mode td.editable .price-input-wrap { display:flex !important; align-items:center; gap:5px; }
.price-input-wrap .price-input { flex: 0 0 auto; }
.price-input-wrap .price-input[readonly] { background:#eef1f4; color:#8a929c; cursor:not-allowed; border-color:#c7ccd3; }
.price-input-wrap .price-input:not([readonly]) { background:#fffaf3; border-color:#e67e22; }
.btn-lock {
    display:inline-flex; align-items:center; justify-content:center;
    width:26px; height:26px; flex-shrink:0;
    border-radius:4px; cursor:pointer; font-size:13px;
    border:1.5px solid transparent;
}
.btn-lock.locked   { background:#fff3e6; border-color:#e67e22; }
.btn-lock.unlocked { background:#eef1f4; border-color:#c7ccd3; }
.btn-lock.locked:hover   { background:#ffe4bf; }
.btn-lock.unlocked:hover { background:#e1e5e9; }

/* 카테고리 수식 */
.btn-toolbar { padding:9px 18px; font-size:14px; font-weight:500; border:none; border-radius:4px; cursor:pointer; color:white; }
.btn-formula { background:#e67e22; }
.btn-formula:hover { background:#d35400; }
.formula-panel { background:#fdf0e6; border:2px solid #e67e22; padding:20px; border-radius:8px; margin-bottom:14px; }
.formula-panel h3 { margin:0 0 14px; color:#a04000; font-size:15px; }
.formula-base-row { display:flex; align-items:center; gap:10px; margin-bottom:16px; }
.formula-base-row select { padding:8px 10px; border:1px solid #ddd; border-radius:4px; font-size:13px; }
.formula-rule-row { display:flex; align-items:center; gap:8px; margin-bottom:10px; }
.formula-grade-label { font-weight:600; color:#444; min-width:48px; }
.formula-rule-row select { padding:7px 10px; border:1px solid #ddd; border-radius:4px; font-size:13px; }
.formula-rule-row input[type="number"] { width:120px; padding:7px 10px; border:1px solid #ddd; border-radius:4px; font-size:13px; }
.formula-actions { display:flex; justify-content:flex-end; gap:8px; margin-top:8px; }

/* 카테고리 관리 */
.category-manage-panel { background:#eaf2fb; border:2px solid #5A6778; padding:20px; border-radius:8px; margin-bottom:14px; }
.category-manage-panel h3 { margin:0 0 14px; color:#34495e; font-size:15px; }
.category-manage-list { display:flex; flex-direction:column; gap:8px; margin-bottom:14px; }
.category-manage-row { display:flex; align-items:center; gap:10px; }
.category-manage-row input[type="text"] { flex:1; padding:8px 10px; border:1px solid #ddd; border-radius:4px; font-size:13px; box-sizing:border-box; }
.category-manage-row.marked-delete input[type="text"] { text-decoration:line-through; background:#fdecea; color:#999; }
.btn-cat-delete { padding:6px 14px; background:#e74c3c; color:white; border:none; border-radius:4px; cursor:pointer; font-size:12px; white-space:nowrap; }
.btn-cat-delete.marked { background:#95a5a6; }
.category-manage-actions { display:flex; justify-content:flex-end; gap:8px; margin-top:14px; }
.btn-cat-add {
    display:inline-flex; align-items:center; gap:4px;
    background:white; color:#5A6778;
    border:1.5px dashed #5A6778; border-radius:4px;
    padding:7px 14px; font-size:13px; cursor:pointer;
}
.btn-cat-add:hover { background:#5A6778; color:white; }
.new-category-row input { border-color:#27ae60; }
.btn-category-manage { padding:8px 18px; background:#34495e; color:white; border:none; border-radius:4px; cursor:pointer; font-size:14px; }
.btn-category-manage:hover { background:#2c3e50; }

/* 행 추가 폼 */
.add-product-form { background:#fff8e1; border:2px solid #ffc107; padding:20px; border-radius:8px; margin-bottom:14px; }
.add-product-form h3 { margin:0 0 14px; color:#856404; font-size:15px; }
.form-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(170px, 1fr)); gap:12px; margin-bottom:14px; }
.form-grid label { display:block; font-size:13px; font-weight:500; margin-bottom:4px; color:#444; }
.form-grid input, .form-grid select { width:100%; padding:8px 10px; border:1px solid #ddd; border-radius:4px; font-size:13px; box-sizing:border-box; }
.form-actions { display:flex; justify-content:flex-end; gap:8px; }
.btn-form-save   { padding:8px 20px; background:#27ae60; color:white; border:none; border-radius:4px; cursor:pointer; font-size:14px; }
.btn-form-cancel { padding:8px 16px; background:#95a5a6; color:white; border:none; border-radius:4px; cursor:pointer; font-size:14px; }

/* 쿠폰 적용가 */
.price-original { color:#999; text-decoration:line-through; font-size:12px; }
.price-coupon { color:#2980b9; font-weight:700; }

/* 제품명 칸 — 편집 중 잘려 보이지 않도록 최소 너비 확보 */
.product-name-cell { min-width: 200px; }
.product-name-cell input[type="text"] { min-width: 180px; }

/* 테이블 */
.price-table-wrap {
    position: relative;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.08);
    overflow: auto;
    max-height: calc(100vh - 200px); /* 네비바+여백 제외한 높이 */
    user-select: none;
    -webkit-user-select: none;
}
.price-table-scroll { overflow:visible; }
.price-table { width: max-content; min-width: 100%; border-collapse: separate; border-spacing:0; }
.price-table thead { background:#34495e; color:white; }
.price-table thead th { position:sticky; top:0; z-index:10; background:#34495e; }
.price-table thead tr:nth-child(2) th { top:43px; }
.price-table th { padding:12px 14px; text-align:left; font-weight:500; font-size:13px; white-space:nowrap; }
.price-table td { padding:11px 14px; border-bottom:1px solid #eee; font-size:13px; vertical-align:middle; }
.price-table tbody tr:last-child td { border-bottom:none; }
.price-table tbody tr:hover { background:#f8f9fa; }
.price-table td.num { text-align:right; font-variant-numeric:tabular-nums; }
.no-data { padding:60px 20px; text-align:center; color:#7f8c8d; }

.margin-cell { background: #f0f2f5; }
.edit-mode .margin-cell { background: #e8eaed; }
.price-table th.group-header { text-align: center; border-bottom: 1px solid rgba(255,255,255,0.2); }

/* 설명 셀 — 조회/수정 모두 줄바꿈 */
.desc-cell {
    min-width: 150px;
    max-width: 300px;
    white-space: normal;
    word-break: break-all;
    line-height: 1.5;
}
.desc-cell .cell-display {
    display: block;
}
.desc-cell textarea {
    width: 100%;
    min-height: 40px;
    padding: 5px 8px;
    border: 1px solid #5A6778;
    border-radius: 3px;
    font-size: 13px;
    box-sizing: border-box;
    resize: vertical;
    font-family: inherit;
    line-height: 1.5;
}

/* 수정 모드 */
.edit-mode td.editable .cell-display { display:none !important; }
.edit-mode td.editable input,
.edit-mode td.editable textarea,
.edit-mode td.editable .brand-edit { display:block !important; }
/* 태그 서브행 */
.tag-subrow td {
    background: #f4f6f8;
    border-bottom: 1px solid #e0e4ea;
    padding: 5px 14px 7px 7px;
}
.tag-edit-row { display:flex; flex-wrap:wrap; align-items:center; gap:6px; min-height:28px; }
.tag-chip {
    display:inline-flex; align-items:flex-start; gap:4px;
    background: #5A6778; color: white;
    border-radius: 20px; padding: 3px 10px 3px 12px;
    font-size: 12px; font-weight: 500;
}
.tag-chip button {
    background: none; border: none; color: rgba(255,255,255,0.8);
    cursor: pointer; font-size: 14px; padding: 0; line-height: 1;
}
.tag-chip button:hover { color: white; }
.tag-add-btn {
    display:inline-flex; align-items:center; gap:3px;
    background: white; color: #5A6778;
    border: 1.5px dashed #5A6778; border-radius: 20px;
    padding: 3px 12px; font-size: 12px; cursor: pointer;
}
.tag-add-btn:hover { background: #5A6778; color: white; }
.tag-inline-input {
    border: 1px solid #5A6778; border-radius: 4px;
    padding: 3px 8px; font-size: 12px; width: 100px;
    outline: none;
}
.price-table input[type="text"],
.price-table input[type="number"],
.price-table select { width:100%; padding:5px 8px; border:1px solid #5A6778; border-radius:3px; font-size:13px; box-sizing:border-box; }
.price-table input[type="number"] { text-align:right; }
.delete-mode tr.selected-for-delete td { background:#fdecea; }

/* ── 수정 모드: 숫자 입력칸은 자릿수 기준 고정 폭 ── */
.price-table input[type="number"] {
    width: calc(10ch + 18px);          /* 숫자 10자리 + 좌우 padding 16px + border 2px */
    min-width: 0;
    font-variant-numeric: tabular-nums; /* 숫자 폭을 균일하게 → ch 계산이 정확해짐 */
    -moz-appearance: textfield;         /* 위아래 화살표 제거 (폭 약 15px 절약) */
    appearance: textfield;
}
.price-table input[type="number"]::-webkit-outer-spin-button,
.price-table input[type="number"]::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

/* (선택) 수정 모드일 때만 셀 패딩을 조금 줄이기 */
.price-table.edit-mode td { padding-left: 10px; padding-right: 10px; }
</style>

<div class="container">

    <?php if (isset($_SESSION['success_message'])): ?>
    <div data-auto-dismiss style="background:#d4edda;color:#155724;padding:14px;border-radius:4px;margin-bottom:16px;border-left:4px solid #28a745;">
        <?php echo h($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
    </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_message'])): ?>
    <div data-auto-dismiss style="background:#f8d7da;color:#721c24;padding:14px;border-radius:4px;margin-bottom:16px;border-left:4px solid #dc3545;">
        <?php echo h($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
    </div>
    <?php endif; ?>

        <!-- 상단 바: 월 네비게이션 + 업체 검색 -->
    <div class="top-bar">
        <?php if (!$show_company_results): ?>
            <?php if ($effective_is_admin): ?>
            <div class="month-nav">
                <button onclick="moveMonth(-1)">◀</button>
                <div class="month-display" onclick="openMonthPicker()">
                    <?php echo date('Y년 m월', strtotime($selected_month_full)); ?>
                </div>
                <button onclick="moveMonth(1)">▶</button>
                <input type="month" id="month-picker" value="<?php echo h($selected_month); ?>"
                onchange="changeMonth(this.value)"
                style="position:absolute;opacity:0;pointer-events:none;">
                <?php if (is_superadmin()): ?>
                <button class="btn-snapshot" onclick="createSnapshot()">스냅샷 생성</button>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div style="font-size:18px;font-weight:700;">
                <?php echo date('Y년 m월', strtotime($selected_month_full)); ?>
            </div>
            <?php endif; ?>
        <?php else: ?>
        <div></div>
        <?php endif; ?>

        <?php if (is_admin()): ?>
        <form method="GET" action="" class="company-search-form">
            <input type="hidden" name="month" value="<?php echo h($selected_month); ?>">
            <?php if ($is_previewing): ?>
            <span class="company-chip">
                <?php echo h($preview_company['company_name']); ?>의 검색 결과
                <a href="?month=<?php echo h($selected_month); ?>" class="chip-close" title="초기화">⊗</a>
            </span>
            <?php else: ?>
            <input type="text" name="company_search" class="company-search-input"
                    value="<?php echo h($company_search); ?>" placeholder="업체명 검색">
            <?php endif; ?>
            <button type="submit" class="btn-company-search">업체 검색</button>
        </form>
        <?php endif; ?>
    </div>

    <?php if (!$show_company_results): ?>
    <!-- 검색 폼 -->
    <form id="search-form" class="search-section" method="GET" action="">
        <input type="hidden" name="month"      value="<?php echo h($selected_month); ?>">
        <input type="hidden" name="category"   value="<?php echo $selected_category_id; ?>">
        <input type="hidden" name="cat_filter" id="cat-filter-input" value="<?php echo h($cat_filter_str); ?>">
        <input type="hidden" name="preview_company" value="<?php echo $preview_company_id; ?>">

        <div class="search-row">
            <input type="text" name="q" id="search-input" class="search-input"
                    value="<?php echo h($search_query); ?>"
                    placeholder="검색어 입력 (쉼표로 구분하여 다중 검색)">
            <button type="submit" class="btn-search">검색</button>
                        <?php if ($is_searching || !empty($cat_filters)): ?>
            <a href="?month=<?php echo h($selected_month); ?>&category=<?php echo $selected_category_id; ?>&preview_company=<?php echo $preview_company_id; ?>" class="btn-reset-search">초기화</a>
            <?php endif; ?>
        </div>

        <!-- 필터 칩 -->
        <div class="filter-chips" id="filter-chips">
            <?php
            $cat_map = array_column($all_categories, 'category_name', 'id');
            foreach ($cat_filters as $cid):
                $cname = $cat_map[$cid] ?? ('카테고리 ' . $cid);
            ?>
            <span class="filter-chip">
                <?php echo h($cname); ?>
                <button type="button" onclick="removeCatFilter(<?php echo $cid; ?>)">✕</button>
            </span>
            <?php endforeach; ?>
        </div>
    </form>

    <!-- 카테고리 버블 (검색 결과) -->
    <?php if ($is_searching && !empty($bubble_categories)): ?>
    <div class="bubble-section">
        <div class="bubble-label">검색 결과가 있는 구분:</div>
        <div class="bubble-list">
            <?php foreach ($bubble_categories as $bc): ?>
            <button type="button"
                    class="category-bubble <?php echo in_array($bc['id'], $cat_filters) ? 'active' : ''; ?>"
                    onclick="toggleCatFilter(<?php echo $bc['id']; ?>)">
                <?php echo h($bc['category_name']); ?>
            </button>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- 카테고리 탭 + 카테고리 관리 버튼 -->
    <div class="category-bar">
        <?php if (!$is_searching): ?>
        <div class="category-tabs">
            <?php foreach ($all_categories as $cat): ?>
            <a href="?month=<?php echo h($selected_month); ?>&category=<?php echo $cat['id']; ?>&preview_company=<?php echo $preview_company_id; ?>"
                class="category-tab <?php echo $cat['id'] == $selected_category_id ? 'active' : ''; ?>">
                <?php echo h($cat['category_name']); ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div style="font-size:13px;color:#888;">전체 카테고리 검색 결과</div>
        <?php endif; ?>

        <?php if (is_superadmin() && !$is_previewing): ?>
        <button class="btn-category-manage" id="btn-category-manage" onclick="openCategoryManage()">카테고리 관리</button>
        <?php endif; ?>
    </div>

    <!-- 카테고리 관리 패널 -->
    <?php if (is_superadmin() && !$is_previewing):
        // 카테고리별 제품 수 (삭제 확인 모달용)
        $cat_product_counts = $pdo->query("SELECT category_id, COUNT(*) cnt FROM products GROUP BY category_id")
            ->fetchAll(PDO::FETCH_KEY_PAIR);
    ?>
    <div id="category-manage-panel" class="category-manage-panel hidden">
        <h3>카테고리 관리</h3>
        <form id="category-manage-form" method="POST" action="<?php echo BASE_URL; ?>/api/category/save.php">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <div class="category-manage-list" id="category-manage-list">
                <?php foreach ($all_categories as $cat): ?>
                <div class="category-manage-row" id="cat-row-<?php echo $cat['id']; ?>"
                    data-product-count="<?php echo $cat_product_counts[$cat['id']] ?? 0; ?>">
                    <input type="text" name="categories[<?php echo $cat['id']; ?>][name]"
                        value="<?php echo h($cat['category_name']); ?>">
                    <input type="hidden" name="categories[<?php echo $cat['id']; ?>][delete]" value="0" class="cat-delete-flag">
                    <button type="button" class="btn-cat-delete"
                            onclick="toggleCategoryDelete(<?php echo $cat['id']; ?>, '<?php echo h(addslashes($cat['category_name'])); ?>')">삭제</button>
                </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn-cat-add" onclick="addCategoryRow()">＋ 카테고리 추가</button>
            <div class="category-manage-actions">
                <button type="button" class="btn-form-cancel" onclick="closeCategoryManage()">취소</button>
                <button type="submit" class="btn-form-save">저장</button>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- 카테고리 수식 패널 -->
    <?php if (is_superadmin() && !$is_searching && !$is_previewing): ?>
    <div id="formula-panel" class="formula-panel hidden">
        <h3><?php echo h($selected_category['category_name'] ?? ''); ?> — 카테고리 가격 수식</h3>
        <form id="formula-form" method="POST" action="<?php echo BASE_URL; ?>/api/category/formula_save.php">
            <input type="hidden" name="csrf_token"  value="<?php echo generate_csrf_token(); ?>">
            <input type="hidden" name="category_id" value="<?php echo $selected_category_id; ?>">
            <input type="hidden" name="price_month" value="<?php echo $selected_month_full; ?>">

            <div class="formula-base-row">
                <label>기준 등급 (직접 입력하는 등급)</label>
                <select name="base_grade" id="formula-base-grade" onchange="renderFormulaRuleRows()">
                    <option value="A">A등급</option>
                    <option value="B">B등급</option>
                    <option value="C">C등급</option>
                </select>
            </div>

            <div id="formula-rule-rows"></div>

            <div class="formula-actions">
                <button type="button" class="btn-form-cancel" onclick="closeFormulaPanel()">취소</button>
                <button type="submit" class="btn-form-save">저장 (전체 재계산)</button>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- 테이블 툴바 -->
    <?php if (is_superadmin() && !$is_searching && !$is_previewing): ?>
    <div class="table-toolbar">
        <div class="toolbar-left">
            <button class="btn-toolbar btn-formula" id="btn-formula" onclick="openFormulaPanel()">수식</button>
            <button class="btn-toolbar btn-add-row" id="btn-add-row" onclick="showAddForm()">+ 행 추가</button>
        </div>
        <div class="toolbar-right">
            <button class="btn-toolbar btn-edit" id="btn-edit" onclick="enableEditMode()">수정</button>
            <button class="btn-save  hidden" id="btn-save"        onclick="savePrices()">저장</button>
            <button class="btn-cancel hidden" id="btn-cancel"     onclick="location.reload()">취소</button>
            <button class="btn-delete hidden" id="btn-delete"     onclick="enableDeleteMode()">삭제</button>
            <button class="btn-del-confirm hidden" id="btn-del-confirm" onclick="deleteSelected()">🗑 선택 삭제</button>
        </div>
    </div>
    <?php endif; ?>

    <!-- 행 추가 폼 (관리자, 비검색) -->
    <?php if (is_superadmin() && !$is_searching && !$is_previewing): ?>
    <div id="add-product-form" class="add-product-form hidden">
        <h3>새 제품 추가</h3>
        <form method="POST" action="<?php echo BASE_URL; ?>/api/price/add_row.php">
            <input type="hidden" name="csrf_token"   value="<?php echo generate_csrf_token(); ?>">
            <input type="hidden" name="price_month"  value="<?php echo $selected_month_full; ?>">

            <div class="form-grid">
                <div>
                    <label>구분 <span style="color:red">*</span></label>
                    <select name="category_id" id="add-cat-select" onchange="handleAddCatChange(this)" required>
                        <option value="">-- 선택 --</option>
                        <?php foreach ($all_categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>" <?php echo $cat['id'] == $selected_category_id ? 'selected' : ''; ?>>
                            <?php echo h($cat['category_name']); ?>
                        </option>
                        <?php endforeach; ?>
                        <option value="new">+ 직접 입력</option>
                    </select>
                    <input type="text" name="category_name_new" id="add-cat-new" placeholder="새 구분명" style="display:none;margin-top:6px;width:100%;padding:8px;border:1px solid #ddd;border-radius:4px;box-sizing:border-box;">
                </div>
                <div>
                    <label>브랜드</label>
                    <select name="brand_id" id="add-brand-select" onchange="handleAddBrandChange(this)">
                        <option value="">-- 선택 안함 --</option>
                        <?php foreach ($category_brands as $b): ?>
                        <option value="<?php echo $b['id']; ?>"><?php echo h($b['brand_name']); ?></option>
                        <?php endforeach; ?>
                        <option value="new">+ 직접 입력</option>
                    </select>
                    <input type="text" name="brand_name_new" id="add-brand-new" placeholder="새 브랜드명"
                    style="display:none;margin-top:6px;width:100%;padding:8px; border:1px solid #ddd;border-radius:4px;box-sizing:border-box;">
                </div>
                <div>
                    <label>제품명 <span style="color:red">*</span></label>
                    <input type="text" name="product_name" required>
                </div>
                <div>
                    <label>설명</label>
                    <input type="text" name="description">
                </div>
                <div>
                    <label>매입가</label>
                    <input type="number" name="purchase_price" min="0" step="1">
                </div>
                <div>
                    <label>원가</label>
                    <input type="number" name="cost_price" min="0" step="1">
                </div>
                <div>
                    <label>현금가 A</label>
                    <input type="number" name="cash_price_a" min="0" step="1">
                </div>
                <div>
                    <label>현금가 B</label>
                    <input type="number" name="cash_price_b" min="0" step="1">
                </div>
                <div>
                    <label>현금가 C</label>
                    <input type="number" name="cash_price_c" min="0" step="1">
                </div>
                <div>
                    <label>태그</label>
                    <input type="text" name="tags" placeholder="쉼표로 구분 (예: 정품, A3)">
                </div>
            </div>

            <div class="form-actions">
                <button type="button" class="btn-form-cancel" onclick="hideAddForm()">취소</button>
                <button type="submit" class="btn-form-save">추가</button>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <?php include __DIR__ . '/../includes/catalog_price_table.php'; ?>

    <?php else: // $show_company_results ?>
    <div class="company-results-wrap">
        <?php if (empty($company_search_results)): ?>
        <div class="no-data">검색 결과가 없습니다.</div>
        <?php else: ?>
        <?php foreach ($company_search_results as $c): ?>
        <a class="company-result-row"
            href="?company_search=<?php echo urlencode($company_search); ?>&preview_company=<?php echo $c['id']; ?>&month=<?php echo h($selected_month); ?>">
            <span><?php echo h($c['company_name']); ?> (대표자명: <?php echo h($c['representative_name'] ?: '-'); ?> / 담당자명: <?php echo h($c['manager_name'] ?: '-'); ?>)</span>
            <span class="grade-badge">등급<?php echo h($c['grade']); ?></span>
        </a>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php endif; // $show_company_results ?>
</div>

<button id="scroll-top-btn" onclick="window.scrollTo({top:0,behavior:'smooth'})"
    title="맨 위로"
    style="
        display:none;
        position:fixed;
        bottom:28px;
        right:28px;
        width:44px;
        height:44px;
        border-radius:50%;
        background:#5A6778;
        color:white;
        border:none;
        font-size:20px;
        cursor:pointer;
        box-shadow:0 2px 8px rgba(0,0,0,0.25);
        z-index:1001;
        line-height:44px;
        text-align:center;
    ">↑</button>

<!-- 삭제 폼 -->
<form id="delete-form" method="POST" action="<?php echo BASE_URL; ?>/api/price/delete_rows.php" style="display:none">
    <input type="hidden" name="csrf_token"  value="<?php echo generate_csrf_token(); ?>">
    <input type="hidden" name="price_month" value="<?php echo $selected_month_full; ?>">
    <input type="hidden" name="category_id" value="<?php echo $selected_category_id; ?>">
</form>

<script>
const CSRF_TOKEN = '<?php echo generate_csrf_token(); ?>';
const FORMULAS_BY_CATEGORY = <?php echo json_encode($formulas_by_category); ?>;

function toggleLock(btn) {
    const wrap  = btn.closest('.price-input-wrap');
    const input = wrap.querySelector('.price-input');
    const flag  = wrap.querySelector('.manual-flag-input');
    const isLocked = btn.classList.contains('locked');

    if (isLocked) {
        // 고정 해제 → 자동계산 모드
        btn.classList.replace('locked', 'unlocked');
        btn.textContent = '🔓';
        btn.title = '수동입력으로 고정';
        flag.value = '0';
        input.readOnly = true;
        recalcRow(input.closest('tr'));
    } else {
        // 고정 → 수동입력 모드
        btn.classList.replace('unlocked', 'locked');
        btn.textContent = '🔒';
        btn.title = '자동계산으로 전환';
        flag.value = '1';
        input.readOnly = false;
        input.focus();
    }
}

function recalcRow(row) {
    const formula = FORMULAS_BY_CATEGORY[row.dataset.categoryId];
    if (!formula) return;

    const baseGrade = formula.base_grade.toLowerCase();
    const baseInput = row.querySelector(`.price-input[data-grade="${baseGrade}"]`);
    if (!baseInput) return;

    const raw = baseInput.value.trim();
    const baseVal = parseFloat(raw);
    // 기준 등급 가격이 비어있거나 0 이하면 "가격 없음" 상태로 간주 — 다른 등급도 전부 비움
    const hasValidBase = raw !== '' && !isNaN(baseVal) && baseVal > 0;

    ['a', 'b', 'c'].forEach(grade => {
        if (grade === baseGrade) return;
        const input = row.querySelector(`.price-input[data-grade="${grade}"]`);
        if (!input || !input.readOnly) return; // 자물쇠로 고정된(수동) 셀은 건드리지 않음

        if (!hasValidBase) {
            input.value = ''; // 기준 등급 가격이 없으면 다른 등급 가격도 비움 (0/음수 방지)
            return;
        }

        const rule = formula.rules[grade.toUpperCase()];
        if (!rule) return;

        let newVal;
        if (rule.calc_type === 'percent')         newVal = Math.round(baseVal * (1 + rule.calc_value / 100));
        else if (rule.calc_type === 'amount_add')      newVal = Math.round(baseVal + rule.calc_value);
        else if (rule.calc_type === 'amount_subtract') newVal = Math.max(0, Math.round(baseVal - rule.calc_value));
        else return;

        input.value = newVal;
    });
}

document.addEventListener('input', function (e) {
    if (!e.target.matches('.price-input')) return;
    const row = e.target.closest('tr');
    const formula = FORMULAS_BY_CATEGORY[row.dataset.categoryId];
    if (formula && e.target.dataset.grade === formula.base_grade.toLowerCase()) {
        recalcRow(row);
    }
});

/* ── 카테고리 필터 ── */
let activeCatFilters = <?php echo json_encode($cat_filters); ?>;

function toggleCatFilter(catId) {
    const idx = activeCatFilters.indexOf(catId);
    if (idx === -1) activeCatFilters.push(catId);
    else activeCatFilters.splice(idx, 1);
    document.getElementById('cat-filter-input').value = activeCatFilters.join(',');
    document.getElementById('search-form').submit();
}

function removeCatFilter(catId) {
    activeCatFilters = activeCatFilters.filter(id => id !== catId);
    document.getElementById('cat-filter-input').value = activeCatFilters.join(',');
    document.getElementById('search-form').submit();
}

/* ── 카테고리 수식 ── */
const EXISTING_FORMULA = {
    base_grade: '<?php echo $existing_formula['base_grade'] ?? "A"; ?>',
    rules: {
        <?php foreach (['A','B','C'] as $g): if (isset($existing_rules_by_grade[$g])): $r = $existing_rules_by_grade[$g]; ?>
        <?php echo $g; ?>: { calc_type: '<?php echo $r['calc_type']; ?>', calc_value: <?php echo $r['calc_value']; ?> },
        <?php endif; endforeach; ?>
    }
};

const GRADE_LABELS = { A: 'A등급', B: 'B등급', C: 'C등급' };
const CALC_LABELS  = { percent: '퍼센트(%) (+인상/-할인)', amount_add: '금액(원) 추가', amount_subtract: '금액(원) 차감' };

function openFormulaPanel() {
    document.getElementById('formula-base-grade').value = EXISTING_FORMULA.base_grade;
    renderFormulaRuleRows();
    document.getElementById('formula-panel').classList.remove('hidden');
    document.getElementById('btn-formula').classList.add('hidden');
}
function closeFormulaPanel() {
    document.getElementById('formula-panel').classList.add('hidden');
    document.getElementById('btn-formula').classList.remove('hidden');
}

function renderFormulaRuleRows() {
    const base = document.getElementById('formula-base-grade').value;
    const otherGrades = ['A','B','C'].filter(g => g !== base);
    const container = document.getElementById('formula-rule-rows');
    container.innerHTML = '';

        otherGrades.forEach(grade => {
        const existing = (EXISTING_FORMULA.base_grade === base) ? EXISTING_FORMULA.rules[grade] : null;
        const calcType  = existing ? existing.calc_type  : 'percent';
        const calcValue = existing ? existing.calc_value : '';
        const minAttr   = calcType === 'percent' ? '' : 'min="0"';

        const row = document.createElement('div');
        row.className = 'formula-rule-row';
        row.innerHTML = `
            <span class="formula-grade-label">${GRADE_LABELS[grade]}</span>
            <span>=</span>
            <span class="formula-grade-label">${GRADE_LABELS[base]}</span>
            <select name="rules[${grade}][calc_type]" onchange="updateRuleValueConstraints(this)">
                <option value="percent" ${calcType==='percent' ? 'selected' : ''}>${CALC_LABELS.percent}</option>
                <option value="amount_add" ${calcType==='amount_add' ? 'selected' : ''}>${CALC_LABELS.amount_add}</option>
                <option value="amount_subtract" ${calcType==='amount_subtract' ? 'selected' : ''}>${CALC_LABELS.amount_subtract}</option>
            </select>
            <input type="number" name="rules[${grade}][calc_value]" step="0.01" ${minAttr} value="${calcValue}" placeholder="값" required>
        `;
        container.appendChild(row);
    });
}

function updateRuleValueConstraints(select) {
    const input = select.closest('.formula-rule-row').querySelector('input[type="number"]');
    if (select.value === 'percent') {
        input.removeAttribute('min');
    } else {
        input.min = '0';
        if (parseFloat(input.value) < 0) input.value = '';
    }
}

document.getElementById('formula-form')?.addEventListener('submit', function(e) {
    if (!confirm('수식을 저장하면 이 카테고리의 모든 제품 가격이 즉시 재계산됩니다 (수동입력 포함).\n계속하시겠습니까?')) {
        e.preventDefault();
    }
});

/* ── 카테고리 관리 ── */
function openCategoryManage() {
    document.getElementById('category-manage-panel').classList.remove('hidden');
    document.getElementById('btn-category-manage').classList.add('hidden');
}
function closeCategoryManage() {
    document.getElementById('category-manage-panel').classList.add('hidden');
    document.getElementById('btn-category-manage').classList.remove('hidden');
}

let newCategoryCount = 0;

function addCategoryRow() {
    newCategoryCount++;
    const row = document.createElement('div');
    row.className = 'category-manage-row new-category-row';
    row.innerHTML = `
        <input type="text" name="new_categories[]" placeholder="새 카테고리명">
        <button type="button" class="btn-cat-delete" onclick="this.closest('.category-manage-row').remove()">삭제</button>
    `;
    document.getElementById('category-manage-list').appendChild(row);
    row.querySelector('input').focus();
}

function toggleCategoryDelete(catId, catName) {
    const row  = document.getElementById(`cat-row-${catId}`);
    const flag = row.querySelector('.cat-delete-flag');
    const btn  = row.querySelector('.btn-cat-delete');
    const input = row.querySelector('input[type="text"]');
    const isMarked = flag.value === '1';

    if (!isMarked) {
        const count = row.dataset.productCount;
        const msg = count > 0
            ? `'${catName}' 카테고리를 삭제하면 이 안의 제품 ${count}개도 함께 삭제됩니다.\n정말 삭제하시겠습니까?`
            : `'${catName}' 카테고리를 삭제하시겠습니까?`;
        if (!confirm(msg)) return;
        flag.value = '1';
        row.classList.add('marked-delete');
        btn.textContent = '삭제 취소';
        btn.classList.add('marked');
        input.disabled = true;
    } else {
        flag.value = '0';
        row.classList.remove('marked-delete');
        btn.textContent = '삭제';
        btn.classList.remove('marked');
        input.disabled = false;
    }
}

/* ── 월 네비 ── */
function moveMonth(diff) {
    const d = new Date('<?php echo $selected_month; ?>-01');
    d.setMonth(d.getMonth() + diff);
    const m = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
    changeMonth(m);
}
function changeMonth(m) {
    window.location.href = `?month=${m}&category=<?php echo $selected_category_id; ?>`;
}
function openMonthPicker() { document.getElementById('month-picker').showPicker(); }

/* ── 스냅샷 ── */
function createSnapshot() {
    if (!confirm('<?php echo date('Y년 m월', strtotime($selected_month_full)); ?> 스냅샷을 생성하시겠습니까?')) return;
    const f = document.createElement('form');
    f.method = 'POST';
    f.action = '<?php echo BASE_URL; ?>/api/create_snapshot.php';
    f.innerHTML = `
        <input type="hidden" name="target_month" value="<?php echo $selected_month_full; ?>">
        <input type="hidden" name="csrf_token"   value="<?php echo generate_csrf_token(); ?>">
    `;
    document.body.appendChild(f);
    f.submit();
}

// 페이지 로드 시 비활성 행 숨기기
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.inactive-row').forEach(el => el.style.display = 'none');
});

/* ── 행 추가 버튼 토글 ── */
function showAddForm() {
    document.getElementById('add-product-form').classList.remove('hidden');
    document.getElementById('btn-add-row').classList.add('hidden');
}
function hideAddForm() {
    document.getElementById('add-product-form').classList.add('hidden');
    document.getElementById('btn-add-row').classList.remove('hidden');
}

/* ── 수정 모드 ── */
function enableEditMode() {
    const table = document.getElementById('price-table');
    if (!table) return;
    table.classList.add('edit-mode');
    document.querySelectorAll('.tag-subrow').forEach(el => el.style.display = '');
    document.querySelectorAll('.inactive-row').forEach(el => el.style.display = '');
    document.getElementById('btn-edit').classList.add('hidden');
    document.getElementById('btn-save').classList.remove('hidden');
    document.getElementById('btn-cancel').classList.remove('hidden');
    document.getElementById('btn-delete').classList.remove('hidden');
}

function handleBrandSelectChange(select) {
    const inp = select.closest('.brand-edit').querySelector('.brand-new-input');
    const isNew = select.value === 'new';
    inp.style.display = isNew ? 'block' : 'none';
    inp.required = isNew;
    if (!isNew) inp.value = '';
}

/* ── 삭제 모드 ── */
function enableDeleteMode() {
    document.querySelectorAll('.del-col').forEach(el => el.style.display = 'table-cell');
    document.getElementById('btn-delete').classList.add('hidden');
    document.getElementById('btn-save').classList.add('hidden');
    document.getElementById('btn-del-confirm').classList.remove('hidden');
}

function toggleRowHighlight(cb) {
    cb.closest('tr').classList.toggle('selected-for-delete', cb.checked);
}

function deleteSelected() {
    const checked = [...document.querySelectorAll('.row-checkbox:checked')];
    if (!checked.length) { alert('삭제할 항목을 선택해주세요.'); return; }
    if (!confirm(`선택한 ${checked.length}개 제품을 삭제하시겠습니까?\n이 작업은 되돌릴 수 없습니다.`)) return;
    const f = document.getElementById('delete-form');
    f.querySelectorAll('input[name="product_ids[]"]').forEach(el => el.remove());
    checked.forEach(cb => {
        const inp = document.createElement('input');
        inp.type = 'hidden'; inp.name = 'product_ids[]'; inp.value = cb.value;
        f.appendChild(inp);
    });
    f.submit();
}

/* ── 저장 ── */
function savePrices() {
    if (!confirm('저장하시겠습니까?')) return;
    document.getElementById('price-form').submit();
}

/* ── 행 추가 폼 ── */

function handleAddCatChange(select) {
    const inp = document.getElementById('add-cat-new');
    const isNew = select.value === 'new';
    inp.style.display = isNew ? 'block' : 'none';
    inp.required = isNew;
    if (!isNew) inp.value = '';
}

function handleAddBrandChange(select) {
    const inp = document.getElementById('add-brand-new');
    const isNew = select.value === 'new';
    inp.style.display = isNew ? 'block' : 'none';
    if (!isNew) inp.value = '';
}

function toggleActive(pid, newState, btn) {
    const label = newState === 1 ? '활성화' : '비활성화';
    if (!confirm(`이 제품을 ${label}하시겠습니까?`)) return;

    fetch('<?php echo BASE_URL; ?>/api/price/toggle.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `product_id=${pid}&is_active=${newState}&csrf_token=${encodeURIComponent(CSRF_TOKEN)}`
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) { alert(data.message); return; }
        const row = document.getElementById(`row-${pid}`);
        if (newState === 0) {
            row.classList.add('inactive-row');
            btn.textContent = '비활성';
            btn.className = 'btn-toggle inactive';
            btn.onclick = () => toggleActive(pid, 1, btn);
        } else {
            row.classList.remove('inactive-row');
            btn.textContent = '활성';
            btn.className = 'btn-toggle active';
            btn.onclick = () => toggleActive(pid, 0, btn);
        }
    })
    .catch(() => alert('오류가 발생했습니다.'));
}

/* ── 태그 관리 ── */
function getTagsHidden(pid) {
    return document.querySelector(`#tag-edit-${pid} .tags-hidden`);
}

function syncTagsHidden(pid) {
    const row = document.getElementById(`tag-edit-${pid}`);
    const tags = [...row.querySelectorAll('.tag-chip span')]
        .map(el => el.textContent.replace(/^#/, '').trim())
        .filter(Boolean);
    getTagsHidden(pid).value = tags.join(', ');
}

function removeTag(btn, pid) {
    btn.closest('.tag-chip').remove();
    syncTagsHidden(pid);
}

function showTagInput(pid) {
    const addBtn = document.querySelector(`#tag-edit-${pid} .tag-add-btn`);
    // 이미 입력창이 열려 있으면 무시
    if (document.getElementById(`tag-input-${pid}`)) return;
    const inp = document.createElement('input');
    inp.type = 'text';
    inp.id = `tag-input-${pid}`;
    inp.className = 'tag-inline-input';
    inp.placeholder = '태그명 입력';
    addBtn.insertAdjacentElement('beforebegin', inp);
    inp.focus();

    function commit() {
        const val = inp.value.trim().replace(/^#/, '');
        inp.remove();
        if (!val) return;
        // 중복 체크
        const existing = [...document.querySelectorAll(`#tag-edit-${pid} .tag-chip span`)]
            .map(el => el.textContent.replace(/^#/, '').trim());
        if (existing.includes(val)) return;
        const chip = document.createElement('span');
        chip.className = 'tag-chip';
        chip.innerHTML = `<span>#${val}</span><button type="button" onclick="removeTag(this, ${pid})">×</button>`;
        addBtn.insertAdjacentElement('beforebegin', chip);
        syncTagsHidden(pid);
    }
    inp.addEventListener('keydown', e => {
        if (e.key === 'Enter') { e.preventDefault(); commit(); }
        if (e.key === 'Escape') inp.remove();
    });
    inp.addEventListener('blur', commit);
}

/*Scroll to Top 버튼*/
const scrollBtn = document.getElementById('scroll-top-btn');
window.addEventListener('scroll', () => {
    const scrollTop = window.scrollY || document.documentElement.scrollTop || document.body.scrollTop;
    scrollBtn.style.display = scrollTop > 300 ? 'block' : 'none';
});

</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>