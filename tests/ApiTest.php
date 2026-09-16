<?php

declare (strict_types = 1);

/**
 * ชุดทดสอบ REST API ของ DormPlus
 *
 * รัน:  php tests/ApiTest.php
 *
 * สคริปต์จะสร้างฐานข้อมูล SQLite ชั่วคราว รัน migration + seed
 * เปิด PHP built-in server บนพอร์ตว่าง แล้วยิงคำขอ HTTP จริงไปทดสอบ
 * ไม่แตะฐานข้อมูลที่ใช้งานอยู่
 */

$root = dirname(__DIR__);
$databasePath = sys_get_temp_dir().'/dormplus-test-'.bin2hex(random_bytes(4)).'.sqlite';
$port = 8900 + random_int(0, 99);
$baseUrl = "http://127.0.0.1:{$port}/api";

putenv("DB_PATH={$databasePath}");
putenv('DORMPLUS_ADMIN_PASSWORD=Test@12345');

/* ---------------------------------------------------------------------- */
/* เตรียมฐานข้อมูล                                                          */
/* ---------------------------------------------------------------------- */

echo "เตรียมฐานข้อมูลทดสอบ... ";

ob_start();
$migrateExit = run("php {$root}/database/migrate.php");
$seedExit = run("php {$root}/database/seed.php");
ob_end_clean();

if ($migrateExit !== 0 || $seedExit !== 0) {
    fwrite(STDERR, "migration/seed ล้มเหลว\n");
    exit(1);
}

echo "เรียบร้อย\n";

/* ---------------------------------------------------------------------- */
/* เปิดเซิร์ฟเวอร์                                                          */
/* ---------------------------------------------------------------------- */

$descriptors = [0 => ['pipe', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']];
$server = proc_open(
    ['php', '-S', "127.0.0.1:{$port}", '-t', "{$root}/public", "{$root}/public/router.php"],
    $descriptors,
    $pipes,
    $root,
    ['DB_PATH' => $databasePath, 'PATH' => getenv('PATH')]
);

if (!is_resource($server)) {
    fwrite(STDERR, "ไม่สามารถเปิดเซิร์ฟเวอร์ทดสอบได้\n");
    exit(1);
}

register_shutdown_function(static function () use ($server, $databasePath): void {
    proc_terminate($server);
    proc_close($server);

    foreach (['', '-shm', '-wal'] as $suffix) {
        @unlink($databasePath.$suffix);
    }
});

$ready = false;

for ($attempt = 0; $attempt < 50; $attempt++) {
    usleep(100000);
    $response = request('GET', '/health');

    if ($response['status'] === 200) {
        $ready = true;
        break;
    }
}

if (!$ready) {
    fwrite(STDERR, "เซิร์ฟเวอร์ทดสอบไม่ตอบสนอง\n");
    exit(1);
}

echo "เซิร์ฟเวอร์ทดสอบพร้อมที่ {$baseUrl}\n\n";

/* ---------------------------------------------------------------------- */
/* HTTP client แบบง่าย (เก็บ cookie + CSRF token)                            */
/* ---------------------------------------------------------------------- */

$cookieJar = tempnam(sys_get_temp_dir(), 'dormplus-cookie-');
$csrfToken = null;

function request(string $method, string $path, ?array $body = null, array $headers = []): array
{
    global $baseUrl, $cookieJar, $csrfToken;

    $handle = curl_init($baseUrl.$path);
    $requestHeaders = ['Accept: application/json'];

    if ($body !== null) {
        $requestHeaders[] = 'Content-Type: application/json';
        curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
    }

    if ($csrfToken !== null && !isset($headers['no-csrf'])) {
        $requestHeaders[] = 'X-CSRF-Token: '.$csrfToken;
    }

    curl_setopt_array($handle, [
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $requestHeaders,
        CURLOPT_COOKIEJAR => $cookieJar,
        CURLOPT_COOKIEFILE => $cookieJar,
        CURLOPT_TIMEOUT => 10
    ]);

    $raw = curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    curl_close($handle);

    $decoded = is_string($raw) ? json_decode($raw, true) : null;

    return [
        'status' => $status,
        'body' => is_array($decoded) ? $decoded : [],
        'data' => $decoded['data'] ?? null,
        'error' => $decoded['error'] ?? null
    ];
}

function run(string $command): int
{
    exec($command.' 2>&1', $output, $exit);

    return $exit;
}

/* ---------------------------------------------------------------------- */
/* ตัวช่วยตรวจสอบ                                                           */
/* ---------------------------------------------------------------------- */

$passed = 0;
$failed = 0;

function check(string $name, bool $condition, string $detail = ''): void
{
    global $passed, $failed;

    if ($condition) {
        $passed++;
        echo "  ✔ {$name}\n";
    } else {
        $failed++;
        echo "  ✘ {$name}".($detail !== '' ? " — {$detail}" : '')."\n";
    }
}

function section(string $title): void
{
    echo "\n{$title}\n";
}

function describe(array $response): string
{
    return "HTTP {$response['status']} ".json_encode($response['body'], JSON_UNESCAPED_UNICODE);
}

/* ---------------------------------------------------------------------- */
/* การทดสอบ                                                                */
/* ---------------------------------------------------------------------- */

section('Authentication');

$response = request('GET', '/rooms');
check('ยังไม่ล็อกอิน → 401', $response['status'] === 401, describe($response));

$response = request('POST', '/auth/login', ['username' => 'admin', 'password' => 'wrong']);
check('รหัสผ่านผิด → 401', $response['status'] === 401, describe($response));

$response = request('POST', '/auth/login', ['username' => '', 'password' => '']);
check('ฟอร์มว่าง → 422 พร้อม fields', $response['status'] === 422 && isset($response['error']['fields']['username']), describe($response));

$response = request('POST', '/auth/login', ['username' => 'admin', 'password' => 'Test@12345']);
check('ล็อกอินสำเร็จ', $response['status'] === 200 && ($response['data']['user']['username'] ?? '') === 'admin', describe($response));
$csrfToken = $response['data']['csrf_token'] ?? null;
check('ได้รับ CSRF token', is_string($csrfToken) && strlen($csrfToken) === 64);

$response = request('GET', '/auth/me');
check('GET /auth/me คืนผู้ใช้ปัจจุบัน', ($response['data']['user']['role'] ?? '') === 'owner', describe($response));

$savedToken = $csrfToken;
$csrfToken = null;
$response = request('POST', '/rooms', ['room_number' => '999']);
check('POST โดยไม่มี CSRF token → 419', $response['status'] === 419, describe($response));
$csrfToken = $savedToken;

section('Dashboard');

$response = request('GET', '/dashboard');
$statistics = $response['data']['statistics'] ?? [];
check('GET /dashboard สำเร็จ', $response['status'] === 200, describe($response));
check('ห้องทั้งหมด 48 ห้อง จาก seed', ($statistics['rooms']['total'] ?? 0) === 48);
check('ห้องว่าง 6 ห้อง', ($statistics['rooms']['available'] ?? 0) === 6);
check('ผู้เช่า 42 คน (ชาย 18 หญิง 24)', ($statistics['tenants']['total'] ?? 0) === 42 && ($statistics['tenants']['male'] ?? 0) === 18);
check('รายได้เดือนนี้ = ผลรวมการชำระที่ paid', (float) ($statistics['monthly_income']['total'] ?? 0) === 126200.0);
check('งานซ่อมค้าง 3 รายการ', ($statistics['maintenance']['total'] ?? 0) === 3);

$summary = $response['data']['financial_summary'] ?? [];
check('กำไรสุทธิ = รายรับ - รายจ่าย', abs(($summary['income']['total'] - $summary['expenses']['total']) - $summary['net_profit']['total']) < 0.01);
check('กราฟการเงินมี 4 เดือน', count($response['data']['financial_chart'] ?? []) === 4);
check('สถานะห้อง occupied + available + maintenance + reserved = total', (function (array $status): bool {
    return ($status['occupied'] + $status['available'] + $status['maintenance'] + $status['reserved']) === $status['total'];
})($response['data']['room_status']));

section('Rooms CRUD');

$response = request('POST', '/rooms', ['room_number' => '', 'floor' => 'x', 'monthly_rent' => -5]);
check('สร้างห้องด้วยข้อมูลผิด → 422', $response['status'] === 422 && isset($response['error']['fields']['room_number'], $response['error']['fields']['floor']), describe($response));

$response = request('POST', '/rooms', [
    'room_number' => '501', 'floor' => 5, 'type' => 'deluxe', 'area' => 28,
    'monthly_rent' => 4500, 'status' => 'available', 'description' => 'ห้องทดสอบ'
]);
$roomId = $response['data']['id'] ?? 0;
check('สร้างห้อง → 201', $response['status'] === 201 && $roomId > 0, describe($response));

$response = request('POST', '/rooms', ['room_number' => '501', 'floor' => 5, 'monthly_rent' => 4500]);
check('เลขห้องซ้ำ → 422', $response['status'] === 422 && isset($response['error']['fields']['room_number']), describe($response));

$response = request('GET', "/rooms/{$roomId}");
check('อ่านรายละเอียดห้อง', ($response['data']['room']['room_number'] ?? '') === '501' && isset($response['data']['contracts']), describe($response));

$response = request('PUT', "/rooms/{$roomId}", [
    'room_number' => '501', 'floor' => 5, 'type' => 'suite', 'area' => 30,
    'monthly_rent' => 4800, 'status' => 'available', 'description' => 'แก้ไข'
]);
check('แก้ไขห้อง', $response['status'] === 200 && (float) ($response['data']['monthly_rent'] ?? 0) === 4800.0, describe($response));

$response = request('PUT', "/rooms/{$roomId}/status", ['status' => 'occupied']);
check('ตั้ง occupied โดยไม่มีสัญญา → 422', $response['status'] === 422, describe($response));

$response = request('GET', '/rooms?q=501');
check('ค้นหาห้อง 501', $response['status'] === 200 && count($response['data']) === 1 && ($response['body']['pagination']['total'] ?? 0) === 1, describe($response));

$response = request('GET', '/rooms?status=available&per_page=3&page=2');
check('แบ่งหน้า per_page=3 page=2', count($response['data']) === 3 && ($response['body']['pagination']['page'] ?? 0) === 2 && ($response['body']['pagination']['total_pages'] ?? 0) === 3, describe($response));

$response = request('GET', '/rooms/abc');
check('id ไม่ถูกต้อง → 404', $response['status'] === 404, describe($response));

$response = request('GET', '/rooms/999999');
check('id ไม่มีอยู่ → 404', $response['status'] === 404, describe($response));

section('Tenants CRUD');

$response = request('POST', '/tenants', ['first_name' => 'ทดสอบ', 'phone' => 'abc']);
check('สร้างผู้เช่าข้อมูลผิด → 422', $response['status'] === 422 && isset($response['error']['fields']['last_name'], $response['error']['fields']['phone']), describe($response));

$response = request('POST', '/tenants', [
    'first_name' => 'ทดสอบ', 'last_name' => 'ระบบ', 'gender' => 'female',
    'phone' => '0812345678', 'email' => 'test@example.test', 'national_id' => '1234567890123'
]);
$tenantId = $response['data']['id'] ?? 0;
check('สร้างผู้เช่า → 201', $response['status'] === 201 && $tenantId > 0, describe($response));

$response = request('PUT', "/tenants/{$tenantId}", [
    'first_name' => 'ทดสอบ', 'last_name' => 'แก้ไข', 'gender' => 'female', 'phone' => '0812345678', 'status' => 'active'
]);
check('แก้ไขผู้เช่า', ($response['data']['last_name'] ?? '') === 'แก้ไข', describe($response));

$response = request('GET', '/tenants?'.http_build_query(['q' => 'ทดสอบ']));
check('ค้นหาผู้เช่าภาษาไทย', count($response['data']) === 1, describe($response));

section('Contracts (transaction)');

$response = request('POST', '/contracts', [
    'tenant_id' => $tenantId, 'room_id' => $roomId, 'start_date' => '2026-09-01',
    'end_date' => '2026-08-01', 'monthly_rent' => 4800
]);
check('วันสิ้นสุดก่อนวันเริ่ม → 422', $response['status'] === 422 && isset($response['error']['fields']['end_date']), describe($response));

$response = request('POST', '/contracts', [
    'tenant_id' => $tenantId, 'room_id' => $roomId, 'start_date' => '2026-09-01',
    'end_date' => '2027-08-31', 'monthly_rent' => 4800, 'deposit' => 4800,
    'electricity_rate' => 8, 'water_rate' => 18
]);
$contractId = $response['data']['id'] ?? 0;
check('สร้างสัญญา → 201', $response['status'] === 201 && $contractId > 0, describe($response));

$response = request('GET', "/rooms/{$roomId}");
check('ห้องเปลี่ยนเป็น occupied และผูกผู้เช่า', ($response['data']['room']['status'] ?? '') === 'occupied' && ($response['data']['room']['tenant_id'] ?? 0) === $tenantId, describe($response));

$response = request('POST', '/contracts', [
    'tenant_id' => $tenantId, 'room_id' => $roomId, 'start_date' => '2026-09-01',
    'end_date' => '2027-08-31', 'monthly_rent' => 4800
]);
check('ห้อง/ผู้เช่าที่มีสัญญาแล้ว → 422', $response['status'] === 422 && isset($response['error']['fields']['room_id']), describe($response));

$response = request('DELETE', "/rooms/{$roomId}");
check('ลบห้องที่มีสัญญา → 409', $response['status'] === 409, describe($response));

$response = request('GET', '/dashboard/statistics');
check('สถิติ: ห้องใหม่ถูกนับเป็น occupied (ว่างยังคง 6 จาก 49)', ($response['data']['rooms']['available'] ?? 0) === 6 && ($response['data']['rooms']['occupied'] ?? 0) === 43 && ($response['data']['rooms']['total'] ?? 0) === 49, describe($response));

section('Payments');

$response = request('POST', '/payments', ['tenant_id' => $tenantId, 'amount' => 'x']);
check('ยอดเงินไม่ใช่ตัวเลข → 422', $response['status'] === 422, describe($response));

$response = request('POST', '/payments', [
    'tenant_id' => $tenantId, 'amount' => 4800, 'payment_type' => 'rent',
    'period_month' => (int) date('n'), 'period_year' => (int) date('Y')
]);
$paymentId = $response['data']['id'] ?? 0;
check('บันทึกค่าเช่า → 201 พร้อมห้อง/สัญญาอัตโนมัติ', $response['status'] === 201 && ($response['data']['room_id'] ?? 0) === $roomId && ($response['data']['contract_id'] ?? 0) === $contractId, describe($response));
check('สร้างเลขอ้างอิงอัตโนมัติ', str_starts_with((string) ($response['data']['reference'] ?? ''), 'PAY-'));

$response = request('GET', '/dashboard/statistics');
check('รายได้เดือนนี้เพิ่มขึ้น 4,800', (float) ($response['data']['monthly_income']['total'] ?? 0) === 131000.0, describe($response));

$response = request('GET', '/payments?'.http_build_query(['month' => date('Y-m'), 'status' => 'paid', 'q' => 'ทดสอบ']));
check('กรองการชำระตามเดือน/สถานะ/ค้นหา', count($response['data']) === 1, describe($response));

$response = request('GET', '/payments?month=2026/09');
check('รูปแบบเดือนผิด → 422', $response['status'] === 422, describe($response));

$response = request('PUT', "/payments/{$paymentId}/cancel");
check('ยกเลิกรายการชำระ', ($response['data']['status'] ?? '') === 'cancelled', describe($response));

$response = request('GET', '/dashboard/statistics');
check('รายได้กลับเป็นเดิมหลังยกเลิก', (float) ($response['data']['monthly_income']['total'] ?? 0) === 126200.0, describe($response));

section('Maintenance');

$response = request('POST', '/maintenance', ['room_id' => $roomId, 'title' => '']);
check('แจ้งซ่อมไม่มีหัวข้อ → 422', $response['status'] === 422, describe($response));

$response = request('POST', '/maintenance', [
    'room_id' => $roomId, 'tenant_id' => $tenantId, 'title' => 'แอร์เสีย',
    'description' => 'เปิดแล้วไม่เย็น', 'priority' => 'high'
]);
$maintenanceId = $response['data']['id'] ?? 0;
check('สร้างงานซ่อม → 201 สถานะ pending', $response['status'] === 201 && ($response['data']['status'] ?? '') === 'pending', describe($response));

$response = request('GET', '/notifications?unread=1');
$titles = array_column($response['data']['items'] ?? [], 'title');
check('มีการแจ้งเตือน "มีแจ้งซ่อมใหม่"', in_array('มีแจ้งซ่อมใหม่', $titles, true), describe($response));

$response = request('PUT', "/maintenance/{$maintenanceId}/status", ['status' => 'in_progress', 'assigned_to' => 'ช่างทดสอบ']);
check('เปลี่ยนเป็น in_progress ตั้ง started_at', ($response['data']['status'] ?? '') === 'in_progress' && !empty($response['data']['started_at']), describe($response));

$response = request('PUT', "/maintenance/{$maintenanceId}/status", ['status' => 'completed', 'cost' => 1500]);
check('ปิดงานพร้อมค่าใช้จ่าย', ($response['data']['status'] ?? '') === 'completed' && (float) ($response['data']['cost'] ?? 0) === 1500.0 && !empty($response['data']['completed_at']), describe($response));

$response = request('GET', '/dashboard/statistics');
check('งานซ่อมค้างยังคง 3 (งานที่ปิดไม่นับ)', ($response['data']['maintenance']['total'] ?? 0) === 3, describe($response));

section('Notifications');

$response = request('GET', '/notifications');
$firstUnread = null;

foreach ($response['data']['items'] ?? [] as $item) {
    if ((int) $item['is_read'] === 0) {
        $firstUnread = $item;
        break;
    }
}

$unreadBefore = $response['data']['unread_count'] ?? 0;
check('มีรายการที่ยังไม่อ่าน', $firstUnread !== null && $unreadBefore > 0);

$response = request('PUT', "/notifications/{$firstUnread['id']}/read");
check('ทำเครื่องหมายอ่านแล้ว ลด unread_count', ($response['data']['unread_count'] ?? -1) === $unreadBefore - 1, describe($response));

$response = request('PUT', '/notifications/read-all');
check('อ่านทั้งหมด → unread_count 0', ($response['data']['unread_count'] ?? -1) === 0, describe($response));

section('Contracts: renew / end / delete');

$response = request('POST', "/contracts/{$contractId}/renew", ['end_date' => '2028-08-31', 'monthly_rent' => 5000]);
$renewedId = $response['data']['id'] ?? 0;
check('ต่อสัญญาสร้างสัญญาใหม่', $response['status'] === 201 && $renewedId > $contractId && ($response['data']['start_date'] ?? '') === '2027-09-01', describe($response));

$response = request('GET', "/contracts/{$contractId}");
check('สัญญาเดิมกลายเป็น expired', ($response['data']['status'] ?? '') === 'expired', describe($response));

$response = request('PUT', "/contracts/{$renewedId}/end", ['status' => 'terminated']);
check('สิ้นสุดสัญญา', ($response['data']['status'] ?? '') === 'terminated', describe($response));

$response = request('GET', "/rooms/{$roomId}");
check('ห้องกลับเป็น available', ($response['data']['room']['status'] ?? '') === 'available', describe($response));

$response = request('GET', "/tenants/{$tenantId}");
check('ผู้เช่ากลายเป็น inactive', ($response['data']['tenant']['status'] ?? '') === 'inactive', describe($response));

section('Reports');

$response = request('GET', '/reports/income?year='.date('Y'));
check('รายงานรายรับมี 12 เดือน', count($response['data']['months'] ?? []) === 12, describe($response));
check('ยอดรวมรายรับ = ผลรวมรายเดือน', abs(array_sum(array_column($response['data']['months'], 'total')) - $response['data']['totals']['total']) < 0.01);

$response = request('GET', '/reports/occupancy');
check('รายงานอัตราเข้าพัก', ($response['data']['summary']['total'] ?? 0) === 49, describe($response));

$response = request('GET', '/reports/payments?from=2026-13-01&to=2026-09-30');
check('ช่วงวันที่ผิด → 422', $response['status'] === 422, describe($response));

section('Messages');

$response = request('GET', '/messages/recipients');
$recipientId = $response['data'][0]['id'] ?? 0;
check('มีผู้รับข้อความ', $recipientId > 0, describe($response));

$response = request('POST', '/messages/threads', ['subject' => 'ทดสอบ', 'participant_ids' => [$recipientId], 'body' => 'สวัสดี']);
$threadId = $response['data']['thread']['id'] ?? 0;
check('สร้างบทสนทนา → 201', $response['status'] === 201 && $threadId > 0, describe($response));

$response = request('POST', "/messages/threads/{$threadId}", ['body' => 'ตอบกลับ']);
check('ตอบกลับได้ 2 ข้อความ', count($response['data']['messages'] ?? []) === 2, describe($response));

section('Settings & account');

$response = request('PUT', '/settings/rental', ['default_monthly_rent' => 3600, 'electricity_rate' => 8, 'water_rate' => 18, 'payment_due_day' => 40]);
check('วันครบกำหนดเกิน 31 → 422', $response['status'] === 422, describe($response));

$response = request('PUT', '/settings/property', ['name' => 'หอพักทดสอบ']);
check('แก้ไขชื่อหอพัก', ($response['data']['property']['name'] ?? '') === 'หอพักทดสอบ', describe($response));

$response = request('PUT', '/auth/password', ['current_password' => 'wrong', 'new_password' => '12345678', 'confirm_password' => '12345678']);
check('รหัสผ่านปัจจุบันผิด → 422', $response['status'] === 422, describe($response));

section('Delete & foreign keys');

$response = request('DELETE', "/tenants/{$tenantId}");
check('ลบผู้เช่าที่มีประวัติ → 409', $response['status'] === 409, describe($response));

foreach (["/payments/{$paymentId}", "/maintenance/{$maintenanceId}", "/contracts/{$renewedId}", "/contracts/{$contractId}"] as $path) {
    $response = request('DELETE', $path);
    check("DELETE {$path}", $response['status'] === 200, describe($response));
}

$response = request('DELETE', "/tenants/{$tenantId}");
check('ลบผู้เช่าหลังล้างประวัติ', $response['status'] === 200, describe($response));

$response = request('DELETE', "/rooms/{$roomId}");
check('ลบห้องหลังล้างประวัติ', $response['status'] === 200, describe($response));

$response = request('GET', "/rooms/{$roomId}");
check('ห้องที่ลบแล้ว → 404', $response['status'] === 404, describe($response));

$pdo = new PDO('sqlite:'.$databasePath);
$orphans = (int) $pdo->query('PRAGMA foreign_key_check')->fetchColumn();
check('ไม่มี foreign key ที่ชี้ไปยังข้อมูลที่ไม่มีอยู่', $orphans === 0);

$response = request('GET', '/dashboard/statistics');
check('สถิติกลับสู่ค่าเดิมหลังลบข้อมูลทดสอบ', ($response['data']['rooms']['total'] ?? 0) === 48 && ($response['data']['tenants']['total'] ?? 0) === 42, describe($response));

section('Logout');

$response = request('POST', '/auth/logout');
check('ออกจากระบบ', $response['status'] === 200, describe($response));

$response = request('GET', '/auth/me');
check('หลังออกจากระบบ → 401', $response['status'] === 401, describe($response));

/* ---------------------------------------------------------------------- */

@unlink($cookieJar);

echo "\nผ่าน {$passed} รายการ, ไม่ผ่าน {$failed} รายการ\n";

exit($failed === 0 ? 0 : 1);
