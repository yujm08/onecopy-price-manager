<?php
// api/price_delete_rows.php
// 제품 삭제 처리 (관리자 전용)

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/config.php';

require_admin();

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $_SESSION['error_message'] = '보안 토큰이 유효하지 않습니다.';
    header('Location: ' . BASE_URL . '/admin/price_manage.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('잘못된 요청입니다.');
}

$product_ids = $_POST['product_ids'] ?? [];
$price_month = $_POST['price_month'] ?? '';
$category_id = (int)($_POST['category_id'] ?? 0);
$month       = date('Y-m', strtotime($price_month));

if (empty($product_ids)) {
    $_SESSION['error_message'] = '삭제할 항목이 없습니다.';
    header("Location: " . BASE_URL . "/admin/price_manage.php?month={$month}&category={$category_id}");
    exit;
}

// 정수 배열로 정제 (SQL Injection 방지)
$product_ids = array_map('intval', $product_ids);
$product_ids = array_filter($product_ids, fn($id) => $id > 0);

if (empty($product_ids)) {
    $_SESSION['error_message'] = '잘못된 요청입니다.';
    header("Location: " . BASE_URL . "/admin/price_manage.php?month={$month}&category={$category_id}");
    exit;
}

try {
    $placeholders = implode(',', array_fill(0, count($product_ids), '?'));

    // 삭제할 제품들의 category_id 미리 수집
    $stmt = $pdo->prepare("SELECT DISTINCT category_id FROM products WHERE id IN ($placeholders)");
    $stmt->execute($product_ids);
    $affected_category_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // products 삭제 → prices는 FK CASCADE로 자동 삭제
    $stmt = $pdo->prepare("DELETE FROM products WHERE id IN ($placeholders)");
    $stmt->execute($product_ids);
    $deleted_count = $stmt->rowCount();

    // 제품이 하나도 없어진 카테고리 삭제
    $category_was_deleted = false;
    foreach ($affected_category_ids as $cat_id) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
        $stmt->execute([$cat_id]);
        if ($stmt->fetchColumn() == 0) {
            $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$cat_id]);
            if ($cat_id == $category_id) {
                $category_was_deleted = true;
            }
        }
    }

    // 현재 카테고리가 삭제됐으면 첫 번째 남은 카테고리로 이동
    if ($category_was_deleted) {
        $stmt = $pdo->query("SELECT id FROM categories ORDER BY id LIMIT 1");
        $first_cat = $stmt->fetch();
        $category_id = $first_cat ? $first_cat['id'] : 0;
    }

} catch (PDOException $e) {
    error_log('price_delete_rows error: ' . $e->getMessage());
    $_SESSION['error_message'] = '삭제 중 오류가 발생했습니다.';
}

header("Location: " . BASE_URL . "/admin/price_manage.php?month={$month}&category={$category_id}");
exit;
?>