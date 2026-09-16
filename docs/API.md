# DormPlus REST API

Base URL: `/api` (เมื่อติดตั้งใน subdirectory จะเป็น `/<folder>/public/api`)

ทุกคำขอและคำตอบเป็น JSON (`Content-Type: application/json; charset=utf-8`)

## การยืนยันตัวตน

ใช้ PHP session ผ่าน cookie `dormplus_session` (HttpOnly, SameSite=Lax)

1. `POST /auth/login` → ได้ `csrf_token`
2. ส่ง cookie กลับมาทุกคำขอ (`credentials: 'same-origin'`)
3. คำขอ `POST` / `PUT` / `DELETE` ต้องมี header `X-CSRF-Token: <csrf_token>`

Endpoint ที่ไม่ต้องล็อกอิน: `POST /auth/login`, `GET /health`

## รูปแบบคำตอบ

สำเร็จ

```json
{ "success": true, "data": { } }
```

รายการแบบแบ่งหน้า

```json
{
    "success": true,
    "data": [ ],
    "pagination": { "page": 1, "per_page": 20, "total": 48, "total_pages": 3 }
}
```

ข้อผิดพลาด

```json
{
    "success": false,
    "error": {
        "code": "VALIDATION_ERROR",
        "message": "กรุณาตรวจสอบข้อมูลที่กรอก",
        "fields": { "room_number": "กรุณากรอกเลขห้อง" }
    }
}
```

| HTTP | code | ความหมาย |
| --- | --- | --- |
| 200 | – | สำเร็จ |
| 201 | – | สร้างข้อมูลแล้ว |
| 401 | `UNAUTHORIZED` / `INVALID_CREDENTIALS` | ยังไม่ล็อกอิน / รหัสผ่านผิด |
| 404 | `NOT_FOUND` | ไม่พบข้อมูลหรือ endpoint |
| 409 | `CONFLICT` | ลบไม่ได้เพราะมีข้อมูลที่เกี่ยวข้อง |
| 419 | `CSRF_TOKEN_MISMATCH` | ไม่มีหรือ token ไม่ตรง |
| 422 | `VALIDATION_ERROR` | ข้อมูลไม่ผ่านการตรวจสอบ (`fields` บอกรายฟิลด์) |
| 500 | `INTERNAL_SERVER_ERROR` | ข้อผิดพลาดของระบบ (รายละเอียดอยู่ใน `storage/logs/php-error.log`) |

## การแบ่งหน้าและตัวกรอง

Endpoint รายการรับ query string

| พารามิเตอร์ | ค่าเริ่มต้น | หมายเหตุ |
| --- | --- | --- |
| `page` | 1 | หน้าที่ต้องการ |
| `per_page` (หรือ `limit`) | 20 | สูงสุด 100 |
| `q` | – | ค้นหาข้อความ (ชื่อ เลขห้อง เบอร์โทร ฯลฯ) |
| `sort` | ต่างกันตาม endpoint | ดูในแต่ละหัวข้อ |

---

## Auth

### `POST /auth/login`

```json
{ "username": "admin", "password": "DormPlus@2026" }
```

```json
{
    "success": true,
    "data": {
        "user": { "id": 1, "property_id": 1, "username": "admin", "first_name": "ถาวร",
                  "last_name": "ศรีสุวรรณ", "role": "owner", "avatar": null,
                  "property_name": "หอพักสุขสันต์" },
        "csrf_token": "0f8c…"
    }
}
```

### `GET /auth/me` — ผู้ใช้ปัจจุบัน (โครงสร้างเดียวกับ login)
### `POST /auth/logout`
### `PUT /auth/password` — `{ current_password, new_password, confirm_password }` (อย่างน้อย 8 ตัวอักษร)
### `PUT /auth/profile` — `{ first_name, last_name }`

---

## Dashboard

### `GET /dashboard` — ข้อมูลทั้งหมดของหน้าหลักในคำขอเดียว

```json
{
    "statistics": {
        "rooms": { "total": 48, "available": 6, "occupied": 42, "maintenance": 0, "reserved": 0 },
        "tenants": { "total": 42, "male": 18, "female": 24, "other": 0 },
        "monthly_income": { "total": 126200, "change_percentage": 12 },
        "maintenance": { "total": 3, "pending": 1, "in_progress": 2 }
    },
    "financial_summary": {
        "period": "2026-09",
        "income": { "total": 126200, "change_percentage": 12 },
        "expenses": { "total": 52300, "change_percentage": 5 },
        "net_profit": { "total": 73900, "change_percentage": 17.5 }
    },
    "financial_chart": [ { "period": "2026-06", "income": 94500, "expenses": 44500, "is_current": false }, "…" ],
    "room_status": { "total": 48, "occupied": 42, "available": 6, "maintenance": 0, "reserved": 0,
                     "occupied_percentage": 87.5, "available_percentage": 12.5 },
    "notifications": { "items": [ "…" ], "unread_count": 4 },
    "latest_rooms": [ "…" ],
    "latest_payments": [ "…" ]
}
```

แยกส่วนได้ที่ `GET /dashboard/statistics`, `/dashboard/financial-summary`, `/dashboard/financial-chart`,
`/dashboard/room-status`, `/dashboard/notifications?limit=6`

การคำนวณ

- รายได้เดือนนี้ = `SUM(amount)` ของ `payments` ที่ `status = 'paid'` และ `payment_date` อยู่ในเดือนปัจจุบัน
- รายจ่าย = `SUM(amount)` ของ `expenses` ในเดือน
- `change_percentage` เทียบกับเดือนก่อนหน้า (`null` เมื่อเดือนก่อนเป็น 0)
- แจ้งซ่อมค้าง = `status IN ('pending', 'in_progress')`

---

## ห้องพัก `/rooms`

| Method | Path | หน้าที่ |
| --- | --- | --- |
| GET | `/rooms` | รายการ (`q`, `status`, `floor`, `sort` = `room_number`/`latest`/`rent_asc`/`rent_desc`/`status`) |
| GET | `/rooms/options` | `{ floors, types, statuses, rooms[] }` สำหรับ select/ตัวกรอง |
| GET | `/rooms/{id}` | `{ room, contracts[], payments[], maintenance[] }` |
| POST | `/rooms` | สร้าง |
| PUT | `/rooms/{id}` | แก้ไข |
| PUT | `/rooms/{id}/status` | `{ status }` |
| DELETE | `/rooms/{id}` | ลบ (409 หากมีสัญญา/การชำระ/งานซ่อม) |

ฟิลด์: `room_number`* (ไม่ซ้ำในหอพัก), `floor`* (จำนวนเต็ม ≥ 0), `type` (`standard`/`deluxe`/`suite`/`studio`),
`area`, `monthly_rent`* (≥ 0), `status` (`available`/`occupied`/`maintenance`/`reserved`), `description`

กฎ: ห้องที่มีสัญญา active เปลี่ยนเป็น `available`/`reserved` ไม่ได้ และห้องที่ไม่มีสัญญาตั้งเป็น `occupied` ไม่ได้
(สถานะ `occupied` เกิดจากการสร้างสัญญาเท่านั้น)

รายการแต่ละแถวมี `tenant_name`, `tenant_id`, `contract_id`, `contract_end_date` ของสัญญาปัจจุบัน

---

## ผู้เช่า `/tenants`

| Method | Path | หน้าที่ |
| --- | --- | --- |
| GET | `/tenants` | รายการ (`q`, `status`, `gender`, `sort` = `name`/`room`/`latest`) |
| GET | `/tenants/options` | รายชื่อย่อพร้อมห้องและค่าเช่าตามสัญญา |
| GET | `/tenants/{id}` | `{ tenant, contracts[], payments[] }` |
| POST | `/tenants` | สร้าง |
| PUT | `/tenants/{id}` | แก้ไข |
| DELETE | `/tenants/{id}` | ลบ (409 หากมีสัญญา/การชำระ) |

ฟิลด์: `first_name`*, `last_name`*, `gender` (`male`/`female`/`other`), `phone`*, `email`, `national_id` (13 หลัก),
`address`, `emergency_contact`, `emergency_phone`, `status` (`active`/`inactive`/`blacklisted`)

รายการไม่ส่ง `national_id`; หน้ารายละเอียดส่ง `national_id_masked` เพิ่ม (`xxxxxxxxx1234`)

---

## สัญญาเช่า `/contracts`

| Method | Path | หน้าที่ |
| --- | --- | --- |
| GET | `/contracts` | `{ items[], status_counts }` (`q`, `status`, `expiring_within` = วัน, `room_id`, `tenant_id`, `from`, `to`, `sort` = `latest`/`end_date`/`room`) |
| GET | `/contracts/expiring?days=30` | สัญญา active ที่หมดอายุภายใน N วัน |
| GET | `/contracts/{id}` | รายละเอียด (มี `days_remaining`) |
| POST | `/contracts` | สร้าง — **transaction**: เพิ่มสัญญา + ห้อง → `occupied` + ผู้เช่า → `active` |
| PUT | `/contracts/{id}` | แก้ไขเงื่อนไข (ผู้เช่า/ห้องเปลี่ยนไม่ได้) |
| PUT | `/contracts/{id}/end` | `{ status: terminated|expired, end_date? }` → ห้องกลับเป็น `available` |
| POST | `/contracts/{id}/renew` | `{ end_date*, start_date?, monthly_rent?, deposit?, … }` → สัญญาเดิม `expired` + สร้างสัญญาใหม่ (201) |
| DELETE | `/contracts/{id}` | ลบ (การชำระที่อ้างถึงจะถูกตั้ง `contract_id = NULL`) |

ฟิลด์: `tenant_id`*, `room_id`*, `start_date`*, `end_date`* (≥ start), `monthly_rent`*, `deposit`,
`electricity_rate`, `water_rate`, `status` (`active`/`pending`/`expired`/`terminated`), `notes`

ตรวจสอบ: ห้องต้องไม่มีสัญญา active อยู่แล้ว, ผู้เช่าต้องไม่มีสัญญา active อยู่แล้ว, ผู้เช่าต้องไม่อยู่ในบัญชีดำ,
ห้องต้องไม่อยู่ในสถานะซ่อมบำรุง

---

## การเงิน `/payments`, `/expenses`

| Method | Path | หน้าที่ |
| --- | --- | --- |
| GET | `/payments` | รายการ (`q`, `month` = `YYYY-MM` ตามงวด, `room_id`, `tenant_id`, `status`, `type`, `sort` = `latest`/`amount`/`room`) |
| GET | `/payments/summary?month=YYYY-MM` | `{ paid_total, pending_total, paid_count, pending_count, cancelled_count }` |
| GET | `/payments/{id}` | รายละเอียด |
| POST | `/payments` | บันทึก (201) |
| PUT | `/payments/{id}` | แก้ไข |
| PUT | `/payments/{id}/cancel` | เปลี่ยนสถานะเป็น `cancelled` |
| DELETE | `/payments/{id}` | ลบถาวร |

ฟิลด์: `tenant_id`*, `room_id` (ถ้าไม่ส่งจะใช้ห้องจากสัญญา active ของผู้เช่า), `contract_id` (เช่นเดียวกัน),
`amount`*, `payment_type` (`rent`/`electricity`/`water`/`other`), `payment_date` (`YYYY-MM-DD HH:MM[:SS]`;
ถ้า `status = paid` และไม่ส่งจะใช้เวลาปัจจุบัน), `period_month` (1-12), `period_year`, `reference`
(สร้างอัตโนมัติ `PAY-YYYYMM-XXXXXX` หากเว้นว่าง), `notes`, `status` (`paid`/`pending`/`cancelled`)

เมื่อบันทึกรายการที่ `paid` ระบบสร้างการแจ้งเตือน "ผู้เช่าชำระค่าเช่า" ให้อัตโนมัติ

รายจ่าย: `GET /expenses` (`q`, `month`, `category`; คืน `{ items[], month_total }`), `GET/PUT/DELETE /expenses/{id}`, `POST /expenses`
ฟิลด์: `title`*, `category` (`utilities`/`salary`/`maintenance`/`supplies`/`tax`/`other`), `amount`*, `expense_date`, `notes`

---

## แจ้งซ่อม `/maintenance`

| Method | Path | หน้าที่ |
| --- | --- | --- |
| GET | `/maintenance` | `{ items[], status_counts }` (`q`, `status`, `priority`, `room_id`, `open=1`, `from`, `to`, `sort` = `latest`/`priority`/`oldest`) |
| GET | `/maintenance/{id}` | รายละเอียด |
| POST | `/maintenance` | สร้าง (สถานะ `pending`, สร้างการแจ้งเตือน "มีแจ้งซ่อมใหม่") |
| PUT | `/maintenance/{id}` | แก้ไขทั้งรายการ |
| PUT | `/maintenance/{id}/status` | อัปเดตบางส่วน: `status`, `assigned_to`, `cost`, `notes`, `priority` |
| DELETE | `/maintenance/{id}` | ลบ |

ฟิลด์: `room_id`*, `tenant_id`, `title`*, `description`*, `priority` (`low`/`normal`/`high`/`urgent`),
`status` (`pending`/`in_progress`/`completed`/`cancelled`), `assigned_to`, `cost`, `notes`

ระบบตั้ง `started_at` เมื่อเปลี่ยนเป็น `in_progress` และ `completed_at` เมื่อ `completed`

---

## รายงาน `/reports` (GET ทั้งหมด)

| Path | พารามิเตอร์ | คำตอบ |
| --- | --- | --- |
| `/reports/income` | `year` | `{ year, years[], months[12]{rent, electricity, water, other, total, payment_count}, totals }` |
| `/reports/expenses` | `year`, `month` | `{ months[12], categories[], total }` |
| `/reports/profit` | `year` | `{ months[12]{income, expenses, net_profit}, totals }` |
| `/reports/occupancy` | – | `{ floors[]{total, occupied, available, occupancy_rate, occupied_rent, potential_rent}, summary }` |
| `/reports/payments` | `from`, `to`, `status` | `{ items[], summary{paid, pending, cancelled, count} }` |
| `/reports/tenants` | `status` | `{ items[]{…, total_paid, total_pending}, summary }` |
| `/reports/maintenance` | `from`, `to` | `{ items[], summary{count, pending, in_progress, completed, cancelled, total_cost} }` |
| `/reports/contracts` | `days` | `{ items[]{…, days_remaining}, summary{count, overdue} }` |

`from`/`to` ค่าเริ่มต้นคือเดือนปัจจุบัน; รูปแบบวันที่ผิดหรือ `from > to` → 422

---

## การแจ้งเตือน `/notifications`

| Method | Path | หน้าที่ |
| --- | --- | --- |
| GET | `/notifications` | `{ items[], unread_count }` + pagination (`unread=1`, `type`) |
| PUT | `/notifications/{id}/read` | `{ id, unread_count }` |
| PUT | `/notifications/read-all` | `{ updated, unread_count: 0 }` |
| DELETE | `/notifications/{id}` | ลบ |

ประเภท: `maintenance`, `payment`, `contract`, `room` — แต่ละรายการมี `related_type`/`related_id` ชี้ไปยังข้อมูลต้นทาง

---

## ข้อความ `/messages`

| Method | Path | หน้าที่ |
| --- | --- | --- |
| GET | `/messages/threads?q=` | `{ items[]{subject, participants, last_message, unread_count}, unread_count }` |
| GET | `/messages/threads/{id}` | `{ thread, participants[], messages[]{…, is_mine}, unread_count }` (ทำเครื่องหมายว่าอ่านแล้ว) |
| POST | `/messages/threads` | `{ subject*, participant_ids*[], body* }` → 201 |
| POST | `/messages/threads/{id}` | `{ body* }` ตอบกลับ → 201 |
| PUT | `/messages/threads/{id}/read` | ทำเครื่องหมายว่าอ่านแล้ว |
| GET | `/messages/unread-count` | `{ unread_count }` |
| GET | `/messages/recipients` | ผู้ใช้ในหอพักเดียวกัน (ไม่รวมตัวเอง) |

---

## ตั้งค่า `/settings`

| Method | Path | หน้าที่ |
| --- | --- | --- |
| GET | `/settings` | `{ property, settings }` |
| PUT | `/settings/property` | `{ name*, address, phone, email, description }` |
| PUT | `/settings/rental` | `{ default_monthly_rent*, electricity_rate*, water_rate*, payment_due_day* (1-31), contract_warning_days }` |
| PUT | `/settings/system` | `{ language (th/en), date_format, currency (THB/USD) }` |

---

## ตัวอย่างการเรียกด้วย curl

```bash
# login และเก็บ cookie
curl -c cookie.txt -X POST http://localhost:8000/api/auth/login \
  -H 'Content-Type: application/json' \
  -d '{"username":"admin","password":"DormPlus@2026"}'

# ใช้ csrf_token จากคำตอบด้านบน
curl -b cookie.txt -X POST http://localhost:8000/api/rooms \
  -H 'Content-Type: application/json' -H 'X-CSRF-Token: <token>' \
  -d '{"room_number":"501","floor":5,"monthly_rent":4500}'

curl -b cookie.txt 'http://localhost:8000/api/rooms?status=available&per_page=10'
```
