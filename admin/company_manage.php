<?php
$page_title = '업체 관리';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/config.php';

require_admin();

$search_field   = $_GET['search_field']   ?? 'company_name';
$search_keyword = trim($_GET['search_keyword'] ?? '');

$allowed_fields = [
    'company_name',
    'representative_name',
    'manager_name',
    'phone',
    'address',
    'grade'
];
if (!in_array($search_field, $allowed_fields)) $search_field = 'company_name';

$per_page     = 10;
$current_page = max(1, (int)($_GET['page'] ?? 1));
$offset       = ($current_page - 1) * $per_page;

$params = [];
$where  = "WHERE 1=1";
if (!empty($search_keyword)) {
    if ($search_field === 'grade') {
        $where   .= " AND grade = ?";
        $params[] = $search_keyword;
    } else {
        $where   .= " AND $search_field LIKE ?";
        $params[] = "%$search_keyword%";
    }
}

$total_count = $pdo->prepare("SELECT COUNT(*) FROM companies $where");
$total_count->execute($params);
$total_count = $total_count->fetchColumn();
$total_pages = (int)ceil($total_count / $per_page);

$stmt = $pdo->prepare("
    SELECT id, company_name, representative_name, manager_name, phone, address, grade, is_admin
    FROM companies $where
    ORDER BY is_admin DESC, company_name ASC
    LIMIT $per_page OFFSET $offset
");
$stmt->execute($params);
$companies = $stmt->fetchAll();
?>
<style>
.container { max-width: 1200px; margin: 0 auto; }
.search-section { background:white; padding:20px; border-radius:8px; margin-bottom:24px; box-shadow:0 2px 4px rgba(0,0,0,0.08); }
.search-form { display:flex; gap:10px; align-items:center; flex-wrap: wrap; }
.search-form select, .search-form input[type="text"] { padding:10px 14px; border:1px solid #ddd; border-radius:4px; font-size:14px; }
.search-form select { min-width:130px; }
.search-form input[type="text"] { flex:1; max-width:380px; }
.btn-search { padding:10px 22px; background:#5A6778; color:white; border:none; border-radius:4px; cursor:pointer; font-size:14px; }
.btn-search:hover { background:#4B5563; }
.btn-reset { padding:10px 16px; background:#95a5a6; color:white; text-decoration:none; border-radius:4px; font-size:14px; }
.table-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:12px; }
.total-count { font-size:14px; color:#555; }
.btn-add { padding:10px 20px; background:#5A6778; color:white; border:none; border-radius:4px; cursor:pointer; font-size:14px; font-weight:500; }
.btn-add:hover { background:#4B5563; }
.company-table-wrap { background:white; border-radius:8px; box-shadow:0 2px 4px rgba(0,0,0,0.08); overflow:hidden; margin-bottom:24px; overflow-x: auto; }
.company-table { width:100%; border-collapse:collapse; }
.company-table thead { background:#34495e; color:white; }
.company-table th { padding:14px 16px; text-align:left; font-weight:500; font-size:14px; }
.company-table td { padding:12px 16px; border-bottom:1px solid #eee; font-size:14px; }
.company-table tbody tr:last-child td { border-bottom:none; }
.company-table tbody tr:hover { background:#f8f9fa; }
.badge-admin { background:#e74c3c; color:white; padding:2px 7px; border-radius:3px; font-size:11px; margin-left:4px; display:inline-block; vertical-align:middle; white-space:nowrap; }
.badge-grade { padding:2px 8px; border-radius:3px; font-size:12px; font-weight:600; white-space:nowrap; }
@media (max-width: 768px) {
    .grade-full { display:none; }
}
.badge-a { background:#cce5ff; color:#004085; }
.badge-b { background:#d4edda; color:#155724; }
.badge-c { background:#fff3cd; color:#856404; }
.action-cell { white-space:nowrap; }
.company-table td:first-child { min-width:80px; }
.btn-edit-row { padding:6px 14px; background:#27ae60; color:white; border:none; border-radius:3px; cursor:pointer; font-size:13px; margin-right:6px; }
.btn-edit-row:hover { background:#229954; }
.btn-delete-row { padding:6px 14px; background:#e74c3c; color:white; border:none; border-radius:3px; cursor:pointer; font-size:13px; }
.btn-delete-row:hover { background:#c0392b; }
.btn-delete-row:disabled { background:#ccc; cursor:not-allowed; }
.pagination { display:flex; justify-content:center; gap:6px; margin-top:10px; }
.pagination a, .pagination span { padding:8px 14px; border:1px solid #ddd; border-radius:4px; font-size:14px; text-decoration:none; color:#333; background:white; }
.pagination a:hover { background:#f0f0f0; }
.pagination .active { background:#5A6778; color:white; border-color:#5A6778; }
.pagination .disabled { color:#bbb; pointer-events:none; }
.no-data { padding:60px 20px; text-align:center; color:#7f8c8d; }
.modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index:1000; justify-content:center; align-items:center; }
.modal-overlay.active { display:flex; }
.modal { background:white; border-radius:10px; padding:32px 36px; width:100%; max-width:520px; box-shadow:0 8px 32px rgba(0,0,0,0.18); position:relative; max-height:90vh; overflow-y:auto; }
.modal h2 { font-size:18px; margin-bottom:24px; color:#2c3e50; }
.modal-close { position:absolute; top:16px; right:20px; background:none; border:none; font-size:22px; cursor:pointer; color:#888; }
.modal-close:hover { color:#333; }
.modal .form-group { margin-bottom:16px; }
.modal .form-group label { display:block; margin-bottom:6px; font-weight:500; font-size:14px; color:#444; }
.modal .form-group input, .modal .form-group select { width:100%; padding:10px 12px; border:1px solid #ddd; border-radius:4px; font-size:14px; box-sizing:border-box; }
.modal .form-group input:focus { outline:none; border-color:#5A6778; }
.modal .form-group .hint { font-size:12px; color:#888; margin-top:4px; }
.modal-actions { display:flex; gap:10px; justify-content:flex-end; margin-top:24px; }
.btn-modal-save { padding:10px 24px; background:#5A6778; color:white; border:none; border-radius:4px; cursor:pointer; font-size:14px; font-weight:500; }
.btn-modal-save:hover { background:#4B5563; }
.btn-modal-cancel { padding:10px 20px; background:#95a5a6; color:white; border:none; border-radius:4px; cursor:pointer; font-size:14px; }
.btn-modal-cancel:hover { background:#7f8c8d; }
.form-row-2 { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.init-pw-box { background:#f8f9fa; border:1px solid #dee2e6; border-radius:4px; padding:10px 12px; font-size:13px; color:#495057; margin-top:4px; }
</style>

<div class="container">

    <?php if (isset($_SESSION['success_message'])): ?>
        <div data-auto-dismiss style="background:#d4edda;color:#155724;padding:14px;border-radius:4px;margin-bottom:20px;border-left:4px solid #28a745;">
            <?php echo h($_SESSION['success_message']); unset($_SESSION['success_message']); ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_message'])): ?>
        <div data-auto-dismiss style="background:#f8d7da;color:#721c24;padding:14px;border-radius:4px;margin-bottom:20px;border-left:4px solid #dc3545;">
            <?php echo h($_SESSION['error_message']); unset($_SESSION['error_message']); ?>
        </div>
    <?php endif; ?>

    <div class="search-section">
        <form class="search-form" method="GET">
            <label>검색 조건:</label>
            <select name="search_field">
                <option value="company_name" <?php echo $search_field==='company_name'?'selected':''; ?>>업체명</option>
                <option value="representative_name" <?php echo $search_field==='representative_name'?'selected':''; ?>>대표자명</option>
                <option value="manager_name" <?php echo $search_field==='manager_name'?'selected':''; ?>>담당자명</option>
                <option value="phone"        <?php echo $search_field==='phone'       ?'selected':''; ?>>연락처</option>
                <option value="address"      <?php echo $search_field==='address'     ?'selected':''; ?>>주소</option>
                <option value="grade"        <?php echo $search_field==='grade'       ?'selected':''; ?>>등급</option>
            </select>
            <input type="text" name="search_keyword" id="search-keyword-input"
                value="<?php echo h($search_keyword); ?>" placeholder="검색어 입력"
                style="<?php echo $search_field === 'grade' ? 'display:none' : ''; ?>">

            <select name="search_keyword" id="search-keyword-grade"
                    <?php echo $search_field !== 'grade' ? 'disabled style="display:none"' : ''; ?>>
                <option value="">-- 선택 --</option>
                <option value="A" <?php echo $search_keyword==='A'?'selected':''; ?>>A등급</option>
                <option value="B" <?php echo $search_keyword==='B'?'selected':''; ?>>B등급</option>
                <option value="C" <?php echo $search_keyword==='C'?'selected':''; ?>>C등급</option>
            </select>
            <button type="submit" class="btn-search">검색</button>
            <?php if (!empty($search_keyword)): ?>
                <a href="?" class="btn-reset">초기화</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="table-header">
        <span class="total-count">총 <strong><?php echo $total_count; ?></strong>개 업체</span>
        <button class="btn-add" onclick="openAddModal()">+ 업체 등록</button>
    </div>

    <div class="company-table-wrap">
        <?php if (empty($companies)): ?>
            <div class="no-data">검색 결과가 없습니다.</div>
        <?php else: ?>
        <table class="company-table">
            <thead>
                <tr>
                    <th>업체명</th>
                    <th>대표자명</th>
                    <th>담당자명</th>
                    <th>연락처</th>
                    <th>등급</th>
                    <th>주소</th>
                    <th>액션</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($companies as $c): ?>
                <tr>
                    <td>
                        <?php echo h($c['company_name']); ?>
                        <?php if ($c['is_admin']): ?><span class="badge-admin">관리자</span><?php endif; ?>
                    </td>
                    <td><?php echo h($c['representative_name']); ?></td>
                    <td><?php echo h($c['manager_name']); ?></td>
                    <td><?php echo h($c['phone'] ?? '-'); ?></td>
                    <td>
                        <?php if ($c['grade']): ?>
                            <span class="badge-grade badge-<?php echo strtolower($c['grade']); ?>">
                                <span class="grade-full">등급</span><?php echo h($c['grade']); ?>
                            </span>
                        <?php else: ?>-<?php endif; ?>
                    </td>
                    <td><?php echo h($c['address'] ?? '-'); ?></td>
                    <td class="action-cell">
                        <button class="btn-edit-row" onclick="openEditModal(
                            <?php echo $c['id']; ?>,
                            <?php echo json_encode($c['company_name']); ?>,
                            <?php echo json_encode($c['representative_name']); ?>,
                            <?php echo json_encode($c['manager_name']); ?>,
                            <?php echo json_encode($c['phone'] ?? ''); ?>,
                            <?php echo json_encode($c['address'] ?? ''); ?>,
                            <?php echo json_encode($c['grade'] ?? ''); ?>,
                            <?php echo $c['is_admin'] ? 'true' : 'false'; ?>
                        )">수정</button>
                        <button class="btn-delete-row"
                            <?php echo $c['is_admin'] ? 'disabled title="관리자 계정은 삭제할 수 없습니다."' : ''; ?>
                            <?php if (!$c['is_admin']): ?>
                            onclick="confirmDelete(<?php echo $c['id']; ?>, <?php echo json_encode($c['company_name']); ?>)"
                            <?php endif; ?>
                        >삭제</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>

    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <?php
        function page_url($page, $field, $keyword) {
            $p = ['page' => $page];
            if (!empty($keyword)) { $p['search_field'] = $field; $p['search_keyword'] = $keyword; }
            return '?' . http_build_query($p);
        }
        ?>
        <?php if ($current_page > 1): ?>
            <a href="<?php echo page_url($current_page-1, $search_field, $search_keyword); ?>">◀</a>
        <?php else: ?><span class="disabled">◀</span><?php endif; ?>

        <?php for ($p=1; $p<=$total_pages; $p++): ?>
            <?php if ($p===$current_page): ?>
                <span class="active"><?php echo $p; ?></span>
            <?php else: ?>
                <a href="<?php echo page_url($p, $search_field, $search_keyword); ?>"><?php echo $p; ?></a>
            <?php endif; ?>
        <?php endfor; ?>

        <?php if ($current_page < $total_pages): ?>
            <a href="<?php echo page_url($current_page+1, $search_field, $search_keyword); ?>">▶</a>
        <?php else: ?><span class="disabled">▶</span><?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- 등록/수정 모달 -->
<div class="modal-overlay" id="company-modal">
    <div class="modal">
        <button class="modal-close" onclick="closeModal()">✕</button>
        <h2 id="modal-title">업체 등록</h2>

        <form id="company-form" method="POST" action="<?php echo BASE_URL; ?>/api/company_save.php">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="hidden" name="action" id="form-action" value="add">
            <input type="hidden" name="company_id" id="form-company-id" value="">
            <input type="hidden" name="redirect_page"    value="<?php echo $current_page; ?>">
            <input type="hidden" name="redirect_field"   value="<?php echo h($search_field); ?>">
            <input type="hidden" name="redirect_keyword" value="<?php echo h($search_keyword); ?>">

            <div class="form-group">
                <label>업체명 <span style="color:red">*</span></label>
                <input type="text" name="company_name" id="input-company-name" required>
            </div>

            <div class="form-row-2">
                <div class="form-group">
                    <label>대표자명 <span style="color:red">*</span></label>
                    <input type="text" name="representative_name" id="input-rep-name" required>
                </div>
                <div class="form-group">
                    <label>담당자명 <span style="color:red">*</span></label>
                    <input type="text" name="manager_name" id="input-mgr-name" required>
                </div>
            </div>

            <div class="form-group">
                <label>연락처 <span style="color:red">*</span></label>
                <input type="text" name="phone" id="input-phone" placeholder="예: 010-1234-5678" required>
                <div class="hint" id="pw-hint">초기 비밀번호: 전화번호 뒤 4자리로 자동 설정됩니다.</div>
            </div>

            <div class="form-group" id="grade-group">
                <label>등급 <span style="color:red">*</span></label>
                <select name="grade" id="input-grade" required>
                    <option value="">-- 선택 --</option>
                    <option value="A">A등급</option>
                    <option value="B">B등급</option>
                    <option value="C">C등급</option>
                </select>
            </div>

            <div class="form-group">
                <label>주소</label>
                <input type="text" name="address" id="input-address" placeholder="(선택)">
            </div>

            <div class="form-group">
                <label>계정 유형</label>
                <select name="is_admin" id="input-is-admin" onchange="toggleGradeField(this)">
                    <option value="0">일반 업체</option>
                    <option value="1">관리자</option>
                </select>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-modal-cancel" onclick="closeModal()">취소</button>
                <button type="submit" class="btn-modal-save" id="modal-submit-btn">등록</button>
            </div>
        </form>
    </div>
</div>

<!-- 삭제 폼 -->
<form id="delete-form" method="POST" action="<?php echo BASE_URL; ?>/api/company_save.php" style="display:none">
    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="company_id" id="delete-company-id">
    <input type="hidden" name="redirect_page"    value="<?php echo $current_page; ?>">
    <input type="hidden" name="redirect_field"   value="<?php echo h($search_field); ?>">
    <input type="hidden" name="redirect_keyword" value="<?php echo h($search_keyword); ?>">
</form>

<script>
function toggleGradeField(select) {
    const gradeGroup = document.getElementById('grade-group');
    const gradeSelect = document.getElementById('input-grade');
    const pwHint = document.getElementById('pw-hint');
    if (select.value === '1') {
        gradeGroup.style.display = 'none';
        gradeSelect.required = false;
        gradeSelect.value = '';
        pwHint.style.display = 'none';
    } else {
        gradeGroup.style.display = 'block';
        gradeSelect.required = true;
        pwHint.style.display = 'block';
    }
}

function openAddModal() {
    document.getElementById('modal-title').textContent         = '업체 등록';
    document.getElementById('form-action').value              = 'add';
    document.getElementById('form-company-id').value          = '';
    document.getElementById('input-company-name').value       = '';
    document.getElementById('input-rep-name').value           = '';
    document.getElementById('input-mgr-name').value           = '';
    document.getElementById('input-phone').value              = '';
    document.getElementById('input-grade').value              = '';
    document.getElementById('input-address').value            = '';
    document.getElementById('input-is-admin').value           = '0';
    document.getElementById('modal-submit-btn').textContent   = '등록';
    document.getElementById('grade-group').style.display      = 'block';
    document.getElementById('input-grade').required           = true;
    document.getElementById('pw-hint').style.display          = 'block';
    document.getElementById('company-modal').classList.add('active');
}

function openEditModal(id, name, repName, mgrName, phone, address, grade, isAdmin) {
    document.getElementById('modal-title').textContent        = '업체 수정';
    document.getElementById('form-action').value             = 'edit';
    document.getElementById('form-company-id').value         = id;
    document.getElementById('input-company-name').value      = name;
    document.getElementById('input-rep-name').value          = repName;
    document.getElementById('input-mgr-name').value          = mgrName;
    document.getElementById('input-phone').value             = phone;
    document.getElementById('input-grade').value             = grade;
    document.getElementById('input-address').value           = address;
    document.getElementById('input-is-admin').value          = isAdmin ? '1' : '0';
    document.getElementById('modal-submit-btn').textContent  = '저장';

    // 관리자면 등급 숨김
    const gradeGroup = document.getElementById('grade-group');
    const pwHint = document.getElementById('pw-hint');
    if (isAdmin) {
        gradeGroup.style.display = 'none';
        document.getElementById('input-grade').required = false;
        pwHint.style.display = 'none';
    } else {
        gradeGroup.style.display = 'block';
        document.getElementById('input-grade').required = true;
        pwHint.style.display = 'none'; // 수정 시엔 비번 힌트 불필요
    }

    document.getElementById('company-modal').classList.add('active');
}

function closeModal() {
    document.getElementById('company-modal').classList.remove('active');
}
document.getElementById('company-modal').addEventListener('click', function(e) {
    if (e.target === this) closeModal();
});

function confirmDelete(id, name) {
    if (!confirm(`"${name}" 업체를 삭제하시겠습니까?\n이 작업은 되돌릴 수 없습니다.`)) return;
    document.getElementById('delete-company-id').value = id;
    document.getElementById('delete-form').submit();
}

document.querySelector('select[name="search_field"]').addEventListener('change', function() {
    const isGrade = this.value === 'grade';
    const textInput  = document.getElementById('search-keyword-input');
    const gradeSelect = document.getElementById('search-keyword-grade');

    textInput.style.display  = isGrade ? 'none' : '';
    gradeSelect.style.display = isGrade ? '' : 'none';
    textInput.disabled  = isGrade;
    gradeSelect.disabled = !isGrade;
    textInput.value = '';
    gradeSelect.value = '';
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>