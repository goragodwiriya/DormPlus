PRAGMA foreign_keys = ON;

CREATE TABLE IF NOT EXISTS migrations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    migration TEXT NOT NULL UNIQUE,
    executed_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
);

CREATE TABLE IF NOT EXISTS properties (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    address TEXT,
    phone TEXT,
    email TEXT,
    description TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime'))
);

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    property_id INTEGER,
    username TEXT NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    first_name TEXT NOT NULL,
    last_name TEXT NOT NULL,
    role TEXT NOT NULL DEFAULT 'administrator'
        CHECK (role IN ('administrator', 'owner', 'staff')),
    avatar TEXT,
    is_active INTEGER NOT NULL DEFAULT 1 CHECK (is_active IN (0, 1)),
    last_login_at TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime')),
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS rooms (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    property_id INTEGER NOT NULL,
    room_number TEXT NOT NULL,
    floor INTEGER NOT NULL,
    type TEXT NOT NULL DEFAULT 'standard',
    area REAL,
    monthly_rent REAL NOT NULL CHECK (monthly_rent >= 0),
    status TEXT NOT NULL DEFAULT 'available'
        CHECK (status IN ('available', 'occupied', 'maintenance', 'reserved')),
    description TEXT,
    image TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime')),
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
    UNIQUE (property_id, room_number)
);

CREATE TABLE IF NOT EXISTS tenants (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    property_id INTEGER NOT NULL,
    first_name TEXT NOT NULL,
    last_name TEXT NOT NULL,
    gender TEXT CHECK (gender IN ('male', 'female', 'other')),
    phone TEXT NOT NULL,
    email TEXT,
    national_id TEXT,
    address TEXT,
    emergency_contact TEXT,
    emergency_phone TEXT,
    status TEXT NOT NULL DEFAULT 'active'
        CHECK (status IN ('active', 'inactive', 'blacklisted')),
    created_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime')),
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS contracts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    property_id INTEGER NOT NULL,
    tenant_id INTEGER NOT NULL,
    room_id INTEGER NOT NULL,
    start_date TEXT NOT NULL,
    end_date TEXT NOT NULL,
    monthly_rent REAL NOT NULL CHECK (monthly_rent >= 0),
    deposit REAL NOT NULL DEFAULT 0 CHECK (deposit >= 0),
    electricity_rate REAL NOT NULL DEFAULT 0 CHECK (electricity_rate >= 0),
    water_rate REAL NOT NULL DEFAULT 0 CHECK (water_rate >= 0),
    status TEXT NOT NULL DEFAULT 'pending'
        CHECK (status IN ('active', 'expired', 'terminated', 'pending')),
    notes TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime')),
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE RESTRICT,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE RESTRICT,
    CHECK (date(end_date) >= date(start_date))
);

CREATE TABLE IF NOT EXISTS payments (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    property_id INTEGER NOT NULL,
    tenant_id INTEGER NOT NULL,
    room_id INTEGER NOT NULL,
    contract_id INTEGER,
    amount REAL NOT NULL CHECK (amount >= 0),
    payment_type TEXT NOT NULL
        CHECK (payment_type IN ('rent', 'electricity', 'water', 'other')),
    payment_date TEXT,
    period_month INTEGER NOT NULL CHECK (period_month BETWEEN 1 AND 12),
    period_year INTEGER NOT NULL CHECK (period_year BETWEEN 2000 AND 2200),
    reference TEXT,
    notes TEXT,
    status TEXT NOT NULL DEFAULT 'pending'
        CHECK (status IN ('paid', 'pending', 'cancelled')),
    created_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime')),
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE RESTRICT,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE RESTRICT,
    FOREIGN KEY (contract_id) REFERENCES contracts(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS expenses (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    property_id INTEGER NOT NULL,
    title TEXT NOT NULL,
    category TEXT NOT NULL DEFAULT 'other',
    amount REAL NOT NULL CHECK (amount >= 0),
    expense_date TEXT NOT NULL,
    notes TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime')),
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS maintenance_requests (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    property_id INTEGER NOT NULL,
    room_id INTEGER NOT NULL,
    tenant_id INTEGER,
    title TEXT NOT NULL,
    description TEXT NOT NULL,
    priority TEXT NOT NULL DEFAULT 'normal'
        CHECK (priority IN ('low', 'normal', 'high', 'urgent')),
    status TEXT NOT NULL DEFAULT 'pending'
        CHECK (status IN ('pending', 'in_progress', 'completed', 'cancelled')),
    assigned_to TEXT,
    reported_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime')),
    started_at TEXT,
    completed_at TEXT,
    cost REAL NOT NULL DEFAULT 0 CHECK (cost >= 0),
    notes TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime')),
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE RESTRICT,
    FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE SET NULL
);

CREATE TABLE IF NOT EXISTS notifications (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    property_id INTEGER NOT NULL,
    user_id INTEGER,
    type TEXT NOT NULL,
    title TEXT NOT NULL,
    description TEXT,
    related_type TEXT,
    related_id INTEGER,
    is_read INTEGER NOT NULL DEFAULT 0 CHECK (is_read IN (0, 1)),
    created_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime')),
    read_at TEXT,
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS message_threads (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    property_id INTEGER NOT NULL,
    subject TEXT NOT NULL,
    created_by INTEGER NOT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime')),
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS thread_participants (
    thread_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    last_read_at TEXT,
    joined_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime')),
    PRIMARY KEY (thread_id, user_id),
    FOREIGN KEY (thread_id) REFERENCES message_threads(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS messages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    thread_id INTEGER NOT NULL,
    sender_id INTEGER NOT NULL,
    body TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime')),
    FOREIGN KEY (thread_id) REFERENCES message_threads(id) ON DELETE CASCADE,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE RESTRICT
);

CREATE TABLE IF NOT EXISTS settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    property_id INTEGER NOT NULL,
    setting_key TEXT NOT NULL,
    setting_value TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now', 'localtime')),
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
    UNIQUE (property_id, setting_key)
);

CREATE INDEX IF NOT EXISTS idx_rooms_number
    ON rooms(room_number);

CREATE INDEX IF NOT EXISTS idx_rooms_property_status
    ON rooms(property_id, status);

CREATE INDEX IF NOT EXISTS idx_tenants_phone
    ON tenants(phone);

CREATE INDEX IF NOT EXISTS idx_tenants_property_status
    ON tenants(property_id, status);

CREATE INDEX IF NOT EXISTS idx_contracts_end_date
    ON contracts(end_date);

CREATE INDEX IF NOT EXISTS idx_contracts_property_status
    ON contracts(property_id, status);

CREATE INDEX IF NOT EXISTS idx_payments_date
    ON payments(payment_date);

CREATE INDEX IF NOT EXISTS idx_payments_period
    ON payments(period_year, period_month);

CREATE INDEX IF NOT EXISTS idx_payments_property_status
    ON payments(property_id, status);

CREATE INDEX IF NOT EXISTS idx_maintenance_status
    ON maintenance_requests(status);

CREATE INDEX IF NOT EXISTS idx_maintenance_property_status
    ON maintenance_requests(property_id, status);

CREATE INDEX IF NOT EXISTS idx_notifications_read
    ON notifications(is_read);

CREATE INDEX IF NOT EXISTS idx_notifications_user_read
    ON notifications(user_id, is_read);

CREATE INDEX IF NOT EXISTS idx_expenses_date
    ON expenses(expense_date);

CREATE INDEX IF NOT EXISTS idx_messages_thread_created
    ON messages(thread_id, created_at);