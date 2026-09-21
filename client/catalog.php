<?php
$page_title = '상품 조회';
require_once __DIR__ . '/../includes/header.php';               // require_login() 여기서 걸림
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/catalog_helpers.php';

// 관리자/슈퍼관리자는 관리 화면으로 (이 페이지는 일반 업체 전용)
if (is_admin()) {
    header('Location: ' . BASE_URL . '/admin/price_manage.php');
    exit;
}

/* ── 월 (고객은 항상 가격이 등록된 최신 월을 봄, 선택 불가) ── */
$row = $pdo->query("
    SELECT DATE_FORMAT(MAX(price_month),'%Y-%m') FROM prices
    WHERE cash_price_a IS NOT NULL OR cash_price_b IS NOT NULL OR cash_price_c IS NOT NULL
")->fetchColumn();
$selected_month      = $row ?: date('Y-m');
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

/* ── 쿼리 공통 FROM (고객은 활성 상품만) ── */
$from = "FROM products p
    LEFT JOIN brands b     ON p.brand_id = b.id
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN prices pr    ON p.id = pr.product_id AND pr.price_month = ?
    LEFT JOIN product_tags pt ON p.id = pt.product_id
    WHERE p.is_active = 1";

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
        pr.cash_price_a, pr.cash_price_b, pr.cash_price_c
    $from $extra
    ORDER BY c.id, p.product_name
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

/* ── catalog_price_table.php가 참조하는 관리자 전용 변수들 — 이 페이지에선 항상 빈 값 ── */
$brands_by_category   = [];
$formulas_by_category = [];

/* ── 이 페이지는 항상 "고객용 읽기전용" 모드로 테이블을 그림 ── */
$effective_is_admin = false;
$is_previewing       = false;
$current_user        = get_current_login_user();
$effective_grade     = strtolower($_SESSION['grade'] ?? '');
?>

<style>
.container { max-width: 1400px; width: 100%; margin: 0 auto; padding: 0 16px; box-sizing: border-box; }

/* 상단 바 (월 표시) */
.top-bar { display:flex; justify-content:space-between; align-items:center; gap:16px; margin-bottom:20px; flex-wrap:wrap; }

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

/* 테이블 (읽기전용 부분만) */
.price-table-wrap {
    position: relative;
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.08);
    overflow: auto;
    max-height: calc(100vh - 200px);
    user-select: none;
    -webkit-user-select: none;
}
.price-table-scroll { overflow:visible; }
.price-table { width:100%; border-collapse: separate; border-spacing:0; }
.price-table thead { background:#34495e; color:white; }
.price-table thead th { position:sticky; top:0; z-index:10; background:#34495e; }
.price-table th { padding:12px 14px; text-align:left; font-weight:500; font-size:13px; white-space:nowrap; }
.price-table td { padding:11px 14px; border-bottom:1px solid #eee; font-size:13px; vertical-align:middle; }
.price-table tbody tr:last-child td { border-bottom:none; }
.price-table tbody tr:hover { background:#f8f9fa; }
.price-table td.num { text-align:right; font-variant-numeric:tabular-nums; }
.no-data { padding:60px 20px; text-align:center; color:#7f8c8d; }

.desc-cell {
    min-width: 150px;
    max-width: 300px;
    white-space: normal;
    word-break: break-all;
    line-height: 1.5;
}
.desc-cell .cell-display { display: block; }
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

    <div class="top-bar">
        <div style="font-size:18px;font-weight:700;">
            <?php echo date('Y년 m월', strtotime($selected_month_full)); ?>
        </div>
    </div>

    <!-- 검색 폼 -->
    <form id="search-form" class="search-section" method="GET" action="">
        <input type="hidden" name="category"   value="<?php echo $selected_category_id; ?>">
        <input type="hidden" name="cat_filter" id="cat-filter-input" value="<?php echo h($cat_filter_str); ?>">

        <div class="search-row">
            <input type="text" name="q" id="search-input" class="search-input"
                    value="<?php echo h($search_query); ?>"
                    placeholder="검색어 입력 (쉼표로 구분하여 다중 검색)">
            <button type="submit" class="btn-search">검색</button>
            <?php if ($is_searching || !empty($cat_filters)): ?>
            <a href="?category=<?php echo $selected_category_id; ?>" class="btn-reset-search">초기화</a>
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

    <!-- 카테고리 탭 -->
    <div class="category-bar">
        <?php if (!$is_searching): ?>
        <div class="category-tabs">
            <?php foreach ($all_categories as $cat): ?>
            <a href="?category=<?php echo $cat['id']; ?>"
                class="category-tab <?php echo $cat['id'] == $selected_category_id ? 'active' : ''; ?>">
                <?php echo h($cat['category_name']); ?>
            </a>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div style="font-size:13px;color:#888;">전체 카테고리 검색 결과</div>
        <?php endif; ?>
    </div>

    <?php include __DIR__ . '/../includes/catalog_price_table.php'; ?>

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

<script>
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

/* Scroll to Top 버튼 */
const scrollBtn = document.getElementById('scroll-top-btn');
window.addEventListener('scroll', () => {
    const scrollTop = window.scrollY || document.documentElement.scrollTop || document.body.scrollTop;
    scrollBtn.style.display = scrollTop > 300 ? 'block' : 'none';
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>