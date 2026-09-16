import {icon} from './Icons.js';

export class Toast {
    constructor(container) {
        this.container = container;
    }

    success(message, title = 'สำเร็จ') {
        this.show({type: 'success', title, message});
    }

    error(message, title = 'เกิดข้อผิดพลาด') {
        this.show({type: 'error', title, message});
    }

    warning(message, title = 'โปรดตรวจสอบ') {
        this.show({type: 'warning', title, message});
    }

    show({type = 'success', title = '', message = '', duration = 4000}) {
        const element = document.createElement('div');
        const iconName = type === 'success' ? 'check' : 'alert';

        element.className = `toast toast-${type}`;
        element.setAttribute('role', type === 'error' ? 'alert' : 'status');

        const iconBox = document.createElement('span');
        iconBox.className = 'toast-icon';
        iconBox.innerHTML = icon(iconName);

        const body = document.createElement('div');
        body.className = 'toast-body';

        const heading = document.createElement('strong');
        heading.textContent = title;

        const description = document.createElement('p');
        description.textContent = message;

        body.append(heading, description);

        const closeButton = document.createElement('button');
        closeButton.type = 'button';
        closeButton.className = 'toast-close';
        closeButton.setAttribute('aria-label', 'ปิดการแจ้งเตือน');
        closeButton.innerHTML = icon('close');

        closeButton.addEventListener('click', () => element.remove());

        element.append(iconBox, body, closeButton);
        this.container.append(element);

        window.setTimeout(() => {
            element.remove();
        }, duration);
    }
}