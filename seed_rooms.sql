-- =====================================================
-- SEED DATA: Phòng thi - Trường Đại học Thủ Dầu Một
-- Địa chỉ: 06 Trần Văn Ơn, Phú Hòa, Thủ Dầu Một, Bình Dương
-- Dựa theo sơ đồ mặt bằng thực tế của trường
-- =====================================================

CREATE TABLE IF NOT EXISTS rooms (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    room_code   VARCHAR(20)  NOT NULL UNIQUE,
    room_name   VARCHAR(100) NOT NULL,
    building    VARCHAR(50)  NOT NULL,
    floor       TINYINT      NOT NULL DEFAULT 1,
    capacity    INT          NOT NULL DEFAULT 40,
    room_type   ENUM('lecture','lab','exam_hall','seminar') NOT NULL DEFAULT 'lecture',
    status      ENUM('active','maintenance','inactive') NOT NULL DEFAULT 'active',
    note        VARCHAR(255) NULL,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =====================================================
-- KHU A - Dãy phòng học lý thuyết (phía Bắc)
-- =====================================================
INSERT INTO rooms (room_code, room_name, building, floor, capacity, room_type, note) VALUES
('A1.01', 'Phòng A1.01', 'Dãy A1', 1, 45, 'lecture', 'Phòng học lý thuyết'),
('A1.02', 'Phòng A1.02', 'Dãy A1', 1, 45, 'lecture', 'Phòng học lý thuyết'),
('A1.03', 'Phòng A1.03', 'Dãy A1', 1, 45, 'lecture', 'Phòng học lý thuyết'),
('A1.04', 'Phòng A1.04', 'Dãy A1', 1, 45, 'lecture', 'Phòng học lý thuyết'),
('A1.05', 'Phòng A1.05', 'Dãy A1', 1, 45, 'lecture', 'Phòng học lý thuyết'),
('A1.06', 'Phòng A1.06', 'Dãy A1', 1, 45, 'lecture', 'Phòng học lý thuyết'),
('A2.01', 'Phòng A2.01', 'Dãy A2', 2, 45, 'lecture', 'Phòng học lý thuyết'),
('A2.02', 'Phòng A2.02', 'Dãy A2', 2, 45, 'lecture', 'Phòng học lý thuyết'),
('A2.03', 'Phòng A2.03', 'Dãy A2', 2, 45, 'lecture', 'Phòng học lý thuyết'),
('A2.04', 'Phòng A2.04', 'Dãy A2', 2, 45, 'lecture', 'Phòng học lý thuyết'),
('A2.05', 'Phòng A2.05', 'Dãy A2', 2, 45, 'lecture', 'Phòng học lý thuyết'),
('A2.06', 'Phòng A2.06', 'Dãy A2', 2, 45, 'lecture', 'Phòng học lý thuyết'),
('A3.01', 'Phòng A3.01', 'Dãy A3', 3, 45, 'lecture', 'Phòng học lý thuyết'),
('A3.02', 'Phòng A3.02', 'Dãy A3', 3, 45, 'lecture', 'Phòng học lý thuyết'),
('A3.03', 'Phòng A3.03', 'Dãy A3', 3, 45, 'lecture', 'Phòng học lý thuyết'),
('A3.04', 'Phòng A3.04', 'Dãy A3', 3, 45, 'lecture', 'Phòng học lý thuyết'),
('A3.05', 'Phòng A3.05', 'Dãy A3', 3, 45, 'lecture', 'Phòng học lý thuyết'),
('A3.06', 'Phòng A3.06', 'Dãy A3', 3, 45, 'lecture', 'Phòng học lý thuyết'),
('A4.01', 'Phòng A4.01', 'Dãy A4', 4, 45, 'lecture', 'Phòng học lý thuyết'),
('A4.02', 'Phòng A4.02', 'Dãy A4', 4, 45, 'lecture', 'Phòng học lý thuyết'),
('A4.03', 'Phòng A4.03', 'Dãy A4', 4, 45, 'lecture', 'Phòng học lý thuyết'),
('A4.04', 'Phòng A4.04', 'Dãy A4', 4, 45, 'lecture', 'Phòng học lý thuyết'),
('A4.05', 'Phòng A4.05', 'Dãy A4', 4, 45, 'lecture', 'Phòng học lý thuyết'),
('A4.06', 'Phòng A4.06', 'Dãy A4', 4, 45, 'lecture', 'Phòng học lý thuyết');

-- =====================================================
-- KHU B - Dãy phòng học lý thuyết (phía Đông)
-- =====================================================
INSERT INTO rooms (room_code, room_name, building, floor, capacity, room_type, note) VALUES
('B1.01', 'Phòng B1.01', 'Dãy B1', 1, 45, 'lecture', 'Phòng học lý thuyết'),
('B1.02', 'Phòng B1.02', 'Dãy B1', 1, 45, 'lecture', 'Phòng học lý thuyết'),
('B1.03', 'Phòng B1.03', 'Dãy B1', 1, 45, 'lecture', 'Phòng học lý thuyết'),
('B1.04', 'Phòng B1.04', 'Dãy B1', 1, 45, 'lecture', 'Phòng học lý thuyết'),
('B1.05', 'Phòng B1.05', 'Dãy B1', 1, 45, 'lecture', 'Phòng học lý thuyết'),
('B2.01', 'Phòng B2.01', 'Dãy B2', 2, 45, 'lecture', 'Phòng học lý thuyết'),
('B2.02', 'Phòng B2.02', 'Dãy B2', 2, 45, 'lecture', 'Phòng học lý thuyết'),
('B2.03', 'Phòng B2.03', 'Dãy B2', 2, 45, 'lecture', 'Phòng học lý thuyết'),
('B2.04', 'Phòng B2.04', 'Dãy B2', 2, 45, 'lecture', 'Phòng học lý thuyết'),
('B2.05', 'Phòng B2.05', 'Dãy B2', 2, 45, 'lecture', 'Phòng học lý thuyết'),
('B3.01', 'Phòng B3.01', 'Dãy B3', 3, 45, 'lecture', 'Phòng học lý thuyết'),
('B3.02', 'Phòng B3.02', 'Dãy B3', 3, 45, 'lecture', 'Phòng học lý thuyết'),
('B3.03', 'Phòng B3.03', 'Dãy B3', 3, 45, 'lecture', 'Phòng học lý thuyết'),
('B3.04', 'Phòng B3.04', 'Dãy B3', 3, 45, 'lecture', 'Phòng học lý thuyết'),
('B3.05', 'Phòng B3.05', 'Dãy B3', 3, 45, 'lecture', 'Phòng học lý thuyết'),
('B4.01', 'Phòng B4.01', 'Dãy B4', 4, 45, 'lecture', 'Phòng học lý thuyết'),
('B4.02', 'Phòng B4.02', 'Dãy B4', 4, 45, 'lecture', 'Phòng học lý thuyết'),
('B4.03', 'Phòng B4.03', 'Dãy B4', 4, 45, 'lecture', 'Phòng học lý thuyết'),
('B4.04', 'Phòng B4.04', 'Dãy B4', 4, 45, 'lecture', 'Phòng học lý thuyết'),
('B4.05', 'Phòng B4.05', 'Dãy B4', 4, 45, 'lecture', 'Phòng học lý thuyết');

-- =====================================================
-- KHU C - Phòng máy tính / Thực hành CNTT
-- =====================================================
INSERT INTO rooms (room_code, room_name, building, floor, capacity, room_type, note) VALUES
('C1.01', 'Phòng máy C1.01', 'Dãy C1', 1, 40, 'lab', 'Phòng máy tính - 40 máy'),
('C1.02', 'Phòng máy C1.02', 'Dãy C1', 1, 40, 'lab', 'Phòng máy tính - 40 máy'),
('C1.03', 'Phòng máy C1.03', 'Dãy C1', 1, 40, 'lab', 'Phòng máy tính - 40 máy'),
('C2.01', 'Phòng máy C2.01', 'Dãy C2', 2, 40, 'lab', 'Phòng máy tính - 40 máy'),
('C2.02', 'Phòng máy C2.02', 'Dãy C2', 2, 40, 'lab', 'Phòng máy tính - 40 máy'),
('C2.03', 'Phòng máy C2.03', 'Dãy C2', 2, 40, 'lab', 'Phòng máy tính - 40 máy'),
('C3.01', 'Phòng máy C3.01', 'Dãy C3', 3, 40, 'lab', 'Phòng máy tính - 40 máy'),
('C3.02', 'Phòng máy C3.02', 'Dãy C3', 3, 40, 'lab', 'Phòng máy tính - 40 máy'),
('C3.03', 'Phòng máy C3.03', 'Dãy C3', 3, 40, 'lab', 'Phòng máy tính - 40 máy'),
('C4.01', 'Phòng máy C4.01', 'Dãy C4', 4, 40, 'lab', 'Phòng máy tính - 40 máy'),
('C4.02', 'Phòng máy C4.02', 'Dãy C4', 4, 40, 'lab', 'Phòng máy tính - 40 máy'),
('C4.03', 'Phòng máy C4.03', 'Dãy C4', 4, 40, 'lab', 'Phòng máy tính - 40 máy');

-- =====================================================
-- KHU D - Phòng thí nghiệm chuyên ngành
-- =====================================================
INSERT INTO rooms (room_code, room_name, building, floor, capacity, room_type, note) VALUES
('D1.01', 'Phòng TN D1.01', 'Dãy D1', 1, 30, 'lab', 'Phòng thí nghiệm Hóa học'),
('D1.02', 'Phòng TN D1.02', 'Dãy D1', 1, 30, 'lab', 'Phòng thí nghiệm Sinh học'),
('D1.03', 'Phòng TN D1.03', 'Dãy D1', 1, 30, 'lab', 'Phòng thí nghiệm Vật lý'),
('D2.01', 'Phòng TN D2.01', 'Dãy D2', 2, 30, 'lab', 'Phòng thí nghiệm Điện - Điện tử'),
('D2.02', 'Phòng TN D2.02', 'Dãy D2', 2, 30, 'lab', 'Phòng thực hành Kế toán'),
('D2.03', 'Phòng TN D2.03', 'Dãy D2', 2, 30, 'lab', 'Phòng thực hành Kinh tế'),
('D3.01', 'Phòng TN D3.01', 'Dãy D3', 3, 30, 'lab', 'Phòng thực hành Ngoại ngữ'),
('D3.02', 'Phòng TN D3.02', 'Dãy D3', 3, 30, 'lab', 'Phòng thực hành Ngoại ngữ');

-- =====================================================
-- KHU E - Hội trường & Giảng đường lớn
-- =====================================================
INSERT INTO rooms (room_code, room_name, building, floor, capacity, room_type, note) VALUES
('HT.A',  'Hội trường A',   'Hội trường', 1, 300, 'exam_hall', 'Hội trường lớn - thi tập trung'),
('HT.B',  'Hội trường B',   'Hội trường', 1, 300, 'exam_hall', 'Hội trường lớn - thi tập trung'),
('GD.01', 'Giảng đường 01', 'Giảng đường', 1, 120, 'exam_hall', 'Giảng đường lớn'),
('GD.02', 'Giảng đường 02', 'Giảng đường', 1, 120, 'exam_hall', 'Giảng đường lớn'),
('GD.03', 'Giảng đường 03', 'Giảng đường', 2, 120, 'exam_hall', 'Giảng đường lớn'),
('GD.04', 'Giảng đường 04', 'Giảng đường', 2, 120, 'exam_hall', 'Giảng đường lớn');

-- =====================================================
-- KHU F - Phòng hội thảo / Seminar
-- =====================================================
INSERT INTO rooms (room_code, room_name, building, floor, capacity, room_type, note) VALUES
('F1.01', 'Phòng hội thảo F1.01', 'Dãy F1', 1, 60, 'seminar', 'Phòng hội thảo nhỏ'),
('F1.02', 'Phòng hội thảo F1.02', 'Dãy F1', 1, 60, 'seminar', 'Phòng hội thảo nhỏ'),
('F2.01', 'Phòng hội thảo F2.01', 'Dãy F2', 2, 80, 'seminar', 'Phòng hội thảo vừa'),
('F2.02', 'Phòng hội thảo F2.02', 'Dãy F2', 2, 80, 'seminar', 'Phòng hội thảo vừa');
