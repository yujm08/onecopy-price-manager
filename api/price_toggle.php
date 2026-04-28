<?php
// api/price_toggle.php
// 제품 활성/비활성 토글 처리 (관리자 전용)

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

// 관리자 권한 확인
require_admin();

// JSON 응답 헤더
header('Content-Type: application/json');

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    echo json_encode(['success' => false, 'message' => '보안 토큰이 유효하지 않습니다.']);
    exit;
}

// POST 데이터 검증
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '잘못된 요청입니다.']);
    exit;
}

$product_id = $_POST['product_id'] ?? '';
$is_active = $_POST['is_active'] ?? '';

if (empty($product_id) || $is_active === '') {
    echo json_encode(['success' => false, 'message' => '필수 데이터가 누락되었습니다.']);
    exit;
}

// is_active는 0 또는 1만 가능
$is_active = (int)$is_active;
if ($is_active !== 0 && $is_active !== 1) {
    echo json_encode(['success' => false, 'message' => '유효하지 않은 값입니다.']);
    exit;
}

try {
    // 제품 활성/비활성 상태 변경
    $stmt = $pdo->prepare("UPDATE products SET is_active = ? WHERE id = ?");
    $result = $stmt->execute([$is_active, $product_id]);
    
    if ($result) {
        $status_text = $is_active ? '활성화' : '비활성화';
        echo json_encode([
            'success' => true, 
            'message' => "제품이 {$status_text}되었습니다.",
            'is_active' => $is_active
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => '처리 실패']);
    }
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false, 
        'message' => '오류가 발생했습니다: ' . $e->getMessage()
    ]);
}
?>