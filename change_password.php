<?php
$page_title = '비밀번호 변경';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/config/config.php';

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = '보안 토큰이 유효하지 않습니다. 페이지를 새로고침 후 다시 시도해주세요.';
    } else {
        $current_pw  = $_POST['current_password']  ?? '';
        $new_pw      = $_POST['new_password']       ?? '';
        $confirm_pw  = $_POST['confirm_password']   ?? '';

        if (empty($current_pw) || empty($new_pw) || empty($confirm_pw)) {
            $error = '모든 항목을 입력해주세요.';
        } elseif ($new_pw !== $confirm_pw) {
            $error = '새 비밀번호가 일치하지 않습니다.';
        } elseif (mb_strlen($new_pw) < 4) {
            $error = '비밀번호는 4자 이상이어야 합니다.';
        } elseif (mb_strlen($new_pw) > 100) {
            $error = '비밀번호는 100자 이하여야 합니다.';
        } else {
            $stmt = $pdo->prepare("SELECT password FROM companies WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $row = $stmt->fetch();

            if (!$row || !password_verify($current_pw, $row['password'])) {
                $error = '현재 비밀번호가 올바르지 않습니다.';
            } else {
                $pdo->prepare("UPDATE companies SET password = ? WHERE id = ?")
                    ->execute([password_hash($new_pw, PASSWORD_DEFAULT), $_SESSION['user_id']]);
                session_regenerate_id(true);
                $_SESSION['success_message'] = '비밀번호가 성공적으로 변경되었습니다.';
                header('Location: ' . BASE_URL . '/admin/price_manage.php');
                exit;
            }
        }
    }
}
?>

<style>
.pw-container {
    max-width: 460px;
    margin: 60px auto;
}
.pw-box {
    background: white;
    border-radius: 10px;
    padding: 36px 40px;
    box-shadow: 0 4px 16px rgba(0,0,0,0.10);
}
.pw-box h2 {
    font-size: 20px;
    color: #2c3e50;
    margin-bottom: 28px;
    font-weight: 600;
}
.form-group {
    margin-bottom: 18px;
}
.form-group label {
    display: block;
    font-size: 14px;
    font-weight: 500;
    color: #444;
    margin-bottom: 6px;
}
.form-group input {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 14px;
    box-sizing: border-box;
    transition: border-color 0.2s;
}
.form-group input:focus {
    outline: none;
    border-color: #5A6778;
}
.btn-submit {
    width: 100%;
    padding: 12px;
    background: #5A6778;
    color: white;
    border: none;
    border-radius: 4px;
    font-size: 15px;
    font-weight: 500;
    cursor: pointer;
    margin-top: 8px;
}
.btn-submit:hover { background: #4B5563; }
.msg-error {
    background: #f8d7da;
    color: #721c24;
    padding: 12px 14px;
    border-radius: 4px;
    border-left: 4px solid #dc3545;
    font-size: 14px;
    margin-bottom: 20px;
}
.msg-success {
    background: #d4edda;
    color: #155724;
    padding: 12px 14px;
    border-radius: 4px;
    border-left: 4px solid #28a745;
    font-size: 14px;
    margin-bottom: 20px;
}
.hint {
    font-size: 12px;
    color: #888;
    margin-top: 4px;
}
</style>

<div class="pw-container">
    <div class="pw-box">
        <h2>비밀번호 변경</h2>

        <?php if ($error): ?>
            <div class="msg-error"><?php echo h($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="msg-success"><?php echo h($success); ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

            <div class="form-group">
                <label>현재 비밀번호</label>
                <input type="password" name="current_password" required autofocus>
            </div>
            <div class="form-group">
                <label>새 비밀번호</label>
                <input type="password" name="new_password" required>
                <div class="hint">형식 제한 없음, 4자 이상</div>
            </div>
            <div class="form-group">
                <label>새 비밀번호 확인</label>
                <input type="password" name="confirm_password" required>
            </div>

            <button type="submit" class="btn-submit">변경하기</button>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>