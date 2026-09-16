/**
 * คลาสพื้นฐานของทุกหน้า: จัดการ AbortController และ element เนื้อหา
 */
export class Module {
    constructor({shell, api, router, toast}) {
        this.shell = shell;
        this.api = api;
        this.router = router;
        this.toast = toast;
        this.controller = null;
    }

    /**
     * ยกเลิกคำขอที่ค้างอยู่เมื่อออกจากหน้า
     */
    stop() {
        if (this.controller) {
            this.controller.abort();
            this.controller = null;
        }
    }

    /**
     * signal ใหม่สำหรับคำขอของหน้านี้ (ยกเลิกชุดก่อนหน้าอัตโนมัติ)
     */
    signal() {
        this.stop();
        this.controller = new AbortController();

        return this.controller.signal;
    }

    content() {
        return this.shell.contentElement();
    }

    /**
     * true เมื่อ error มาจากการยกเลิกคำขอ (ไม่ต้องแสดงข้อความ)
     */
    isAbort(error) {
        return error?.name === 'AbortError';
    }

    /**
     * สร้าง query string จาก state ของตัวกรอง (ตัดค่าว่างและค่า default)
     */
    queryFrom(state, defaults = {}) {
        const query = {};

        for (const [key, value] of Object.entries(state)) {
            if (value === '' || value === null || value === undefined) {
                continue;
            }

            if (defaults[key] !== undefined && String(defaults[key]) === String(value)) {
                continue;
            }

            query[key] = value;
        }

        return query;
    }
}
