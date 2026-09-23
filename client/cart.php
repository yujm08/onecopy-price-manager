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
.cart-header h1 { font-size:20px; color:#2c3e50; }
.btn-back { color:#5A6778; text-decoration:none; font-size:14px; }
.btn-back:hover { text-decoration:underline; }

.cart-table-wrap { background:white; border-radius:8px; box-shadow:0 2px 4px rgba(0,0,0,0.08); overflow:hidden; margin-bottom:20px; }
.cart-table { width:100%; border-collapse:collapse; }
.cart-table thead { background:#34495e; color:white; }
.cart-table th { padding:14px 16px; text-align:left; font-weight:500; font-size:14px; }
.cart-table td { padding:12px 16px; border-bottom:1px solid #eee; font-size:14px; vertical-align:middle; }
.cart-table tbody tr:last-child td { border-bottom:none; }
.cart-table td.num { text-align:right; font-variant-numeric:tabular-nums; }

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
</style>

<div class="container">
    <div class="cart-header">
        <h1>장바구니</h1>
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

    <div id="cart-content">
        <div class="no-data">불러오는 중...</div>
    </div>
</div>

<div class="cart-toast" id="cart-toast"></div>

<script>
const CSRF_TOKEN = '<?php echo generate_csrf_token(); ?>';

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
let currentBasis = 'cash';        // 'cash' | 'card' — 상단 라디오로 전환

// PHP calc_card_price()와 동일한 공식 (현금가 -> 카드가 환산)
function calcCardPrice(cash) {
    if (!cash) return null;
    return Math.ceil(cash / 0.97 * 1.1 / 1000) * 1000;
}

function onBasisChange(basis) {
    currentBasis = basis;
    renderCart();
}

function loadCart() {
    fetch('<?php echo BASE_URL; ?>/api/cart/list.php')
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                document.getElementById('cart-content').innerHTML =
                    `<div class="no-data">${data.message}</div>`;
                return;
            }
            cartItemsCache = data.items;
            renderCart();
        })
        .catch(() => {
            document.getElementById('cart-content').innerHTML =
                '<div class="no-data">장바구니를 불러오지 못했습니다.</div>';
        });
}

// 한 라인의 현재 적용 쿠폰 상태(선택된 allocation_id, 단가/소계/할인/순금액)를
// 기준가격(currentBasis)에 맞춰 계산.
function computeLine(item) {
    if (item.unit_price === null) {
        return { selectedId: '', unitPrice: null, lineTotal: null, discount: 0, net: null };
    }

    const cashUnit  = item.unit_price;
    const cardUnit  = calcCardPrice(cashUnit);
    const baseUnit  = currentBasis === 'card' ? cardUnit : cashUnit;
    const baseTotal = baseUnit * item.quantity;

    const override = couponOverride[item.cart_item_id];
    const selectedId = override !== undefined ? override : (item.applied_coupon_allocation_id ?? '');

    if (selectedId === '') {
        return { selectedId: '', unitPrice: baseUnit, lineTotal: baseTotal, discount: 0, net: baseTotal };
    }
    const coupon = item.available_coupons.find(c => String(c.allocation_id) === String(selectedId));
    if (!coupon) {
        return { selectedId: '', unitPrice: baseUnit, lineTotal: baseTotal, discount: 0, net: baseTotal };
    }

    const appliedQty = Math.min(item.quantity, coupon.remaining_qty);
    let discount;

    if (currentBasis === 'cash') {
        // coupon.effective_amount는 서버에서 이미 현금가 기준으로 계산해둔 값
        discount = coupon.effective_amount * appliedQty;
    } else {
        // 카드가 기준: 현금가에서 먼저 할인 적용 -> 카드가로 환산 -> 그 차액이 "카드가 기준 할인액"
        const discountedCash = cashUnit - coupon.effective_amount;
        const discountedCard = calcCardPrice(discountedCash);
        discount = (cardUnit - discountedCard) * appliedQty;
    }

    return { selectedId, unitPrice: baseUnit, lineTotal: baseTotal, discount, net: baseTotal - discount };
}

function renderCart() {
    const content = document.getElementById('cart-content');
    const items = cartItemsCache;

    if (items.length === 0) {
        content.innerHTML = '<div class="cart-table-wrap"><div class="no-data">장바구니가 비어있습니다.</div></div>';
        return;
    }

    let rows = '';
    let totalBase = 0, totalDiscount = 0;

    items.forEach(item => {
        const line = computeLine(item);
        if (line.lineTotal !== null) totalBase += line.lineTotal;
        totalDiscount += line.discount;

        const priceCell = line.unitPrice !== null
            ? number_format(line.unitPrice) + '원'
            : '<span class="price-missing">이번 달 단가 미등록</span><div class="row-price-missing-note">계산에서 제외됨</div>';

        let couponCell = '-';
        if (item.available_coupons.length > 0) {
            const options = ['<option value="">쿠폰 미적용</option>'].concat(
                item.available_coupons.map(c => `
                    <option value="${c.allocation_id}" ${String(c.allocation_id) === String(line.selectedId) ? 'selected' : ''}>
                        ${escapeHtml(c.coupon_name)} (${c.discount_type === 'percent' ? c.discount_value + '%' : number_format(c.discount_value) + '원'})
                    </option>
                `)
            );
            couponCell = `<select class="coupon-select" onchange="onCouponChange(${item.cart_item_id}, this.value)">${options.join('')}</select>`;
        }

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

    const netBeforeVat = totalBase - totalDiscount;
    // 현금가는 "부가세별도" 표시이므로 예상결제금액엔 10% 부가세를 더해야 함.
    // 카드가는 calc_card_price() 자체가 이미 부가세+카드수수료를 반영한 값이라 별도 가산 안 함.
    const vat      = currentBasis === 'cash' ? Math.round(netBeforeVat * 0.1) : 0;
    const totalNet = netBeforeVat + vat;

    content.innerHTML = `
        <div class="cart-table-wrap">
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
        <div class="cart-summary">
            <div class="summary-row"><span>상품금액</span><span>${number_format(totalBase)}원</span></div>
            <div class="summary-row discount"><span>쿠폰 할인</span><span>-${number_format(totalDiscount)}원</span></div>
            ${currentBasis === 'cash' ? `<div class="summary-row"><span>부가세 (10%)</span><span>+${number_format(vat)}원</span></div>` : ''}
            <div class="summary-row final"><span>예상결제금액</span><span>${number_format(totalNet)}원</span></div>
        </div>
        <div class="cart-actions">
            <button type="button" class="btn-clear-cart" onclick="clearCart()">전체 비우기</button>
        </div>
    `;
}

function onCouponChange(cartItemId, allocationId) {
    couponOverride[cartItemId] = allocationId; // '' 포함
    renderCart();
}

function updateQty(cartItemId, quantity) {
    quantity = parseInt(quantity, 10);
    if (!quantity || quantity < 1) {
        showCartToast('수량을 확인해주세요.', true);
        loadCart();
        return;
    }
    fetch('<?php echo BASE_URL; ?>/api/cart/update_qty.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `cart_item_id=${cartItemId}&quantity=${quantity}&csrf_token=${encodeURIComponent(CSRF_TOKEN)}`
    })
    .then(r => r.json())
    .then(data => {
        if (!data.success) showCartToast(data.message, true);
        loadCart();
    })
    .catch(() => { showCartToast('오류가 발생했습니다.', true); loadCart(); });
}

function removeRow(cartItemId) {
    if (!confirm('이 항목을 삭제하시겠습니까?')) return;
    fetch('<?php echo BASE_URL; ?>/api/cart/delete_row.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `cart_item_id=${cartItemId}&csrf_token=${encodeURIComponent(CSRF_TOKEN)}`
    })
    .then(r => r.json())
    .then(data => {
        showCartToast(data.message, !data.success);
        loadCart();
    })
    .catch(() => showCartToast('오류가 발생했습니다.', true));
}

function clearCart() {
    if (!confirm('장바구니를 전체 비우시겠습니까?')) return;
    fetch('<?php echo BASE_URL; ?>/api/cart/clear.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `csrf_token=${encodeURIComponent(CSRF_TOKEN)}`
    })
    .then(r => r.json())
    .then(data => {
        showCartToast(data.message, !data.success);
        loadCart();
    })
    .catch(() => showCartToast('오류가 발생했습니다.', true));
}

function number_format(num) {
    return Math.round(num).toLocaleString();
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

document.addEventListener('DOMContentLoaded', loadCart);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>