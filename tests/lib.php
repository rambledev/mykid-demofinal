<?php
/**
 * Local test helpers — CLI only, LOCAL test database only.
 *
 * Connection comes from real environment variables (tests/run-local.sh sets them), never from .env:
 * the guard below refuses to run unless DB_HOST is local and DB_NAME ends with "_test", because
 * loading the app applies database/schema.sql to whatever database it connects to.
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

(function (): void {
    $host = (string) getenv('DB_HOST');
    $name = (string) getenv('DB_NAME');
    if (!in_array($host, ['127.0.0.1', 'localhost'], true) || !str_ends_with($name, '_test') || (string) getenv('DB_PASS') === '') {
        fwrite(STDERR, "[Test] REFUSING: set DB_HOST=127.0.0.1, DB_NAME=*_test and DB_PASS in the environment (tests/run-local.sh)\n");
        exit(2);
    }
})();

require dirname(__DIR__) . '/app/bootstrap.php';

const TEST_PIN = '482915';

final class T
{
    public static int $pass = 0;
    public static int $fail = 0;

    public static function ok(string $name, bool $pass, string $detail = ''): void
    {
        $pass ? self::$pass++ : self::$fail++;
        echo ($pass ? '  PASS ' : '  FAIL ') . $name . ($pass || $detail === '' ? '' : ' — ' . $detail) . "\n";
    }

    public static function section(string $title): void
    {
        echo "\n" . $title . "\n";
    }

    public static function done(): never
    {
        echo "\n[Test] " . self::$pass . ' passed, ' . self::$fail . " failed\n";
        exit(self::$fail ? 1 : 0);
    }
}

/** Fresh reads after HTTP writes made by the server process. */
function fresh(string $table): array
{
    store_rows_cache(clear: true);
    return store_rows($table);
}

/** Minimal browser-like HTTP client: cookie jar + CSRF token, JSON API calls. */
final class Client
{
    private string $jar;
    private string $csrf = '';

    public function __construct(private string $base)
    {
        $this->jar = tempnam(sys_get_temp_dir(), 'mk-test-jar-');
    }

    public function __destruct()
    {
        @unlink($this->jar);
    }

    public function request(string $method, string $path, ?string $body = null, array $headers = []): array
    {
        $ch = curl_init($this->base . $path);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEJAR => $this->jar,
            CURLOPT_COOKIEFILE => $this->jar, CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => 20,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $text = (string) curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if (preg_match('/"csrf":"([0-9a-f]{64})"/', $text, $m)) {
            $this->csrf = $m[1];
        }
        return [$status, $text];
    }

    public function get(string $path): array
    {
        return $this->request('GET', $path);
    }

    public function login(string $role, string $phone): bool
    {
        [, $html] = $this->get('/login');
        preg_match('/name="csrf" value="([^"]+)"/', $html, $m);
        $pin = implode('&', array_map(fn ($d) => 'pin[]=' . $d, str_split(TEST_PIN)));
        [$status] = $this->request('POST', '/login', 'csrf=' . urlencode($m[1] ?? '') . '&role=' . $role . '&phone=' . $phone . '&' . $pin,
            ['Content-Type: application/x-www-form-urlencoded']);
        $this->get('/' . $role); // picks up the page's CSRF token
        return $status === 302;
    }

    /** POST /api → decoded JSON (+ http status). */
    public function api(string $action, array $payload = []): array
    {
        [$status, $text] = $this->request('POST', '/api', json_encode(['action' => $action] + $payload, JSON_UNESCAPED_UNICODE),
            ['Content-Type: application/json', 'Accept: application/json', 'X-CSRF-Token: ' . $this->csrf]);
        return (json_decode($text, true) ?: ['ok' => false, 'raw' => $text]) + ['status' => $status];
    }
}

/**
 * Reset the test database and seed the CCTV fixture:
 *   school A (rooms A1, A2, A3): admin, executive, teacher A1, teacher A2, parent (child in A1 + child in A2)
 *   school B (room B1): admin, teacher B1
 * Every account uses TEST_PIN and does not have to change it.
 */
function seed_fixture(): array
{
    $pdo = mk_db();
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
        $pdo->exec('DROP TABLE ' . mk_db_quote($table));
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
    mk_db_run_sql($pdo, (string) file_get_contents(dirname(__DIR__) . '/database/schema.sql'));
    $pdo->prepare("REPLACE INTO app_meta (k, v) VALUES ('ddl', ?)")->execute([md5_file(dirname(__DIR__) . '/database/schema.sql')]);
    store_rows_cache(clear: true);
    store_state(['schema' => 0, 'seedDate' => '', 'version' => 0, 'updatedAt' => '', 'nextId' => 1]);

    return store_mutate(function (array &$s): array {
        $next = function () use (&$s): int { return $s['nextId']++; };
        $pin = pin_hash(TEST_PIN);
        $f = [];
        foreach (['A' => ['A1', 'A2', 'A3'], 'B' => ['B1']] as $school => $rooms) {
            $sid = $f["school$school"] = $next();
            $s['tables']['schools'][] = ['id' => $sid, 'name' => "โรงเรียน$school", 'shortName' => $school, 'status' => 'active'];
            $s['tables']['settings'][] = ['id' => $next(), 'school_id' => $sid];
            foreach ($rooms as $room) {
                $s['tables']['classrooms'][] = ['id' => $f["room$room"] = $next(), 'school_id' => $sid, 'name' => $room, 'emoji' => '🐣', 'color' => '#FFF4CC', 'capacity' => 20];
            }
        }
        $user = function (string $key, string $role, string $phone, string $school, ?int $room = null, ?int $student = null) use (&$s, &$f, $next, $pin): void {
            $s['tables']['users'][] = ['id' => $f[$key] = $next(), 'name' => $key, 'phone' => $phone, 'pin' => $pin, 'must_change_pin' => false,
                'role' => $role, 'school_id' => $f["school$school"], 'classroom_id' => $room, 'student_id' => $student, 'teacher_id' => null];
        };
        foreach (['kidA1' => 'A1', 'kidA2' => 'A2', 'kidB1' => 'B1'] as $kid => $room) {
            $school = $room[0];
            $s['tables']['students'][] = ['id' => $f[$kid] = $next(), 'school_id' => $f["school$school"], 'classroom_id' => $f["room$room"],
                'name' => $kid, 'nickname' => $kid, 'classroom' => $room, 'gender' => 'm',
                'avatar' => ['skin' => '#FFDBC0', 'hair' => '#3B2A20', 'bg' => '#BFE7FF', 'shirt' => '#7CC6F2', 'style' => 'short']];
        }
        $user('adminA', 'admin', '0810000001', 'A');
        $user('execA', 'executive', '0810000002', 'A');
        $user('teacherA1', 'teacher', '0810000003', 'A', $f['roomA1']);
        $user('teacherA2', 'teacher', '0810000004', 'A', $f['roomA2']);
        $user('parentA', 'parent', '0810000005', 'A', null, $f['kidA1']); // first child A1, second child A2
        $user('adminB', 'admin', '0820000001', 'B');
        $user('teacherB1', 'teacher', '0820000003', 'B', $f['roomB1']);
        foreach (['kidA1', 'kidA2'] as $kid) {
            $s['tables']['parentStudents'][] = ['id' => $next(), 'school_id' => $f['schoolA'], 'user_id' => $f['parentA'], 'student_id' => $f[$kid]];
        }
        return $f;
    })['result'];
}
