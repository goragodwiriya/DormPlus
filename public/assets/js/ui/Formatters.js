/**
 * ตัวช่วยจัดรูปแบบตัวเลข วันที่ และข้อความสำหรับแสดงผลภาษาไทย
 */
const thaiMonthsShort = [
    'ม.ค.', 'ก.พ.', 'มี.ค.', 'เม.ย.', 'พ.ค.', 'มิ.ย.',
    'ก.ค.', 'ส.ค.', 'ก.ย.', 'ต.ค.', 'พ.ย.', 'ธ.ค.'
];

const thaiMonthsLong = [
    'มกราคม', 'กุมภาพันธ์', 'มีนาคม', 'เมษายน', 'พฤษภาคม', 'มิถุนายน',
    'กรกฎาคม', 'สิงหาคม', 'กันยายน', 'ตุลาคม', 'พฤศจิกายน', 'ธันวาคม'
];

const currencyFormatter = new Intl.NumberFormat('th-TH', {
    style: 'currency',
    currency: 'THB',
    maximumFractionDigits: 0
});

const numberFormatter = new Intl.NumberFormat('th-TH', {
    maximumFractionDigits: 2
});

export function formatCurrency(value) {
    return currencyFormatter.format(Number(value) || 0);
}

export function formatNumber(value, digits = 0) {
    if (digits === 0) {
        return new Intl.NumberFormat('th-TH', {maximumFractionDigits: 0})
            .format(Number(value) || 0);
    }

    return numberFormatter.format(Number(value) || 0);
}

/**
 * แปลง "YYYY-MM-DD" หรือ "YYYY-MM-DD HH:MM:SS" เป็น Date (เวลาท้องถิ่น)
 */
export function parseDate(value) {
    if (!value) {
        return null;
    }

    if (value instanceof Date) {
        return value;
    }

    const text = String(value).replace(' ', 'T');
    const date = new Date(text.length === 10 ? `${text}T00:00:00` : text);

    return Number.isNaN(date.getTime()) ? null : date;
}

export function buddhistYear(date) {
    return date.getFullYear() + 543;
}

export function formatDate(value) {
    const date = parseDate(value);

    if (!date) {
        return '-';
    }

    return `${date.getDate()} ${thaiMonthsShort[date.getMonth()]} ${buddhistYear(date)}`;
}

export function formatDateLong(value) {
    const date = parseDate(value);

    if (!date) {
        return '-';
    }

    return `${date.getDate()} ${thaiMonthsLong[date.getMonth()]} ${buddhistYear(date)}`;
}

export function formatTime(value) {
    const date = parseDate(value);

    if (!date) {
        return '-';
    }

    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');

    return `${hours}:${minutes} น.`;
}

export function formatDateTime(value) {
    const date = parseDate(value);

    if (!date) {
        return '-';
    }

    return `${formatDate(date)} ${formatTime(date)}`;
}

/**
 * "2026-09" หรือ (เดือน, ปี) → "ก.ย. 2569"
 */
export function formatMonth(value, year = null) {
    let month;
    let fullYear;

    if (year !== null) {
        month = Number(value);
        fullYear = Number(year);
    } else {
        [fullYear, month] = String(value).split('-').map(Number);
    }

    if (!month || !fullYear) {
        return '-';
    }

    return `${thaiMonthsShort[month - 1]} ${fullYear + 543}`;
}

export function monthLabel(month, long = false) {
    const list = long ? thaiMonthsLong : thaiMonthsShort;

    return list[Number(month) - 1] || '-';
}

export function formatRelative(value) {
    const date = parseDate(value);

    if (!date) {
        return '-';
    }

    const seconds = Math.max(0, Math.floor((Date.now() - date.getTime()) / 1000));

    if (seconds < 60) {
        return 'เมื่อสักครู่';
    }

    if (seconds < 3600) {
        return `${Math.floor(seconds / 60)} นาทีที่แล้ว`;
    }

    if (seconds < 86400) {
        return `${Math.floor(seconds / 3600)} ชม.ที่แล้ว`;
    }

    if (seconds < 86400 * 30) {
        return `${Math.floor(seconds / 86400)} วันที่แล้ว`;
    }

    return formatDate(date);
}

/**
 * วันนี้ในรูปแบบ YYYY-MM-DD (เวลาท้องถิ่น)
 */
export function today() {
    const date = new Date();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${date.getFullYear()}-${month}-${day}`;
}

export function currentMonth() {
    return today().slice(0, 7);
}

/**
 * ค่าสำหรับ input[type=datetime-local] จาก "YYYY-MM-DD HH:MM:SS"
 */
export function toDateTimeLocal(value) {
    const date = parseDate(value) || new Date();
    const pad = (number) => String(number).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
        + `T${pad(date.getHours())}:${pad(date.getMinutes())}`;
}

export function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

/**
 * ชื่อย่อสำหรับ avatar (อักษรแรกของชื่อ)
 */
export function initials(name) {
    return String(name || '').trim().charAt(0) || '?';
}
