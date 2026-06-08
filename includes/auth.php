<?php
// includes/auth.php

require_once __DIR__ . '/../config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name('OCPM_SESSION');
    session_start();
}

function login($company_name, $password_input) {
    global $pdo;

    try {
        // 변경 - 동명 계정 전체 조회 후 비밀번호 매칭
        $stmt = $pdo->prepare("
            SELECT id, company_name, phone, address, grade, password, role
            FROM companies WHERE company_name = ?
        ");
        $stmt->execute([$company_name]);
        $users = $stmt->fetchAll();

        if (empty($users)) {
            return ['success' => false, 'message' => '업체명 또는 비밀번호가 일치하지 않습니다.'];
        }

        $user = null;
        foreach ($users as $u) {
            if (password_verify($password_input, $u['password'])) {
                $user = $u;
                break;
            }
        }

        if (!$user) {
            return ['success' => false, 'message' => '업체명 또는 비밀번호가 일치하지 않습니다.'];
        }

        session_regenerate_id(true);

        $_SESSION['user_id']      = $user['id'];
        $_SESSION['company_name'] = $user['company_name'];
        $_SESSION['role']         = $user['role'];  // 'superadmin' | 'admin' | 'user'
        $_SESSION['grade']        = $user['grade']; // A / B / C / null
        $_SESSION['phone']        = $user['phone'];
        $_SESSION['address']      = $user['address'];
        $_SESSION['login_time']   = date('Y-m-d H:i');

        return [
            'success'  => true,
            'message'  => '로그인 성공',
            'is_admin' => in_array($user['role'], ['superadmin', 'admin']) // login.php 리다이렉트 호환
        ];

    } catch (PDOException $e) {
        return ['success' => false, 'message' => '로그인 처리 중 오류가 발생했습니다.'];
    }
}

function logout() {
    $_SESSION = [];
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    session_destroy();
}

function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/** superadmin + admin 모두 허용 — 관리자 화면 접근용 */
function is_admin(): bool {
    return in_array($_SESSION['role'] ?? '', ['superadmin', 'admin']);
}

/** superadmin만 허용 — 쓰기 작업용 */
function is_superadmin(): bool {
    return ($_SESSION['role'] ?? '') === 'superadmin';
}

function require_login() {
    if (!is_logged_in()) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

/** 관리자 화면 접근 (superadmin + admin 허용) */
function require_admin() {
    require_login();
    if (!is_admin()) {
        die('접근 권한이 없습니다.');
    }
}

/** 쓰기 작업 전용 (superadmin만 허용) */
function require_superadmin() {
    require_login();
    if (!is_superadmin()) {
        http_response_code(403);
        die('슈퍼 관리자만 수행할 수 있는 작업입니다.');
    }
}

function get_current_login_user() {
    if (!is_logged_in()) return null;
    return [
        'id'           => $_SESSION['user_id'],
        'company_name' => $_SESSION['company_name'],
        'role'         => $_SESSION['role'],
        'grade'        => $_SESSION['grade'],
        'phone'        => $_SESSION['phone'],
        'address'      => $_SESSION['address']
    ];
}

function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    if (empty($_SESSION['csrf_token']) || empty($token)) return false;
    return hash_equals($_SESSION['csrf_token'], $token);
}

function get_block_status(PDO $pdo, string $ip): array {
    $window = date('Y-m-d H:i:s', strtotime('-30 minutes'));
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) as cnt, MAX(attempted_at) as last_attempt
        FROM login_attempts 
        WHERE ip = ? AND attempted_at > ?"
    );
    $stmt->execute([$ip, $window]);
    $row = $stmt->fetch();

    if ((int)$row['cnt'] < 10) {
        return ['blocked' => false];
    }
    
    $last = strtotime($row['last_attempt']);
    $remaining = ($last + 1800) - time(); // 1800초 = 30분
    $remaining = max(0, $remaining);

    $min = (int)floor($remaining / 60);
    $sec = $remaining % 60;

    return [
        'blocked'   => true,
        'remaining' => $remaining,
        'label'     => $min > 0 ? "{$min}분 {$sec}초" : "{$sec}초",
    ];
}

function record_login_fail(PDO $pdo, string $ip): void {
    $stmt = $pdo->prepare(
        "INSERT INTO login_attempts (ip, attempted_at) VALUES (?, NOW())"
    );
    $stmt->execute([$ip]);
}

function cleanup_login_attempts(PDO $pdo): void {
    if (rand(1, 100) === 1) {
        $pdo->exec(
            "DELETE FROM login_attempts 
            WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)"
        );
    }
}

?>