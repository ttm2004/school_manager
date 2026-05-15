UPDATE `students` s
JOIN `users` u ON u.id = s.user_id
JOIN `admission_applications` aa ON aa.email = u.email
SET s.`data_mode` = aa.`data_mode`,
    s.`demo_batch_id` = aa.`import_batch_id`
WHERE aa.`data_mode` = 'test'
  AND (s.`data_mode` <> 'test' OR s.`demo_batch_id` IS NULL OR s.`demo_batch_id` = '');

UPDATE `student_subjects` ss
JOIN `students` s ON s.id = ss.student_id
SET ss.`data_mode` = s.`data_mode`,
    ss.`demo_batch_id` = s.`demo_batch_id`
WHERE s.`data_mode` = 'test';

UPDATE `admission_auto_enrollment_requests` aer
JOIN `students` s ON s.id = aer.student_id
SET aer.`auto_enroll_mode` = 'test'
WHERE s.`data_mode` = 'test'
  AND aer.`auto_enroll_mode` <> 'test';
