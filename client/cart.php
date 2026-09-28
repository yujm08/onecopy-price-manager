<?php
$page_title = '장바구니';
require_once __DIR__ . '/../includes/header.php';   // require_login() 여기서 걸림
require_once __DIR__ . '/../config/config.php';

// 관리자/슈퍼관리자는 이 페이지를 쓸 일이 없음
if (is_admin()) {
    header('Location: ' . BASE_URL . '/admin/price_manage.php');
    exit;
}
?>
<style>
.container { max-width: 1100px; margin: 0 auto; padding: 0 16px; box-sizing: border-box; }

.cart-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; }
.cart-header h1 { font-size:20px; color:#2c3e50; display:flex; align-items:center; gap:8px; }
.cart-count-badge {
    display:none; min-width:22px; height:22px; padding:0 6px; border-radius:11px;
    background:#e74c3c; color:white; font-size:12px; font-weight:700;
    align-items:center; justify-content:center;
}
.cart-count-badge.show { display:inline-flex; }
.btn-back { color:#5A6778; text-decoration:none; font-size:14px; }
.btn-back:hover { text-decoration:underline; }

.cart-table-wrap { background:white; border-radius:8px; box-shadow:0 2px 4px rgba(0,0,0,0.08); overflow:hidden; margin-bottom:20px; }
.cart-table { width:100%; border-collapse:collapse; }
.cart-table thead { background:#34495e; color:white; }
.cart-table th { padding:14px 16px; text-align:left; font-weight:500; font-size:14px; }
.cart-table td { padding:12px 16px; border-bottom:1px solid #eee; font-size:14px; vertical-align:middle; }
.cart-table tbody tr:last-child td { border-bottom:none; }
.cart-table td.num { text-align:right; font-variant-numeric:tabular-nums; }

/* 구분 그룹 행 */
.cart-table tr.cart-group-row td {
    background:#f4f6f8; color:#2c3e50; font-weight:700; font-size:13px;
    padding:8px 16px; border-bottom:1px solid #e3e7eb;
}

.qty-input { width:70px; padding:6px 8px; border:1px solid #ddd; border-radius:4px; font-size:13px; text-align:center; }
.btn-remove-row { padding:6px 14px; background:#e74c3c; color:white; border:none; border-radius:3px; cursor:pointer; font-size:13px; }
.btn-remove-row:hover { background:#c0392b; }

.cart-summary { display:flex; flex-direction:column; background:white; padding:20px 24px; border-radius:8px; box-shadow:0 2px 4px rgba(0,0,0,0.08); }
.cart-total-label { font-size:15px; color:#555; }
.cart-total-amount { font-size:22px; font-weight:700; color:#2c3e50; }

.btn-clear-cart { padding:10px 18px; background:#95a5a6; color:white; border:none; border-radius:4px; cursor:pointer; font-size:14px; }
.btn-clear-cart:hover { background:#7f8c8d; }

.cart-actions { display:flex; justify-content:flex-end; gap:10px; margin-top:16px; }

.no-data { padding:60px 20px; text-align:center; color:#7f8c8d; }
.price-missing { color:#e67e22; font-size:12px; }

.row-price-missing {
    background: repeating-linear-gradient(45deg, #f7f7f7, #f7f7f7 8px, #ececec 8px, #ececec 16px);
}
.row-price-missing td { color: #999; }
.row-price-missing-note { font-size:11px; color:#e67e22; margin-top:2px; }

.coupon-select { width:100%; max-width:180px; padding:5px 6px; border:1px solid #ddd; border-radius:4px; font-size:12px; }
.line-discount { color:#2980b9; font-size:12px; }
.line-net { font-weight:600; }
.price-original { color:#999; text-decoration:line-through; font-size:12px; }
.price-coupon { color:#2980b9; font-weight:700; }

.summary-row { display:flex; justify-content:space-between; padding:4px 0; font-size:14px; color:#555; }
.summary-row.discount { color:#2980b9; }
.summary-row.sub { padding:2px 0 2px 12px; font-size:12px; color:#2980b9; }
.summary-row.sub span:first-child { overflow:hidden; text-overflow:ellipsis; white-space:nowrap; margin-right:12px; }
.summary-row.final { border-top:1px solid #eee; margin-top:8px; padding-top:12px; font-size:17px; font-weight:700; color:#2c3e50; }

/* 담기 알림 토스트 (catalog.php와 동일) */
.cart-toast {
    position: fixed; bottom: 28px; right: 28px;
    background: #2c3e50; color: white;
    padding: 12px 20px; border-radius: 6px;
    font-size: 14px; box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    z-index: 1002; opacity: 0; transform: translateY(10px);
    transition: opacity 0.25s ease, transform 0.25s ease;
    pointer-events: none;
}
.cart-toast.show { opacity: 1; transform: translateY(0); }
.cart-toast.error { background: #c0392b; }

.price-basis-bar { background:white; padding:14px 20px; border-radius:8px; box-shadow:0 2px 4px rgba(0,0,0,0.08); margin-bottom:16px; display:flex; align-items:center; gap:20px; }
.price-basis-bar span.label { font-size:13px; color:#888; }
.price-basis-option { display:flex; align-items:center; gap:6px; font-size:14px; color:#444; cursor:pointer; }

/* ───────── 모바일 전용 (768px 이하) ───────── */
.cart-mobile, .mobile-only { display:none; }

@media (max-width: 768px) {
    /* 순서: 헤더 → 목록 → 기준가격 → 합계 (와이어프레임 순서) */
    .container { display:flex; flex-direction:column; padding:0 12px; }
    .cart-header { order:1; margin-bottom:12px; }
    #cart-list   { order:2; }
    .price-basis-bar { order:3; }
    #cart-bottom { order:4; }

    .cart-header h1 { font-size:18px; }

    .desktop-table { display:none; }
    .cart-mobile   { display:block; }
    .mobile-only   { display:flex; }

    .price-basis-bar { padding:12px 16px; gap:16px; flex-wrap:wrap; margin-bottom:12px; }
    .price-basis-bar span.label { width:100%; }
    .price-basis-option { font-size:15px; }
    .price-basis-option input { width:18px; height:18px; }

    .cart-summary { padding:16px; }
    .cart-actions { justify-content:stretch; }
    .btn-clear-cart { flex:1; padding:12px; }

    /* 토스트는 화면 하단 가운데 */
    .cart-toast { left:16px; right:16px; bottom:20px; text-align:center; }
}

/* 모바일 카드 목록 */
.cart-mobile { background:white; border-radius:8px; box-shadow:0 2px 4px rgba(0,0,0,0.08); margin-bottom:12px; overflow:hidden; }
.m-group + .m-group { border-top:6px solid #f4f6f8; }
.m-group-title {
    display:flex; align-items:center; gap:8px;
    padding:12px 16px 4px; font-size:14px; font-weight:700; color:#2c3e50;
}
.m-group-title::before { content:''; width:7px; height:7px; border-radius:50%; background:#2c3e50; }

.m-item { padding:12px 16px; border-bottom:1px solid #f0f0f0; }
.m-item:last-child { border-bottom:none; }
.m-item-missing { background: repeating-linear-gradient(45deg, #fafafa, #fafafa 8px, #f1f1f1 8px, #f1f1f1 16px); }
.m-item-missing .m-item-name { color:#999; }

.m-item-top { display:flex; align-items:flex-start; gap:8px; }
.m-item-info { flex:1; min-width:0; }
.m-item-no { font-size:11px; color:#999; }
.m-item-name { font-size:15px; font-weight:700; color:#2c3e50; word-break:break-all; margin-top:1px; }
.m-item-unit { font-size:13px; color:#555; margin-top:2px; font-variant-numeric:tabular-nums; }

.m-btn-remove {
    flex-shrink:0; width:32px; height:32px; border:none; border-radius:50%;
    background:#eceff1; color:#78909c; font-size:14px; cursor:pointer;
}

/* iOS는 16px 미만 입력칸에 포커스하면 화면이 확대되므로 16px 고정 */
.m-coupon-select { max-width:none; margin-top:8px; padding:8px; font-size:16px; }

.m-item-bottom { display:flex; justify-content:space-between; align-items:center; margin-top:10px; gap:8px; }
.m-stepper { display:flex; align-items:center; border:1px solid #d5dbe0; border-radius:6px; overflow:hidden; }
.m-stepper button {
    width:36px; height:36px; border:none; background:#f4f6f8;
    font-size:18px; font-weight:700; color:#2c3e50; cursor:pointer;
}
.m-stepper button:active { background:#e3e7eb; }
.m-stepper input {
    width:48px; height:36px; border:none; border-left:1px solid #d5dbe0; border-right:1px solid #d5dbe0;
    text-align:center; font-size:16px; -moz-appearance:textfield;
}
.m-stepper input::-webkit-outer-spin-button,
.m-stepper input::-webkit-inner-spin-button { -webkit-appearance:none; margin:0; }

.m-item-subtotal { text-align:right; font-size:14px; font-variant-numeric:tabular-nums; }
.m-item-subtotal .price-original { display:block; }
</style>

<div class="container">
    <div class="cart-header">
        <h1>장바구니 <span class="cart-count-badge" id="cart-count-badge">0</span></h1>
        <a href="<?php echo BASE_URL; ?>/client/catalog.php" class="btn-back">← 상품 목록으로</a>
    </div>

    <div class="price-basis-bar">
        <span class="label">기준 가격</span>
        <label class="price-basis-option">
            <input type="radio" name="price-basis" value="cash" checked onchange="onBasisChange('cash')"> 현금가 (부가세별도)
        </label>
        <label class="price-basis-option">
            <input type="radio" name="price-basis" value="card" onchange="onBasisChange('card')"> 카드가
        </label>
    </div>

    <div id="cart-list">
        <div class="no-data">불러오는 중...</div>
    </div>
    <div id="cart-bottom"></div>
</div>

<div class="cart-toast" id="cart-toast"></div>

<script src="<?php echo BASE_URL; ?>/assets/js/cart_calc.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/cart_calc.js'); ?>"></script>
<script>
const CSRF_TOKEN = '<?php echo generate_csrf_token(); ?>';
const API_BASE   = '<?php echo BASE_URL; ?>/api/cart';

// 공용 유틸 (cart_calc.js)
const number_format = CartCalc.numberFormat;
const escapeHtml    = CartCalc.escapeHtml;

function showCartToast(message, isError = false) {
    const toast = document.getElementById('cart-toast');
    toast.textContent = message;
    toast.classList.toggle('error', isError);
    toast.classList.add('show');
    clearTimeout(showCartToast._timer);
    showCartToast._timer = setTimeout(() => toast.classList.remove('show'), 2000);
}

let cartItemsCache = [];          // 최근 로드한 items (원본, 서버가 계산한 기본 쿠폰 포함)
const couponOverride = {};        // cart_item_id -> allocation_id('' = 쿠폰 미적용) ; 없으면 서버 기본값 사용
let currentBasis = 'cash';        // 'cash' | 'card' — 기준 가격 라디오로 전환
const qtyTimers = {};             // 모바일 스테퍼 연타 대응: cart_item_id -> 저장 대기 타이머

function onBasisChange(basis) {
    currentBasis = basis;
    renderCart();
}

function postForm(url, params) {
    const body = new URLSearchParams({ ...params, csrf_token: CSRF_TOKEN });
    return fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString()
    }).then(r => r.json());
}

function showListMessage(message) {
    document.getElementById('cart-list').innerHTML = `<div class="cart-table-wrap"><div class="no-data">${escapeHtml(message)}</div></div>`;
    document.getElementById('cart-bottom').innerHTML = '';
    updateCountBadge(0);
}

function updateCountBadge(qty) {
    const badge = document.getElementById('cart-count-badge');
    badge.textContent = qty;
    badge.classList.toggle('show', qty > 0);
}

function loadCart() {
    fetch(`${API_BASE}/list.php`)
        .then(r => r.json())
        .then(data => {
            if (!data.success) { showListMessage(data.message); return; }
            cartItemsCache = data.items;
            renderCart();
        })
        .catch(() => showListMessage('장바구니를 불러오지 못했습니다.'));
}

/* 쿠폰 선택 옵션 (PC/모바일 공용) */
function couponOptions(item, line) {
    return ['<option value="">쿠폰 미적용</option>'].concat(
        item.available_coupons.map(c => `
            <option value="${c.allocation_id}" ${String(c.allocation_id) === String(line.selectedId) ? 'selected' : ''}>
                ${escapeHtml(c.coupon_name)} (${c.discount_type === 'percent' ? c.discount_value + '%' : number_format(c.discount_value) + '원'})
            </option>
        `)
    ).join('');
}

/* PC 테이블 */
function renderDesktopTable(groups, summary) {
    let rows = '';

    groups.forEach(group => {
        rows += `<tr class="cart-group-row"><td colspan="7">${escapeHtml(group.category_name)}</td></tr>`;

        group.items.forEach(item => {
            const line = summary.lines[item.cart_item_id];

            const priceCell = line.unitPrice !== null
                ? number_format(line.unitPrice) + '원'
                : '<span class="price-missing">이번 달 단가 미등록</span><div class="row-price-missing-note">계산에서 제외됨</div>';

            const couponCell = item.available_coupons.length > 0
                ? `<select class="coupon-select" onchange="onCouponChange(${item.cart_item_id}, this.value)">${couponOptions(item, line)}</select>`
                : '-';

            const netCell = line.lineTotal === null
                ? '-'
                : line.discount > 0
                    ? `<span class="price-original">${number_format(line.lineTotal)}원</span><br><span class="price-coupon">쿠폰 적용가 ${number_format(line.net)}원</span>`
                    : `<span class="line-net">${number_format(line.net)}원</span>`;

            rows += `
                <tr data-cart-item-id="${item.cart_item_id}" class="${line.unitPrice === null ? 'row-price-missing' : ''}">
                    <td>${escapeHtml(item.product_number)}</td>
                    <td>${escapeHtml(item.product_name)}</td>
                    <td class="num">${priceCell}</td>
                    <td class="num">
                        <input type="number" class="qty-input" value="${item.quantity}" min="1" step="1"
                            onchange="updateQty(${item.cart_item_id}, this.value)">
                    </td>
                    <td>${couponCell}</td>
                    <td class="num">${netCell}</td>
                    <td><button type="button" class="btn-remove-row" onclick="removeRow(${item.cart_item_id})">삭제</button></td>
                </tr>
            `;
        });
    });

    return `
        <div class="cart-table-wrap desktop-table">
            <table class="cart-table">
                <thead>
                    <tr>
                        <th>제품번호</th>
                        <th>제품명</th>
                        <th>단가</th>
                        <th>수량</th>
                        <th>적용 쿠폰</th>
                        <th>소계</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>${rows}</tbody>
            </table>
        </div>
    `;
}

/* 모바일 카드 목록 */
function renderMobileList(groups, summary) {
    let html = '';

    groups.forEach(group => {
        html += `<div class="m-group"><div class="m-group-title">${escapeHtml(group.category_name)}</div>`;

        group.items.forEach(item => {
            const id      = item.cart_item_id;
            const line    = summary.lines[id];
            const missing = line.unitPrice === null;

            const unitText = missing
                ? '<span class="price-missing">이번 달 단가 미등록 · 계산에서 제외됨</span>'
                : `${number_format(line.unitPrice)}원`;

            const couponSelect = item.available_coupons.length > 0
                ? `<select class="coupon-select m-coupon-select" onchange="onCouponChange(${id}, this.value)">${couponOptions(item, line)}</select>`
                : '';

            const subtotal = missing
                ? ''
                : line.discount > 0
                    ? `<span class="price-original">${number_format(line.lineTotal)}원</span><span class="price-coupon">${number_format(line.net)}원</span>`
                    : `<span class="line-net">${number_format(line.net)}원</span>`;

            html += `
                <div class="m-item ${missing ? 'm-item-missing' : ''}" data-cart-item-id="${id}">
                    <div class="m-item-top">
                        <div class="m-item-info">
                            <div class="m-item-no">${escapeHtml(item.product_number)}</div>
                            <div class="m-item-name">${escapeHtml(item.product_name)}</div>
                            <div class="m-item-unit">${unitText}</div>
                        </div>
                        <button type="button" class="m-btn-remove" onclick="removeRow(${id})" aria-label="삭제">✕</button>
                    </div>
                    ${couponSelect}
                    <div class="m-item-bottom">
                        <div class="m-stepper">
                            <button type="button" onclick="stepQty(${id}, -1)" aria-label="수량 줄이기">−</button>
                            <input type="number" inputmode="numeric" min="1" step="1" value="${item.quantity}"
                                onchange="updateQty(${id}, this.value)">
                            <button type="button" onclick="stepQty(${id}, 1)" aria-label="수량 늘리기">+</button>
                        </div>
                        <div class="m-item-subtotal">${subtotal}</div>
                    </div>
                </div>
            `;
        });

        html += '</div>';
    });

    return `<div class="cart-mobile">${html}</div>`;
}

/* 합계 (PC/모바일 공용, 쿠폰별 내역은 모바일에서만 표시) */
function renderSummary(items, summary) {
    let couponLines = '';
    items.forEach(item => {
        const line = summary.lines[item.cart_item_id];
        if (line.discount > 0 && line.coupon) {
            couponLines += `
                <div class="summary-row sub mobile-only">
                    <span>${escapeHtml(line.coupon.coupon_name)}${line.appliedQty > 1 ? ' ×' + line.appliedQty : ''}</span>
                    <span>-${number_format(line.discount)}원</span>
                </div>`;
        }
    });

    return `
        <div class="cart-summary">
            <div class="summary-row"><span>상품금액</span><span>${number_format(summary.totalBase)}원</span></div>
            <div class="summary-row discount"><span>쿠폰 할인</span><span>-${number_format(summary.totalDiscount)}원</span></div>
            ${couponLines}
            ${currentBasis === 'cash' ? `<div class="summary-row"><span>부가세 (10%)</span><span>+${number_format(summary.vat)}원</span></div>` : ''}
            <div class="summary-row final"><span>예상결제금액</span><span>${number_format(summary.totalNet)}원</span></div>
        </div>
        <div class="cart-actions">
            <button type="button" class="btn-clear-cart" onclick="clearCart()">전체 비우기</button>
        </div>
    `;
}

function renderCart() {
    const items = cartItemsCache;

    if (items.length === 0) {
        showListMessage('장바구니가 비어있습니다.');
        return;
    }

    const summary = CartCalc.computeSummary(items, currentBasis, couponOverride);
    const groups  = CartCalc.groupByCategory(items);

    // PC 테이블과 모바일 카드를 둘 다 그리고 CSS(768px)로 하나만 보여줌
    document.getElementById('cart-list').innerHTML =
        renderDesktopTable(groups, summary) + renderMobileList(groups, summary);
    document.getElementById('cart-bottom').innerHTML = renderSummary(items, summary);
    updateCountBadge(summary.totalQty);
}

function onCouponChange(cartItemId, allocationId) {
    couponOverride[cartItemId] = allocationId; // '' 포함
    renderCart();
}

/* 수량 직접 입력 (PC 입력칸 / 모바일 스테퍼 가운데 칸) */
function updateQty(cartItemId, quantity) {
    quantity = parseInt(quantity, 10);
    if (!quantity || quantity < 1) {
        showCartToast('수량을 확인해주세요.', true);
        loadCart();
        return;
    }
    clearTimeout(qtyTimers[cartItemId]);
    delete qtyTimers[cartItemId];

    postForm(`${API_BASE}/update_qty.php`, { cart_item_id: cartItemId, quantity })
        .then(data => {
            if (!data.success) showCartToast(data.message, true);
            loadCart();
        })
        .catch(() => { showCartToast('오류가 발생했습니다.', true); loadCart(); });
}

/* 모바일 스테퍼: 화면은 즉시 반영, 서버 저장은 마지막 탭 후 0.4초 뒤 한 번만 */
function stepQty(cartItemId, delta) {
    const item = cartItemsCache.find(i => i.cart_item_id === cartItemId);
    if (!item) return;

    const next = item.quantity + delta;
    if (next < 1) {
        if (confirm('이 제품을 장바구니에서 삭제하시겠습니까?')) deleteRow(cartItemId);
        return;
    }

    item.quantity = next;
    renderCart();

    clearTimeout(qtyTimers[cartItemId]);
    qtyTimers[cartItemId] = setTimeout(() => {
        delete qtyTimers[cartItemId];
        postForm(`${API_BASE}/update_qty.php`, { cart_item_id: cartItemId, quantity: next })
            .then(data => {
                // 성공 시엔 화면이 이미 맞으므로 다시 불러오지 않음 (연타 중 화면이 되돌아가는 현상 방지)
                if (!data.success) { showCartToast(data.message, true); loadCart(); }
            })
            .catch(() => { showCartToast('수량 저장에 실패했습니다.', true); loadCart(); });
    }, 400);
}

function removeRow(cartItemId) {
    if (!confirm('이 항목을 삭제하시겠습니까?')) return;
    deleteRow(cartItemId);
}

function deleteRow(cartItemId) {
    clearTimeout(qtyTimers[cartItemId]);
    delete qtyTimers[cartItemId];

    postForm(`${API_BASE}/delete_row.php`, { cart_item_id: cartItemId })
        .then(data => {
            showCartToast(data.message, !data.success);
            loadCart();
        })
        .catch(() => showCartToast('오류가 발생했습니다.', true));
}

function clearCart() {
    if (!confirm('장바구니를 전체 비우시겠습니까?')) return;
    Object.keys(qtyTimers).forEach(id => { clearTimeout(qtyTimers[id]); delete qtyTimers[id]; });

    postForm(`${API_BASE}/clear.php`, {})
        .then(data => {
            showCartToast(data.message, !data.success);
            loadCart();
        })
        .catch(() => showCartToast('오류가 발생했습니다.', true));
}

document.addEventListener('DOMContentLoaded', loadCart);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>