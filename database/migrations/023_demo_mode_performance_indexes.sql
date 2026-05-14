ALTER TABLE `course_sections`
    ADD INDEX IF NOT EXISTS `idx_cs_sem_room_status` (`semester_id`, `room`, `status`),
    ADD INDEX IF NOT EXISTS `idx_cs_sem_teacher_status` (`semester_id`, `teacher_id`, `status`),
    ADD INDEX IF NOT EXISTS `idx_cs_sem_mode_status` (`semester_id`, `data_mode`, `status`);

ALTER TABLE `student_subjects`
    ADD INDEX IF NOT EXISTS `idx_ss_section_status` (`course_section_id`, `status`),
    ADD INDEX IF NOT EXISTS `idx_ss_student_status` (`student_id`, `status`);
