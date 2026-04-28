<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/config.php';

require_admin();

$price_month = $_POST['price_month'] ?? '';
$category_id = $_POST['category_id'] ?? 1;
$month       = date('Y-m', strtotime($price_month));
$back        = BASE_URL . "/admin/price_manage.php?month={$month}&category={$category_id}";

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $_SESSION['error_message'] = '보안 토큰이 유효하지 않습니다.';
    header("Location: $back"); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: $back"); exit;
}

$products_data = $_POST['products'] ?? [];
$prices_data   = $_POST['prices']   ?? [];
$current_user  = get_current_login_user();

try {
    $pdo->beginTransaction();

    $product_updated = 0;
    $saved = 0;
    // 제품 정보 + 태그 업데이트
    foreach ($products_data as $pid => $data) {
        $pid          = (int)$pid;
        $product_name = trim($data['product_name'] ?? '');
        $description  = trim($data['description']  ?? '');
        $brand_id     = $data['brand_id']           ?? '';
        $brand_name_new = trim($data['brand_name_new'] ?? '');
        $tags_raw     = trim($data['tags']          ?? '');
        $tags         = array_values(array_filter(array_map('trim', explode(',', $tags_raw))));
        $tags = array_slice($tags, 0, 20);

        if (empty($product_name)) continue;
        if (mb_strlen($product_name) > 100 || mb_strlen($description) > 200) {
            throw new Exception("입력값이 너무 깁니다.");
        }

        // 브랜드
        $final_brand_id = null;
        if (!empty($brand_name_new)) {
            $cat_id = $pdo->prepare("SELECT category_id FROM products WHERE id = ?");
            $cat_id->execute([$pid]);
            $cat_id = $cat_id->fetchColumn();

            $stmt = $pdo->prepare("SELECT id FROM brands WHERE brand_name = ?");
            $stmt->execute([$brand_name_new]);
            $ex = $stmt->fetch();
            if ($ex) {
                $final_brand_id = $ex['id'];
            } else {
                $pdo->prepare("INSERT INTO brands (brand_name, category_id) VALUES (?, ?)")->execute([$brand_name_new, $cat_id]);
                $final_brand_id = $pdo->lastInsertId();
            }
        } elseif (!empty($brand_id) && $brand_id !== 'new') {
            $final_brand_id = (int)$brand_id;
        }

        $stmt = $pdo->prepare("UPDATE products SET product_name=?, description=?, brand_id=? WHERE id=?");
        $stmt->execute([$product_name, $description ?: null, $final_brand_id, $pid]);
        if ($stmt->rowCount() > 0) $product_updated++;

        // 태그 교체
        $pdo->prepare("DELETE FROM product_tags WHERE product_id = ?")->execute([$pid]);
        if (!empty($tags)) {
            $ts = $pdo->prepare("INSERT INTO product_tags (product_id, tag) VALUES (?, ?)");
            foreach ($tags as $tag) {
                if (mb_strlen($tag) <= 50) $ts->execute([$pid, $tag]);
            }
        }
    }

    // 가격 업데이트
    $saved = 0;
    foreach ($prices_data as $pid => $price_data) {
        $pid  = (int)$pid;
        $pa   = isset($price_data['cash_price_a']) && $price_data['cash_price_a'] !== '' ? (int)$price_data['cash_price_a'] : null;
        $pb   = isset($price_data['cash_price_b']) && $price_data['cash_price_b'] !== '' ? (int)$price_data['cash_price_b'] : null;
        $pc   = isset($price_data['cash_price_c']) && $price_data['cash_price_c'] !== '' ? (int)$price_data['cash_price_c'] : null;

        if ($pa === null && $pb === null && $pc === null) {
            $pdo->prepare("DELETE FROM prices WHERE product_id=? AND price_month=?")->execute([$pid, $price_month]);
            continue;
        }

        $exists = $pdo->prepare("SELECT id FROM prices WHERE product_id=? AND price_month=?");
        $exists->execute([$pid, $price_month]);

        if ($exists->fetch()) {
            $stmt = $pdo->prepare("
                UPDATE prices SET cash_price_a=?, cash_price_b=?, cash_price_c=?,
                    updated_by_company_id=?, updated_at=NOW()
                WHERE product_id=? AND price_month=?
            ");
            $stmt->execute([$pa, $pb, $pc, $current_user['id'], $pid, $price_month]);
            if ($stmt->rowCount() > 0) $saved++;
        } else {
            $pdo->prepare("
                INSERT INTO prices (product_id, price_month, cash_price_a, cash_price_b, cash_price_c, updated_by_company_id)
                VALUES (?, ?, ?, ?, ?, ?)
            ")->execute([$pid, $price_month, $pa, $pb, $pc, $current_user['id']]);
            $saved++;
        }
    }

    $pdo->commit();
    $parts = [];
    if ($product_updated > 0) $parts[] = "{$product_updated}개 행이 수정되었습니다.";
    if ($saved > 0)           $parts[] = "{$saved}개 제품 가격이 저장되었습니다.";
    $_SESSION['success_message'] = !empty($parts) ? implode(' ', $parts) : '변경된 내용이 없습니다.';

} catch (Exception $e) {
    $pdo->rollBack();
    error_log('price_save: ' . $e->getMessage());
    $_SESSION['error_message'] = '저장 중 오류가 발생했습니다.';
}

header("Location: $back"); exit;
?>