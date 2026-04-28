<?php
require_once 'config/db.php';
require_once 'includes/auth.php';
require_once __DIR__ . '/config/config.php';

if (is_logged_in()) {
    header('Location: ' . BASE_URL . '/admin/price_manage.php');
    exit;
}

$error_message = '';

if (($_GET['reason'] ?? '') === 'timeout') {
    $error_message = '장시간 미사용으로 자동 로그아웃되었습니다.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company_name   = trim($_POST['company_name'] ?? '');
    $password_input = trim($_POST['password'] ?? '');

    if (!check_login_attempts()) {
        $error_message = '로그인 시도가 너무 많습니다. 30분 후 다시 시도해주세요.';
    } elseif (empty($company_name) || empty($password_input)) {
        $error_message = '업체명과 비밀번호를 모두 입력해주세요.';
    } else {
        $result = login($company_name, $password_input);
        if ($result['success']) {
            header('Location: ' . BASE_URL . '/admin/price_manage.php');
            exit;
        } else {
            $error_message = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>로그인 - 가격관리 시스템</title>
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <h1>가격관리 시스템</h1>
            <p class="subtitle">로그인</p>

            <?php if ($error_message): ?>
                <div class="error-message"><?php echo h($error_message); ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="company_name">업체명</label>
                    <input type="text" id="company_name" name="company_name"
                            value="<?php echo h($_POST['company_name'] ?? ''); ?>"
                            required autofocus>
                </div>
                <div class="form-group">
                    <label for="password">비밀번호</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <button type="submit" class="btn-login">로그인</button>
            </form>

            <div class="login-help">
                <p>로그인 정보를 잊으셨나요?</p>
                <p>(010-8291-6362)로 문의해주세요.</p>
            </div>
        </div>
    </div>
</body>
</html>