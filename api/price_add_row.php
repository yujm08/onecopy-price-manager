<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/config.php';

require_admin();

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $_SESSION['error_message'] = '보안 토큰이 유효하지 않습니다.';
    header('Location: ' . BASE_URL . '/admin/price_manage.php'); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/admin/price_manage.php'); exit;
}

$category_id       = $_POST['category_id']       ?? '';
$category_name_new = trim($_POST['category_name_new'] ?? '');
$price_month       = $_POST['price_month']        ?? '';
$product_name      = trim($_POST['product_name']  ?? '');
$description       = trim($_POST['description']   ?? '');
$brand_id          = $_POST['brand_id']           ?? '';
$brand_name_new    = trim($_POST['brand_name_new'] ?? '');
$cash_price_a = ($_POST['cash_price_a'] ?? '') !== '' ? (int)$_POST['cash_price_a'] : null;
$cash_price_b = ($_POST['cash_price_b'] ?? '') !== '' ? (int)$_POST['cash_price_b'] : null;
$cash_price_c = ($_POST['cash_price_c'] ?? '') !== '' ? (int)$_POST['cash_price_c'] : null;
$tags_raw          = trim($_POST['tags'] ?? '');
$tags              = array_values(array_filter(array_map('trim', explode(',', $tags_raw))));
$tags = array_slice($tags, 0, 20);

$month = $price_month ? date('Y-m', strtotime($price_month)) : date('Y-m');
$back  = BASE_URL . "/admin/price_manage.php?month={$month}&category=" . (int)$category_id;

// 검증
if (mb_strlen($product_name) > 100) { $_SESSION['error_message'] = '제품명은 100자 이하'; header("Location: $back"); exit; }
if (mb_strlen($description)  > 200) { $_SESSION['error_message'] = '설명은 200자 이하';    header("Location: $back"); exit; }
if (empty($product_name) || empty($price_month)) {
    $_SESSION['error_message'] = '제품명과 월은 필수입니다.'; header("Location: $back"); exit;
}
if (empty($category_id) || ($category_id === 'new' && empty($category_name_new))) {
    $_SESSION['error_message'] = '구분을 선택해주세요.'; header("Location: $back"); exit;
}

$current_user = get_current_login_user();

try {
    $pdo->beginTransaction();

    // 카테고리
    if ($category_id === 'new') {
        $stmt = $pdo->prepare("SELECT id FROM categories WHERE category_name = ?");
        $stmt->execute([$category_name_new]);
        $ex = $stmt->fetch();
        if ($ex) {
            $final_cat_id = $ex['id'];
        } else {
            $pdo->prepare("INSERT INTO categories (category_name) VALUES (?)")->execute([$category_name_new]);
            $final_cat_id = $pdo->lastInsertId();
        }
    } else {
        $final_cat_id = (int)$category_id;
    }

    // 브랜드
    $final_brand_id = null;
    if (!empty($brand_name_new)) {
        $stmt = $pdo->prepare("SELECT id FROM brands WHERE brand_name = ? AND category_id = ?");
        $stmt->execute([$brand_name_new, $final_cat_id]);
        $ex = $stmt->fetch();
        if ($ex) {
            $final_brand_id = $ex['id'];
        } else {
            $pdo->prepare("INSERT INTO brands (brand_name, category_id) VALUES (?, ?)")->execute([$brand_name_new, $final_cat_id]);
            $final_brand_id = $pdo->lastInsertId();
        }
    } elseif (!empty($brand_id) && $brand_id !== 'new') {
        $final_brand_id = (int)$brand_id;
    }

    // 제품번호 자동 생성
    $max_num = (int)$pdo->query("SELECT COALESCE(MAX(CAST(product_number AS UNSIGNED)), 0) FROM products")->fetchColumn();
    $product_number = str_pad($max_num + 1, 4, '0', STR_PAD_LEFT);

    // 제품 삽입
    $pdo->prepare("
        INSERT INTO products (product_number, category_id, brand_id, product_name, description, is_active)
        VALUES (?, ?, ?, ?, ?, 1)
    ")->execute([$product_number, $final_cat_id, $final_brand_id, $product_name, $description ?: null]);
    $new_pid = $pdo->lastInsertId();

    // 가격 삽입
    if ($cash_price_a !== null || $cash_price_b !== null || $cash_price_c !== null) {
        $pdo->prepare("
            INSERT INTO prices (product_id, price_month, cash_price_a, cash_price_b, cash_price_c, updated_by_company_id)
            VALUES (?, ?, ?, ?, ?, ?)
        ")->execute([$new_pid, $price_month, $cash_price_a, $cash_price_b, $cash_price_c, $current_user['id']]);
    }

    // 태그 삽입
    if (!empty($tags)) {
        $ts = $pdo->prepare("INSERT INTO product_tags (product_id, tag) VALUES (?, ?)");
        foreach ($tags as $tag) {
            if (mb_strlen($tag) <= 50) $ts->execute([$new_pid, $tag]);
        }
    }

    $pdo->commit();
    $_SESSION['success_message'] = "제품 '{$product_name}' (번호: {$product_number})이(가) 추가되었습니다.";
    header("Location: " . BASE_URL . "/admin/price_manage.php?month={$month}&category={$final_cat_id}");
    exit;

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log('price_add_row: ' . $e->getMessage());
    $_SESSION['error_message'] = '제품 추가 중 오류가 발생했습니다.';
    header("Location: $back"); exit;
}
?>