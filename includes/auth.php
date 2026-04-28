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
        $stmt = $pdo->prepare("
            SELECT id, company_name, phone, address, grade, password, is_admin
            FROM companies
            WHERE company_name = ?
        ");
        $stmt->execute([$company_name]);
        $user = $stmt->fetch();

        if (!$user) {
            record_login_failure();
            return ['success' => false, 'message' => '업체명 또는 비밀번호가 일치하지 않습니다.'];
        }

        if (!password_verify($password_input, $user['password'])) {
            record_login_failure();
            return ['success' => false, 'message' => '업체명 또는 비밀번호가 일치하지 않습니다.'];
        }

        // 로그인 성공
        session_regenerate_id(true);
        reset_login_attempts();

        $_SESSION['user_id']      = $user['id'];
        $_SESSION['company_name'] = $user['company_name'];
        $_SESSION['is_admin']     = $user['is_admin'];
        $_SESSION['grade']        = $user['grade'];   // A / B / C / null(관리자)
        $_SESSION['phone']        = $user['phone'];
        $_SESSION['address']      = $user['address'];
        $_SESSION['login_time']   = date('Y-m-d H:i');

        return [
            'success'  => true,
            'message'  => '로그인 성공',
            'is_admin' => $user['is_admin']
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

function is_admin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
}

function require_login() {
    if (!is_logged_in()) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

function require_admin() {
    require_login();
    if (!is_admin()) {
        die('관리자만 접근 가능합니다.');
    }
}

function get_current_login_user() {
    if (!is_logged_in()) return null;
    return [
        'id'           => $_SESSION['user_id'],
        'company_name' => $_SESSION['company_name'],
        'is_admin'     => $_SESSION['is_admin'],
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

function check_login_attempts() {
    $count = $_SESSION['login_fail_count'] ?? 0;
    $last  = $_SESSION['login_fail_time']  ?? 0;
    if (time() - $last > 1800) {
        unset($_SESSION['login_fail_count'], $_SESSION['login_fail_time']);
        return true;
    }
    return $count < 5;
}

function record_login_failure() {
    $_SESSION['login_fail_count'] = ($_SESSION['login_fail_count'] ?? 0) + 1;
    $_SESSION['login_fail_time']  = time();
}

function reset_login_attempts() {
    unset($_SESSION['login_fail_count'], $_SESSION['login_fail_time']);
}
?>