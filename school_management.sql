-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Máy chủ: 127.0.0.1
-- Thời gian đã tạo: Th1 02, 2026 lúc 07:23 PM
-- Phiên bản máy phục vụ: 10.4.32-MariaDB
-- Phiên bản PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Cơ sở dữ liệu: `school_management`
--

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `assignments`
--

CREATE TABLE `assignments` (
  `id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `deadline` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `file_path` varchar(255) DEFAULT NULL,
  `external_link` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `assignments`
--

INSERT INTO `assignments` (`id`, `class_id`, `subject_id`, `teacher_id`, `title`, `description`, `deadline`, `created_at`, `file_path`, `external_link`) VALUES
(1, 3, 2, 2, 'bài tập học phần', 'làm bài trong hạn, cố gắn hoàn thành', '2026-01-02 23:10:00', '2026-01-02 16:05:27', NULL, 'https://www.google.com/search?sxsrf=AE3TifPInHoPpqjlkRs9ujHld7IJpzZLVw:1767361254618&udm=2&q=side+tr%C6%B0%E1%BB%9Dng+b%C3%A1ch+khoa'),
(2, 3, 2, 2, 'test', 'test', '2026-01-03 00:20:00', '2026-01-02 17:16:22', NULL, 'https://www.youtube.com/watch?v=DZDYZ9nRHfU&list=RDTF70IYJN4sc&index=27'),
(3, 3, 2, 2, 'test', 'a', '2026-01-03 02:21:00', '2026-01-02 18:21:26', NULL, '');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `lesson_log_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `status` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `attendance`
--

INSERT INTO `attendance` (`id`, `lesson_log_id`, `student_id`, `status`, `created_at`) VALUES
(1, 3, 9, 1, '2026-01-02 15:49:49'),
(2, 3, 7, 1, '2026-01-02 15:49:49'),
(3, 3, 8, 1, '2026-01-02 15:49:49'),
(4, 4, 9, 0, '2026-01-02 18:23:02'),
(5, 4, 7, 1, '2026-01-02 18:23:02'),
(6, 4, 5, 1, '2026-01-02 18:23:02'),
(7, 4, 8, 1, '2026-01-02 18:23:02');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `classes`
--

CREATE TABLE `classes` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `teacher_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `classes`
--

INSERT INTO `classes` (`id`, `name`, `department_id`, `teacher_id`, `created_at`) VALUES
(1, 'CNTT_K15', 1, 2, '2026-01-02 13:01:36'),
(2, 'KTE_K16', 2, 3, '2026-01-02 13:01:36'),
(3, 'NNA_K15', 3, 4, '2026-01-02 13:01:36'),
(4, 'Học lại triết', 1, 2, '2026-01-02 14:39:02');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `class_announcements`
--

CREATE TABLE `class_announcements` (
  `id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `content` text NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `class_announcements`
--

INSERT INTO `class_announcements` (`id`, `class_id`, `subject_id`, `teacher_id`, `content`, `created_at`) VALUES
(1, 3, 2, 2, 'c', '2026-01-03 00:51:01');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `class_students`
--

CREATE TABLE `class_students` (
  `class_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `class_students`
--

INSERT INTO `class_students` (`class_id`, `student_id`, `joined_at`) VALUES
(1, 5, '2026-01-02 13:01:36'),
(1, 6, '2026-01-02 13:01:36'),
(3, 5, '2026-01-02 13:01:36'),
(3, 7, '2026-01-02 14:27:05'),
(3, 8, '2026-01-02 15:15:48'),
(3, 9, '2026-01-02 15:16:48');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `class_subject_teachers`
--

CREATE TABLE `class_subject_teachers` (
  `id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `class_subject_teachers`
--

INSERT INTO `class_subject_teachers` (`id`, `class_id`, `teacher_id`, `subject_id`) VALUES
(4, 3, 2, 5),
(5, 3, 2, 2),
(6, 3, 3, 3),
(7, 2, 2, 2),
(8, 1, 4, 4),
(9, 4, 2, 4);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `departments`
--

CREATE TABLE `departments` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `departments`
--

INSERT INTO `departments` (`id`, `name`, `description`) VALUES
(1, 'Khoa Công Nghệ Thông Tin', 'Đào tạo lập trình viên, kỹ sư mạng'),
(2, 'Khoa Kinh Tế', 'Quản trị kinh doanh, Kế toán, Tài chính'),
(3, 'Khoa Ngoại Ngữ', 'Tiếng Anh, Tiếng Nhật, Tiếng Hàn, Tiếng Ý');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `exams`
--

CREATE TABLE `exams` (
  `id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `duration` int(11) NOT NULL,
  `status` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `exams`
--

INSERT INTO `exams` (`id`, `class_id`, `subject_id`, `teacher_id`, `title`, `duration`, `status`, `created_at`) VALUES
(2, 3, 5, 2, 'Đề test', 60, 0, '2026-01-02 16:40:19'),
(3, 3, 2, 2, 'thi thử', 20, 0, '2026-01-02 17:21:14');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `exam_questions`
--

CREATE TABLE `exam_questions` (
  `id` int(11) NOT NULL,
  `exam_id` int(11) NOT NULL,
  `question_text` text NOT NULL,
  `option_a` varchar(255) NOT NULL,
  `option_b` varchar(255) NOT NULL,
  `option_c` varchar(255) NOT NULL,
  `option_d` varchar(255) NOT NULL,
  `correct_option` char(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `exam_questions`
--

INSERT INTO `exam_questions` (`id`, `exam_id`, `question_text`, `option_a`, `option_b`, `option_c`, `option_d`, `correct_option`) VALUES
(1, 2, 'Test', 'Test1', 'Test2', 'Test3', 'Test4', 'A'),
(2, 2, 'Test1', '1', '2', '3', '4', 'B'),
(3, 3, 'trái đất hình gì', 'tròn', 'méo', 'vuông', 'cầu', 'D'),
(4, 3, 'sơn tùng mtp làm nghề gì', 'ca sĩ', 'đầu bếp', 'công nhân', 'nhân viên văn phòng', 'A');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `exam_results`
--

CREATE TABLE `exam_results` (
  `id` int(11) NOT NULL,
  `exam_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `total_questions` int(11) NOT NULL,
  `correct_answers` int(11) NOT NULL,
  `score` decimal(4,2) NOT NULL,
  `completed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `exam_results`
--

INSERT INTO `exam_results` (`id`, `exam_id`, `student_id`, `total_questions`, `correct_answers`, `score`, `completed_at`) VALUES
(1, 3, 7, 2, 1, 5.00, '2026-01-02 17:24:30'),
(2, 3, 7, 2, 1, 5.00, '2026-01-02 17:24:36'),
(3, 3, 7, 2, 1, 5.00, '2026-01-02 17:27:25'),
(4, 2, 7, 2, 1, 5.00, '2026-01-02 17:27:43');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `lesson_logs`
--

CREATE TABLE `lesson_logs` (
  `id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL,
  `teacher_id` int(11) NOT NULL,
  `lesson_date` date NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time NOT NULL,
  `room_name` varchar(50) DEFAULT NULL,
  `is_completed` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `lesson_logs`
--

INSERT INTO `lesson_logs` (`id`, `class_id`, `subject_id`, `teacher_id`, `lesson_date`, `start_time`, `end_time`, `room_name`, `is_completed`, `created_at`) VALUES
(1, 3, 2, 2, '2026-01-02', '22:35:00', '22:36:00', 'a', 1, '2026-01-02 15:34:17'),
(2, 3, 2, 2, '2026-01-02', '22:41:00', '22:42:00', 'a', 1, '2026-01-02 15:40:26'),
(3, 3, 2, 2, '2026-01-02', '22:49:00', '22:51:00', 'a', 1, '2026-01-02 15:48:58'),
(4, 3, 2, 2, '2026-01-03', '01:21:00', '01:22:00', 'a', 1, '2026-01-02 18:20:35');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `news`
--

CREATE TABLE `news` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` text DEFAULT NULL,
  `image_url` varchar(255) DEFAULT NULL,
  `type` enum('news','slide') DEFAULT 'news',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `news`
--

INSERT INTO `news` (`id`, `title`, `content`, `image_url`, `type`, `created_at`) VALUES
(1, 'Chào mừng năm học mới 2025-2026', 'Lễ khai giảng diễn ra tưng bừng...', '1767361324_6346.jpg', 'slide', '2026-01-02 13:01:37'),
(2, 'Hội trại thanh niên', 'Sinh viên tham gia cắm trại...', '1767361317_7504.jpg', 'slide', '2026-01-02 13:01:37'),
(3, 'Lễ tốt nghiệp khóa K12', 'Chúc mừng các tân cử nhân...', '1767361303_9416.jpg', 'slide', '2026-01-02 13:01:37'),
(4, 'Thông báo lịch nghỉ tết', 'Nhà trường thông báo lịch nghỉ...', '1767361294_2058.jpg', 'news', '2026-01-02 13:01:37'),
(5, 'Sinh viên 5 tốt', 'Danh sách tuyên dương...', '1767361286_5282.jpg', 'news', '2026-01-02 13:01:37'),
(6, 'Học bổng khuyến học', 'Trao 50 suất học bổng...', '1767361280_3486.jpg', 'news', '2026-01-02 13:01:37');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `student_subjects`
--

CREATE TABLE `student_subjects` (
  `id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `class_id` int(11) NOT NULL,
  `subject_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `student_subjects`
--

INSERT INTO `student_subjects` (`id`, `student_id`, `class_id`, `subject_id`) VALUES
(1, 7, 3, 5),
(2, 7, 3, 2),
(3, 7, 3, 3),
(4, 6, 1, 4),
(5, 8, 3, 5),
(6, 8, 3, 2),
(7, 8, 3, 3),
(8, 9, 3, 5),
(9, 9, 3, 2),
(10, 9, 3, 3),
(11, 5, 3, 5),
(12, 5, 3, 2),
(13, 5, 3, 3);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `subjects`
--

CREATE TABLE `subjects` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `subjects`
--

INSERT INTO `subjects` (`id`, `name`, `description`) VALUES
(1, 'Toán', NULL),
(2, 'Lập trình', NULL),
(3, 'Tiếng anh', NULL),
(4, 'Triết', NULL),
(5, 'Giáo dục quốc phòng', NULL),
(6, 'Đại số tuyến tính', NULL);

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `submissions`
--

CREATE TABLE `submissions` (
  `id` int(11) NOT NULL,
  `assignment_id` int(11) NOT NULL,
  `student_id` int(11) NOT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `external_link` text DEFAULT NULL,
  `grade` decimal(4,2) DEFAULT NULL,
  `feedback` text DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `submissions`
--

INSERT INTO `submissions` (`id`, `assignment_id`, `student_id`, `file_path`, `external_link`, `grade`, `feedback`, `submitted_at`) VALUES
(1, 2, 7, '1767374205_tải xuống.jpg', '', 99.99, 'ọ', '2026-01-02 17:16:45');

-- --------------------------------------------------------

--
-- Cấu trúc bảng cho bảng `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` varchar(255) DEFAULT NULL,
  `role` enum('admin','teacher','student') NOT NULL,
  `department_id` int(11) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT 'default_avatar.png',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Đang đổ dữ liệu cho bảng `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `full_name`, `email`, `phone`, `address`, `role`, `department_id`, `avatar`, `created_at`) VALUES
(1, 'admin', '123456', 'Quản Trị Viên', 'admin@school.com', NULL, NULL, 'admin', NULL, 'default_avatar.png', '2026-01-02 13:01:36'),
(2, 'gv_toan', '123456', 'Thầy Nguyễn Văn Toán', 'toan@school.com', '0987123123', 'Hà Nam', 'teacher', NULL, '1767360379_6470.jpg', '2026-01-02 13:01:36'),
(3, 'gv_van', '123456', 'Cô Lê Thị Văn', 'van@school.com', '0312312111', 'Hà Nội', 'teacher', NULL, '1767360364_9057.jpg', '2026-01-02 13:01:36'),
(4, 'gv_anh', '123456', 'Mr. John Smith', 'john@school.com', '0312312313', 'Vương quốc anh', 'teacher', NULL, '1767360332_5413.jpg', '2026-01-02 13:01:36'),
(5, 'hs_long', '123456', 'Trần Phi Long', 'long@gmail.com', '0321456789', 'Hà Giang', 'student', 1, '1767360995_4297.jpg', '2026-01-02 13:01:36'),
(6, 'hs_huong', '123456', 'Nguyễn Thu Hương', 'huong@gmail.com', '0213456783', 'Thái bình', 'student', 2, '1767360969_5346.jpg', '2026-01-02 13:01:36'),
(7, 'hs_nam', '123456', 'Lê Văn Nam', 'nam@gmail.com', '0123456782', 'Hà nội', 'student', 3, '1767360947_8478.jpg', '2026-01-02 13:01:36'),
(8, 'huyen', '123456', 'Trần Thị Huyền', NULL, '0123456789', 'Bắc Ninh', 'student', 3, NULL, '2026-01-02 15:15:19'),
(9, 'vu', '123456', 'Dương Văn Vũ', NULL, '0123456781', 'Bắc Ninh', 'student', 3, NULL, '2026-01-02 15:16:40');

--
-- Chỉ mục cho các bảng đã đổ
--

--
-- Chỉ mục cho bảng `assignments`
--
ALTER TABLE `assignments`
  ADD PRIMARY KEY (`id`);

--
-- Chỉ mục cho bảng `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_attendance` (`lesson_log_id`,`student_id`);

--
-- Chỉ mục cho bảng `classes`
--
ALTER TABLE `classes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `department_id` (`department_id`),
  ADD KEY `teacher_id` (`teacher_id`);

--
-- Chỉ mục cho bảng `class_announcements`
--
ALTER TABLE `class_announcements`
  ADD PRIMARY KEY (`id`);

--
-- Chỉ mục cho bảng `class_students`
--
ALTER TABLE `class_students`
  ADD PRIMARY KEY (`class_id`,`student_id`),
  ADD KEY `student_id` (`student_id`);

--
-- Chỉ mục cho bảng `class_subject_teachers`
--
ALTER TABLE `class_subject_teachers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `teacher_id` (`teacher_id`),
  ADD KEY `fk_subject_assignment` (`subject_id`);

--
-- Chỉ mục cho bảng `departments`
--
ALTER TABLE `departments`
  ADD PRIMARY KEY (`id`);

--
-- Chỉ mục cho bảng `exams`
--
ALTER TABLE `exams`
  ADD PRIMARY KEY (`id`);

--
-- Chỉ mục cho bảng `exam_questions`
--
ALTER TABLE `exam_questions`
  ADD PRIMARY KEY (`id`);

--
-- Chỉ mục cho bảng `exam_results`
--
ALTER TABLE `exam_results`
  ADD PRIMARY KEY (`id`);

--
-- Chỉ mục cho bảng `lesson_logs`
--
ALTER TABLE `lesson_logs`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_lesson` (`class_id`,`subject_id`,`lesson_date`,`start_time`);

--
-- Chỉ mục cho bảng `news`
--
ALTER TABLE `news`
  ADD PRIMARY KEY (`id`);

--
-- Chỉ mục cho bảng `student_subjects`
--
ALTER TABLE `student_subjects`
  ADD PRIMARY KEY (`id`),
  ADD KEY `student_id` (`student_id`),
  ADD KEY `class_id` (`class_id`),
  ADD KEY `subject_id` (`subject_id`);

--
-- Chỉ mục cho bảng `subjects`
--
ALTER TABLE `subjects`
  ADD PRIMARY KEY (`id`);

--
-- Chỉ mục cho bảng `submissions`
--
ALTER TABLE `submissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_submission` (`assignment_id`,`student_id`);

--
-- Chỉ mục cho bảng `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD KEY `fk_user_department` (`department_id`);

--
-- AUTO_INCREMENT cho các bảng đã đổ
--

--
-- AUTO_INCREMENT cho bảng `assignments`
--
ALTER TABLE `assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT cho bảng `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT cho bảng `classes`
--
ALTER TABLE `classes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT cho bảng `class_announcements`
--
ALTER TABLE `class_announcements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT cho bảng `class_subject_teachers`
--
ALTER TABLE `class_subject_teachers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT cho bảng `departments`
--
ALTER TABLE `departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT cho bảng `exams`
--
ALTER TABLE `exams`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT cho bảng `exam_questions`
--
ALTER TABLE `exam_questions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT cho bảng `exam_results`
--
ALTER TABLE `exam_results`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT cho bảng `lesson_logs`
--
ALTER TABLE `lesson_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT cho bảng `news`
--
ALTER TABLE `news`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT cho bảng `student_subjects`
--
ALTER TABLE `student_subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT cho bảng `subjects`
--
ALTER TABLE `subjects`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT cho bảng `submissions`
--
ALTER TABLE `submissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT cho bảng `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- Các ràng buộc cho các bảng đã đổ
--

--
-- Các ràng buộc cho bảng `classes`
--
ALTER TABLE `classes`
  ADD CONSTRAINT `classes_ibfk_1` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `classes_ibfk_2` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Các ràng buộc cho bảng `class_students`
--
ALTER TABLE `class_students`
  ADD CONSTRAINT `class_students_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `class_students_ibfk_2` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `class_subject_teachers`
--
ALTER TABLE `class_subject_teachers`
  ADD CONSTRAINT `class_subject_teachers_ibfk_1` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `class_subject_teachers_ibfk_2` FOREIGN KEY (`teacher_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_subject_assignment` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `student_subjects`
--
ALTER TABLE `student_subjects`
  ADD CONSTRAINT `student_subjects_ibfk_1` FOREIGN KEY (`student_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_subjects_ibfk_2` FOREIGN KEY (`class_id`) REFERENCES `classes` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `student_subjects_ibfk_3` FOREIGN KEY (`subject_id`) REFERENCES `subjects` (`id`) ON DELETE CASCADE;

--
-- Các ràng buộc cho bảng `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_user_department` FOREIGN KEY (`department_id`) REFERENCES `departments` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
