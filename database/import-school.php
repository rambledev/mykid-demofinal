<?php
/**
 * CLI twin of the admin "นำเข้า Seed Data" button (same code: app/lib/importer.php).
 *
 *   php database/import-school.php [files...]            dry run: show what would change
 *   php database/import-school.php [files...] --confirm  write it (one transaction)
 *
 * Default files: database/data/seed-teachers.json + database/data/seed-students.json
 * (formats: *.example.json next to them). The real files hold children's names, so they are
 * gitignored / dockerignored (PDPA) and stay on the admin's machine.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
require $root . '/app/bootstrap.php';

$files = array_values(array_filter(array_slice($argv, 1), fn ($a) => !str_starts_with($a, '--')));
$files = $files ?: [$root . '/database/data/seed-teachers.json', $root . '/database/data/seed-students.json'];
$confirm = in_array('--confirm', $argv, true);

echo '[Import] ' . ($confirm ? 'WRITING' : 'dry run (add --confirm to write)') . ' · ' . mk_env('DB_HOST') . ':' . mk_env('DB_PORT') . '/' . mk_env('DB_NAME') . "\n";
try {
    $docs = [];
    foreach ($files as $file) {
        $doc = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;
        if ($doc === null) {
            throw new ValidationException("อ่านไฟล์ไม่ได้หรือไม่ใช่ JSON: $file");
        }
        $docs[basename($file)] = $doc;
        echo "  file      $file\n";
    }
    $report = seed_import(seed_classify($docs), $confirm);
} catch (ValidationException $e) {
    fwrite(STDERR, '❌ ' . $e->getMessage() . "\n");
    exit(1);
}
foreach (seed_report($report) as $line) {
    echo "  $line\n";
}
echo $confirm ? "✅ นำเข้าเรียบร้อย\n" : "ยังไม่ได้บันทึก — รันอีกครั้งพร้อม --confirm\n";
