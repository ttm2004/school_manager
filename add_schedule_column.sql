-- Chạy SQL này để thêm cột lưu lịch học có cấu trúc
ALTER TABLE course_sections ADD COLUMN IF NOT EXISTS schedule_data JSON NULL AFTER schedule_text;

-- Cập nhật dữ liệu mẫu (6 buổi, mỗi buổi 5 tiết)
-- CNTT101_01: Thứ 2 tiết 1-5, Thứ 4 tiết 1-5, Thứ 6 tiết 1-5 (3 buổi/tuần x 2 tuần = 6 buổi)
UPDATE course_sections SET schedule_data = '[{"day":2,"session":"sang","period_start":1},{"day":4,"session":"sang","period_start":1},{"day":6,"session":"sang","period_start":1}]' WHERE section_code='CNTT101_01';
UPDATE course_sections SET schedule_data = '[{"day":3,"session":"chieu","period_start":1},{"day":5,"session":"chieu","period_start":1},{"day":7,"session":"chieu","period_start":1}]' WHERE section_code='CNTT102_01';
UPDATE course_sections SET schedule_data = '[{"day":4,"session":"sang","period_start":1},{"day":6,"session":"sang","period_start":1},{"day":2,"session":"chieu","period_start":1}]' WHERE section_code='CNTT201_01';
UPDATE course_sections SET schedule_data = '[{"day":5,"session":"chieu","period_start":1},{"day":7,"session":"chieu","period_start":1},{"day":3,"session":"sang","period_start":1}]' WHERE section_code='CNTT202_01';
UPDATE course_sections SET schedule_data = '[{"day":6,"session":"chieu","period_start":1},{"day":2,"session":"toi","period_start":1},{"day":4,"session":"toi","period_start":1}]' WHERE section_code='CNTT203_01';
UPDATE course_sections SET schedule_data = '[{"day":2,"session":"chieu","period_start":1},{"day":4,"session":"chieu","period_start":1},{"day":6,"session":"chieu","period_start":1}]' WHERE section_code='KTPM101_01';
UPDATE course_sections SET schedule_data = '[{"day":3,"session":"sang","period_start":1},{"day":5,"session":"sang","period_start":1},{"day":7,"session":"sang","period_start":1}]' WHERE section_code='KTPM201_01';
UPDATE course_sections SET schedule_data = '[{"day":5,"session":"sang","period_start":1},{"day":7,"session":"sang","period_start":1},{"day":3,"session":"chieu","period_start":1}]' WHERE section_code='QTKD101_01';
UPDATE course_sections SET schedule_data = '[{"day":6,"session":"sang","period_start":1},{"day":2,"session":"sang","period_start":6},{"day":4,"session":"sang","period_start":6}]' WHERE section_code='KT101_01';
UPDATE course_sections SET schedule_data = '[{"day":7,"session":"sang","period_start":1},{"day":3,"session":"toi","period_start":1},{"day":5,"session":"toi","period_start":1}]' WHERE section_code='NNA101_01';
