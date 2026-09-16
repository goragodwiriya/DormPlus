# โครงสร้างฐานข้อมูล DormPlus

SQLite (`database/database.sqlite`) สร้างโดย `php database/migrate.php` จาก
[`database/migrations/001_initial_schema.sql`](../database/migrations/001_initial_schema.sql)

- เปิด `PRAGMA foreign_keys = ON` ทุกการเชื่อมต่อ, `journal_mode = WAL`
- คีย์หลักทุกตารางเป็น `INTEGER PRIMARY KEY AUTOINCREMENT`
- วันที่เก็บเป็นข้อความ ISO: `YYYY-MM-DD` (วันที่) และ `YYYY-MM-DD HH:MM:SS` (เวลาท้องถิ่น)
- `created_at` / `updated_at` ค่าเริ่มต้น `datetime('now', 'localtime')`
- จำนวนเงินเก็บเป็นตัวเลข (`REAL`) ไม่เก็บสตริงที่จัดรูปแบบแล้ว
- ทุกตารางข้อมูลหลักอ้างอิง `property_id` เพื่อรองรับหลายหอพักในอนาคต (ตอนนี้ใช้ 1 หอพัก)

## ความสัมพันธ์

```
properties ──┬── users ──────────┬── notifications (user_id, nullable = ทุกคน)
             │                   ├── message_threads (created_by)
             │                   ├── thread_participants ── message_threads
             │                   └── messages (sender_id)
             ├── rooms ──────────┬── contracts ── payments (contract_id, SET NULL)
             │                   ├── payments (room_id)
             │                   └── maintenance_requests (room_id)
             ├── tenants ────────┬── contracts (tenant_id)
             │                   ├── payments (tenant_id)
             │                   └── maintenance_requests (tenant_id, SET NULL)
             ├── expenses
             ├── notifications
             └── settings
```

การลบ: `properties` ลบแบบ CASCADE ไปยังตารางลูกทั้งหมด; `rooms`/`tenants` ที่มี `contracts` หรือ `payments`
ลบไม่ได้ (`ON DELETE RESTRICT`) — API ตอบ 409 พร้อมคำแนะนำให้เปลี่ยนสถานะแทน

## ตาราง

### properties — หอพัก

| คอลัมน์ | ชนิด | หมายเหตุ |
| --- | --- | --- |
| id | INTEGER PK | |
| name | TEXT NOT NULL | ชื่อหอพัก |
| address, phone, email, description | TEXT | |
| created_at, updated_at | TEXT | |

### users — ผู้ใช้ระบบ

| คอลัมน์ | ชนิด | หมายเหตุ |
| --- | --- | --- |
| property_id | INTEGER FK → properties (SET NULL) | |
| username | TEXT UNIQUE | |
| password_hash | TEXT | `password_hash()` |
| first_name, last_name | TEXT | |
| role | TEXT | `administrator` / `owner` / `staff` |
| avatar | TEXT | path รูป (ไม่บังคับ) |
| is_active | INTEGER 0/1 | |
| last_login_at | TEXT | |

### rooms — ห้องพัก

| คอลัมน์ | ชนิด | หมายเหตุ |
| --- | --- | --- |
| property_id | FK → properties (CASCADE) | |
| room_number | TEXT | UNIQUE ร่วมกับ property_id |
| floor | INTEGER | |
| type | TEXT | `standard` / `deluxe` / `suite` / `studio` |
| area | REAL | ตร.ม. |
| monthly_rent | REAL ≥ 0 | |
| status | TEXT | `available` / `occupied` / `maintenance` / `reserved` |
| description, image | TEXT | image เป็น path สัมพัทธ์ เช่น `assets/images/room-placeholder.svg` |

ดัชนี: `idx_rooms_number (room_number)`, `idx_rooms_property_status (property_id, status)`

### tenants — ผู้เช่า

| คอลัมน์ | ชนิด | หมายเหตุ |
| --- | --- | --- |
| property_id | FK → properties (CASCADE) | |
| first_name, last_name | TEXT NOT NULL | |
| gender | TEXT | `male` / `female` / `other` |
| phone | TEXT NOT NULL | |
| email, national_id, address | TEXT | |
| emergency_contact, emergency_phone | TEXT | |
| status | TEXT | `active` / `inactive` / `blacklisted` |

ดัชนี: `idx_tenants_phone (phone)`, `idx_tenants_property_status (property_id, status)`

### contracts — สัญญาเช่า

| คอลัมน์ | ชนิด | หมายเหตุ |
| --- | --- | --- |
| property_id | FK → properties (CASCADE) | |
| tenant_id | FK → tenants (RESTRICT) | |
| room_id | FK → rooms (RESTRICT) | |
| start_date, end_date | TEXT | CHECK `end_date >= start_date` |
| monthly_rent, deposit | REAL ≥ 0 | |
| electricity_rate, water_rate | REAL ≥ 0 | บาท/หน่วย |
| status | TEXT | `active` / `expired` / `terminated` / `pending` |
| notes | TEXT | |

ดัชนี: `idx_contracts_end_date (end_date)`, `idx_contracts_property_status (property_id, status)`

กฎที่บังคับใน `ContractService` (ไม่ใช่ใน schema): ห้องหนึ่งมีสัญญา `active` ได้ครั้งละ 1 ฉบับ,
ผู้เช่าหนึ่งมีสัญญา `active` ได้ครั้งละ 1 ฉบับ, การสร้าง/สิ้นสุด/ต่อสัญญาปรับสถานะห้องและผู้เช่าใน transaction เดียว

### payments — การชำระเงิน

| คอลัมน์ | ชนิด | หมายเหตุ |
| --- | --- | --- |
| property_id | FK → properties (CASCADE) | |
| tenant_id | FK → tenants (RESTRICT) | |
| room_id | FK → rooms (RESTRICT) | |
| contract_id | FK → contracts (SET NULL) | |
| amount | REAL ≥ 0 | |
| payment_type | TEXT | `rent` / `electricity` / `water` / `other` |
| payment_date | TEXT | เวลาที่ชำระจริง (NULL เมื่อยังไม่ชำระ) |
| period_month | INTEGER 1-12 | งวดที่ชำระ |
| period_year | INTEGER | ค.ศ. |
| reference | TEXT | เลขอ้างอิง (สร้างอัตโนมัติได้) |
| notes | TEXT | |
| status | TEXT | `paid` / `pending` / `cancelled` |

ดัชนี: `idx_payments_date (payment_date)`, `idx_payments_period (period_year, period_month)`,
`idx_payments_property_status (property_id, status)`

รายได้ของเดือนบน Dashboard คิดจาก `payment_date` (เงินเข้าเมื่อไร) ส่วนรายงานรายรับและตัวกรอง `month`
ในหน้าการเงินคิดจาก `period_year/period_month` (ค่าเช่าของงวดไหน)

### expenses — รายจ่าย

| คอลัมน์ | ชนิด | หมายเหตุ |
| --- | --- | --- |
| property_id | FK → properties (CASCADE) | |
| title | TEXT NOT NULL | |
| category | TEXT | `utilities` / `salary` / `maintenance` / `supplies` / `tax` / `other` |
| amount | REAL ≥ 0 | |
| expense_date | TEXT NOT NULL | |
| notes | TEXT | |

ดัชนี: `idx_expenses_date (expense_date)`

### maintenance_requests — งานแจ้งซ่อม

| คอลัมน์ | ชนิด | หมายเหตุ |
| --- | --- | --- |
| property_id | FK → properties (CASCADE) | |
| room_id | FK → rooms (RESTRICT) | |
| tenant_id | FK → tenants (SET NULL) | ผู้แจ้ง (ไม่บังคับ) |
| title, description | TEXT NOT NULL | |
| priority | TEXT | `low` / `normal` / `high` / `urgent` |
| status | TEXT | `pending` / `in_progress` / `completed` / `cancelled` |
| assigned_to | TEXT | ชื่อช่าง/ผู้รับผิดชอบ |
| reported_at, started_at, completed_at | TEXT | |
| cost | REAL ≥ 0 | |
| notes | TEXT | |

ดัชนี: `idx_maintenance_status (status)`, `idx_maintenance_property_status (property_id, status)`

### notifications — การแจ้งเตือน

| คอลัมน์ | ชนิด | หมายเหตุ |
| --- | --- | --- |
| property_id | FK → properties (CASCADE) | |
| user_id | FK → users (CASCADE) | NULL = แสดงให้ทุกผู้ใช้ในหอพัก |
| type | TEXT | `maintenance` / `payment` / `contract` / `room` |
| title, description | TEXT | |
| related_type, related_id | TEXT, INTEGER | ข้อมูลต้นทาง เช่น `contract` + id |
| is_read | INTEGER 0/1 | |
| created_at, read_at | TEXT | |

ดัชนี: `idx_notifications_read (is_read)`, `idx_notifications_user_read (user_id, is_read)`

### message_threads / thread_participants / messages — ข้อความภายใน

```
message_threads (id, property_id, subject, created_by → users, created_at, updated_at)
thread_participants (thread_id → message_threads CASCADE, user_id → users CASCADE,
                     last_read_at, joined_at)   PK (thread_id, user_id)
messages (id, thread_id → message_threads CASCADE, sender_id → users RESTRICT, body, created_at)
```

จำนวนที่ยังไม่อ่านของผู้ใช้ = ข้อความในบทสนทนาที่ผู้ใช้เป็นสมาชิก ซึ่งส่งโดยคนอื่น
และ `created_at > last_read_at` (หรือ `last_read_at IS NULL`)

ดัชนี: `idx_messages_thread_created (thread_id, created_at)`

### settings — การตั้งค่าแบบ key/value ต่อหอพัก

| คอลัมน์ | ชนิด | หมายเหตุ |
| --- | --- | --- |
| property_id | FK → properties (CASCADE) | |
| setting_key | TEXT | UNIQUE ร่วมกับ property_id |
| setting_value | TEXT | |

คีย์ที่ระบบใช้: `default_monthly_rent`, `electricity_rate`, `water_rate`, `payment_due_day`,
`contract_warning_days`, `language`, `date_format`, `currency`

### migrations — บันทึกไฟล์ migration ที่รันแล้ว

| คอลัมน์ | ชนิด |
| --- | --- |
| migration | TEXT UNIQUE (ชื่อไฟล์) |
| executed_at | TEXT |

## การเพิ่ม migration

วางไฟล์ `.sql` ใหม่ใน `database/migrations/` ตั้งชื่อเรียงลำดับ เช่น `002_add_room_photos.sql`
แล้วรัน `php database/migrate.php` — ไฟล์ที่เคยรันแล้วจะถูกข้าม แต่ละไฟล์รันใน transaction เดียว
