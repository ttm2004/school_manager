<?php

require_once __DIR__ . '/../../includes/teacher_assignment_rules.php';

final class TeacherAssignmentService
{
    public static function validate(mysqli $conn, int $teacherId, int $subjectId, int $semesterId): array
    {
        return validateTeacherAssignment($conn, $teacherId, $subjectId, $semesterId);
    }

    public static function validateForSection(mysqli $conn, int $teacherId, int $sectionId): array
    {
        return validateTeacherAssignmentForSection($conn, $teacherId, $sectionId);
    }
}

