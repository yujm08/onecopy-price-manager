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

$categories     = $_POST['categories'] ?? [];
$new_categories = $_POST['new_categories'] ?? [];

try {
    $pdo->beginTransaction();

    // 기존 카테고리 수정/삭제
    foreach ($categories as $id => $data) {
        $id     = (int)$id;
        $delete = ($data['delete'] ?? '0') === '1';
        $name   = trim($data['name'] ?? '');

        if ($delete) {
            // products는 FK ON DELETE CASCADE라 함께 삭제됨
            $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
            $stmt->execute([$id]);
            continue;
        }

        if ($name === '') continue; // 이름 비우고 삭제 체크 안 했으면 무시

        $stmt = $pdo->prepare("UPDATE categories SET category_name = ? WHERE id = ?");
        $stmt->execute([$name, $id]);
    }

    // 새 카테고리 추가 (중복 이름은 건너뜀)
    $stmt_check  = $pdo->prepare("SELECT COUNT(*) FROM categories WHERE category_name = ?");
    $stmt_insert = $pdo->prepare("INSERT INTO categories (category_name) VALUES (?)");

    foreach ($new_categories as $name) {
        $name = trim($name);
        if ($name === '') continue;

        $stmt_check->execute([$name]);
        if ($stmt_check->fetchColumn() > 0) continue; // 이미 있는 이름이면 건너뜀

        $stmt_insert->execute([$name]);
    }

    $pdo->commit();
    $_SESSION['success_message'] = '카테고리 정보가 저장되었습니다.';
} catch (Exception $e) {
    $pdo->rollBack();
    $_SESSION['error_message'] = '저장 중 오류가 발생했습니다.';
}

// 삭제된 카테고리가 현재 선택된 카테고리였을 수도 있으니 category 파라미터 없이 리다이렉트
header('Location: ' . BASE_URL . '/admin/price_manage.php');
exit;