<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../config/config.php';

require_superadmin();

header('Content-Type: application/json');

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => '보안 토큰이 유효하지 않습니다.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '잘못된 요청입니다.']);
    exit;
}

// changes[i][allocation_id], changes[i][issued_qty]
$changes = $_POST['changes'] ?? [];
if (empty($changes) || !is_array($changes)) {
    echo json_encode(['success' => false, 'message' => '변경할 내용이 없습니다.']);
    exit;
}

$results = [];

foreach ($changes as $change) {
    $allocation_id = (int)($change['allocation_id'] ?? 0);
    $new_qty       = (int)($change['issued_qty'] ?? -1);

    if (!$allocation_id || $new_qty < 0) {
        $results[] = ['allocation_id' => $allocation_id, 'success' => false, 'message' => '잘못된 요청입니다.'];
        continue;
    }

    try {
        $stmt = $pdo->prepare("SELECT used_qty FROM coupon_allocations WHERE id = ?");
        $stmt->execute([$allocation_id]);
        $row = $stmt->fetch();

        if (!$row) {
            $results[] = ['allocation_id' => $allocation_id, 'success' => false, 'message' => '존재하지 않는 항목입니다.'];
            continue;
        }

        $used_qty = (int)$row['used_qty'];

        if ($new_qty < $used_qty) {
            $results[] = ['allocation_id' => $allocation_id, 'success' => false,
                           'message' => "이미 {$used_qty}개 사용되어 그보다 적게 설정할 수 없습니다."];
            continue;
        }

        if ($new_qty === 0 && $used_qty === 0) {
            // 사용 이력이 없고 0개면 할당 자체를 제거
            $pdo->prepare("DELETE FROM coupon_allocations WHERE id = ?")->execute([$allocation_id]);
            $results[] = ['allocation_id' => $allocation_id, 'success' => true, 'deleted' => true];
        } else {
            $pdo->prepare("UPDATE coupon_allocations SET issued_qty = ? WHERE id = ?")
                ->execute([$new_qty, $allocation_id]);
            $results[] = ['allocation_id' => $allocation_id, 'success' => true, 'deleted' => false];
        }

    } catch (PDOException $e) {
        error_log('coupon/batch_update error: ' . $e->getMessage());
        $results[] = ['allocation_id' => $allocation_id, 'success' => false, 'message' => '처리 중 오류가 발생했습니다.'];
    }
}

$all_success = !in_array(false, array_column($results, 'success'), true);

echo json_encode(['success' => $all_success, 'results' => $results]);