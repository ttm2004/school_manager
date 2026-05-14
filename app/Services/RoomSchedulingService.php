<?php

require_once __DIR__ . '/../../includes/AcademicPolicy.php';

final class RoomSchedulingService
{
    public static function validateTeachingSchedule(string $teachingMode, ?string $daySessions): array
    {
        return academicPolicyValidateTeachingSchedule($teachingMode, $daySessions);
    }

    public static function findAvailableClassrooms(
        mysqli $conn,
        int $sectionId,
        int $semesterId,
        int $subjectId,
        int $maxStudents,
        string $teachingMode,
        ?string $daySessions,
        string $roomRequirement = ''
    ): array {
        return academicPolicyFindAvailableClassrooms(
            $conn,
            $sectionId,
            $semesterId,
            $subjectId,
            $maxStudents,
            $teachingMode,
            $daySessions,
            $roomRequirement
        );
    }

    public static function validateRoom(
        mysqli $conn,
        string $roomCode,
        int $maxStudents,
        int $subjectId,
        string $teachingMode,
        string $roomRequirement = ''
    ): array {
        return academicPolicyValidateRoom($conn, $roomCode, $maxStudents, $subjectId, $teachingMode, $roomRequirement);
    }
}

