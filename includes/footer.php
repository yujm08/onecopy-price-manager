<?php
// includes/footer.php
// 페이지 공통 푸터
?>
    <footer class="main-footer">
        <p>본 시스템의 모든 데이터는 기밀 정보입니다. 무단 유출 시 법적 책임이 발생할 수 있습니다.</p>
        <p>&copy; 2026. All rights reserved.</p>
    </footer>
</body>
</html>

<script>
// 알림 메시지 자동 사라짐 (3초 후 페이드아웃)
document.querySelectorAll('[data-auto-dismiss]').forEach(function(el) {
    setTimeout(function() {
        el.style.transition = 'opacity 0.6s ease';
        el.style.opacity = '0';
        setTimeout(function() { el.style.display = 'none'; }, 600);
    }, 5000);
});

// 우클릭 방지
document.addEventListener('contextmenu', function(e) {
    e.preventDefault();
});

// 인쇄, 저장, 개발자도구 단축키 차단
document.addEventListener('keydown', function(e) {
    // Ctrl+P (인쇄), Ctrl+S (저장), Ctrl+U (소스보기)
    if (e.ctrlKey && ['p', 's', 'u'].includes(e.key.toLowerCase())) {
        e.preventDefault();
        return false;
    }
    F12 (개발자도구)
    if (e.key === 'F12') {
        e.preventDefault();
        return false;
    }
    Ctrl+Shift+I / Ctrl+Shift+J (개발자도구)
    if (e.ctrlKey && e.shiftKey && ['i', 'j'].includes(e.key.toLowerCase())) {
        e.preventDefault();
        return false;
    }
});

// 드래그로 텍스트 선택 방지 (테이블 영역)
document.querySelectorAll('.price-table-wrap, .price-table-scroll').forEach(function(el) {
    el.addEventListener('dragstart', function(e) { e.preventDefault(); });
    el.addEventListener('selectstart', function(e) { e.preventDefault(); });
});
</script>