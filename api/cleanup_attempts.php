<?php

$is_cli      = (php_sapi_name() === 'cli');
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
    // 웹 직접 접근 차단
    http_response_code(403);
    exit('Forbidden');
}

// 30분 지난 로그인 실패 기록 전체 삭제
$deleted = $pdo->exec(
    "DELETE FROM login_attempts 
    WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)"
);

$message = "login_attempts 정리 완료: {$deleted}건 삭제";

if ($is_cron_url) {
    http_response_code(200);
    header('Content-Type: text/plain; charset=utf-8');
    echo $message;
} else {
    echo $message . PHP_EOL; // CLI
}