<?php
// api/create_snapshot.php
// 스냅샷 생성 API + 함수 정의 (cron 및 웹 호출 모두 처리)

// CLI 실행이면 DB/설정만 로드, 웹 요청이면 인증까지
if (php_sapi_name() === 'cli') {
    require_once __DIR__ . '/../config/db.php';
    require_once __DIR__ . '/../config/config.php';
} else {
    require_once __DIR__ . '/../config/db.php';
    require_once __DIR__ . '/../includes/auth.php';
    require_admin();
}

/**
 * 월별 가격 스냅샷 생성
 * 전월의 모든 제품 가격을 대상 월로 복사
 */
function create_monthly_snapshot($pdo, $target_month = null) {
    try {
        if (!$target_month) {
            $target_month = date('Y-m-01');
        } else {
            $target_month = date('Y-m-01', strtotime($target_month));
        }

        $prev_month = date('Y-m-01', strtotime($target_month . ' -1 month'));

        // 전월 실데이터 확인
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM prices WHERE price_month = ?
            AND (cash_price_a IS NOT NULL OR cash_price_b IS NOT NULL OR cash_price_c IS NOT NULL)");
        $stmt->execute([$prev_month]);
        if ($stmt->fetchColumn() === 0) {
            $message = "[" . date('Y년 m월', strtotime($prev_month)) . "] 전월 데이터가 없습니다.";
            log_snapshot_message($message);
            return ['success' => false, 'message' => $message];
        }

        // Step 1: 대상 월에 행 자체가 없는 제품 → 전월 행 전체 INSERT
        $stmt = $pdo->prepare("
            INSERT INTO prices (product_id, price_month, cash_price_a, cash_price_b, cash_price_c, updated_by_company_id)
            SELECT p.product_id, ? as price_month, p.cash_price_a, p.cash_price_b, p.cash_price_c, NULL
            FROM prices p
            WHERE p.price_month = ?
              AND (p.cash_price_a IS NOT NULL OR p.cash_price_b IS NOT NULL OR p.cash_price_c IS NOT NULL)
              AND NOT EXISTS (
                  SELECT 1 FROM prices t
                  WHERE t.product_id = p.product_id AND t.price_month = ?
              )
        ");
        $stmt->execute([$target_month, $prev_month, $target_month]);
        $inserted = $stmt->rowCount();

        // Step 2: 대상 월에 행은 있지만 NULL 컬럼이 있는 제품 → NULL 컬럼만 전월 값으로 채우기
        $stmt = $pdo->prepare("
            UPDATE prices t
            JOIN prices p ON t.product_id = p.product_id AND p.price_month = ?
            SET
                t.cash_price_a = COALESCE(t.cash_price_a, p.cash_price_a),
                t.cash_price_b = COALESCE(t.cash_price_b, p.cash_price_b),
                t.cash_price_c = COALESCE(t.cash_price_c, p.cash_price_c)
            WHERE t.price_month = ?
              AND (t.cash_price_a IS NULL OR t.cash_price_b IS NULL OR t.cash_price_c IS NULL)
        ");
        $stmt->execute([$prev_month, $target_month]);
        $updated = $stmt->rowCount();

        $target_display = date('Y년 m월', strtotime($target_month));
        $prev_display   = date('Y년 m월', strtotime($prev_month));

        $parts = [];
        if ($inserted > 0) $parts[] = "신규 {$inserted}개 제품 복사됨";
        if ($updated  > 0) $parts[] = "부분 입력 {$updated}개 제품 보완됨";

        if (empty($parts)) {
            $message = "[{$target_display}] 모든 제품 가격이 이미 완전히 입력되어 있습니다.";
        } else {
            $message = "[{$target_display}] 스냅샷 완료 ({$prev_display} 기준). " . implode(', ', $parts) . '.';
        }

        log_snapshot_message($message);
        return ['success' => true, 'message' => $message, 'count' => $inserted + $updated];

    } catch (PDOException $e) {
        $message = "스냅샷 생성 중 오류가 발생했습니다. 잠시 후 다시 시도해주세요.";
        error_log('[create_snapshot] ' . $e->getMessage());
        log_snapshot_message("스냅샷 생성 오류 (상세는 php error log 참조)", 'ERROR');
        return ['success' => false, 'message' => $message];
    }
}

/**
 * 로그 기록
 */
function log_snapshot_message($message, $level = 'INFO') {
    $log_file = __DIR__ . '/../logs/snapshot.log';
    $log_dir  = dirname($log_file);

    if (!is_dir($log_dir)) {
        mkdir($log_dir, 0755, true);
    }

    $timestamp = date('Y-m-d H:i:s');
    $log_entry = "[$timestamp] [$level] $message" . PHP_EOL;
    file_put_contents($log_file, $log_entry, FILE_APPEND);

    if (php_sapi_name() === 'cli') {
        echo $log_entry;
    }
}


// 실행부
if (php_sapi_name() === 'cli') {
    // cron 실행
    $result = create_monthly_snapshot($pdo);
    exit($result['success'] ? 0 : 1);

} else {
    // 웹 POST 요청
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $_SESSION['error_message'] = '잘못된 요청입니다.';
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? BASE_URL . '/admin/price_manage.php'));
        exit;
    }

    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $_SESSION['error_message'] = '보안 토큰이 유효하지 않습니다. 페이지를 새로고침 후 다시 시도해주세요.';
        header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? BASE_URL . '/admin/price_manage.php'));
        exit;
    }

    $target_month = $_POST['target_month'] ?? null;
    $result = create_monthly_snapshot($pdo, $target_month);

    if ($result['success']) {
        $_SESSION[] = $result['message'];
    } else {
        $_SESSION['error_message'] = $result['message'];
    }

    $redirect_url = $_SERVER['HTTP_REFERER'] ?? BASE_URL . '/admin/price_manage.php';
    header('Location: ' . $redirect_url);
    exit;
}
?>