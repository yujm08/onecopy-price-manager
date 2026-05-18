<?php
$page_title = '가격 관리';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/config.php';

/* ── 월 ── */
if (is_admin()) {
    $selected_month = $_GET['month'] ?? date('Y-m');
} else {
    $row = $pdo->query("
        SELECT DATE_FORMAT(MAX(price_month),'%Y-%m') FROM prices
        WHERE cash_price_a IS NOT NULL OR cash_price_b IS NOT NULL OR cash_price_c IS NOT NULL
    ")->fetchColumn();
    $selected_month = $row ?: date('Y-m');
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
$from_condition = is_admin() ? "WHERE 1=1" : "WHERE p.is_active = 1";
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
        pr.cost_price, pr.cash_price_a, pr.cash_price_b, pr.cash_price_c, pr.updated_at
    $from $extra
    ORDER BY c.id, p.is_active DESC, CAST(p.product_number AS UNSIGNED)
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

/* ── 헬퍼 ── */
function calc_card_price($cash) {
    if (!$cash) return null;
    return (int)(ceil((float)$cash / 0.97 / 1000) * 1000);
}
$user_grade = strtolower($_SESSION['grade'] ?? '');
?>

<style>
.container { max-width: 1400px; width: 100%; margin: 0 auto; padding: 0 16px; box-sizing: border-box; }

/* 월 네비 */
.month-nav { display:flex; align-items:center; gap:12px; margin-bottom:20px; }
.month-nav button { padding:8px 14px; background:#5A6778; color:white; border:none; border-radius:4px; cursor:pointer; font-size:16px; }
.month-nav button:hover { background:#4B5563; }
.month-display { font-size:18px; font-weight:700; padding:8px 16px; border:2px solid #ddd; border-radius:4px; background:white; cursor:pointer; min-width:130px; text-align:center; }
.month-display:hover { background:#f8f9fa; }
.btn-snapshot { padding:8px 16px; background:#8e44ad; color:white; border:none; border-radius:4px; cursor:pointer; font-size:13px; }
.btn-snapshot:hover { background:#7d3c98; }

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
.admin-buttons { display:flex; gap:8px; flex-shrink:0; }

/* 액션 버튼 */
.btn-edit     { padding:8px 18px; background:#f39c12; color:white; border:none; border-radius:4px; cursor:pointer; font-size:14px; font-weight:500; }
.btn-edit:hover { background:#e67e22; }
.btn-save     { padding:8px 18px; background:#27ae60; color:white; border:none; border-radius:4px; cursor:pointer; font-size:14px; font-weight:500; }
.btn-save:hover { background:#229954; }
.btn-cancel   { padding:8px 18px; background:#95a5a6; color:white; border:none; border-radius:4px; cursor:pointer; font-size:14px; }
.btn-cancel:hover { background:#7f8c8d; }
.btn-delete   { padding:8px 18px; background:#e74c3c; color:white; border:none; border-radius:4px; cursor:pointer; font-size:14px; }
.btn-delete:hover { background:#c0392b; }
.btn-del-confirm { padding:8px 18px; background:#c0392b; color:white; border:none; border-radius:4px; cursor:pointer; font-size:14px; }
.btn-add-row  { margin-top:12px; padding:9px 18px; background:#5A6778; color:white; border:none; border-radius:4px; cursor:pointer; font-size:14px; }
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

/* 행 추가 폼 */
.add-product-form { background:#fff8e1; border:2px solid #ffc107; padding:20px; border-radius:8px; margin-bottom:14px; }
.add-product-form h3 { margin:0 0 14px; color:#856404; font-size:15px; }
.form-grid { display:grid; grid-template-columns:repeat(auto-fit, minmax(170px, 1fr)); gap:12px; margin-bottom:14px; }
.form-grid label { display:block; font-size:13px; font-weight:500; margin-bottom:4px; color:#444; }
.form-grid input, .form-grid select { width:100%; padding:8px 10px; border:1px solid #ddd; border-radius:4px; font-size:13px; box-sizing:border-box; }
.form-actions { display:flex; justify-content:flex-end; gap:8px; }
.btn-form-save   { padding:8px 20px; background:#27ae60; color:white; border:none; border-radius:4px; cursor:pointer; font-size:14px; }
.btn-form-cancel { padding:8px 16px; background:#95a5a6; color:white; border:none; border-radius:4px; cursor:pointer; font-size:14px; }

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
.price-table { width:100%; border-collapse: separate; border-spacing:0; }
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

    <!-- 월 네비게이션 -->
    <?php if (is_admin()): ?>
    <div class="month-nav">
        <button onclick="moveMonth(-1)">◀</button>
        <div class="month-display" onclick="openMonthPicker()">
            <?php echo date('Y년 m월', strtotime($selected_month_full)); ?>
        </div>
        <button onclick="moveMonth(1)">▶</button>
        <input type="month" id="month-picker" value="<?php echo h($selected_month); ?>"
                onchange="changeMonth(this.value)"
                style="position:absolute;opacity:0;pointer-events:none;">
        <button class="btn-snapshot" onclick="createSnapshot()">스냅샷 생성</button>
    </div>
    <?php else: ?>
    <div style="font-size:18px;font-weight:700;margin-bottom:20px;">
        <?php echo date('Y년 m월', strtotime($selected_month_full)); ?>
    </div>
    <?php endif; ?>

    <!-- 검색 폼 -->
    <form id="search-form" class="search-section" method="GET" action="">
        <input type="hidden" name="month"      value="<?php echo h($selected_month); ?>">
        <input type="hidden" name="category"   value="<?php echo $selected_category_id; ?>">
        <input type="hidden" name="cat_filter" id="cat-filter-input" value="<?php echo h($cat_filter_str); ?>">

        <div class="search-row">
            <input type="text" name="q" id="search-input" class="search-input"
                    value="<?php echo h($search_query); ?>"
                    placeholder="검색어 입력 (쉼표로 구분하여 다중 검색)">
            <button type="submit" class="btn-search">검색</button>
            <?php if ($is_searching || !empty($cat_filters)): ?>
            <a href="?month=<?php echo h($selected_month); ?>&category=<?php echo $selected_category_id; ?>" class="btn-reset-search">초기화</a>
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

    <!-- 카테고리 탭 + 관리자 버튼 -->
    <div class="category-bar">
        <?php if (!$is_searching): ?>
        <div class="category-tabs">
            <?php foreach ($all_categories as $cat): ?>
            <a href="?month=<?php echo h($selected_month); ?>&category=<?php echo $cat['id']; ?>"
               class="category-tab <?php echo $cat['id'] == $selected_category_id ? 'active' : ''; ?>">
                <?php echo h($cat['category_name']); ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div style="font-size:13px;color:#888;">전체 카테고리 검색 결과</div>
        <?php endif; ?>

        <?php if (is_admin()): ?>
        <div class="admin-buttons">
            <button class="btn-edit"        id="btn-edit"        onclick="enableEditMode()">수정</button>
            <button class="btn-save  hidden" id="btn-save"        onclick="savePrices()">저장</button>
            <button class="btn-cancel hidden" id="btn-cancel"     onclick="location.reload()">취소</button>
            <button class="btn-delete hidden" id="btn-delete"     onclick="enableDeleteMode()">삭제</button>
            <button class="btn-del-confirm hidden" id="btn-del-confirm" onclick="deleteSelected()">🗑 선택 삭제</button>
        </div>
        <?php endif; ?>
    </div>

    <!-- 행 추가 폼 (관리자, 비검색) -->
    <?php if (is_admin() && !$is_searching): ?>
    <div id="add-product-form" class="add-product-form hidden">
        <h3>새 제품 추가</h3>
        <form method="POST" action="<?php echo BASE_URL; ?>/api/price_add_row.php">
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

    <!-- 가격 테이블 -->
    <?php if (empty($products)): ?>
    <div class="price-table-wrap">
        <div class="price-table-scroll">
            <div class="no-data"><?php echo $is_searching ? '검색 결과가 없습니다.' : '해당 월의 가격 정보가 없습니다.'; ?></div>
        </div>
    </div>
    <?php else: ?>

    <form id="price-form" method="POST" action="<?php echo BASE_URL; ?>/api/price_save.php">
        <input type="hidden" name="csrf_token"  value="<?php echo generate_csrf_token(); ?>">
        <input type="hidden" name="price_month" value="<?php echo $selected_month_full; ?>">
        <input type="hidden" name="category_id" value="<?php echo $selected_category_id; ?>">

        <div class="price-table-wrap">
            <div class="price-table-scroll">
            <table class="price-table" id="price-table">
               <thead>
                    <?php if (is_admin()): ?>
                    <tr>
                        <th rowspan="2" class="del-col" style="display:none;width:36px"></th>
                        <th rowspan="2">제품번호</th>
                        <?php if ($is_searching): ?><th rowspan="2">구분</th><?php endif; ?>
                        <?php if ($has_brand): ?><th rowspan="2">브랜드</th><?php endif; ?>
                        <th rowspan="2">제품명</th>
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
                    if (!is_admin()) {
                        $my_cash = $product['cash_price_' . $user_grade] ?? null;
                        $my_card = calc_card_price($my_cash);
                    }
                ?>
                <tr id="row-<?php echo $pid; ?>" <?php echo !$product['is_active'] ? 'class="inactive-row"' : ''; ?>>
                    <?php if (is_admin()): ?>
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
                    <?php if (is_admin()): ?>
                        <!-- 원가 -->
                        <td class="num editable">
                            <span class="cell-display">
                                <?php echo $product['cost_price'] !== null ? number_format($product['cost_price']) . '원' : '-'; ?>
                            </span>
                            <input type="number" name="prices[<?php echo $pid; ?>][cost_price]"
                                value="<?php echo $product['cost_price'] ?? ''; ?>"
                                style="display:none" min="0" step="1">
                        </td>
                        <!-- 현금가 A + 이익률 A / B / C -->
                        <?php foreach (['cash_price_a' => 'A', 'cash_price_b' => 'B', 'cash_price_c' => 'C'] as $col => $grade):
                            $cost   = $product['cost_price'];
                            $cash   = $product[$col];
                            $margin = ($cost > 0 && $cash !== null) ? round(($cash - $cost) / $cash * 100, 1) : null;
                        ?>
                        <td class="num editable">
                            <span class="cell-display">
                                <?php echo $cash !== null ? number_format($cash) . '원' : '-'; ?>
                            </span>
                            <input type="number" name="prices[<?php echo $pid; ?>][<?php echo $col; ?>]"
                                value="<?php echo $cash ?? ''; ?>"
                                style="display:none" min="0" step="1">
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
                    <?php if (is_admin()): ?>
                    <td class="toggle-col">
                        <button type="button"
                            class="btn-toggle <?php echo $product['is_active'] ? 'active' : 'inactive'; ?>"
                            onclick="toggleActive(<?php echo $pid; ?>, <?php echo $product['is_active'] ? 0 : 1; ?>, this)">
                            <?php echo $product['is_active'] ? '활성' : '비활성'; ?>
                        </button>
                    </td>
                    <?php endif; ?>
                </tr>
                    <?php if (is_admin()): ?>
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

    <!-- 행 추가 버튼 -->
    <?php if (is_admin() && !$is_searching): ?>
    <button class="btn-add-row" onclick="showAddForm()">+ 행 추가</button>
    <?php endif; ?>
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
<form id="delete-form" method="POST" action="<?php echo BASE_URL; ?>/api/price_delete_rows.php" style="display:none">
    <input type="hidden" name="csrf_token"  value="<?php echo generate_csrf_token(); ?>">
    <input type="hidden" name="price_month" value="<?php echo $selected_month_full; ?>">
    <input type="hidden" name="category_id" value="<?php echo $selected_category_id; ?>">
</form>

<script>
const CSRF_TOKEN = '<?php echo generate_csrf_token(); ?>';

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

function toggleCheckAll(cb) {
    document.querySelectorAll('.row-checkbox').forEach(el => {
        el.checked = cb.checked;
        toggleRowHighlight(el);
    });
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
function showAddForm() { document.getElementById('add-product-form').classList.remove('hidden'); }
function hideAddForm() { document.getElementById('add-product-form').classList.add('hidden'); }

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

    fetch('<?php echo BASE_URL; ?>/api/price_toggle.php', {
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