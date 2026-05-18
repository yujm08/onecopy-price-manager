<?php
// api/create_snapshot.php

$is_cli     = (php_sapi_name() === 'cli');
$is_cron_url = !$is_cli && isset($_GET['key']);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/db.php';

if ($is_cli) {
    // CLI: 인증 없음

} elseif ($is_cron_url) {
    // URL 크론: 시크릿 키 인증
    if (!hash_equals(CRON_SECRET_KEY, $_GET['key'])) {
        http_response_code(403);
        exit('Unauthorized');
    }

} else {
    // 관리자 웹 버튼: 세션 인증
    require_once __DIR__ . '/../includes/auth.php';
    require_admin();
}

/**
 * 월별 가격 스냅샷 생성
 */
function create_monthly_snapshot($pdo, $target_month = null) {
    try {
        if (!$target_month) {
            $target_month = date('Y-m-01');
        } else {
            $target_month = date('Y-m-01', strtotime($target_month));
        }

        $prev_month = date('Y-m-01', strtotime($target_month . ' -1 month'));

        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM prices WHERE price_month = ?
            AND (cash_price_a IS NOT NULL OR cash_price_b IS NOT NULL OR cash_price_c IS NOT NULL)
        ");
        $stmt->execute([$prev_month]);
        if ($stmt->fetchColumn() == 0) {
            $message = "[" . date('Y년 m월', strtotime($prev_month)) . "] 전월 데이터가 없습니다.";
            return ['success' => false, 'message' => $message];
        }

        $stmt = $pdo->prepare("
            INSERT INTO prices (product_id, price_month, cash_price_a, cash_price_b, cash_price_c, cost_price, updated_by_company_id)
            SELECT p.product_id, ? AS price_month,
                p.cash_price_a, p.cash_price_b, p.cash_price_c, p.cost_price, NULL
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

        $stmt = $pdo->prepare("
            UPDATE prices t
            JOIN prices p ON t.product_id = p.product_id AND p.price_month = ?
            SET
                t.cash_price_a = COALESCE(t.cash_price_a, p.cash_price_a),
                t.cash_price_b = COALESCE(t.cash_price_b, p.cash_price_b),
                t.cash_price_c = COALESCE(t.cash_price_c, p.cash_price_c),
                t.cost_price   = COALESCE(t.cost_price,   p.cost_price)
            WHERE t.price_month = ?
            AND (t.cash_price_a IS NULL OR t.cash_price_b IS NULL OR t.cash_price_c IS NULL OR t.cost_price IS NULL)
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

        return ['success' => true, 'message' => $message];

    } catch (PDOException $e) {
        error_log('[create_snapshot] ' . $e->getMessage());
        return ['success' => false, 'message' => "스냅샷 생성 중 오류가 발생했습니다: " . $e->getMessage()];
    }
}

/**
 * 로그 기록
 *
 * $source:
 *   'cron' → 성공/실패 모두 기록, 매번 덮어쓰기 (최근 1건만 유지)
 *   'web'  → 실패만 기록, 누적 append
 */
function log_snapshot($message, $level = 'INFO', $source = 'web') {
    // 웹 버튼 성공은 로그 안 남김
    if ($source === 'web' && $level === 'INFO') {
        return;
    }

    $log_file = __DIR__ . '/../logs/snapshot.log';
    if (!is_dir(dirname($log_file))) {
        mkdir(dirname($log_file), 0755, true);
    }

    $timestamp = date('Y-m-d H:i:s');
    $entry     = "[$timestamp] [$source] [$level] $message" . PHP_EOL;

    // 크론은 덮어쓰기, 웹 실패는 누적
    $flag = ($source === 'cron') ? 0 : FILE_APPEND;
    file_put_contents($log_file, $entry, $flag);

    if (php_sapi_name() === 'cli') {
        echo $entry;
    }
}

// ── 실행부 ───────────────────────────────────────────

if ($is_cli || $is_cron_url) {
    $target_month = date('Y-m');
    $result = create_monthly_snapshot($pdo, $target_month);

    $level = $result['success'] ? 'INFO' : 'ERROR';
    log_snapshot($result['message'], $level, 'cron');

    if ($is_cron_url) {
        http_response_code($result['success'] ? 200 : 500);
        header('Content-Type: text/plain; charset=utf-8');
        echo $result['message'];
    } else {
        exit($result['success'] ? 0 : 1);
    }

} else {
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

    // 웹: 실패만 로그
    if (!$result['success']) {
        log_snapshot($result['message'], 'ERROR', 'web');
        $_SESSION['error_message'] = $result['message'];
    } else {
        $_SESSION['success_message'] = $result['message'];
        // 성공 로그 없음 (의도적)
    }

    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? BASE_URL . '/admin/price_manage.php'));
    exit;
}
?>