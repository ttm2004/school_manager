-- ============================================================
-- Seed: Chương trình đào tạo (curriculum)
-- Liên kết môn học với ngành
-- ============================================================
SET NAMES utf8mb4;

-- Kiểm tra bảng curriculum tồn tại
-- Nếu chưa có, chạy create_curriculum_table.sql trước

-- ── CTĐT ngành Kế toán (major_id=5) ──────────────────────────
INSERT IGNORE INTO `curriculum`
    (`major_id`,`subject_id`,`credits`,`suggested_semester`,`subject_type`)
SELECT 5, s.id, s.credits,
    CASE s.semester_order
        WHEN 1 THEN 1 WHEN 2 THEN 2 WHEN 3 THEN 3
        WHEN 4 THEN 4 WHEN 5 THEN 5 WHEN 6 THEN 6
        WHEN 7 THEN 7 WHEN 8 THEN 8 WHEN 9 THEN 9
        ELSE 1 END,
    s.subject_type
FROM subjects s
WHERE s.major_id = 5
  AND s.subject_code IN (
    'KETO023','LING127','LING185','LING346',
    'KTCH001','LING095','LING138','LING169','LING347',
    'LING166','LING293','LING330',
    'KETO010','KETO025','KETO028','LING096','LING238',
    'KETO011','KETO022','LING070','LING277',
    'KETO018','KETO012','KETO013','KETO014',
    'KETO008','KETO017','KETO024','KETO026','KETO034',
    'KETO004','KETO027','KETO031','KETO035','KETO036','LING181',
    'KETO003','KETO039','KETO042','KETO044',
    'KETO005','KETO020','KETO029','KETO030','KETO032',
    'TCNH034','KETO006','KETO009'
  );

-- ── CTĐT ngành CNTT (major_id=1) ─────────────────────────────
INSERT IGNORE INTO `curriculum`
    (`major_id`,`subject_id`,`credits`,`suggested_semester`,`subject_type`)
SELECT 1, s.id, s.credits, s.semester_order, s.subject_type
FROM subjects s
WHERE s.major_id = 1
  AND s.subject_code IN (
    'CNTT101','CNTT102','CNTT201','CNTT202','CNTT203',
    'CNTT301','CNTT302','CNTT303','CNTT401','CNTT402',
    'CNTT403','CNTT404','CNTT501',
    -- Môn đã có từ seed_curriculum.sql
    'GDTC1','GDQP1','TOAN1','XSTK','LTLT','TTHCM','TOAN2',
    'CTDL','LTHDT','KTMT','MLNL','TOAN3','GDTC2','LLCT1',
    'CSDL','HTTT','LTMANG','HHDH','LLCT2','GDQP2','TIENGANH1',
    'PTTKHT','LTJAVA','ANTT','TIENGANH2','LLCT3','LTMOBILE'
  );

SELECT 'Seed 003: curriculum done' AS status;
SELECT major_id, COUNT(*) AS subject_count, SUM(credits) AS total_credits
FROM curriculum WHERE deleted_at IS NULL
GROUP BY major_id;
