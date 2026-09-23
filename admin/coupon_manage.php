<?php
$page_title = '쿠폰 관리';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../config/config.php';

require_admin();
?>
<style>
.container { max-width: 1100px; margin: 0 auto; padding: 0 16px; box-sizing: border-box; }

.page-header { display:flex; justify-content:flex-end; margin-bottom:20px; }
.btn-issue-coupon { padding:10px 20px; background:#5A6778; color:white; border:none; border-radius:4px; cursor:pointer; font-size:14px; font-weight:500; }
.btn-issue-coupon:hover { background:#4B5563; }

.company-card { background:white; border-radius:8px; box-shadow:0 2px 4px rgba(0,0,0,0.08); margin-bottom:14px; overflow:hidden; }
.company-card-header { display:flex; align-items:center; justify-content:space-between; padding:18px 20px; }
.company-card-title { display:flex; align-items:center; gap:10px; font-size:15px; font-weight:500; color:#2c3e50; }
.badge-grade { padding:2px 10px; border-radius:12px; font-size:12px; font-weight:600; }
.badge-a { background:#cce5ff; color:#004085; }
.badge-b { background:#d4edda; color:#155724; }
.badge-c { background:#fff3cd; color:#856404; }

.btn-detail { padding:7px 16px; background:white; border:1px solid #ddd; border-radius:4px; cursor:pointer; font-size:13px; color:#555; }
.btn-detail:hover { background:#f8f9fa; }
.card-header-actions { display:flex; gap:8px; }
.btn-card-cancel { padding:7px 16px; background:#95a5a6; color:white; border:none; border-radius:4px; cursor:pointer; font-size:13px; }
.btn-card-cancel:hover { background:#7f8c8d; }
.btn-card-save { padding:7px 16px; background:#27ae60; color:white; border:none; border-radius:4px; cursor:pointer; font-size:13px; }
.btn-card-save:hover { background:#229954; }

.company-card-body { border-top:1px solid #eee; padding:16px 20px; display:flex; flex-direction:column; gap:10px; }
.coupon-row { display:flex; align-items:center; gap:14px; }
.coupon-qty-btn {
    width:28px; height:28px; border-radius:50%; border:1px solid #ddd; background:white;
    cursor:pointer; font-size:16px; line-height:1; color:#555; flex-shrink:0;
}
.coupon-qty-btn:hover { background:#f0f0f0; }
.coupon-qty-btn:disabled { opacity:0.4; cursor:not-allowed; }
.coupon-info {
    flex:1; display:flex; align-items:center; justify-content:space-between; gap:16px;
    background:#f8f9fa; border-radius:6px; padding:12px 16px;
}
.coupon-info-main { display:flex; align-items:baseline; gap:10px; flex-wrap:wrap; }
.coupon-info-name { font-weight:600; color:#2c3e50; font-size:14px; }
.coupon-info-discount { font-size:17px; font-weight:700; color:#2980b9; }
.coupon-info-target { font-size:12px; color:#888; }
.coupon-info-meta { text-align:right; font-size:12px; color:#888; white-space:nowrap; }
.coupon-info-remaining { font-weight:600; color:#444; }
.coupon-row.qty-zero .coupon-info { opacity:0.45; text-decoration:line-through; }
.no-coupons { color:#999; font-size:13px; padding:8px 0; }
.no-data { padding:40px 20px; text-align:center; color:#7f8c8d; }

/* 쿠폰 발행 모달 */
.modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,0.45); z-index:1000; justify-content:center; align-items:center; }
.modal-overlay.active { display:flex; }
.modal { background:white; border-radius:10px; padding:32px 36px; width:100%; max-width:750px; box-shadow:0 8px 32px rgba(0,0,0,0.18); position:relative; max-height:90vh; overflow-y:auto; }
.modal h2 { font-size:18px; margin-bottom:24px; color:#2c3e50; }
.modal-close { position:absolute; top:16px; right:20px; background:none; border:none; font-size:22px; cursor:pointer; color:#888; }
.modal-close:hover { color:#333; }
.modal .form-group { margin-bottom:18px; }
.modal .form-group label { display:block; margin-bottom:6px; font-weight:500; font-size:14px; color:#444; }
.modal .form-group input { width:100%; padding:10px 12px; border:1px solid #ddd; border-radius:4px; font-size:14px; box-sizing:border-box; }
.form-row-2 { display:grid; grid-template-columns:1fr 1fr; gap:12px; }
.search-with-btn { display:flex; gap:8px; }
.search-with-btn input { flex:1; }
.btn-search-small { padding:10px 16px; background:#34495e; color:white; border:none; border-radius:4px; cursor:pointer; font-size:13px; white-space:nowrap; }
.btn-search-small:hover { background:#2c3e50; }
.btn-select-all { padding:10px 14px; background:#e67e22; color:white; border:none; border-radius:4px; cursor:pointer; font-size:13px; white-space:nowrap; }
.btn-select-all:hover { background:#d35400; }
.search-results-list { border:1px solid #ddd; border-radius:4px; margin-top:6px; max-height:160px; overflow-y:auto; }
.search-result-item { padding:9px 12px; cursor:pointer; font-size:13px; border-bottom:1px solid #f0f0f0; }
.search-result-item:last-child { border-bottom:none; }
.search-result-item:hover { background:#f0f4f8; }
.selected-product-chip, .selected-company-chip {
    display:inline-flex; align-items:center; gap:6px;
    background:#eef1f4; border:1px solid #c7ccd3; border-radius:4px;
    padding:6px 10px; font-size:13px; margin-top:6px;
}
.chip-remove { cursor:pointer; color:#888; font-weight:700; }
.chip-remove:hover { color:#333; }
.selected-companies-list { display:flex; flex-wrap:wrap; gap:6px; margin-top:8px; }
.selected-count { font-size:12px; color:#888; margin-top:6px; }
.qty-stepper { display:flex; align-items:center; gap:8px; }
.qty-stepper input { width:70px; text-align:center; }
.checkbox-row { display:flex; align-items:center; gap:8px; font-size:13px; color:#555; margin-bottom:18px; }
.checkbox-note { font-size:12px; color:#999; margin-left:24px; margin-top:-12px; margin-bottom:18px; }
.modal-actions { display:flex; gap:10px; justify-content:flex-end; margin-top:24px; }
.btn-modal-save { padding:10px 24px; background:#5A6778; color:white; border:none; border-radius:4px; cursor:pointer; font-size:14px; font-weight:500; }
.btn-modal-save:hover { background:#4B5563; }
.btn-modal-cancel { padding:10px 20px; background:#95a5a6; color:white; border:none; border-radius:4px; cursor:pointer; font-size:14px; }
.btn-modal-cancel:hover { background:#7f8c8d; }

.toast {
    position: fixed; bottom: 28px; right: 28px;
    background: #2c3e50; color: white;
    padding: 12px 20px; border-radius: 6px;
    font-size: 14px; box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    z-index: 1002; opacity: 0; transform: translateY(10px);
    transition: opacity 0.25s ease, transform 0.25s ease;
    pointer-events: none;
}
.toast.show { opacity: 1; transform: translateY(0); }
.toast.error { background: #c0392b; }
</style>

<div class="container">
    <div class="page-header">
        <button class="btn-issue-coupon" onclick="openIssueModal()">+ 쿠폰 발행</button>
    </div>

    <div id="company-list">
        <div class="no-data">불러오는 중...</div>
    </div>
</div>

<!-- 쿠폰 발행 모달 -->
<div class="modal-overlay" id="issue-modal">
    <div class="modal">
        <button class="modal-close" onclick="closeIssueModal()">✕</button>
        <h2>쿠폰 발행</h2>

        <div class="form-row-2">
            <div class="form-group">
                <label>대상 제품 <span style="color:red">*</span></label>
                <div class="search-with-btn">
                    <input type="text" id="product-search-input" placeholder="상품명 또는 제품번호">
                    <button type="button" class="btn-search-small" onclick="searchProducts()">제품 검색</button>
                </div>
                <div class="search-results-list" id="product-search-results" style="display:none"></div>
                <div id="selected-product-chip-wrap"></div>
            </div>
            <div class="form-group">
                <label>쿠폰명 (선택)</label>
                <input type="text" id="coupon-name-input" placeholder="미입력 시 상품명으로 자동 설정">
            </div>
        </div>

        <div class="form-group">
            <label>대상 업체 <span style="color:red">*</span></label>
            <div class="search-with-btn">
                <input type="text" id="company-search-input" placeholder="업체명 검색">
                <button type="button" class="btn-search-small" onclick="searchCompanies()">업체 검색</button>
                <button type="button" class="btn-select-all" onclick="selectAllCompanies()">전체 업체 선택</button>
            </div>
            <div class="search-results-list" id="company-search-results" style="display:none"></div>
            <div class="selected-companies-list" id="selected-companies-list"></div>
            <div class="selected-count" id="selected-companies-count">(현재 0개 업체 선택)</div>
        </div>

        <div class="form-row-2">
            <div class="form-group">
                <label>할인 금액 (원) <span style="color:red">*</span></label>
                <input type="number" id="discount-value-input" min="1" step="1" placeholder="예: 10000">
            </div>
            <div class="form-group">
                <label>발행 수량 (업체당) <span style="color:red">*</span></label>
                <div class="qty-stepper">
                    <button type="button" class="coupon-qty-btn" onclick="stepIssueQty(-1)">−</button>
                    <input type="number" id="issue-qty-input" value="10" min="1" step="1">
                    <button type="button" class="coupon-qty-btn" onclick="stepIssueQty(1)">+</button>
                </div>
            </div>
        </div>

        <div class="form-row-2">
            <div class="form-group">
                <label>사용 시작일 <span style="color:red">*</span></label>
                <input type="date" id="valid-from-input">
            </div>
            <div class="form-group">
                <label>사용 종료일 <span style="color:red">*</span></label>
                <input type="date" id="valid-to-input">
            </div>
        </div>

        <div class="checkbox-row">
            <input type="checkbox" id="send-alimtalk-checkbox" checked>
            <label for="send-alimtalk-checkbox" style="margin:0;font-weight:normal;">쿠폰 발행 후 대상 업체에 카카오 알림톡 자동 발송</label>
        </div>
        <div class="checkbox-note">※ 알림톡 자동 발송 기능은 아직 연동되지 않았습니다 (준비 중).</div>

        <div class="modal-actions">
            <button type="button" class="btn-modal-cancel" onclick="closeIssueModal()">취소</button>
            <button type="button" class="btn-modal-save" onclick="submitIssueCoupon()">쿠폰발행</button>
        </div>
    </div>
</div>

<div class="toast" id="toast"></div>

<script>
const CSRF_TOKEN = '<?php echo generate_csrf_token(); ?>';

function showToast(message, isError = false) {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.classList.toggle('error', isError);
    toast.classList.add('show');
    clearTimeout(showToast._timer);
    showToast._timer = setTimeout(() => toast.classList.remove('show'), 2500);
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str ?? '';
    return div.innerHTML;
}

/* ══════════════════ 업체 목록 + 상세 펼치기 ══════════════════ */

// 카드별 상태: { expanded, original: [...], pending: {allocation_id: qty} }
const cardState = {};

function loadCompanyList() {
    fetch('<?php echo BASE_URL; ?>/api/coupon/list_companies.php?only_with_coupons=1')
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                document.getElementById('company-list').innerHTML = `<div class="no-data">${data.message}</div>`;
                return;
            }
            renderCompanyList(data.companies);
        })
        .catch(() => {
            document.getElementById('company-list').innerHTML = '<div class="no-data">불러오지 못했습니다.</div>';
        });
}

function renderCompanyList(companies) {
    const wrap = document.getElementById('company-list');
    if (companies.length === 0) {
        wrap.innerHTML = '<div class="no-data">쿠폰이 발행된 업체가 없습니다. 위의 "쿠폰 발행" 버튼으로 새로 발행해보세요.</div>';
        return;
    }
    wrap.innerHTML = companies.map(c => `
        <div class="company-card" id="company-card-${c.id}">
            <div class="company-card-header">
                <div class="company-card-title">
                    ${escapeHtml(c.company_name)}
                    ${c.grade ? `<span class="badge-grade badge-${c.grade.toLowerCase()}">${c.grade}등급</span>` : ''}
                </div>
                <div class="card-header-actions" id="card-actions-${c.id}">
                    <button type="button" class="btn-detail" onclick="toggleDetail(${c.id})">상세</button>
                </div>
            </div>
            <div class="company-card-body" id="card-body-${c.id}" style="display:none"></div>
        </div>
    `).join('');
}

function toggleDetail(companyId) {
    const body = document.getElementById(`card-body-${companyId}`);
    const isOpen = body.style.display !== 'none';

    if (isOpen) {
        closeDetail(companyId);
    } else {
        openDetail(companyId);
    }
}

function openDetail(companyId) {
    fetch(`<?php echo BASE_URL; ?>/api/coupon/company_coupons.php?company_id=${companyId}`)
        .then(r => r.json())
        .then(data => {
            if (!data.success) { showToast(data.message, true); return; }

            cardState[companyId] = {
                original: data.coupons.map(c => ({ ...c })),
                pending: {} // allocation_id -> new issued_qty
            };

            document.getElementById(`card-body-${companyId}`).style.display = 'flex';
            document.getElementById(`card-actions-${companyId}`).innerHTML = `
                <button type="button" class="btn-card-cancel" onclick="cancelDetail(${companyId})">취소</button>
                <button type="button" class="btn-card-save" onclick="saveDetail(${companyId})">저장</button>
            `;
            renderCouponRows(companyId);
        })
        .catch(() => showToast('불러오지 못했습니다.', true));
}

function closeDetail(companyId) {
    document.getElementById(`card-body-${companyId}`).style.display = 'none';
    document.getElementById(`card-actions-${companyId}`).innerHTML =
        `<button type="button" class="btn-detail" onclick="toggleDetail(${companyId})">상세</button>`;
    delete cardState[companyId];
}

function cancelDetail(companyId) {
    closeDetail(companyId);
}

function renderCouponRows(companyId) {
    const state = cardState[companyId];
    const body = document.getElementById(`card-body-${companyId}`);

    if (state.original.length === 0) {
        body.innerHTML = '<div class="no-coupons">발급된 쿠폰이 없습니다.</div>';
        return;
    }

    body.innerHTML = state.original.map(c => {
        const currentQty = state.pending[c.allocation_id] !== undefined ? state.pending[c.allocation_id] : c.issued_qty;
        const remaining = currentQty - c.used_qty;
        const isZero = currentQty === 0 && c.used_qty === 0;
        const nameLabel = c.is_custom_name
            ? `${escapeHtml(c.coupon_name)} <span class="coupon-info-target">대상 제품: ${escapeHtml(c.product_name)}</span>`
            : `${escapeHtml(c.product_name)}`;
        const discountLabel = c.discount_type === 'percent'
            ? `${c.discount_value}% 할인`
            : `${Number(c.discount_value).toLocaleString()}원 할인`;

        return `
            <div class="coupon-row ${isZero ? 'qty-zero' : ''}" data-allocation-id="${c.allocation_id}">
                <button type="button" class="coupon-qty-btn" onclick="stepAllocationQty(${companyId}, ${c.allocation_id}, -1)" ${currentQty <= c.used_qty ? 'disabled' : ''}>−</button>
                <div class="coupon-info">
                    <div class="coupon-info-main">
                        <span class="coupon-info-name">${nameLabel}</span>
                        <span class="coupon-info-discount">${discountLabel}</span>
                    </div>
                    <div class="coupon-info-meta">
                        잔여: ${remaining}개 (발행 ${currentQty} / 사용 ${c.used_qty})<br>
                        사용 기한: ${c.valid_from} ~ ${c.valid_to}
                    </div>
                </div>
                <button type="button" class="coupon-qty-btn" onclick="stepAllocationQty(${companyId}, ${c.allocation_id}, 1)">+</button>
            </div>
        `;
    }).join('');
}

function stepAllocationQty(companyId, allocationId, delta) {
    const state = cardState[companyId];
    const original = state.original.find(c => c.allocation_id === allocationId);
    const current = state.pending[allocationId] !== undefined ? state.pending[allocationId] : original.issued_qty;
    const next = current + delta;

    if (next < original.used_qty) {
        showToast(`이미 ${original.used_qty}개 사용되어 그보다 적게 설정할 수 없습니다.`, true);
        return;
    }
    if (next < 0) return;

    state.pending[allocationId] = next;
    renderCouponRows(companyId);
}

function saveDetail(companyId) {
    const state = cardState[companyId];
    const changes = Object.keys(state.pending)
        .filter(allocId => state.pending[allocId] !== state.original.find(c => c.allocation_id == allocId).issued_qty)
        .map(allocId => ({ allocation_id: allocId, issued_qty: state.pending[allocId] }));

    if (changes.length === 0) {
        showToast('변경사항이 없습니다.');
        closeDetail(companyId);
        return;
    }

    const params = new URLSearchParams();
    params.append('csrf_token', CSRF_TOKEN);
    changes.forEach((chg, i) => {
        params.append(`changes[${i}][allocation_id]`, chg.allocation_id);
        params.append(`changes[${i}][issued_qty]`, chg.issued_qty);
    });

    fetch('<?php echo BASE_URL; ?>/api/coupon/batch_update.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: params.toString()
    })
    .then(r => r.json())
    .then(data => {
        const failed = (data.results || []).filter(r => !r.success);
        if (failed.length > 0) {
            showToast(failed[0].message || '일부 항목 저장에 실패했습니다.', true);
        } else {
            showToast('저장되었습니다.');
        }
        closeDetail(companyId);
    })
    .catch(() => showToast('저장 중 오류가 발생했습니다.', true));
}

/* ══════════════════ 쿠폰 발행 모달 ══════════════════ */

let selectedProduct = null;
let selectedCompanies = {}; // id -> company_name

function openIssueModal() {
    selectedProduct = null;
    selectedCompanies = {};
    document.getElementById('product-search-input').value = '';
    document.getElementById('company-search-input').value = '';
    document.getElementById('coupon-name-input').value = '';
    document.getElementById('discount-value-input').value = '';
    document.getElementById('issue-qty-input').value = 10;
    document.getElementById('valid-from-input').value = '';
    document.getElementById('valid-to-input').value = '';
    document.getElementById('product-search-results').style.display = 'none';
    document.getElementById('company-search-results').style.display = 'none';
    renderSelectedProduct();
    renderSelectedCompanies();
    document.getElementById('issue-modal').classList.add('active');
}
function closeIssueModal() {
    document.getElementById('issue-modal').classList.remove('active');
}
document.getElementById('issue-modal').addEventListener('click', function(e) {
    if (e.target === this) closeIssueModal();
});

function stepIssueQty(delta) {
    const input = document.getElementById('issue-qty-input');
    const next = Math.max(1, parseInt(input.value || 1, 10) + delta);
    input.value = next;
}

function searchProducts() {
    const q = document.getElementById('product-search-input').value.trim();
    if (!q) return;
    fetch(`<?php echo BASE_URL; ?>/api/coupon/search_products.php?q=${encodeURIComponent(q)}`)
        .then(r => r.json())
        .then(data => {
            const resultsEl = document.getElementById('product-search-results');
            if (!data.success || data.products.length === 0) {
                resultsEl.innerHTML = '<div class="search-result-item" style="color:#999;">검색 결과가 없습니다.</div>';
                resultsEl.style.display = 'block';
                return;
            }
            resultsEl.innerHTML = data.products.map(p => `
                <div class="search-result-item" onclick='selectProduct(${p.id}, ${JSON.stringify(p.product_name)})'>
                    [${escapeHtml(p.product_number)}] ${escapeHtml(p.product_name)}
                </div>
            `).join('');
            resultsEl.style.display = 'block';
        });
}

function selectProduct(id, name) {
    selectedProduct = { id, name };
    document.getElementById('product-search-results').style.display = 'none';
    renderSelectedProduct();
}

function renderSelectedProduct() {
    const wrap = document.getElementById('selected-product-chip-wrap');
    if (!selectedProduct) { wrap.innerHTML = ''; return; }
    wrap.innerHTML = `
        <span class="selected-product-chip">
            ${escapeHtml(selectedProduct.name)}
            <span class="chip-remove" onclick="removeSelectedProduct()">✕</span>
        </span>
    `;
}
function removeSelectedProduct() {
    selectedProduct = null;
    renderSelectedProduct();
}

function searchCompanies() {
    const q = document.getElementById('company-search-input').value.trim();
    fetch(`<?php echo BASE_URL; ?>/api/coupon/list_companies.php?q=${encodeURIComponent(q)}`)
        .then(r => r.json())
        .then(data => {
            const resultsEl = document.getElementById('company-search-results');
            if (!data.success || data.companies.length === 0) {
                resultsEl.innerHTML = '<div class="search-result-item" style="color:#999;">검색 결과가 없습니다.</div>';
                resultsEl.style.display = 'block';
                return;
            }
            resultsEl.innerHTML = data.companies.map(c => `
                <div class="search-result-item" onclick='addSelectedCompany(${c.id}, ${JSON.stringify(c.company_name)})'>
                    ${escapeHtml(c.company_name)} ${c.grade ? `(${c.grade}등급)` : ''}
                </div>
            `).join('');
            resultsEl.style.display = 'block';
        });
}

function selectAllCompanies() {
    fetch('<?php echo BASE_URL; ?>/api/coupon/list_companies.php')
        .then(r => r.json())
        .then(data => {
            if (!data.success) return;
            data.companies.forEach(c => { selectedCompanies[c.id] = c.company_name; });
            document.getElementById('company-search-results').style.display = 'none';
            renderSelectedCompanies();
        });
}

function addSelectedCompany(id, name) {
    selectedCompanies[id] = name;
    document.getElementById('company-search-results').style.display = 'none';
    renderSelectedCompanies();
}
function removeSelectedCompany(id) {
    delete selectedCompanies[id];
    renderSelectedCompanies();
}

function renderSelectedCompanies() {
    const listEl = document.getElementById('selected-companies-list');
    const ids = Object.keys(selectedCompanies);
    listEl.innerHTML = ids.map(id => `
        <span class="selected-company-chip">
            ${escapeHtml(selectedCompanies[id])}
            <span class="chip-remove" onclick="removeSelectedCompany(${id})">✕</span>
        </span>
    `).join('');
    document.getElementById('selected-companies-count').textContent = `(현재 ${ids.length}개 업체 선택)`;
}

function submitIssueCoupon() {
    if (!selectedProduct) { showToast('대상 제품을 선택해주세요.', true); return; }
    const companyIds = Object.keys(selectedCompanies);
    if (companyIds.length === 0) { showToast('대상 업체를 1개 이상 선택해주세요.', true); return; }

    const discountValue = parseInt(document.getElementById('discount-value-input').value, 10);
    const issueQty      = parseInt(document.getElementById('issue-qty-input').value, 10);
    const validFrom      = document.getElementById('valid-from-input').value;
    const validTo        = document.getElementById('valid-to-input').value;
    const couponName     = document.getElementById('coupon-name-input').value.trim();

    if (!discountValue || discountValue <= 0) { showToast('할인 금액을 입력해주세요.', true); return; }
    if (!issueQty || issueQty <= 0) { showToast('발행 수량을 입력해주세요.', true); return; }
    if (!validFrom || !validTo) { showToast('사용 기한을 입력해주세요.', true); return; }

    const params = new URLSearchParams();
    params.append('csrf_token', CSRF_TOKEN);
    params.append('product_id', selectedProduct.id);
    params.append('coupon_name', couponName);
    params.append('discount_value', discountValue);
    params.append('issued_qty', issueQty);
    params.append('valid_from', validFrom);
    params.append('valid_to', validTo);
    companyIds.forEach(id => params.append('company_ids[]', id));

    fetch('<?php echo BASE_URL; ?>/api/coupon/create.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: params.toString()
    })
    .then(r => r.json())
    .then(data => {
        showToast(data.message, !data.success);
        if (data.success) {
            closeIssueModal();
            loadCompanyList();
        }
    })
    .catch(() => showToast('발행 중 오류가 발생했습니다.', true));
}

document.addEventListener('DOMContentLoaded', loadCompanyList);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>