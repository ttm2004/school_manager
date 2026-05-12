<?php
/**
 * Migration Runner — CLI only
 * Usage: php migrate.php [--fresh] [--seed] [--status]
 *
 * php migrate.php          → chạy migrations chưa chạy
 * php migrate.php --fresh  → drop all + re-run (NGUY HIỂM)
 * php migrate.php --seed   → chạy seed data
 * php migrate.php --status → xem trạng thái migrations
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    die('CLI only.');
}

require_once __DIR__ . '/config/app.php';

// Kết nối DB trực tiếp (không qua session)
$conn = new mysqli(
    config('db.host'),
    config('db.user'),
    config('db.pass'),
    config('db.name'),
    config('db.port')
);
$conn->set_charset('utf8mb4');

if ($conn->connect_error) {
    die("❌ DB connection failed: " . $conn->connect_error . "\n");
}

$args  = array_slice($argv, 1);
$fresh  = in_array('--fresh', $args);
$seed   = in_array('--seed', $args);
$status = in_array('--status', $args);

// ── Tạo bảng migrations nếu chưa có ─────────────────────────
$conn->query("
    CREATE TABLE IF NOT EXISTS `_migrations` (
        `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `migration`  VARCHAR(255) NOT NULL,
        `batch`      INT          NOT NULL DEFAULT 1,
        `ran_at`     TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        UNIQUE KEY `uq_migration` (`migration`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// ── --fresh: xóa bảng migrations để chạy lại ─────────────────
if ($fresh) {
    if (config('app.env') === 'production') {
        die("❌ --fresh không được phép trên production!\n");
    }
    echo "⚠️  --fresh: Xóa lịch sử migrations...\n";
    $conn->query("TRUNCATE TABLE `_migrations`");
}

// ── --status: hiển thị trạng thái ────────────────────────────
if ($status) {
    $ran = [];
    $res = $conn->query("SELECT migration, ran_at FROM `_migrations` ORDER BY id");
    while ($row = $res->fetch_assoc()) $ran[$row['migration']] = $row['ran_at'];

    $files = glob(__DIR__ . '/database/migrations/*.sql');
    sort($files);

    echo "\n📋 Migration Status:\n";
    echo str_repeat('─', 60) . "\n";
    foreach ($files as $file) {
        $name = basename($file);
        $done = isset($ran[$name]);
        $icon = $done ? '✅' : '⬜';
        $date = $done ? "  [{$ran[$name]}]" : '';
        echo "$icon  $name$date\n";
    }
    echo str_repeat('─', 60) . "\n";
    echo "✅ Ran: " . count($ran) . " | ⬜ Pending: " . (count($files) - count($ran)) . "\n\n";
    exit(0);
}

// ── Chạy migrations chưa chạy ────────────────────────────────
$files = glob(__DIR__ . '/database/migrations/*.sql');
sort($files);

$ran = [];
$res = $conn->query("SELECT migration FROM `_migrations`");
while ($row = $res->fetch_assoc()) $ran[] = $row['migration'];

$batch   = (int)($conn->query("SELECT MAX(batch) AS b FROM `_migrations`")->fetch_assoc()['b'] ?? 0) + 1;
$pending = 0;

echo "\n🚀 Running migrations (batch $batch)...\n";

foreach ($files as $file) {
    $name = basename($file);
    if (in_array($name, $ran)) continue;

    echo "  ▶ $name ... ";
    $sql = file_get_contents($file);

    // Tách và chạy từng statement
    $conn->multi_query($sql);
    $errors = [];
    do {
        if ($conn->errno) $errors[] = $conn->error;
    } while ($conn->next_result());

    if (!empty($errors)) {
        echo "❌ FAILED\n";
        foreach ($errors as $err) echo "     Error: $err\n";
        echo "\n⛔ Migration stopped at $name\n\n";
        exit(1);
    }

    // Ghi vào bảng migrations
    $stmt = $conn->prepare("INSERT INTO `_migrations` (migration, batch) VALUES (?, ?)");
    $stmt->bind_param('si', $name, $batch);
    $stmt->execute();
    $stmt->close();

    echo "✅ Done\n";
    $pending++;
}

if ($pending === 0) {
    echo "  ✨ Nothing to migrate. All up to date.\n";
} else {
    echo "\n✅ $pending migration(s) ran successfully.\n";
}

// ── --seed: chạy seed data ────────────────────────────────────
if ($seed) {
    echo "\n🌱 Running seeders...\n";
    $seedFiles = glob(__DIR__ . '/database/seeds/*.php');
    sort($seedFiles);
    foreach ($seedFiles as $seeder) {
        $name = basename($seeder);
        echo "  ▶ $name ... ";
        require $seeder;
        echo "✅ Done\n";
    }
    echo "\n✅ Seeding complete.\n";
}

echo "\n";
$conn->close();
