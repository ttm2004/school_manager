<?php
require_once __DIR__ . '/../config.php';
adm_require_auth();
header('Content-Type: application/json');

$data    = json_decode(file_get_contents('php://input'), true);
$majorId = intval($data['major_id'] ?? 0);
$year    = intval($data['year'] ?? date('Y'));

$conn->begin_transaction();
try {
    // Get majors to process
    if ($majorId > 0) {
        $majorIds = [$majorId];
    } else {
        $res = $conn->query("SELECT DISTINCT major_id FROM adm_cutoff_scores WHERE year=$year");
        $majorIds = [];
        while ($r = $res->fetch_assoc()) $majorIds[] = $r['major_id'];
    }

    $stats = ['passed' => 0, 'failed' => 0, 'pending' => 0];

    foreach ($majorIds as $mid) {
        $quota = $conn->query("SELECT quota FROM adm_quota WHERE major_id=$mid AND year=$year")->fetch_assoc()['quota'] ?? 999;

        // Get all registrations with scores for this major/year, sorted desc
        $regs = $conn->query("
            SELECT r.id, r.method_code, r.combination_id, s.total_score
            FROM adm_registrations r
            JOIN adm_scores s ON r.id = s.registration_id
            WHERE r.major_id = $mid AND YEAR(r.created_at) = $year AND s.total_score IS NOT NULL
            ORDER BY s.total_score DESC
        ");

        // Get cutoff for this major
        $cutoffRow = $conn->query("SELECT score FROM adm_cutoff_scores WHERE major_id=$mid AND year=$year ORDER BY score DESC LIMIT 1")->fetch_assoc();
        $cutoff = $cutoffRow ? $cutoffRow['score'] : 0;

        $rank = 0;
        while ($reg = $regs->fetch_assoc()) {
            $rank++;
            $status = ($reg['total_score'] >= $cutoff && $rank <= $quota) ? 'passed' : 'failed';
            $regStatus = $status === 'passed' ? 'approved' : 'rejected';

            // Update registration status
            $conn->query("UPDATE adm_registrations SET status='$regStatus' WHERE id={$reg['id']}");

            // Upsert result
            $note = $status === 'passed' ? 'Trúng tuyển' : 'Không trúng tuyển';
            $stmt = $conn->prepare("INSERT INTO adm_results (registration_id, major_id, year, method_code, combination_id, total_score, cutoff_score, status, note)
                VALUES (?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status), total_score=VALUES(total_score), cutoff_score=VALUES(cutoff_score), note=VALUES(note)");
            $stmt->bind_param('iiisiddss', $reg['id'], $mid, $year, $reg['method_code'], $reg['combination_id'], $reg['total_score'], $cutoff, $status, $note);
            $stmt->execute();

            $stats[$status]++;
        }

        // Registrations without scores stay pending
        $conn->query("UPDATE adm_registrations r LEFT JOIN adm_scores s ON r.id=s.registration_id
            SET r.status='pending' WHERE r.major_id=$mid AND YEAR(r.created_at)=$year AND s.id IS NULL");
    }

    $conn->commit();
    adm_json(true, 'Xét tuyển hoàn tất!', ['stats' => $stats]);
} catch (Exception $e) {
    $conn->rollback();
    adm_json(false, 'Lỗi: ' . $e->getMessage());
}
