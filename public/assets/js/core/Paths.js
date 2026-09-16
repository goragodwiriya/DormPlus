/**
 * โฟลเดอร์ที่แอปถูกเปิด เช่น "/" หรือ "/DormPlus/public/"
 * ทำให้แอปทำงานได้ทั้งที่ root ของโดเมนและใน subdirectory
 */
export const basePath = window.location.pathname.replace(/[^/]*$/, '');

export function asset(path) {
    const value = String(path || '');

    if (/^(?:[a-z]+:)?\/\//i.test(value) || value.startsWith('data:')) {
        return value;
    }

    return basePath + value.replace(/^\/+/, '');
}
