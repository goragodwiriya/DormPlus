<?php

declare (strict_types = 1);

use DormPlus\Config\Database;

require dirname(__DIR__).'/bootstrap.php';

$pdo = Database::connection();

$administratorPassword = getenv('DORMPLUS_ADMIN_PASSWORD') ?: 'DormPlus@2026';
$currentYear = (int) date('Y');
$currentMonth = (int) date('n');
$today = new DateTimeImmutable('today');
$now = new DateTimeImmutable();

$firstNames = [
    'วราภรณ์', 'ศักดิ์ชัย', 'สมพร', 'กิตติพงษ์', 'อรทัย',
    'ธนภัทร', 'ปิยะดา', 'ณัฐวุฒิ', 'ชลธิชา', 'ภานุวัฒน์',
    'กัญญารัตน์', 'วีรชัย', 'สุพัตรา', 'จักรกฤษณ์', 'นภัสสร',
    'พงศกร', 'รัตนา', 'ชัยวัฒน์', 'ปวีณา', 'ธีรภัทร',
    'ศิริพร', 'อนุชา', 'ชนัญชิดา', 'วิทยา', 'สุธิดา',
    'ธนกร', 'เบญจมาศ', 'อภิชาติ', 'พรทิพย์', 'ภูริณัฐ',
    'อัญชลี', 'เกรียงไกร', 'มนัสวี', 'วรวิทย์', 'ศศิธร',
    'ปกรณ์', 'ญาดา', 'ธีรเดช', 'ขวัญฤดี', 'ณรงค์',
    'ลลิตา', 'นที'
];

$lastNames = [
    'ใจดี', 'แสนสุข', 'พรหมมา', 'รัตนวงศ์', 'จันทร์ดี',
    'ศรีสุข', 'บุญมี', 'ทองคำ', 'วัฒนา', 'มั่นคง'
];

try {
    $pdo->beginTransaction();

    $pdo->exec('DELETE FROM messages');
    $pdo->exec('DELETE FROM thread_participants');
    $pdo->exec('DELETE FROM message_threads');
    $pdo->exec('DELETE FROM notifications');
    $pdo->exec('DELETE FROM maintenance_requests');
    $pdo->exec('DELETE FROM expenses');
    $pdo->exec('DELETE FROM payments');
    $pdo->exec('DELETE FROM contracts');
    $pdo->exec('DELETE FROM tenants');
    $pdo->exec('DELETE FROM rooms');
    $pdo->exec('DELETE FROM settings');
    $pdo->exec('DELETE FROM users');
    $pdo->exec('DELETE FROM properties');

    $propertyStatement = $pdo->prepare(
        'INSERT INTO properties (
            name, address, phone, email, description
        ) VALUES (
            :name, :address, :phone, :email, :description
        )'
    );

    $propertyStatement->execute([
        'name' => 'หอพักสุขสันต์',
        'address' => '88 ถนนสุขุมวิท กรุงเทพมหานคร 10110',
        'phone' => '02-000-0000',
        'email' => 'contact@example.test',
        'description' => 'หอพักตัวอย่างสำหรับระบบ DormPlus'
    ]);

    $propertyId = (int) $pdo->lastInsertId();

    $userStatement = $pdo->prepare(
        'INSERT INTO users (
            property_id, username, password_hash,
            first_name, last_name, role
        ) VALUES (
            :property_id, :username, :password_hash,
            :first_name, :last_name, :role
        )'
    );

    $userStatement->execute([
        'property_id' => $propertyId,
        'username' => 'admin',
        'password_hash' => password_hash(
            $administratorPassword,
            PASSWORD_DEFAULT
        ),
        'first_name' => 'ถาวร',
        'last_name' => 'ศรีสุวรรณ',
        'role' => 'owner'
    ]);

    $userId = (int) $pdo->lastInsertId();

    $staffAccounts = [
        ['somchai', 'สมชาย', 'ช่างดี', 'staff'],
        ['nattaya', 'ณัฐญา', 'บัญชีดี', 'administrator']
    ];

    $staffIds = [];

    foreach ($staffAccounts as [$username, $firstName, $lastName, $role]) {
        $userStatement->execute([
            'property_id' => $propertyId,
            'username' => $username,
            'password_hash' => password_hash($administratorPassword, PASSWORD_DEFAULT),
            'first_name' => $firstName,
            'last_name' => $lastName,
            'role' => $role
        ]);

        $staffIds[] = (int) $pdo->lastInsertId();
    }

    $roomStatement = $pdo->prepare(
        'INSERT INTO rooms (
            property_id, room_number, floor, type,
            area, monthly_rent, status, description, image
        ) VALUES (
            :property_id, :room_number, :floor, :type,
            :area, :monthly_rent, :status, :description, :image
        )'
    );

    $roomIds = [];
    $roomRents = [];

    /*
     * ห้อง 103 (ลำดับ 3) เว้นว่างไว้ตามตัวอย่างหน้า Dashboard
     * ผู้เช่า 42 คน จึงอยู่ห้องลำดับ 1, 2, 4-43
     */
    $roomOfTenant = static fn(int $tenant): int => $tenant < 3
        ? $tenant
        : $tenant + 1;

    for ($index = 1; $index <= 48; $index++) {
        $floor = (int) ceil($index / 12);
        $numberOnFloor = (($index - 1) % 12) + 1;
        $roomNumber = (string) ($floor * 100 + $numberOnFloor);
        $occupied = $index !== 3 && $index <= 43;
        $deluxe = $index % 4 === 3;
        $rent = $floor === 1 ? 3500 : ($floor === 2 ? 3800 : 4000);

        if ($deluxe) {
            $rent += 300;
        }

        $roomStatement->execute([
            'property_id' => $propertyId,
            'room_number' => $roomNumber,
            'floor' => $floor,
            'type' => $deluxe ? 'deluxe' : 'standard',
            'area' => $deluxe ? 28 : 24,
            'monthly_rent' => $rent,
            'status' => $occupied ? 'occupied' : 'available',
            'description' => 'ห้องพักพร้อมเฟอร์นิเจอร์และห้องน้ำส่วนตัว',
            'image' => 'assets/images/room-placeholder.svg'
        ]);

        $roomIds[$index] = (int) $pdo->lastInsertId();
        $roomRents[$index] = $rent;
    }

    $tenantStatement = $pdo->prepare(
        'INSERT INTO tenants (
            property_id, first_name, last_name, gender,
            phone, email, national_id, address,
            emergency_contact, emergency_phone, status
        ) VALUES (
            :property_id, :first_name, :last_name, :gender,
            :phone, :email, :national_id, :address,
            :emergency_contact, :emergency_phone, :status
        )'
    );

    $contractStatement = $pdo->prepare(
        'INSERT INTO contracts (
            property_id, tenant_id, room_id,
            start_date, end_date, monthly_rent,
            deposit, electricity_rate, water_rate,
            status, notes
        ) VALUES (
            :property_id, :tenant_id, :room_id,
            :start_date, :end_date, :monthly_rent,
            :deposit, :electricity_rate, :water_rate,
            :status, :notes
        )'
    );

    $paymentStatement = $pdo->prepare(
        'INSERT INTO payments (
            property_id, tenant_id, room_id, contract_id,
            amount, payment_type, payment_date,
            period_month, period_year, reference,
            notes, status
        ) VALUES (
            :property_id, :tenant_id, :room_id, :contract_id,
            :amount, :payment_type, :payment_date,
            :period_month, :period_year, :reference,
            :notes, :status
        )'
    );

    $tenantIds = [];
    $contractIds = [];

    for ($index = 1; $index <= 42; $index++) {
        $firstName = $firstNames[$index - 1];
        $lastName = $lastNames[($index - 1) % count($lastNames)];
        $gender = $index <= 18 ? 'male' : 'female';

        $tenantStatement->execute([
            'property_id' => $propertyId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'gender' => $gender,
            'phone' => sprintf('08%08d', $index),
            'email' => "tenant{$index}@example.test",
            'national_id' => null,
            'address' => 'กรุงเทพมหานคร',
            'emergency_contact' => 'ญาติผู้เช่า',
            'emergency_phone' => sprintf('09%08d', $index),
            'status' => 'active'
        ]);

        $tenantId = (int) $pdo->lastInsertId();
        $tenantIds[$index] = $tenantId;

        $startDate = $today->modify('-'.(($index % 7) + 1).' months');
        $endDate = $index === 9
            ? $today->modify('+15 days')
            : $startDate->modify('+1 year');

        $monthlyRent = $roomRents[$roomOfTenant($index)];

        $contractStatement->execute([
            'property_id' => $propertyId,
            'tenant_id' => $tenantId,
            'room_id' => $roomIds[$roomOfTenant($index)],
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d'),
            'monthly_rent' => $monthlyRent,
            'deposit' => $monthlyRent,
            'electricity_rate' => 8,
            'water_rate' => 18,
            'status' => 'active',
            'notes' => null
        ]);

        $contractId = (int) $pdo->lastInsertId();
        $contractIds[$index] = $contractId;
    }

    /*
     * ค่าเช่าเดือนปัจจุบัน: ผู้เช่า 33 รายแรกชำระแล้วตามค่าเช่าห้อง
     * (รวม 126,200 บาท) ที่เหลือยังค้างชำระ
     * ผู้เช่า 5 รายแรกชำระล่าสุดในวันนี้ เพื่อให้แสดงในหน้า Dashboard
     */
    for ($index = 1; $index <= 42; $index++) {
        $paid = $index <= 33;
        $paymentDate = $today
            ->modify('-'.intdiv($index - 1, 5).' days')
            ->setTime(20, 5)
            ->modify('-'.((($index - 1) % 5) * 8).' minutes');

        $paymentStatement->execute([
            'property_id' => $propertyId,
            'tenant_id' => $tenantIds[$index],
            'room_id' => $roomIds[$roomOfTenant($index)],
            'contract_id' => $contractIds[$index],
            'amount' => $roomRents[$roomOfTenant($index)],
            'payment_type' => 'rent',
            'payment_date' => $paid ? $paymentDate->format('Y-m-d H:i:s') : null,
            'period_month' => $currentMonth,
            'period_year' => $currentYear,
            'reference' => sprintf('PAY-%04d-%02d-%03d', $currentYear, $currentMonth, $index),
            'notes' => 'ค่าเช่าประจำเดือน',
            'status' => $paid ? 'paid' : 'pending'
        ]);
    }

    /*
     * ค่าน้ำ-ค่าไฟของผู้เช่า 3 รายแรกในเดือนนี้ (ยังค้างชำระ)
     */
    foreach ([1, 2, 3] as $index) {
        foreach ([['electricity', 640], ['water', 180]] as [$type, $amount]) {
            $paymentStatement->execute([
                'property_id' => $propertyId,
                'tenant_id' => $tenantIds[$index],
                'room_id' => $roomIds[$roomOfTenant($index)],
                'contract_id' => $contractIds[$index],
                'amount' => $amount,
                'payment_type' => $type,
                'payment_date' => null,
                'period_month' => $currentMonth,
                'period_year' => $currentYear,
                'reference' => sprintf('%s-%04d%02d-%03d', strtoupper(substr($type, 0, 3)), $currentYear, $currentMonth, $index),
                'notes' => null,
                'status' => 'pending'
            ]);
        }
    }

    $previousMonths = [
        ['months' => 1, 'income' => 112700],
        ['months' => 2, 'income' => 103500],
        ['months' => 3, 'income' => 94500]
    ];

    foreach ($previousMonths as $monthData) {
        $period = $today->modify("-{$monthData['months']} months");
        $amountPerPayment = $monthData['income'] / 35;

        for ($index = 1; $index <= 35; $index++) {
            $paymentStatement->execute([
                'property_id' => $propertyId,
                'tenant_id' => $tenantIds[$index],
                'room_id' => $roomIds[$roomOfTenant($index)],
                'contract_id' => $contractIds[$index],
                'amount' => round($amountPerPayment, 2),
                'payment_type' => 'rent',
                'payment_date' => $period->format('Y-m-').sprintf('%02d 10:00:00', (($index - 1) % 20) + 1),
                'period_month' => (int) $period->format('n'),
                'period_year' => (int) $period->format('Y'),
                'reference' => sprintf(
                    'HIS-%s-%03d',
                    $period->format('Ym'),
                    $index
                ),
                'notes' => 'ข้อมูลการชำระย้อนหลัง',
                'status' => 'paid'
            ]);
        }
    }

    $expenseStatement = $pdo->prepare(
        'INSERT INTO expenses (
            property_id, title, category, amount, expense_date, notes
        ) VALUES (
            :property_id, :title, :category, :amount, :expense_date, :notes
        )'
    );

    /*
     * รายจ่ายเดือนปัจจุบันรวม 52,300 บาท
     * เดือนก่อนหน้า 49,800 และ 46,500 บาท สำหรับกราฟและการเปรียบเทียบ
     */
    $expensesByMonth = [
        0 => [
            ['ค่าส่วนกลาง', 'utilities', 18000],
            ['ค่าจ้างพนักงาน', 'salary', 22000],
            ['ค่าซ่อมบำรุง', 'maintenance', 7300],
            ['ค่าอุปกรณ์สำนักงาน', 'supplies', 5000]
        ],
        1 => [
            ['ค่าส่วนกลาง', 'utilities', 17500],
            ['ค่าจ้างพนักงาน', 'salary', 22000],
            ['ค่าซ่อมบำรุง', 'maintenance', 6300],
            ['ค่าอุปกรณ์สำนักงาน', 'supplies', 4000]
        ],
        2 => [
            ['ค่าส่วนกลาง', 'utilities', 16800],
            ['ค่าจ้างพนักงาน', 'salary', 22000],
            ['ค่าซ่อมบำรุง', 'maintenance', 4200],
            ['ค่าอุปกรณ์สำนักงาน', 'supplies', 3500]
        ],
        3 => [
            ['ค่าส่วนกลาง', 'utilities', 16200],
            ['ค่าจ้างพนักงาน', 'salary', 22000],
            ['ค่าซ่อมบำรุง', 'maintenance', 3800],
            ['ค่าภาษีโรงเรือน', 'tax', 2500]
        ]
    ];

    foreach ($expensesByMonth as $monthsAgo => $expenses) {
        $expenseDate = $today->modify("-{$monthsAgo} months");

        foreach ($expenses as [$title, $category, $amount]) {
            $expenseStatement->execute([
                'property_id' => $propertyId,
                'title' => $title,
                'category' => $category,
                'amount' => $amount,
                'expense_date' => $expenseDate->format('Y-m-d'),
                'notes' => null
            ]);
        }
    }

    $maintenanceStatement = $pdo->prepare(
        'INSERT INTO maintenance_requests (
            property_id, room_id, tenant_id, title,
            description, priority, status, assigned_to,
            reported_at, started_at, completed_at, cost
        ) VALUES (
            :property_id, :room_id, :tenant_id, :title,
            :description, :priority, :status, :assigned_to,
            :reported_at, :started_at, :completed_at, :cost
        )'
    );

    $maintenanceItems = [
        [14, 'น้ำรั่วในห้องน้ำ', 'พบรอยรั่วบริเวณใต้อ่างล้างหน้า', 'urgent', 'pending', 0, 1],
        [17, 'เครื่องปรับอากาศไม่เย็น', 'เครื่องปรับอากาศทำงานแต่ไม่มีลมเย็น', 'high', 'in_progress', 0, 2],
        [25, 'หลอดไฟเสีย', 'หลอดไฟบริเวณหน้าห้องไม่ทำงาน', 'normal', 'in_progress', 0, 3],
        [5, 'ก๊อกน้ำปิดไม่สนิท', 'ก๊อกน้ำในห้องน้ำหยดตลอดเวลา', 'normal', 'completed', 350, 96],
        [9, 'ประตูล็อกไม่ได้', 'ลูกบิดประตูหลวม ล็อกไม่ได้', 'high', 'completed', 850, 168],
        [30, 'ขอเปลี่ยนที่นอน', 'ผู้เช่าขอเปลี่ยนที่นอนใหม่', 'low', 'cancelled', 0, 240]
    ];

    foreach ($maintenanceItems as $item) {
        [$tenantIndex, $title, $description, $priority, $status, $cost, $hoursAgo] = $item;

        $reportedAt = $now->modify("-{$hoursAgo} hours");
        $closed = in_array($status, ['completed', 'cancelled'], true);

        $maintenanceStatement->execute([
            'property_id' => $propertyId,
            'room_id' => $roomIds[$roomOfTenant($tenantIndex)],
            'tenant_id' => $tenantIds[$tenantIndex],
            'title' => $title,
            'description' => $description,
            'priority' => $priority,
            'status' => $status,
            'assigned_to' => $status === 'pending' ? null : 'สมชาย ช่างดี',
            'reported_at' => $reportedAt->format('Y-m-d H:i:s'),
            'started_at' => $status === 'pending' ? null : $reportedAt->modify('+2 hours')->format('Y-m-d H:i:s'),
            'completed_at' => $closed ? $reportedAt->modify('+1 day')->format('Y-m-d H:i:s') : null,
            'cost' => $cost
        ]);
    }

    $notificationStatement = $pdo->prepare(
        'INSERT INTO notifications (
            property_id, user_id, type, title,
            description, related_type, related_id,
            is_read, created_at
        ) VALUES (
            :property_id, :user_id, :type, :title,
            :description, :related_type, :related_id,
            :is_read, :created_at
        )'
    );

    $notifications = [
        ['maintenance', 'มีแจ้งซ่อมใหม่', 'ห้อง 203 - น้ำรั่วในห้องน้ำ', 'maintenance', 1, 1],
        ['payment', 'ผู้เช่าชำระค่าเช่า', 'ห้อง 101 - 3,500 บาท', 'payment', 1, 3],
        ['contract', 'ใกล้หมดสัญญาเช่า', 'ห้อง 110 - เหลือ 15 วัน', 'contract', $contractIds[9], 5],
        ['room', 'ห้องว่างพร้อมให้เช่า', 'ห้อง 103 - 3,800 บาท/เดือน', 'room', $roomIds[3], 7]
    ];

    foreach ($notifications as $item) {
        [$type, $title, $description, $relatedType, $relatedId, $hours] = $item;

        $notificationStatement->execute([
            'property_id' => $propertyId,
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'description' => $description,
            'related_type' => $relatedType,
            'related_id' => $relatedId,
            'is_read' => 0,
            'created_at' => $now
                ->modify("-{$hours} hours")
                ->format('Y-m-d H:i:s')
        ]);
    }

    $settingStatement = $pdo->prepare(
        'INSERT INTO settings (
            property_id, setting_key, setting_value
        ) VALUES (
            :property_id, :setting_key, :setting_value
        )'
    );

    $settings = [
        'default_monthly_rent' => '3500',
        'electricity_rate' => '8',
        'water_rate' => '18',
        'payment_due_day' => '5',
        'language' => 'th',
        'date_format' => 'd/m/Y',
        'currency' => 'THB',
        'contract_warning_days' => '30'
    ];

    foreach ($settings as $key => $value) {
        $settingStatement->execute([
            'property_id' => $propertyId,
            'setting_key' => $key,
            'setting_value' => $value
        ]);
    }

    $threadStatement = $pdo->prepare(
        'INSERT INTO message_threads (
            property_id, subject, created_by
        ) VALUES (
            :property_id, :subject, :created_by
        )'
    );

    $participantStatement = $pdo->prepare(
        'INSERT INTO thread_participants (
            thread_id, user_id, last_read_at
        ) VALUES (
            :thread_id, :user_id, :last_read_at
        )'
    );

    $messageStatement = $pdo->prepare(
        'INSERT INTO messages (
            thread_id, sender_id, body, created_at
        ) VALUES (
            :thread_id, :sender_id, :body, :created_at
        )'
    );

    [$technicianId, $accountantId] = $staffIds;

    $threads = [
        [
            'subject' => 'แจ้งกำหนดตรวจสอบระบบไฟฟ้า',
            'participants' => [$userId, $technicianId],
            'messages' => [
                [$userId, 'จะมีการตรวจสอบระบบไฟฟ้าส่วนกลางในวันเสาร์ เวลา 10:00 น. ช่วยเตรียมอุปกรณ์ด้วยครับ', 30],
                [$technicianId, 'รับทราบครับ จะเตรียมเครื่องมือและแจ้งผู้เช่าชั้น 2-3 ล่วงหน้า', 28],
                [$userId, 'ขอบคุณครับ ถ้าพบจุดผิดปกติให้ถ่ายรูปส่งมาด้วยนะครับ', 27]
            ],
            'admin_read_hours' => 26
        ],
        [
            'subject' => 'สรุปยอดค่าเช่าเดือนนี้',
            'participants' => [$userId, $accountantId],
            'messages' => [
                [$accountantId, 'ยอดค่าเช่าเดือนนี้เก็บได้แล้ว 33 ห้อง เหลืออีก 9 ห้องที่ยังไม่ชำระค่ะ', 6],
                [$accountantId, 'ห้อง 110 ใกล้หมดสัญญาแล้ว ต้องการให้ส่งหนังสือแจ้งต่อสัญญาไหมคะ', 5]
            ],
            'admin_read_hours' => null
        ],
        [
            'subject' => 'งานซ่อมห้อง 203',
            'participants' => [$userId, $technicianId, $accountantId],
            'messages' => [
                [$technicianId, 'ห้อง 203 น้ำรั่วใต้อ่างล้างหน้า ต้องเปลี่ยนท่อ ประเมินค่าอะไหล่ประมาณ 450 บาทครับ', 2],
                [$userId, 'อนุมัติครับ ดำเนินการได้เลย', 1]
            ],
            'admin_read_hours' => 1
        ]
    ];

    foreach ($threads as $thread) {
        $threadStatement->execute([
            'property_id' => $propertyId,
            'subject' => $thread['subject'],
            'created_by' => $thread['participants'][0]
        ]);

        $threadId = (int) $pdo->lastInsertId();

        foreach ($thread['participants'] as $participantId) {
            $lastRead = null;

            if ($participantId === $userId && $thread['admin_read_hours'] !== null) {
                $lastRead = $now->modify("-{$thread['admin_read_hours']} hours")->format('Y-m-d H:i:s');
            } elseif ($participantId !== $userId) {
                $lastRead = $now->format('Y-m-d H:i:s');
            }

            $participantStatement->execute([
                'thread_id' => $threadId,
                'user_id' => $participantId,
                'last_read_at' => $lastRead
            ]);
        }

        foreach ($thread['messages'] as [$senderId, $body, $hoursAgo]) {
            $messageStatement->execute([
                'thread_id' => $threadId,
                'sender_id' => $senderId,
                'body' => $body,
                'created_at' => $now->modify("-{$hoursAgo} hours")->format('Y-m-d H:i:s')
            ]);
        }
    }

    $pdo->commit();

    echo "สร้างข้อมูลตัวอย่างเรียบร้อยแล้ว\n";
    echo "ชื่อผู้ใช้: admin\n";
    echo "รหัสผ่านถูกกำหนดจาก DORMPLUS_ADMIN_PASSWORD หรือค่าเริ่มต้นสำหรับการพัฒนา\n";
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    fwrite(STDERR, "สร้างข้อมูลตัวอย่างไม่สำเร็จ: {$exception->getMessage()}\n");
    exit(1);
}