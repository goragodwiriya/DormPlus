/**
 * ดาวน์โหลดข้อมูลเป็นไฟล์ CSV (ใส่ BOM เพื่อให้ Excel อ่านภาษาไทยได้)
 *
 * downloadCsv('รายงาน.csv', ['ห้อง', 'ยอด'], rows.map((row) => [row.room, row.amount]))
 */
export function downloadCsv(filename, headers, rows) {
    const escapeCell = (value) => {
        const text = value === null || value === undefined ? '' : String(value);

        return /[",\n\r]/.test(text) ? `"${text.replace(/"/g, '""')}"` : text;
    };

    const lines = [headers, ...rows].map((row) => row.map(escapeCell).join(','));
    const blob = new Blob([`﻿${lines.join('\r\n')}`], {
        type: 'text/csv;charset=utf-8'
    });

    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = filename;
    document.body.append(link);
    link.click();
    link.remove();

    window.setTimeout(() => URL.revokeObjectURL(url), 1000);
}
