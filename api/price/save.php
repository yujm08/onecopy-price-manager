<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/config.php';

require_superadmin();

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

// 이 카테고리의 가격 수식 조회 (자동계산 등급은 서버에서 재계산해서 신뢰)
$formula_stmt = $pdo->prepare("SELECT * FROM category_price_formulas WHERE category_id = ?");
$formula_stmt->execute([$category_id]);
$formula = $formula_stmt->fetch();

$formula_rules = [];
if ($formula) {
    $rstmt = $pdo->prepare("SELECT * FROM category_price_formula_rules WHERE formula_id = ?");
    $rstmt->execute([$formula['id']]);
    foreach ($rstmt->fetchAll() as $r) {
        $formula_rules[$r['target_grade']] = $r;
    }
}

function calc_formula_price($base_price, $calc_type, $calc_value) {
    switch ($calc_type) {
        case 'percent':         return (int) round($base_price * (1 + $calc_value / 100));
        case 'amount_add':      return (int) round($base_price + $calc_value);
        case 'amount_subtract': return (int) round($base_price - $calc_value);
        default: return null;
    }
}

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
        $purchase = isset($price_data['purchase_price']) && $price_data['purchase_price'] !== '' ? (int)$price_data['purchase_price'] : null;
        $cost = isset($price_data['cost_price']) && $price_data['cost_price'] !== '' ? (int)$price_data['cost_price'] : null;
        $pa   = isset($price_data['cash_price_a']) && $price_data['cash_price_a'] !== '' ? (int)$price_data['cash_price_a'] : null;
        $pb   = isset($price_data['cash_price_b']) && $price_data['cash_price_b'] !== '' ? (int)$price_data['cash_price_b'] : null;
        $pc   = isset($price_data['cash_price_c']) && $price_data['cash_price_c'] !== '' ? (int)$price_data['cash_price_c'] : null;

        $manual_a = !empty($price_data['cash_price_a_manual']) ? 1 : 0;
        $manual_b = !empty($price_data['cash_price_b_manual']) ? 1 : 0;
        $manual_c = !empty($price_data['cash_price_c_manual']) ? 1 : 0;

        if ($pa === null && $pb === null && $pc === null) {
            $pdo->prepare("DELETE FROM prices WHERE product_id=? AND price_month=?")->execute([$pid, $price_month]);
            continue;
        }

        // 이 카테고리에 수식이 있으면, 자물쇠 풀린(자동) 등급은 서버에서 다시 계산 (클라이언트 값 신뢰 안 함)
        if ($formula) {
            $base_grade = $formula['base_grade'];
            $vals   = ['A' => $pa, 'B' => $pb, 'C' => $pc];
            $manual = ['A' => $manual_a, 'B' => $manual_b, 'C' => $manual_c];
            $base_val = $vals[$base_grade];

            if ($base_val !== null) {
                foreach (['A', 'B', 'C'] as $g) {
                    if ($g === $base_grade || $manual[$g]) continue;
                    $rule = $formula_rules[$g] ?? null;
                    if ($rule) {
                        $vals[$g] = calc_formula_price($base_val, $rule['calc_type'], $rule['calc_value']);
                    }
                }
            }
            $pa = $vals['A']; $pb = $vals['B']; $pc = $vals['C'];
        }

        $exists = $pdo->prepare("SELECT id FROM prices WHERE product_id=? AND price_month=?");
        $exists->execute([$pid, $price_month]);

        if ($exists->fetch()) {
            $stmt = $pdo->prepare("
                UPDATE prices SET cash_price_a=?, cash_price_b=?, cash_price_c=?, purchase_price=?, cost_price=?,
                    cash_price_a_manual=?, cash_price_b_manual=?, cash_price_c_manual=?,
                    updated_by_company_id=?, updated_at=NOW()
                WHERE product_id=? AND price_month=?
            ");
            $stmt->execute([$pa, $pb, $pc, $purchase, $cost, $manual_a, $manual_b, $manual_c, $current_user['id'], $pid, $price_month]);
            if ($stmt->rowCount() > 0) $saved++;
        } else {
                        $pdo->prepare("
                INSERT INTO prices (
                    product_id, price_month, cash_price_a, cash_price_b, cash_price_c, purchase_price, cost_price,
                    cash_price_a_manual, cash_price_b_manual, cash_price_c_manual, updated_by_company_id
                )
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([$pid, $price_month, $pa, $pb, $pc, $purchase, $cost, $manual_a, $manual_b, $manual_c, $current_user['id']]);
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
    error_log('price/save: ' . $e->getMessage());
    $_SESSION['error_message'] = '저장 중 오류가 발생했습니다.';
}

header("Location: $back"); exit;
?>