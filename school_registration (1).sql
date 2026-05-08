-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Máy chủ: 127.0.0.1
-- Thời gian đã tạo: Th5 04, 2026 lúc 10:19 AM
-- Phiên bản máy phục vụ: 10.4.32-MariaDB
-- Phiên bản PHP: 8.0.30

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Cơ sở dữ liệu: `school_registration`
--

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `admission_applications`
--

CREATE TABLE `admission_applications` (
  `id` int(11) NOT NULL,
  `major_id` int(11) NOT NULL,
  `method_id` int(11) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `gender` enum('Nam','Nữ','Khác') DEFAULT 'Nam',
  `birthday` date DEFAULT NULL,
  `citizen_id` varchar(20) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `high_school` varchar(255) DEFAULT NULL,
  `graduation_year` varchar(10) DEFAULT NULL,
  `math_score` decimal(4,2) DEFAULT NULL,
  `literature_score` decimal(4,2) DEFAULT NULL,
  `english_score` decimal(4,2) DEFAULT NULL,
  `total_score` decimal(4,2) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `status` enum('new','checking','approved','rejected') DEFAULT 'new',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `admission_applications`
--

INSERT INTO `admission_applications` (`id`, `major_id`, `method_id`, `full_name`, `gender`, `birthday`, `citizen_id`, `email`, `phone`, `address`, `high_school`, `graduation_year`, `math_score`, `literature_score`, `english_score`, `total_score`, `note`, `status`, `created_at`) VALUES
(1, 1, 1, 'Nguyễn Minh Nhật', 'Nam', '2008-03-12', '074208000001', 'nhat2008@gmail.com', '0903000001', 'Bình Dương', 'THPT Trịnh Hoài Đức', '2026', 8.20, 7.50, 8.00, 23.70, 'Đăng ký ngành Công nghệ thông tin bằng điểm thi THPT', 'approved', '2026-05-02 10:52:32'),
(2, 1, 2, 'Trần Thị Bích Ngọc', 'Nữ', '2008-05-20', '074208000002', 'ngoc2008@gmail.com', '0903000002', 'TP.HCM', 'THPT Nguyễn Trãi', '2026', 8.50, 8.00, 8.20, 24.70, 'Xét tuyển học bạ ngành Công nghệ thông tin', 'approved', '2026-05-02 10:52:32'),
(3, 2, 2, 'Lê Quốc Hưng', 'Nam', '2008-07-10', '074208000003', 'hung2008@gmail.com', '0903000003', 'Đồng Nai', 'THPT Long Thành', '2026', 7.80, 7.20, 7.50, 22.50, 'Đăng ký ngành Kỹ thuật phần mềm', 'approved', '2026-05-02 10:52:32'),
(4, 4, 1, 'Phạm Gia Hân', 'Nữ', '2008-09-18', '074208000004', 'han2008@gmail.com', '0903000004', 'Bình Phước', 'THPT Đồng Xoài', '2026', 7.00, 8.00, 7.80, 22.80, 'Đăng ký ngành Quản trị kinh doanh', 'new', '2026-05-02 10:52:32'),
(5, 5, 2, 'Võ Minh Quân', 'Nam', '2008-11-05', '074208000005', 'quan2008@gmail.com', '0903000005', 'Tây Ninh', 'THPT Tây Ninh', '2026', 7.50, 7.00, 7.20, 21.70, 'Đăng ký ngành Kế toán', 'checking', '2026-05-02 10:52:32'),
(6, 6, 1, 'Đặng Khánh Linh', 'Nữ', '2008-01-25', '074208000006', 'linh2008@gmail.com', '0903000006', 'Long An', 'THPT Tân An', '2026', 7.20, 8.30, 8.50, 24.00, 'Đăng ký ngành Ngôn ngữ Anh', 'approved', '2026-05-02 10:52:32'),
(7, 7, 3, 'Huỳnh Anh Khoa', 'Nam', '2008-04-08', '074208000007', 'khoa2008@gmail.com', '0903000007', 'Cần Thơ', 'THPT Châu Văn Liêm', '2026', 8.00, 8.00, 7.00, 23.00, 'Hồ sơ xét tuyển thẳng ngành Luật', 'new', '2026-05-02 10:52:32'),
(8, 8, 2, 'Ngô Thanh Thảo', 'Nữ', '2008-06-30', '074208000008', 'thao2008@gmail.com', '0903000008', 'Bình Định', 'THPT Quy Nhơn', '2026', 7.00, 8.50, 7.50, 23.00, 'Đăng ký ngành Giáo dục Tiểu học', 'approved', '2026-05-02 10:52:32');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `admission_methods`
--

CREATE TABLE `admission_methods` (
  `id` int(11) NOT NULL,
  `method_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `condition_text` text DEFAULT NULL,
  `status` enum('open','closed') DEFAULT 'open',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `admission_methods`
--

INSERT INTO `admission_methods` (`id`, `method_name`, `description`, `condition_text`, `status`, `created_at`) VALUES
(1, 'Xét tuyển theo kết quả thi tốt nghiệp THPT', 'Thí sinh sử dụng điểm thi tốt nghiệp THPT để đăng ký xét tuyển vào các ngành đào tạo.', 'Thí sinh đã tốt nghiệp THPT và có điểm thi đạt ngưỡng xét tuyển theo quy định.', 'open', '2026-05-02 10:52:32'),
(2, 'Xét tuyển theo học bạ THPT', 'Thí sinh sử dụng kết quả học tập THPT để đăng ký xét tuyển.', 'Điểm trung bình các môn theo tổ hợp xét tuyển đạt điều kiện của ngành đăng ký.', 'open', '2026-05-02 10:52:32'),
(3, 'Xét tuyển thẳng', 'Áp dụng cho thí sinh đạt giải học sinh giỏi, có chứng chỉ quốc tế hoặc thuộc diện ưu tiên theo quy định.', 'Thí sinh đáp ứng điều kiện xét tuyển thẳng theo đề án tuyển sinh.', 'open', '2026-05-02 10:52:32'),
(4, 'Xét tuyển theo kết quả đánh giá năng lực', 'Thí sinh sử dụng kết quả kỳ thi đánh giá năng lực để đăng ký xét tuyển.', 'Thí sinh có kết quả đánh giá năng lực đạt ngưỡng đảm bảo chất lượng đầu vào.', 'open', '2026-05-02 10:52:32');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `admission_news`
--

CREATE TABLE `admission_news` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `content` text NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('show','hide') DEFAULT 'show',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `admission_news`
--

INSERT INTO `admission_news` (`id`, `title`, `image`, `content`, `start_date`, `end_date`, `status`, `created_at`) VALUES
(1, 'Thông báo tuyển sinh đại học chính quy năm 2026', 'https://images.unsplash.com/photo-1523050854058-8df90110c9f1', 'Nhà trường thông báo tuyển sinh đại học chính quy năm 2026 với nhiều ngành đào tạo thuộc các lĩnh vực công nghệ thông tin, kinh tế, ngoại ngữ, luật và sư phạm. Thí sinh có thể đăng ký trực tuyến trên website tuyển sinh.', '2026-01-01', '2026-09-30', 'show', '2026-05-02 10:52:32'),
(2, 'Hướng dẫn đăng ký xét tuyển trực tuyến', 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3', 'Thí sinh chuẩn bị thông tin cá nhân, học bạ, giấy tờ tùy thân và chọn ngành đăng ký. Sau khi gửi hồ sơ, cán bộ tuyển sinh sẽ kiểm tra và phản hồi trạng thái hồ sơ.', '2026-01-10', '2026-08-31', 'show', '2026-05-02 10:52:32'),
(3, 'Danh sách ngành đào tạo tuyển sinh năm 2026', 'https://images.unsplash.com/photo-1519452575417-564c1401ecc0', 'Các ngành tuyển sinh gồm Công nghệ thông tin, Kỹ thuật phần mềm, Hệ thống thông tin, Quản trị kinh doanh, Kế toán, Ngôn ngữ Anh, Luật và Giáo dục Tiểu học.', '2026-01-15', '2026-09-30', 'show', '2026-05-02 10:52:32'),
(4, 'Thông báo thời gian nhận hồ sơ xét tuyển', 'https://images.unsplash.com/photo-1498243691581-b145c3f54a5a', 'Thời gian nhận hồ sơ xét tuyển bắt đầu từ tháng 1 đến tháng 9. Thí sinh cần theo dõi thông báo thường xuyên để không bỏ lỡ thời gian đăng ký.', '2026-01-20', '2026-09-15', 'show', '2026-05-02 10:52:32');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `classes`
--

CREATE TABLE `classes` (
  `id` int(11) NOT NULL,
  `major_id` int(11) NOT NULL,
  `class_code` varchar(30) NOT NULL,
  `class_name` varchar(100) NOT NULL,
  `school_year` varchar(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `classes`
--

INSERT INTO `classes` (`id`, `major_id`, `class_code`, `class_name`, `school_year`, `created_at`) VALUES
(1, 1, 'D22CNTT01', 'Đại học Công nghệ thông tin 01', '2022-2026', '2026-05-02 10:52:32'),
(2, 1, 'D22CNTT02', 'Đại học Công nghệ thông tin 02', '2022-2026', '2026-05-02 10:52:32'),
(3, 2, 'D22KTPM01', 'Đại học Kỹ thuật phần mềm 01', '2022-2026', '2026-05-02 10:52:32'),
(4, 3, 'D22HTTT01', 'Đại học Hệ thống thông tin 01', '2022-2026', '2026-05-02 10:52:32'),
(5, 4, 'D22QTKD01', 'Đại học Quản trị kinh doanh 01', '2022-2026', '2026-05-02 10:52:32'),
(6, 5, 'D22KT01', 'Đại học Kế toán 01', '2022-2026', '2026-05-02 10:52:32'),
(7, 6, 'D22NNA01', 'Đại học Ngôn ngữ Anh 01', '2022-2026', '2026-05-02 10:52:32'),
(8, 7, 'D22LUAT01', 'Đại học Luật 01', '2022-2026', '2026-05-02 10:52:32'),
(9, 1, '123', 'D22CNTT04', '2026-2031', '2026-05-04 03:01:00');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `contacts`
--

CREATE TABLE `contacts` (
  `id` int(11) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `message` text NOT NULL,
  `status` enum('new','read','replied') DEFAULT 'new',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `contacts`
--

INSERT INTO `contacts` (`id`, `full_name`, `email`, `phone`, `subject`, `message`, `status`, `created_at`) VALUES
(1, 'Nguyễn Văn Phúc', 'phuc@gmail.com', '0912000001', 'Hỏi về đăng ký học phần', 'Em muốn hỏi thời gian đăng ký học phần học kỳ mới bắt đầu khi nào?', 'read', '2026-05-02 10:52:32'),
(2, 'Trần Thị Mai', 'mai@gmail.com', '0912000002', 'Hỏi về tuyển sinh', 'Cho em hỏi ngành Công nghệ thông tin xét tuyển bằng những phương thức nào?', 'read', '2026-05-02 10:52:32'),
(3, 'Lê Hoàng Nam', 'nam@gmail.com', '0912000003', 'Hỏi về học phí', 'Cho em hỏi học phí ngành Kỹ thuật phần mềm tính như thế nào?', 'read', '2026-05-02 10:52:32'),
(4, 'Phạm Minh Châu', 'chau@gmail.com', '0912000004', 'Hỏi về hồ sơ xét tuyển', 'Em đã gửi hồ sơ tuyển sinh, khi nào nhận được phản hồi?', 'replied', '2026-05-02 10:52:32'),
(7, 'Hoai TDMU', 'lehoai010192@gmail.com', '0334352211', 'Tư vấn free fire', 'Xin chào', 'read', '2026-05-03 08:41:23');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `course_sections`
--

CREATE TABLE `course_sections` (
  `id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `semester_id` int(11) NOT NULL,
  `section_code` varchar(30) NOT NULL,
  `schedule_text` varchar(255) DEFAULT NULL,
  `schedule_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`schedule_data`)),
  `room` varchar(50) DEFAULT NULL,
  `max_students` int(11) DEFAULT 60,
  `current_students` int(11) DEFAULT 0,
  `tuition_fee` decimal(12,2) DEFAULT 1350000.00,
  `status` enum('open','full','closed') DEFAULT 'open',
  `note` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `sessions_per_week` tinyint(4) DEFAULT 2,
  `study_days` varchar(20) DEFAULT NULL COMMENT '2,3,4,5,6,7,8',
  `session_type` varchar(10) DEFAULT NULL COMMENT 'sang/chieu/toi',
  `day_sessions` varchar(50) DEFAULT NULL COMMENT '2:sang,4:chieu',
  `class_id` int(11) DEFAULT NULL COMMENT 'Lớp học được gán'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `course_sections`
--

INSERT INTO `course_sections` (`id`, `subject_id`, `teacher_id`, `semester_id`, `section_code`, `schedule_text`, `schedule_data`, `room`, `max_students`, `current_students`, `tuition_fee`, `status`, `note`, `created_at`, `start_time`, `end_time`, `start_date`, `end_date`, `sessions_per_week`, `study_days`, `session_type`, `day_sessions`, `class_id`) VALUES
(5, 5, 3, 1, 'CNTT203_01', '', '[{\"day\":2,\"session\":\"chieu\",\"period_start\":1},{\"day\":4,\"session\":\"chieu\",\"period_start\":1},{\"day\":6,\"session\":\"chieu\",\"period_start\":1}]', 'B2.305', 45, 3, 1350000.00, 'open', 'Lớp học phần Phân tích thiết kế hệ thống', '2026-05-02 10:52:32', NULL, NULL, '2026-05-05', '2026-06-08', 2, '3', NULL, '3:chieu', NULL),
(24, 3, 9, 1, 'NNA101_018', '', NULL, 'D1.101', 60, 1, 1350000.00, 'open', NULL, '2026-05-03 06:51:20', NULL, NULL, '2026-05-10', '2026-05-26', 2, '2,4', NULL, '2:sang,4:chieu', NULL),
(25, 9, 3, 1, 'NNA101_019', '', NULL, 'C1.202', 60, 2, 1350000.00, 'open', NULL, '2026-05-03 07:07:19', NULL, NULL, '2026-05-10', '2026-05-28', 2, '6,8', NULL, '6:chieu,8:sang', NULL),
(26, 1, 5, 1, 'NNA101_0120', '', NULL, 'C1.202', 60, 2, 1350000.00, 'open', NULL, '2026-05-03 07:09:56', NULL, NULL, '2026-05-10', '2026-06-15', 2, '3', NULL, '3:sang', NULL),
(27, 3, 8, 1, 'NNA101_022', '', NULL, 'C1.202', 60, 0, 1350000.00, 'open', NULL, '2026-05-03 08:01:45', NULL, NULL, '2026-05-10', '2026-05-28', 2, '2,6', NULL, '2:chieu,6:chieu', NULL),
(28, 3, 9, 2, 'NNA101_023', '', NULL, 'C1.202', 60, 0, 1350000.00, 'open', NULL, '2026-05-03 08:43:18', NULL, NULL, '2026-07-10', '2026-08-16', 2, '2', NULL, '2:sang', NULL),
(29, 3, 8, 1, 'NNA101_0255', '', NULL, 'A1.03', 60, 0, 1350000.00, 'closed', NULL, '2026-05-04 03:53:52', NULL, NULL, '2026-05-13', '2026-05-22', 2, '5,6,7', NULL, '5:sang,6:toi,7:', 9),
(30, 7, 3, 2, 'NNA101_020', '', NULL, 'A1.02', 60, 0, 1350000.00, 'open', NULL, '2026-05-04 05:56:38', NULL, NULL, '2026-05-10', '2026-06-17', 2, '5', NULL, '5:chieu', 9),
(31, 6, 8, 2, 'NNA101_0100', '', NULL, 'A1.01', 60, 0, 1350000.00, 'open', NULL, '2026-05-04 05:58:31', NULL, NULL, '2026-05-05', '2026-06-09', 2, '4', NULL, '4:toi', 1);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `evaluation_levels`
--

CREATE TABLE `evaluation_levels` (
  `id` int(11) NOT NULL,
  `level_value` int(11) NOT NULL,
  `level_name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `evaluation_periods`
--

CREATE TABLE `evaluation_periods` (
  `id` int(11) NOT NULL,
  `semester_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `status` enum('open','closed') DEFAULT 'open',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `evaluation_periods`
--

INSERT INTO `evaluation_periods` (`id`, `semester_id`, `title`, `description`, `start_date`, `end_date`, `status`, `created_at`) VALUES
(1, 1, 'Đánh giá học phần học kỳ 1 năm học 2025-2026', 'Sinh viên thực hiện đánh giá môn học và giảng viên giảng dạy trong học kỳ 1.', '2025-12-04 08:00:00', '2026-11-08 23:59:00', 'open', '2026-05-03 03:06:42'),
(2, 2, 'Đánh giá học phần học kỳ 2 năm học 2025-2026', 'Sinh viên thực hiện đánh giá môn học và giảng viên giảng dạy trong học kỳ 2.', '2026-05-01 08:00:00', '2026-06-10 23:59:59', 'closed', '2026-05-03 03:06:42');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `evaluation_questions`
--

CREATE TABLE `evaluation_questions` (
  `id` int(11) NOT NULL,
  `question_text` varchar(255) NOT NULL,
  `question_type` enum('rating','text') DEFAULT 'rating',
  `status` enum('show','hide') DEFAULT 'show',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `evaluation_questions`
--

INSERT INTO `evaluation_questions` (`id`, `question_text`, `question_type`, `status`, `created_at`) VALUES
(1, 'Giảng viên giảng dạy dễ hiểu, rõ ràng', 'rating', 'show', '2026-05-03 03:37:33'),
(2, 'Giảng viên chuẩn bị bài giảng đầy đủ trước khi lên lớp', 'rating', 'show', '2026-05-03 03:37:33'),
(3, 'Giảng viên truyền đạt nội dung phù hợp với trình độ sinh viên', 'rating', 'show', '2026-05-03 03:37:33'),
(4, 'Giảng viên có thái độ thân thiện, tôn trọng sinh viên', 'rating', 'show', '2026-05-03 03:37:33'),
(5, 'Giảng viên hỗ trợ sinh viên khi gặp khó khăn trong học tập', 'rating', 'show', '2026-05-03 03:37:33'),
(6, 'Giảng viên giải đáp thắc mắc của sinh viên kịp thời', 'rating', 'show', '2026-05-03 03:37:33'),
(7, 'Giảng viên sử dụng ví dụ thực tế giúp sinh viên dễ hiểu bài', 'rating', 'show', '2026-05-03 03:37:33'),
(8, 'Nội dung môn học phù hợp với chương trình đào tạo', 'rating', 'show', '2026-05-03 03:37:33'),
(9, 'Nội dung môn học có tính ứng dụng thực tế', 'rating', 'show', '2026-05-03 03:37:33'),
(10, 'Tài liệu học tập được cung cấp đầy đủ và dễ tiếp cận', 'rating', 'show', '2026-05-03 03:37:33'),
(11, 'Bài tập, bài kiểm tra phù hợp với nội dung đã học', 'rating', 'show', '2026-05-03 03:37:33'),
(12, 'Thời lượng lý thuyết và thực hành được phân bổ hợp lý', 'rating', 'show', '2026-05-03 03:37:33'),
(13, 'Phòng học, thiết bị học tập đáp ứng yêu cầu môn học', 'rating', 'show', '2026-05-03 03:37:33'),
(14, 'Sinh viên hài lòng chung về chất lượng giảng dạy của môn học', 'rating', 'show', '2026-05-03 03:37:33'),
(15, 'Góp ý thêm cho giảng viên hoặc môn học', 'rating', 'show', '2026-05-03 03:37:33');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `faculties`
--

CREATE TABLE `faculties` (
  `id` int(11) NOT NULL,
  `faculty_code` varchar(20) NOT NULL,
  `faculty_name` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `faculties`
--

INSERT INTO `faculties` (`id`, `faculty_code`, `faculty_name`, `description`, `created_at`) VALUES
(1, 'CNTT', 'Công nghệ thông tin', 'Đào tạo công nghệ thông tin, phần mềm, cơ sở dữ liệu và hệ thống thông tin', '2026-05-02 10:52:32'),
(2, 'KT', 'Kinh tế', 'Đào tạo quản trị kinh doanh, kế toán, tài chính ngân hàng', '2026-05-02 10:52:32'),
(3, 'NN', 'Ngoại ngữ', 'Đào tạo ngôn ngữ Anh, biên phiên dịch và tiếng Anh thương mại', '2026-05-02 10:52:32'),
(4, 'LUAT', 'Luật', 'Đào tạo luật kinh tế, luật dân sự và luật học', '2026-05-02 10:52:32'),
(5, 'SP', 'Sư phạm', 'Đào tạo nghiệp vụ sư phạm và giáo dục học', '2026-05-02 10:52:32');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `final_exam_schedules`
--

CREATE TABLE `final_exam_schedules` (
  `id` int(11) NOT NULL,
  `course_section_id` int(11) NOT NULL,
  `exam_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `room` varchar(50) DEFAULT NULL,
  `exam_form` enum('Tự luận','Trắc nghiệm','Tiểu luận') DEFAULT 'Tự luận',
  `note` varchar(255) DEFAULT NULL,
  `status` enum('scheduled','completed','cancelled') DEFAULT 'scheduled',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `final_exam_schedules`
--

INSERT INTO `final_exam_schedules` (`id`, `course_section_id`, `exam_date`, `start_time`, `end_time`, `room`, `exam_form`, `note`, `status`, `created_at`, `updated_at`) VALUES
(1, 30, '2026-08-26', '07:00:00', '09:00:00', 'A1.01', 'Tự luận', '', 'scheduled', '2026-05-04 05:57:27', '2026-05-04 05:57:27');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `grades`
--

CREATE TABLE `grades` (
  `id` int(11) NOT NULL,
  `student_subject_id` int(11) NOT NULL,
  `process_score` decimal(4,2) DEFAULT NULL,
  `midterm_score` decimal(4,2) DEFAULT NULL,
  `final_score` decimal(4,2) DEFAULT NULL,
  `total_score` decimal(4,2) DEFAULT NULL,
  `letter_grade` varchar(5) DEFAULT NULL,
  `note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `grades`
--

INSERT INTO `grades` (`id`, `student_subject_id`, `process_score`, `midterm_score`, `final_score`, `total_score`, `letter_grade`, `note`, `created_at`, `updated_at`) VALUES
(10, 10, 6.50, 7.00, 7.00, 6.90, 'C+', 'Đạt', '2026-05-02 10:52:32', '2026-05-02 10:52:32');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `majors`
--

CREATE TABLE `majors` (
  `id` int(11) NOT NULL,
  `faculty_id` int(11) NOT NULL,
  `major_code` varchar(30) NOT NULL,
  `major_name` varchar(150) NOT NULL,
  `training_level` varchar(50) DEFAULT 'Đại học',
  `training_type` varchar(100) DEFAULT 'Chính quy',
  `total_credits` int(11) DEFAULT 120,
  `tuition_per_credit` decimal(12,2) DEFAULT 450000.00,
  `description` text DEFAULT NULL,
  `status` enum('open','closed') DEFAULT 'open',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `majors`
--

INSERT INTO `majors` (`id`, `faculty_id`, `major_code`, `major_name`, `training_level`, `training_type`, `total_credits`, `tuition_per_credit`, `description`, `status`, `created_at`) VALUES
(1, 1, '7480201', 'Công nghệ thông tin', 'Đại học', 'Chính quy', 120, 450000.00, 'Ngành đào tạo lập trình, cơ sở dữ liệu, mạng máy tính và phát triển phần mềm', 'open', '2026-05-02 10:52:32'),
(2, 1, '7480103', 'Kỹ thuật phần mềm', 'Đại học', 'Chính quy', 120, 450000.00, 'Ngành đào tạo chuyên sâu về quy trình phát triển phần mềm', 'open', '2026-05-02 10:52:32'),
(3, 1, '7480104', 'Hệ thống thông tin', 'Đại học', 'Chính quy', 120, 450000.00, 'Ngành đào tạo về phân tích, thiết kế và quản trị hệ thống thông tin', 'open', '2026-05-02 10:52:32'),
(4, 2, '7340101', 'Quản trị kinh doanh', 'Đại học', 'Chính quy', 120, 420000.00, 'Ngành đào tạo quản trị doanh nghiệp, marketing và kinh doanh', 'open', '2026-05-02 10:52:32'),
(5, 2, '7340301', 'Kế toán', 'Đại học', 'Chính quy', 120, 420000.00, 'Ngành đào tạo kế toán doanh nghiệp, kiểm toán và tài chính', 'open', '2026-05-02 10:52:32'),
(6, 3, '7220201', 'Ngôn ngữ Anh', 'Đại học', 'Chính quy', 120, 430000.00, 'Ngành đào tạo tiếng Anh thương mại, biên phiên dịch', 'open', '2026-05-02 10:52:32'),
(7, 4, '7380101', 'Luật', 'Đại học', 'Chính quy', 120, 420000.00, 'Ngành đào tạo kiến thức pháp luật cơ bản và chuyên sâu', 'open', '2026-05-02 10:52:32'),
(8, 5, '7140202', 'Giáo dục Tiểu học', 'Đại học', 'Chính quy', 120, 400000.00, 'Ngành đào tạo giáo viên tiểu học', 'open', '2026-05-02 10:52:32');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `image` varchar(255) DEFAULT NULL,
  `type` enum('general','registration','grade','tuition','admission') DEFAULT 'general',
  `status` enum('show','hide') DEFAULT 'show',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `notifications`
--

INSERT INTO `notifications` (`id`, `title`, `content`, `image`, `type`, `status`, `created_at`) VALUES
(1, 'Thông báo mở đăng ký học phần học kỳ 1 năm học 2025-2026', 'Sinh viên đăng nhập hệ thống để đăng ký học phần trong thời gian quy định. Sau thời gian trên, hệ thống sẽ tự động khóa đăng ký.', 'https://images.unsplash.com/photo-1523580846011-d3a5bc25702b', 'registration', 'show', '2026-05-02 10:52:32'),
(2, 'Thông báo lịch học học kỳ mới', 'Sinh viên kiểm tra thời khóa biểu cá nhân sau khi hoàn tất đăng ký học phần.', 'https://images.unsplash.com/photo-1509062522246-3755977927d7', 'general', 'show', '2026-05-02 10:52:32'),
(3, 'Thông báo xem điểm học tập', 'Sinh viên có thể xem điểm quá trình, điểm giữa kỳ, điểm cuối kỳ và điểm tổng kết trong mục Kết quả học tập.', 'https://images.unsplash.com/photo-1434030216411-0b793f4b4173', 'grade', 'show', '2026-05-02 10:52:32'),
(4, 'Thông báo nộp học phí', 'Sinh viên cần hoàn thành học phí đúng thời hạn theo thông báo của nhà trường.', 'https://images.unsplash.com/photo-1554224155-6726b3ff858f', 'tuition', 'show', '2026-05-02 10:52:32');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `semesters`
--

CREATE TABLE `semesters` (
  `id` int(11) NOT NULL,
  `semester_name` varchar(100) NOT NULL,
  `school_year` varchar(20) NOT NULL,
  `register_start` datetime DEFAULT NULL,
  `register_end` datetime DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('open','closed') DEFAULT 'open',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `semesters`
--

INSERT INTO `semesters` (`id`, `semester_name`, `school_year`, `register_start`, `register_end`, `start_date`, `end_date`, `status`, `created_at`) VALUES
(1, 'Học kỳ 1', '2025-2026', '2026-05-03 10:47:28', '2026-05-10 10:47:28', '2025-05-01', '2026-10-15', 'open', '2026-05-02 10:52:32'),
(2, 'Học kỳ 2', '2025-2026', '2026-05-03 10:47:33', '2026-05-03 10:47:52', '2026-02-01', '2026-08-15', 'closed', '2026-05-02 10:52:32'),
(3, 'Học kỳ hè', '2025-2026', '2026-05-02 14:13:49', '2026-05-03 05:34:53', '2026-06-15', '2026-08-15', 'closed', '2026-05-02 10:52:32');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `students`
--

CREATE TABLE `students` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `student_code` varchar(30) NOT NULL,
  `gender` enum('Nam','Nữ','Khác') DEFAULT 'Nam',
  `birthday` date DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `academic_status` enum('Đang học','Bảo lưu','Thôi học','Đã tốt nghiệp') DEFAULT 'Đang học',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `students`
--

INSERT INTO `students` (`id`, `user_id`, `class_id`, `student_code`, `gender`, `birthday`, `address`, `academic_status`, `created_at`) VALUES
(1, 2, 1, '222480201001', 'Nam', '2004-02-10', 'Bình Dương', 'Đang học', '2026-05-02 10:52:32'),
(2, 3, 1, '222480201002', 'Nữ', '2004-10-23', 'Bình Định', 'Đang học', '2026-05-02 10:52:32'),
(3, 4, 2, '222480201003', 'Nam', '2004-05-15', 'TP.HCM', 'Đang học', '2026-05-02 10:52:32'),
(4, 5, 2, '222480201004', 'Nữ', '2004-07-20', 'Đồng Nai', 'Đang học', '2026-05-02 10:52:32'),
(5, 6, 3, '222480201005', 'Nam', '2004-03-12', 'Bình Phước', 'Đang học', '2026-05-02 10:52:32'),
(6, 7, 4, '222480201006', 'Nữ', '2004-08-18', 'Tây Ninh', 'Đang học', '2026-05-02 10:52:32'),
(7, 8, 5, '222480201007', 'Nam', '2004-11-05', 'Long An', 'Đang học', '2026-05-02 10:52:32'),
(8, 9, 6, '222480201008', 'Nữ', '2004-12-01', 'Cần Thơ', 'Đang học', '2026-05-02 10:52:32');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `student_evaluations`
--

CREATE TABLE `student_evaluations` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `course_section_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `period_id` int(11) NOT NULL,
  `question_id` int(11) NOT NULL,
  `rating` int(11) DEFAULT NULL,
  `comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `student_extra_comments`
--

CREATE TABLE `student_extra_comments` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `course_section_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `period_id` int(11) NOT NULL,
  `comment` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `student_extra_comments`
--

INSERT INTO `student_extra_comments` (`id`, `student_id`, `course_section_id`, `teacher_id`, `period_id`, `comment`, `created_at`, `updated_at`) VALUES
(1, 3, 1, 1, 1, 'Good', '2026-05-03 04:48:54', '2026-05-03 04:48:54');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `student_subjects`
--

CREATE TABLE `student_subjects` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `course_section_id` int(11) NOT NULL,
  `register_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('registered','cancelled','completed') DEFAULT 'registered'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `student_subjects`
--

INSERT INTO `student_subjects` (`id`, `student_id`, `course_section_id`, `register_date`, `status`) VALUES
(10, 4, 5, '2026-05-02 10:52:32', 'registered'),
(26, 5, 5, '2026-05-02 12:33:15', 'cancelled'),
(32, 6, 5, '2026-05-02 12:39:32', 'registered'),
(56, 1, 5, '2026-05-02 14:35:48', 'registered'),
(62, 3, 25, '2026-05-03 07:11:05', 'registered'),
(68, 3, 26, '2026-05-03 07:57:50', 'registered'),
(70, 8, 24, '2026-05-04 05:50:59', 'registered'),
(71, 8, 25, '2026-05-04 05:51:21', 'registered'),
(72, 8, 26, '2026-05-04 05:51:28', 'registered');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `subjects`
--

CREATE TABLE `subjects` (
  `id` int(11) NOT NULL,
  `major_id` int(11) NOT NULL,
  `subject_code` varchar(30) NOT NULL,
  `subject_name` varchar(150) NOT NULL,
  `credits` int(11) DEFAULT 3,
  `theory_periods` int(11) DEFAULT 30,
  `practice_periods` int(11) DEFAULT 30,
  `subject_type` enum('Bắt buộc','Tự chọn') DEFAULT 'Bắt buộc',
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `semester_order` tinyint(4) NOT NULL DEFAULT 1 COMMENT 'Học kỳ thứ mấy trong CTĐT',
  `subject_type_new` varchar(20) DEFAULT 'required',
  `is_mandatory` tinyint(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `subjects`
--

INSERT INTO `subjects` (`id`, `major_id`, `subject_code`, `subject_name`, `credits`, `theory_periods`, `practice_periods`, `subject_type`, `description`, `created_at`, `semester_order`, `subject_type_new`, `is_mandatory`) VALUES
(1, 1, 'CNTT101', 'Nhập môn lập trình', 3, 30, 30, 'Bắt buộc', 'Môn học cơ bản về tư duy lập trình', '2026-05-02 10:52:32', 1, 'required', 1),
(2, 1, 'CNTT102', 'Công nghệ phần mềm', 2, 30, 30, 'Bắt buộc', 'Thiết kế và quản trị cơ sở dữ liệu', '2026-05-02 10:52:32', 1, 'required', 1),
(3, 1, 'CNTT201', 'Lập trình Web', 3, 30, 30, 'Bắt buộc', 'Xây dựng website bằng HTML, CSS, JavaScript, PHP và MySQL', '2026-05-02 10:52:32', 1, 'required', 1),
(4, 1, 'CNTT202', 'Ứng dụng đa nền tảng', 3, 30, 30, 'Bắt buộc', 'Phát triển ứng dụng đa nền tảng', '2026-05-02 10:52:32', 1, 'required', 1),
(5, 1, 'CNTT203', 'Phân tích thiết kế hệ thống', 3, 30, 15, 'Bắt buộc', 'Phân tích yêu cầu và thiết kế hệ thống thông tin', '2026-05-02 10:52:32', 1, 'required', 1),
(6, 2, 'KTPM101', 'Nhập môn kỹ thuật phần mềm', 3, 30, 30, 'Bắt buộc', 'Kiến thức nền tảng về kỹ thuật phần mềm', '2026-05-02 10:52:32', 1, 'required', 1),
(7, 2, 'KTPM201', 'Kiểm thử phần mềm', 3, 30, 30, 'Bắt buộc', 'Kỹ thuật kiểm thử và đảm bảo chất lượng phần mềm', '2026-05-02 10:52:32', 1, 'required', 1),
(8, 4, 'QTKD101', 'Quản trị học', 3, 45, 0, 'Bắt buộc', 'Kiến thức nền tảng về quản trị', '2026-05-02 10:52:32', 1, 'required', 1),
(9, 5, 'KT101', 'Nguyên lý kế toán', 3, 45, 0, 'Bắt buộc', 'Kiến thức nền tảng về kế toán', '2026-05-02 10:52:32', 1, 'required', 1),
(10, 6, 'NNA101', 'Tiếng Anh tổng quát', 3, 30, 30, 'Bắt buộc', 'Phát triển kỹ năng nghe nói đọc viết tiếng Anh', '2026-05-02 10:52:32', 1, 'required', 1);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `teachers`
--

CREATE TABLE `teachers` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `faculty_id` int(11) NOT NULL,
  `teacher_code` varchar(30) NOT NULL,
  `degree` varchar(100) DEFAULT NULL,
  `specialization` varchar(150) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `teachers`
--

INSERT INTO `teachers` (`id`, `user_id`, `faculty_id`, `teacher_code`, `degree`, `specialization`, `created_at`) VALUES
(1, 10, 1, 'GV001', 'Thạc sĩ', 'Công nghệ phần mềm', '2026-05-02 10:52:32'),
(2, 11, 1, 'GV002', 'Thạc sĩ', 'Cơ sở dữ liệu', '2026-05-02 10:52:32'),
(3, 12, 1, 'GV003', 'Tiến sĩ', 'Hệ thống thông tin', '2026-05-02 10:52:32'),
(4, 13, 2, 'GV004', 'Thạc sĩ', 'Quản trị kinh doanh', '2026-05-02 10:52:32'),
(5, 14, 3, 'GV005', 'Thạc sĩ', 'Ngôn ngữ Anh', '2026-05-02 10:52:32'),
(8, 15, 1, 'GV007', 'Tiến sĩ', 'Công Nghệ Thông Tin', '2026-05-02 12:53:23'),
(9, 16, 1, 'GV008', 'Tiến sĩ', 'Công Nghệ Thông Tin', '2026-05-02 13:29:11');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `training_programs`
--

CREATE TABLE `training_programs` (
  `id` int(11) NOT NULL,
  `major_id` int(11) NOT NULL,
  `program_name` varchar(255) NOT NULL,
  `school_year` varchar(20) DEFAULT NULL,
  `total_credits` int(11) DEFAULT 150,
  `note` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `training_program_subjects`
--

CREATE TABLE `training_program_subjects` (
  `id` int(11) NOT NULL,
  `program_id` int(11) NOT NULL,
  `semester_name` varchar(100) NOT NULL,
  `school_year` varchar(20) NOT NULL,
  `stt` int(11) NOT NULL,
  `subject_code` varchar(30) NOT NULL,
  `subject_name` varchar(255) NOT NULL,
  `specialization` varchar(255) DEFAULT NULL,
  `credits` int(11) NOT NULL,
  `is_required` tinyint(1) DEFAULT 1,
  `is_completed` tinyint(1) DEFAULT 0,
  `total_periods` int(11) DEFAULT 0,
  `theory_periods` int(11) DEFAULT 0,
  `practice_periods` int(11) DEFAULT 0,
  `component_periods` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(150) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `role` enum('admin','student','teacher') NOT NULL,
  `status` tinyint(4) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Đang đổ dữ liệu cho bảng `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `email`, `phone`, `address`, `avatar`, `role`, `status`, `created_at`) VALUES
(1, 'admin', '123456', 'Quản trị viên', 'admin@gmail.com', '0900000000', 'Bình Dương', NULL, 'admin', 1, '2026-05-02 10:52:32'),
(2, 'sv001', '123456', 'Nguyễn Văn An', 'sv001@gmail.com', '0911111111', 'Bình Dương', 'assets/uploads/avatars/sv_2_1777857245.jpg', 'student', 1, '2026-05-02 10:52:32'),
(3, 'sv002', '123456', 'Lê Hoàng Vy', 'sv002@gmail.com', '0922222222', 'Bình Định', NULL, 'student', 1, '2026-05-02 10:52:32'),
(4, 'sv003', '123456', 'Trần Minh Khang', 'sv003@gmail.com', '0933333333', 'TP.HCM', NULL, 'student', 1, '2026-05-02 10:52:32'),
(5, 'sv004', '123456', 'Phạm Thị Ngọc Mai', 'sv004@gmail.com', '0944444444', 'Đồng Nai', NULL, 'student', 1, '2026-05-02 10:52:32'),
(6, 'sv005', '123456', 'Võ Quốc Huy', 'sv005@gmail.com', '0955555555', 'Bình Phước', NULL, 'student', 1, '2026-05-02 10:52:32'),
(7, 'sv006', '123456', 'Đặng Thanh Trúc', 'sv006@gmail.com', '0966666666', 'Tây Ninh', NULL, 'student', 1, '2026-05-02 10:52:32'),
(8, 'sv007', '123456', 'Huỳnh Gia Bảo', 'sv007@gmail.com', '0977777777', 'Long An', NULL, 'student', 1, '2026-05-02 10:52:32'),
(9, 'sv008', '123456', 'Ngô Phương Linh', 'sv008@gmail.com', '0988888888', 'Cần Thơ', 'assets/uploads/avatars/sv_9_1777873992.jpg', 'student', 1, '2026-05-02 10:52:32'),
(10, 'gv001', '123456', 'ThS. Phạm Minh Tuấn', 'gv001@gmail.com', '0901000001', 'Bình Dương', NULL, 'teacher', 1, '2026-05-02 10:52:32'),
(11, 'gv002', '123456', 'ThS. Nguyễn Thị Hạnh', 'gv002@gmail.com', '0901000002', 'TP.HCM', NULL, 'teacher', 1, '2026-05-02 10:52:32'),
(12, 'gv003', '123456', 'TS. Trần Quốc Bảo', 'gv003@gmail.com', '0901000003', 'Đồng Nai', NULL, 'teacher', 1, '2026-05-02 10:52:32'),
(13, 'gv004', '123456', 'ThS. Lê Thị Mỹ Duyên', 'gv004@gmail.com', '0901000004', 'Bình Dương', NULL, 'teacher', 1, '2026-05-02 10:52:32'),
(14, 'gv005', '123456', 'ThS. Nguyễn Quốc Thịnh', 'gv005@gmail.com', '0901000005', 'TP.HCM', NULL, 'teacher', 1, '2026-05-02 10:52:32'),
(15, 'gv007', '231020', 'Đinh Công Vy', 'lehoangvy2310@gmail.com', '0334352211', '', NULL, 'teacher', 1, '2026-05-02 12:53:23'),
(16, 'gv008', '123456', 'Lê Thanh Hoài', 'lehoangvy2310@gmail.com', '0334352211', NULL, NULL, 'teacher', 1, '2026-05-02 13:29:11'),
(21, 'sv009', '123456', 'vyle', 'lehoangvy.010192@gmail.com', NULL, NULL, NULL, 'student', 1, '2026-05-02 14:40:41');

--
-- Chỉ mục cho các bảng đã đổ
--

--
-- Chỉ mục cho bảng `admission_applications`
--
ALTER TABLE `admission_applications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `major_id` (`major_id`),
  ADD KEY `method_id` (`method_id`);

--
-- Chỉ mục cho bảng `admission_methods`
--
ALTER TABLE `admission_methods`
  ADD PRIMARY KEY (`id`);

--
-- Chỉ mục cho bảng `admission_news`
--
ALTER TABLE `admission_news`
  ADD PRIMARY KEY (`id`);

--
-- Chỉ mục cho bảng `classes`
--
ALTER TABLE `classes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `class_code` (`class_code`),
  ADD KEY `major_id` (`major_id`);

--
-- Chỉ mục cho bảng `contacts`
--
ALTER TABLE `contacts`
  ADD PRIMARY KEY (`id`);

--
-- Chỉ mục cho bảng `course_sections`
--
ALTER TABLE `course_sections`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `section_code` (`section_code`),
  ADD KEY `subject_id` (`subject_id`),
  ADD KEY `teacher_id` (`teacher_id`),
  ADD KEY `semester_id` (`semester_id`);

--
-- Chỉ mục cho bảng `evaluation_levels`
--
ALTER TABLE `evaluation_levels`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `level_value` (`level_value`);

--
-- Chỉ mục cho bảng `evaluation_periods`
--
ALTER TABLE `evaluation_periods`
  ADD PRIMARY KEY (`id`),
  ADD KEY `semester_id` (`semester_id`);

--
-- Chỉ mục cho bảng `evaluation_questions`
--
ALTER TABLE `evaluation_questions`
  ADD PRIMARY KEY (`id`);

--
-- Chỉ mục cho bảng `faculties`
--
ALTER TABLE `faculties`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `faculty_code` (`faculty_code`);

--
-- Chỉ mục cho bảng `final_exam_schedules`
--
ALTER TABLE `final_exam_schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `course_section_id` (`course_section_id`);

--
-- Chỉ mục cho bảng `grades`
--
ALTER TABLE `grades`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `student_subject_id` (`student_subject_id`);

--
-- Chỉ mục cho bảng `majors`
--
ALTER TABLE `majors`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `major_code` (`major_code`),
  ADD KEY `faculty_id` (`faculty_id`);

--
-- Chỉ mục cho bảng `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`);

--
-- Chỉ mục cho bảng `semesters`
--
ALTER TABLE `semesters`
  ADD PRIMARY KEY (`id`);

--
-- Chỉ mục cho bảng `students`
--
ALTER TABLE `students`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `student_code` (`student_code`),
  ADD KEY `class_id` (`class_id`);

--
-- Chỉ mục cho bảng `student_evaluations`
--
ALTER TABLE `student_evaluations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_student_evaluation` (`student_id`,`course_section_id`,`period_id`,`question_id`),
  ADD KEY `course_section_id` (`course_section_id`),
  ADD KEY `teacher_id` (`teacher_id`),
  ADD KEY `period_id` (`period_id`),
  ADD KEY `question_id` (`question_id`);

--
-- Chỉ mục cho bảng `student_extra_comments`
--
ALTER TABLE `student_extra_comments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_extra` (`student_id`,`course_section_id`,`period_id`);

--
-- Chỉ mục cho bảng `student_subjects`
--
ALTER TABLE `student_subjects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_student_subject` (`student_id`,`course_section_id`),
  ADD KEY `course_section_id` (`course_section_id`);

--
-- Chỉ mục cho bảng `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `subject_code` (`subject_code`),
  ADD KEY `major_id` (`major_id`);

--
-- Chỉ mục cho bảng `teachers`
--
ALTER TABLE `teachers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_id` (`user_id`),
  ADD UNIQUE KEY `teacher_code` (`teacher_code`),
  ADD KEY `faculty_id` (`faculty_id`);

--
-- Chỉ mục cho bảng `training_programs`
--
ALTER TABLE `training_programs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `major_id` (`major_id`);

--
-- Chỉ mục cho bảng `training_program_subjects`
--
ALTER TABLE `training_program_subjects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `program_id` (`program_id`);

--
-- Chỉ mục cho bảng `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT cho các bảng đã đổ
--

--
-- AUTO_INCREMENT cho bảng `admission_applications`
--
ALTER TABLE `admission_applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT cho bảng `admission_methods`
--
ALTER TABLE `admission_methods`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT cho bảng `admission_news`
--
ALTER TABLE `admission_news`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT cho bảng `classes`
--
ALTER TABLE `classes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT cho bảng `contacts`
--
ALTER TABLE `contacts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT cho bảng `course_sections`
--
ALTER TABLE `course_sections`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=32;

--
-- AUTO_INCREMENT cho bảng `evaluation_levels`
--
ALTER TABLE `evaluation_levels`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `evaluation_periods`
--
ALTER TABLE `evaluation_periods`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT cho bảng `evaluation_questions`
--
ALTER TABLE `evaluation_questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT cho bảng `faculties`
--
ALTER TABLE `faculties`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT cho bảng `final_exam_schedules`
--
ALTER TABLE `final_exam_schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT cho bảng `grades`
--
ALTER TABLE `grades`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT cho bảng `majors`
--
ALTER TABLE `majors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT cho bảng `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT cho bảng `semesters`
--
ALTER TABLE `semesters`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT cho bảng `students`
--
ALTER TABLE `students`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT cho bảng `student_evaluations`
--
ALTER TABLE `student_evaluations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=108;

--
-- AUTO_INCREMENT cho bảng `student_extra_comments`
--
ALTER TABLE `student_extra_comments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT cho bảng `student_subjects`
--
ALTER TABLE `student_subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=73;

--
-- AUTO_INCREMENT cho bảng `subjects`
--
ALTER TABLE `subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT cho bảng `teachers`
--
ALTER TABLE `teachers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT cho bảng `training_programs`
--
ALTER TABLE `training_programs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `training_program_subjects`
--
ALTER TABLE `training_program_subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT cho bảng `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- Các ràng buộc cho các bảng đã đổ
--

--
-- Các ràng buộc cho bảng `admission_applications`
--
ALTER TABLE `admission_applications`
  ADD CONSTRAINT `admission_applications_ibfk_1` FOREIGN KEY (`major_id`) REFERENCES `majors` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `admission_applications_ibfk_2` FOREIGN KEY (`method_id`) REFERENCES `admission_methods` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `classes`
--
ALTER TABLE `classes`
  ADD CONSTRAINT `classes_ibfk_1` FOREIGN KEY (`major_id`) REFERENCES `majors` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `course_sections`
--
ALTER TABLE `course_sections`
  ADD CONSTRAINT `course_sections_ibfk_1` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `course_sections_ibfk_2` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `course_sections_ibfk_3` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `evaluation_periods`
--
ALTER TABLE `evaluation_periods`
  ADD CONSTRAINT `evaluation_periods_ibfk_1` FOREIGN KEY (`semester_id`) REFERENCES `semesters` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `final_exam_schedules`
--
ALTER TABLE `final_exam_schedules`
  ADD CONSTRAINT `final_exam_schedules_ibfk_1` FOREIGN KEY (`course_section_id`) REFERENCES `course_sections` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `grades`
--
ALTER TABLE `grades`
  ADD CONSTRAINT `grades_ibfk_1` FOREIGN KEY (`student_subject_id`) REFERENCES `student_subjects` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `majors`
--
ALTER TABLE `majors`
  ADD CONSTRAINT `majors_ibfk_1` FOREIGN KEY (`faculty_id`) REFERENCES `faculties` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `students`
--
ALTER TABLE `students`
  ADD CONSTRAINT `students_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `students_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `student_evaluations`
--
ALTER TABLE `student_evaluations`
  ADD CONSTRAINT `student_evaluations_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_evaluations_ibfk_2` FOREIGN KEY (`course_section_id`) REFERENCES `course_sections` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_evaluations_ibfk_3` FOREIGN KEY (`teacher_id`) REFERENCES `teachers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_evaluations_ibfk_4` FOREIGN KEY (`period_id`) REFERENCES `evaluation_periods` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_evaluations_ibfk_5` FOREIGN KEY (`question_id`) REFERENCES `evaluation_questions` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `student_subjects`
--
ALTER TABLE `student_subjects`
  ADD CONSTRAINT `student_subjects_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `students` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_subjects_ibfk_2` FOREIGN KEY (`course_section_id`) REFERENCES `course_sections` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `subjects`
--
ALTER TABLE `subjects`
  ADD CONSTRAINT `subjects_ibfk_1` FOREIGN KEY (`major_id`) REFERENCES `majors` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `teachers`
--
ALTER TABLE `teachers`
  ADD CONSTRAINT `teachers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `teachers_ibfk_2` FOREIGN KEY (`faculty_id`) REFERENCES `faculties` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `training_programs`
--
ALTER TABLE `training_programs`
  ADD CONSTRAINT `training_programs_ibfk_1` FOREIGN KEY (`major_id`) REFERENCES `majors` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Các ràng buộc cho bảng `training_program_subjects`
--
ALTER TABLE `training_program_subjects`
  ADD CONSTRAINT `training_program_subjects_ibfk_1` FOREIGN KEY (`program_id`) REFERENCES `training_programs` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
