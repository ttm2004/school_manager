<?php

require_once __DIR__ . '/../../includes/grade_windows.php';

final class GradeWindowService
{
    public static function isOpen(?string $sectionEndDate, ?string $gradeDeadline): bool
    {
        return isGradeInputWindowOpen($sectionEndDate, $gradeDeadline);
    }

    public static function message(array $row): string
    {
        return gradeInputWindowMessage($row);
    }

    public static function forSection(mysqli $conn, int $sectionId, ?int $teacherId = null): ?array
    {
        return getGradeInputWindowForSection($conn, $sectionId, $teacherId);
    }

    public static function openSectionsForTeacher(mysqli $conn, int $teacherId): array
    {
        return getTeacherOpenGradeSections($conn, $teacherId);
    }
}

