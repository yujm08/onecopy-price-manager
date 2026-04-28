<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
    $_SESSION['error_message'] = '보안 토큰이 유효하지 않습니다.';
    header('Location: ' . BASE_URL . '/admin/company_manage.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . BASE_URL . '/admin/company_manage.php');
    exit;
}

$action     = $_POST['action']     ?? '';
$company_id = (int)($_POST['company_id'] ?? 0);

$redirect_page    = (int)($_POST['redirect_page']    ?? 1);
$redirect_field   = $_POST['redirect_field']   ?? 'company_name';
$redirect_keyword = $_POST['redirect_keyword'] ?? '';

$redirect_params = ['page' => $redirect_page];
if (!empty($redirect_keyword)) {
    $redirect_params['search_field']   = $redirect_field;
    $redirect_params['search_keyword'] = $redirect_keyword;
}
$back_url = BASE_URL . '/admin/company_manage.php?' . http_build_query($redirect_params);

function extract_phone_last4(string $phone): string {
    $digits = preg_replace('/\D/', '', $phone);
    return mb_strlen($digits) >= 4 ? mb_substr($digits, -4) : $digits;
}

// 전화번호 정규화
function format_phone(string $phone): string {
    $d = preg_replace('/\D/', '', $phone); // 숫자만 추출

    if (substr($d, 0, 2) === '02') {
        // 서울 (02)
        // 9자리: 02-XXX-XXXX, 10자리: 02-XXXX-XXXX
        if (strlen($d) === 9)  return substr($d,0,2).'-'.substr($d,2,3).'-'.substr($d,5);
        if (strlen($d) === 10) return substr($d,0,2).'-'.substr($d,2,4).'-'.substr($d,6);
    } else {
        // 010, 011, 031 등 3자리 지역번호/통신사
        // 10자리: 0XX-XXX-XXXX, 11자리: 0XX-XXXX-XXXX
        if (strlen($d) === 10) return substr($d,0,3).'-'.substr($d,3,3).'-'.substr($d,6);
        if (strlen($d) === 11) return substr($d,0,3).'-'.substr($d,3,4).'-'.substr($d,7);
    }

    // 위 케이스 외엔 숫자만 반환 (비정상 입력)
    return $d;
}

switch ($action) {

    case 'add':
        $company_name  = trim($_POST['company_name']        ?? '');
        $rep_name      = trim($_POST['representative_name'] ?? '');
        $mgr_name      = trim($_POST['manager_name']        ?? '');
        $phone         = trim($_POST['phone']               ?? '');
        $address       = trim($_POST['address']             ?? '');
        $is_admin      = ($_POST['is_admin'] ?? '0') === '1' ? 1 : 0;
        $grade         = $_POST['grade'] ?? '';

        // 필수값 검증
        if (empty($company_name) || empty($rep_name) || empty($mgr_name) || empty($phone)) {
            $_SESSION['error_message'] = '업체명, 대표자명, 담당자명, 연락처는 필수 입력 항목입니다.';
            header('Location: ' . $back_url); exit;
        }
        if (!$is_admin && !in_array($grade, ['A', 'B', 'C'])) {
            $_SESSION['error_message'] = '등급을 선택해주세요.';
            header('Location: ' . $back_url); exit;
        }

        // 초기 비밀번호 = 전화번호 뒤 4자리
        $phone = format_phone($phone);
        $last4    = extract_phone_last4($phone);
        $hashed_pw = password_hash($last4, PASSWORD_DEFAULT);

        try {
            $pdo->prepare("
                INSERT INTO companies
                    (company_name, representative_name, manager_name, phone, address, grade, password, is_admin)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ")->execute([
                $company_name, $rep_name, $mgr_name, $phone,
                $address ?: null,
                $is_admin ? null : $grade,
                $hashed_pw,
                $is_admin
            ]);
            $_SESSION['success_message'] = "\"$company_name\" 업체가 등록되었습니다. 초기 비밀번호: $last4";
        } catch (PDOException $e) {
            $_SESSION['error_message'] = '등록 중 오류가 발생했습니다.';
        }
        header('Location: ' . $back_url); exit;

    case 'edit':
        if ($company_id <= 0) {
            $_SESSION['error_message'] = '잘못된 요청입니다.';
            header('Location: ' . $back_url); exit;
        }

        $company_name = trim($_POST['company_name']        ?? '');
        $rep_name     = trim($_POST['representative_name'] ?? '');
        $mgr_name     = trim($_POST['manager_name']        ?? '');
        $phone        = trim($_POST['phone']               ?? '');
        $phone = format_phone($phone);
        $address      = trim($_POST['address']             ?? '');
        $is_admin     = ($_POST['is_admin'] ?? '0') === '1' ? 1 : 0;
        $grade        = $_POST['grade'] ?? '';

        if (empty($company_name) || empty($rep_name) || empty($mgr_name) || empty($phone)) {
            $_SESSION['error_message'] = '업체명, 대표자명, 담당자명, 연락처는 필수 입력 항목입니다.';
            header('Location: ' . $back_url); exit;
        }
        if (!$is_admin && !in_array($grade, ['A', 'B', 'C'])) {
            $_SESSION['error_message'] = '등급을 선택해주세요.';
            header('Location: ' . $back_url); exit;
        }

        try {
            $pdo->prepare("
                UPDATE companies
                SET company_name = ?, representative_name = ?, manager_name = ?,
                    phone = ?, address = ?, grade = ?
                WHERE id = ?
            ")->execute([
                $company_name, $rep_name, $mgr_name, $phone,
                $address ?: null,
                $is_admin ? null : $grade,
                $company_id
            ]);
            $_SESSION['success_message'] = "\"$company_name\" 업체 정보가 수정되었습니다.";
        } catch (PDOException $e) {
            $_SESSION['error_message'] = '수정 중 오류가 발생했습니다.';
        }
        header('Location: ' . $back_url); exit;

    case 'delete':
        if ($company_id <= 0) {
            $_SESSION['error_message'] = '잘못된 요청입니다.';
            header('Location: ' . $back_url); exit;
        }

        $stmt = $pdo->prepare("SELECT company_name, is_admin FROM companies WHERE id = ?");
        $stmt->execute([$company_id]);
        $target = $stmt->fetch();

        if (!$target) {
            $_SESSION['error_message'] = '존재하지 않는 업체입니다.';
            header('Location: ' . $back_url); exit;
        }
        if ($target['is_admin']) {
            $_SESSION['error_message'] = '관리자 계정은 삭제할 수 없습니다.';
            header('Location: ' . $back_url); exit;
        }

        try {
            $pdo->prepare("DELETE FROM companies WHERE id = ?")->execute([$company_id]);
            $_SESSION['success_message'] = "\"" . $target['company_name'] . "\" 업체가 삭제되었습니다.";
        } catch (PDOException $e) {
            $_SESSION['error_message'] = '삭제 중 오류가 발생했습니다.';
        }
        header('Location: ' . $back_url); exit;

    default:
        $_SESSION['error_message'] = '알 수 없는 요청입니다.';
        header('Location: ' . $back_url); exit;
}
?>