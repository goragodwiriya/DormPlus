/**
 * ป้ายภาษาไทยและสีของสถานะต่าง ๆ ที่ใช้ทั้งแอป
 */
export const roomStatus = {
    available: {label: 'ว่าง', variant: 'blue'},
    occupied: {label: 'มีผู้เช่า', variant: 'green'},
    maintenance: {label: 'ซ่อมบำรุง', variant: 'orange'},
    reserved: {label: 'จองแล้ว', variant: 'purple'}
};

export const roomType = {
    standard: 'มาตรฐาน',
    deluxe: 'ดีลักซ์',
    suite: 'สวีท',
    studio: 'สตูดิโอ'
};

export const tenantStatus = {
    active: {label: 'พักอยู่', variant: 'green'},
    inactive: {label: 'ย้ายออกแล้ว', variant: 'gray'},
    blacklisted: {label: 'บัญชีดำ', variant: 'red'}
};

export const gender = {
    male: 'ชาย',
    female: 'หญิง',
    other: 'อื่น ๆ'
};

export const contractStatus = {
    active: {label: 'ใช้งาน', variant: 'green'},
    pending: {label: 'รอเริ่ม', variant: 'orange'},
    expired: {label: 'หมดอายุ', variant: 'gray'},
    terminated: {label: 'ยกเลิก', variant: 'red'}
};

export const paymentStatus = {
    paid: {label: 'ชำระแล้ว', variant: 'green'},
    pending: {label: 'รอชำระ', variant: 'orange'},
    cancelled: {label: 'ยกเลิก', variant: 'red'}
};

export const paymentType = {
    rent: 'ค่าเช่า',
    electricity: 'ค่าไฟ',
    water: 'ค่าน้ำ',
    other: 'อื่น ๆ'
};

export const expenseCategory = {
    utilities: 'สาธารณูปโภค',
    salary: 'เงินเดือน/ค่าจ้าง',
    maintenance: 'ซ่อมบำรุง',
    supplies: 'วัสดุ/อุปกรณ์',
    tax: 'ภาษี/ค่าธรรมเนียม',
    other: 'อื่น ๆ'
};

export const maintenanceStatus = {
    pending: {label: 'รอรับงาน', variant: 'orange'},
    in_progress: {label: 'กำลังดำเนินการ', variant: 'blue'},
    completed: {label: 'เสร็จสิ้น', variant: 'green'},
    cancelled: {label: 'ยกเลิก', variant: 'gray'}
};

export const maintenancePriority = {
    low: {label: 'ต่ำ', variant: 'gray'},
    normal: {label: 'ปกติ', variant: 'blue'},
    high: {label: 'สูง', variant: 'orange'},
    urgent: {label: 'เร่งด่วน', variant: 'red'}
};

export const userRole = {
    owner: 'เจ้าของหอพัก',
    administrator: 'ผู้ดูแลระบบ',
    staff: 'พนักงาน'
};

/**
 * แปลง map เป็นตัวเลือกสำหรับ select
 */
export function toOptions(map, includeEmpty = null) {
    const options = Object.entries(map).map(([value, item]) => ({
        value,
        label: typeof item === 'string' ? item : item.label
    }));

    if (includeEmpty !== null) {
        options.unshift({value: '', label: includeEmpty});
    }

    return options;
}
