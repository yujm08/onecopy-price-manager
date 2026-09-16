<?php

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../config/config.php';

require_superadmin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die('잘못된 요청입니다.');
}

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $_SESSION['error_message'] = '유효하지 않은 요청입니다.';
    header('Location: ' . BASE_URL . '/admin/price_manage.php');
    exit;
}

$category_id = (int)($_POST['category_id'] ?? 0);
$base_grade  = $_POST['base_grade'] ?? '';
$price_month = $_POST['price_month'] ?? date('Y-m-01');
$rules_input = $_POST['rules'] ?? []; // ['B' => ['calc_type'=>..,'calc_value'=>..], 'C' => [...]]

if (!$category_id || !in_array($base_grade, ['A', 'B', 'C'], true)) {
    $_SESSION['error_message'] = '잘못된 요청입니다.';
    header('Location: ' . BASE_URL . '/admin/price_manage.php?category=' . $category_id);
    exit;
}

function calc_formula_price($base_price, $calc_type, $calc_value) {
    switch ($calc_type) {
        case 'percent':         return (int) round($base_price * (1 + $calc_value / 100));
        case 'amount_add':      return (int) round($base_price + $calc_value);
        case 'amount_subtract': return (int) round($base_price - $calc_value);
        default: return null;
    }
}

$current_user = get_current_login_user();
$other_grades = array_values(array_diff(['A', 'B', 'C'], [$base_grade]));

try {
    $pdo->beginTransaction();

    // 1. 수식 upsert (category_id UNIQUE 제약 활용)
    $stmt = $pdo->prepare("
        INSERT INTO category_price_formulas (category_id, base_grade, updated_by_company_id)
        VALUES (?, ?, ?)
        ON DUPLICATE KEY UPDATE base_grade = VALUES(base_grade), updated_by_company_id = VALUES(updated_by_company_id)
    ");
    $stmt->execute([$category_id, $base_grade, $current_user['id']]);

    $fstmt = $pdo->prepare("SELECT id FROM category_price_formulas WHERE category_id = ?");
    $fstmt->execute([$category_id]);
    $formula_id = $fstmt->fetchColumn();

    // 2. 규칙 갱신 (기존 삭제 후 재삽입)
    $pdo->prepare("DELETE FROM category_price_formula_rules WHERE formula_id = ?")->execute([$formula_id]);

    $ins_rule = $pdo->prepare("
        INSERT INTO category_price_formula_rules (formula_id, target_grade, calc_type, calc_value)
        VALUES (?, ?, ?, ?)
    ");
    $rules_by_grade = [];
    foreach ($other_grades as $grade) {
        $calc_type  = $rules_input[$grade]['calc_type']  ?? 'percent';
        $calc_value = (float)($rules_input[$grade]['calc_value'] ?? 0);
        $ins_rule->execute([$formula_id, $grade, $calc_type, $calc_value]);
        $rules_by_grade[$grade] = ['calc_type' => $calc_type, 'calc_value' => $calc_value];
    }

    // 3. 이 카테고리 · 이 월의 전체 제품 재계산 (수동입력 포함, manual 플래그도 초기화)
    $base_col = 'cash_price_' . strtolower($base_grade);

    $prod_stmt = $pdo->prepare("
        SELECT p.id, pr.$base_col AS base_price
        FROM products p
        LEFT JOIN prices pr ON pr.product_id = p.id AND pr.price_month = ?
        WHERE p.category_id = ?
    ");
    $prod_stmt->execute([$price_month, $category_id]);
    $products = $prod_stmt->fetchAll();

    foreach ($products as $prod) {
        if ($prod['base_price'] === null) continue; // 기준등급 가격이 없으면 계산 불가

        foreach ($other_grades as $grade) {
            $rule = $rules_by_grade[$grade];
            $new_price  = calc_formula_price($prod['base_price'], $rule['calc_type'], $rule['calc_value']);
            $target_col = 'cash_price_' . strtolower($grade);
            $manual_col = $target_col . '_manual';

            $u = $pdo->prepare("
                UPDATE prices SET $target_col = ?, $manual_col = 0
                WHERE product_id = ? AND price_month = ?
            ");
            $u->execute([$new_price, $prod['id'], $price_month]);
        }
    }

    $pdo->commit();
    $_SESSION['success_message'] = '카테고리 수식이 저장되고 가격이 재계산되었습니다.';
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error_message'] = '저장 중 오류가 발생했습니다.';
}

header('Location: ' . BASE_URL . '/admin/price_manage.php?month=' . date('Y-m', strtotime($price_month)) . '&category=' . $category_id);
exit;