import {icon} from './Icons.js';

/**
 * สถานะว่าง / กำลังโหลด / ผิดพลาด ที่ใช้ซ้ำในทุกหน้า
 */
export function emptyState({
    iconName = 'inbox',
    title = 'ไม่พบข้อมูล',
    message = '',
    actionLabel = '',
    onAction = null
}) {
    const box = document.createElement('div');
    box.className = 'empty-state';

    const iconBox = document.createElement('span');
    iconBox.className = 'empty-state-icon';
    iconBox.innerHTML = icon(iconName);

    const heading = document.createElement('h3');
    heading.textContent = title;

    box.append(iconBox, heading);

    if (message) {
        const text = document.createElement('p');
        text.textContent = message;
        box.append(text);
    }

    if (actionLabel && onAction) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-primary';
        button.innerHTML = `${icon('plus')}<span></span>`;
        button.querySelector('span').textContent = actionLabel;
        button.addEventListener('click', onAction);
        box.append(button);
    }

    return box;
}

export function loadingState(message = 'กำลังโหลดข้อมูล...') {
    const box = document.createElement('div');
    box.className = 'loading-state';
    box.setAttribute('role', 'status');

    const spinner = document.createElement('span');
    spinner.className = 'spinner';
    spinner.setAttribute('aria-hidden', 'true');

    const text = document.createElement('p');
    text.textContent = message;

    box.append(spinner, text);

    return box;
}

export function errorState(message, onRetry = null) {
    const box = document.createElement('div');
    box.className = 'empty-state error-state';

    const iconBox = document.createElement('span');
    iconBox.className = 'empty-state-icon';
    iconBox.innerHTML = icon('alert');

    const heading = document.createElement('h3');
    heading.textContent = 'ไม่สามารถโหลดข้อมูลได้';

    const text = document.createElement('p');
    text.textContent = message || 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง';

    box.append(iconBox, heading, text);

    if (onRetry) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-secondary';
        button.textContent = 'ลองใหม่';
        button.addEventListener('click', onRetry);
        box.append(button);
    }

    return box;
}
