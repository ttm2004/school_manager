-- Chạy file này để xóa đánh giá của 1 sinh viên cụ thể để test lại
-- Thay 1 bằng student_id thực tế, 1 bằng course_section_id, 1 bằng period_id

-- Xem danh sách đánh giá hiện có:
SELECT se.id, st.student_code, cs.section_code, ep.title, se.question_id, se.rating, se.comment
FROM student_evaluations se
JOIN students st ON se.student_id = st.id
JOIN course_sections cs ON se.course_section_id = cs.id
JOIN evaluation_periods ep ON se.period_id = ep.id
ORDER BY se.student_id, se.course_section_id, se.question_id;

-- Xóa toàn bộ đánh giá của 1 sinh viên trong 1 lớp + đợt (để test lại):
-- DELETE FROM student_evaluations
-- WHERE student_id = ? AND course_section_id = ? AND period_id = ?;
