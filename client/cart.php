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
.container { max-width: 900px; margin: 0 auto; padding: 0 16px; box-sizing: border-box; }

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

.cart-summary { display:flex; justify-content:space-between; align-items:center; background:white; padding:20px 24px; border-radius:8px; box-shadow:0 2px 4px rgba(0,0,0,0.08); }
.cart-total-label { font-size:15px; color:#555; }
.cart-total-amount { font-size:22px; font-weight:700; color:#2c3e50; }

.btn-clear-cart { padding:10px 18px; background:#95a5a6; color:white; border:none; border-radius:4px; cursor:pointer; font-size:14px; }
.btn-clear-cart:hover { background:#7f8c8d; }

.cart-actions { display:flex; justify-content:flex-end; gap:10px; margin-top:16px; }

.no-data { padding:60px 20px; text-align:center; color:#7f8c8d; }
.price-missing { color:#e67e22; font-size:12px; }

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
</style>

<div class="container">
    <div class="cart-header">
        <h1>장바구니</h1>
        <a href="<?php echo BASE_URL; ?>/client/catalog.php" class="btn-back">← 상품 목록으로</a>
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

function loadCart() {
    fetch('<?php echo BASE_URL; ?>/api/cart/list.php')
        .then(r => r.json())
        .then(data => {
            if (!data.success) {
                document.getElementById('cart-content').innerHTML =
                    `<div class="no-data">${data.message}</div>`;
                return;
            }
            renderCart(data.items, data.total);
        })
        .catch(() => {
            document.getElementById('cart-content').innerHTML =
                '<div class="no-data">장바구니를 불러오지 못했습니다.</div>';
        });
}

function renderCart(items, total) {
    const content = document.getElementById('cart-content');

    if (items.length === 0) {
        content.innerHTML = '<div class="cart-table-wrap"><div class="no-data">장바구니가 비어있습니다.</div></div>';
        return;
    }

    let rows = '';
    items.forEach(item => {
        const priceCell = item.unit_price !== null
            ? number_format(item.unit_price) + '원'
            : '<span class="price-missing">이번 달 단가 미등록</span>';
        const lineTotalCell = item.line_total !== null ? number_format(item.line_total) + '원' : '-';

        rows += `
            <tr data-cart-item-id="${item.cart_item_id}">
                <td>${escapeHtml(item.product_number)}</td>
                <td>${escapeHtml(item.product_name)}</td>
                <td class="num">${priceCell}</td>
                <td class="num">
                    <input type="number" class="qty-input" value="${item.quantity}" min="1" step="1"
                        onchange="updateQty(${item.cart_item_id}, this.value)">
                </td>
                <td class="num">${lineTotalCell}</td>
                <td><button type="button" class="btn-remove-row" onclick="removeRow(${item.cart_item_id})">삭제</button></td>
            </tr>
        `;
    });

    content.innerHTML = `
        <div class="cart-table-wrap">
            <table class="cart-table">
                <thead>
                    <tr>
                        <th>제품번호</th>
                        <th>제품명</th>
                        <th>단가</th>
                        <th>수량</th>
                        <th>소계</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>${rows}</tbody>
            </table>
        </div>
        <div class="cart-summary">
            <span class="cart-total-label">합계</span>
            <span class="cart-total-amount">${number_format(total)}원</span>
        </div>
        <div class="cart-actions">
            <button type="button" class="btn-clear-cart" onclick="clearCart()">전체 비우기</button>
        </div>
    `;
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