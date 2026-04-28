-- 업체 (비밀번호는 phpMyAdmin에서 import 후 별도 UPDATE로 해싱 필요 — 아래 주석 참고)
INSERT INTO companies (company_name, representative_name, manager_name, phone, grade, password, is_admin) VALUES
('원카피',         '홍길동', '홍길동', '02-0000-0000',   NULL, '__HASH_PLACEHOLDER__', 1),
('A등급 테스트업체', '김철수', '이영희', '010-1111-2222', 'A',  '__HASH_PLACEHOLDER__', 0),
('B등급 테스트업체', '박민준', '최지연', '010-3333-4444', 'B',  '__HASH_PLACEHOLDER__', 0),
('C등급 테스트업체', '정수아', '한동훈', '010-1234-5678', 'C',  '__HASH_PLACEHOLDER__', 0);

-- 카테고리
INSERT INTO categories (category_name, show_brand, show_description) VALUES
('토너',   1, 1),
('복사기', 1, 1),
('잉크',   1, 1),
('A4용지', 0, 1);

-- 브랜드 (토너=1, 복사기=2, 잉크=3, A4용지=4)
INSERT INTO brands (category_id, brand_name) VALUES
(1, '삼성'), (1, 'HP'), (1, '신도'),
(2, '캐논'), (2, '신도'),
(3, '엡손'), (3, '캐논'),
(4, '더블에이');

-- 제품 (product_id 1~8)
INSERT INTO products (product_number, category_id, brand_id, product_name, description) VALUES
('0001', 1, 1, '삼성 CLT-K406S 검정 토너',  '삼성 SL-C410W 호환'),
('0002', 1, 2, 'HP CE285A 레이저 토너',     'HP P1102W 호환'),
('0003', 1, 3, '신도 정품 토너 SD-2160',    '신도 2160 전용'),
('0004', 2, 4, '캐논 iR2206 복사기',        'A3 지원, 25ppm'),
('0005', 2, 5, '신도 복합기 N400',          '팩스/복사/스캔'),
('0006', 3, 6, '엡손 T664 4색 잉크 세트',   'L100/L200 호환'),
('0007', 3, 7, '캐논 GI-790 대용량 잉크',   'G시리즈 호환'),
('0008', 4, 8, '더블에이 A4 80g 500매',     '5묶음 단위 판매');

-- 태그
INSERT INTO product_tags (product_id, tag) VALUES
(1, '정품'), (1, '검정'),
(2, '정품'),
(4, 'A3'),
(5, '팩스'), (5, '복합기'),
(6, '4색'),
(7, '대용량'),
(8, '소모품');

-- 이번 달 가격
INSERT INTO prices (product_id, price_month, cash_price_a, cash_price_b, cash_price_c) VALUES
(1, DATE_FORMAT(NOW(), '%Y-%m-01'),  25000,  28000,  31000),
(2, DATE_FORMAT(NOW(), '%Y-%m-01'),  22000,  25000,  28000),
(3, DATE_FORMAT(NOW(), '%Y-%m-01'),  18000,  21000,  24000),
(4, DATE_FORMAT(NOW(), '%Y-%m-01'), 550000, 600000, 650000),
(5, DATE_FORMAT(NOW(), '%Y-%m-01'), 320000, 350000, 380000),
(6, DATE_FORMAT(NOW(), '%Y-%m-01'),  32000,  35000,  38000),
(7, DATE_FORMAT(NOW(), '%Y-%m-01'),  28000,  31000,  34000),
(8, DATE_FORMAT(NOW(), '%Y-%m-01'),   5000,   5500,   6000);