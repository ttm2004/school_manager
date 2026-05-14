<?php

require_once __DIR__ . '/../../includes/AcademicPolicy.php';

final class StudentRegistrationService
{
    public static function validateRegistration(mysqli $conn, int $studentId, int $sectionId, int $maxCredits = 25): array
    {
        return academicPolicyValidateStudentRegistration($conn, $studentId, $sectionId, $maxCredits);
    }

    public static function scheduleConflict(mysqli $conn, int $studentId, int $sectionId): array
    {
        return academicPolicyStudentScheduleConflict($conn, $studentId, $sectionId);
    }
}

