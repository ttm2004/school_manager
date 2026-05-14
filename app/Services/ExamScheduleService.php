<?php

require_once __DIR__ . '/../../includes/AcademicPolicy.php';

final class ExamScheduleService
{
    public static function validate(
        mysqli $conn,
        int $courseSectionId,
        string $examDate,
        string $startTime,
        string $endTime,
        string $room,
        int $ignoreExamId = 0
    ): array {
        return academicPolicyValidateExamSchedule(
            $conn,
            $courseSectionId,
            $examDate,
            $startTime,
            $endTime,
            $room,
            $ignoreExamId
        );
    }
}

