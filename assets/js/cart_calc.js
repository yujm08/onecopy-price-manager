/**
 * assets/js/cart_calc.js
 * -------------------------------------------------
 * 장바구니 금액 계산 공용 로직.
 * 사용처: client/cart.php (PC 테이블 + 모바일 카드), client/catalog.php (하단 요약바)
 *
 * ⚠️ calcCardPrice()는 includes/catalog_helpers.php의 calc_card_price()와 반드시 같은 공식이어야 함.
 * ⚠️ 이 파일의 로직은 원래 client/cart.php 인라인 스크립트에 있던 것을 동작 변경 없이 옮긴 것.
 *    (차이점: 전역변수 currentBasis/couponOverride를 읽던 것을 파라미터로 받도록만 변경)
 */
const CartCalc = (function () {
  // PHP calc_card_price()와 동일한 공식 (현금가 -> 카드가 환산)
  function calcCardPrice(cash) {
    if (!cash) return null;
    return Math.ceil(((cash / 0.97) * 1.1) / 1000) * 1000;
  }

  /**
   * 한 라인의 현재 적용 쿠폰 상태(선택된 allocation_id, 단가/소계/할인/순금액)를
   * 기준가격(basis: 'cash' | 'card')에 맞춰 계산.
   * overrides: { cart_item_id: allocation_id | '' } — 없으면 서버 기본값(applied_coupon_allocation_id) 사용
   */
  function computeLine(item, basis, overrides) {
    if (item.unit_price === null) {
      return {
        selectedId: "",
        unitPrice: null,
        lineTotal: null,
        discount: 0,
        net: null,
        appliedQty: 0,
        coupon: null,
      };
    }

    const cashUnit = item.unit_price;
    const cardUnit = calcCardPrice(cashUnit);
    const baseUnit = basis === "card" ? cardUnit : cashUnit;
    const baseTotal = baseUnit * item.quantity;

    const noCoupon = {
      selectedId: "",
      unitPrice: baseUnit,
      lineTotal: baseTotal,
      discount: 0,
      net: baseTotal,
      appliedQty: 0,
      coupon: null,
    };

    const override = overrides ? overrides[item.cart_item_id] : undefined;
    const selectedId =
      override !== undefined
        ? override
        : (item.applied_coupon_allocation_id ?? "");
    if (selectedId === "") return noCoupon;

    const coupon = item.available_coupons.find(
      (c) => String(c.allocation_id) === String(selectedId),
    );
    if (!coupon) return noCoupon;

    // 쿠폰은 대당 1장 — 잔여수량만큼만 적용, 나머지 수량은 정가
    const appliedQty = Math.min(item.quantity, coupon.remaining_qty);
    let discount;

    if (basis === "cash") {
      // coupon.effective_amount는 서버에서 이미 현금가 기준으로 계산해둔 값
      discount = coupon.effective_amount * appliedQty;
    } else {
      // 카드가 기준: 현금가에서 먼저 할인 적용 -> 카드가로 환산 -> 그 차액이 "카드가 기준 할인액"
      const discountedCard = calcCardPrice(cashUnit - coupon.effective_amount);
      discount = (cardUnit - discountedCard) * appliedQty;
    }

    return {
      selectedId,
      unitPrice: baseUnit,
      lineTotal: baseTotal,
      discount,
      net: baseTotal - discount,
      appliedQty,
      coupon,
    };
  }

  /**
   * 전체 합계.
   * 현금가는 "부가세별도" 표시이므로 예상결제금액엔 10% 부가세를 더함.
   * 카드가는 calc_card_price() 자체가 이미 부가세+카드수수료를 반영한 값이라 별도 가산 안 함.
   */
  function computeSummary(items, basis, overrides) {
    const lines = {};
    let totalBase = 0,
      totalDiscount = 0,
      totalQty = 0;

    items.forEach((item) => {
      const line = computeLine(item, basis, overrides);
      lines[item.cart_item_id] = line;
      if (line.lineTotal !== null) totalBase += line.lineTotal;
      totalDiscount += line.discount;
      totalQty += item.quantity;
    });

    const netBeforeVat = totalBase - totalDiscount;
    const vat = basis === "cash" ? Math.round(netBeforeVat * 0.1) : 0;

    return {
      lines,
      totalBase,
      totalDiscount,
      vat,
      totalNet: netBeforeVat + vat,
      totalQty,
    };
  }

  /**
   * list.php가 구분 순으로 정렬해서 주므로, 순서를 유지한 채 구분별로 묶기만 함.
   * 반환: [ { category_id, category_name, items: [...] }, ... ]
   */
  function groupByCategory(items) {
    const groups = [];
    let current = null;
    items.forEach((item) => {
      if (!current || current.category_id !== item.category_id) {
        current = {
          category_id: item.category_id,
          category_name: item.category_name || "미분류",
          items: [],
        };
        groups.push(current);
      }
      current.items.push(item);
    });
    return groups;
  }

  function numberFormat(num) {
    return Math.round(num).toLocaleString();
  }

  function escapeHtml(str) {
    const div = document.createElement("div");
    div.textContent = str ?? "";
    return div.innerHTML;
  }

  return {
    calcCardPrice,
    computeLine,
    computeSummary,
    groupByCategory,
    numberFormat,
    escapeHtml,
  };
})();
