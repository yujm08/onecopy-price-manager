<?php
/**
 * admin/price_manage.php 와 client/catalog.php(예정) 양쪽에서 공용으로 쓰는 함수 모음.
 * 원본 price_manage.php에서 그대로(내용 변경 없이) 옮긴 함수
 */

function calc_card_price($cash) {
    if (!$cash) return null;
    return (int)(ceil((float)$cash / 0.97 * 1.1 / 1000) * 1000);
}