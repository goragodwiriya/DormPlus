import {icon} from './Icons.js';
import {Modal} from './Modal.js';

/**
 * กล่องยืนยันการทำรายการ คืนค่า Promise<boolean>
 *
 * if (await confirmDialog({title: 'ลบห้อง 101', message: '...', danger: true})) { ... }
 */
export function confirmDialog({
    title = 'ยืนยันการทำรายการ',
    message = '',
    confirmLabel = 'ยืนยัน',
    cancelLabel = 'ยกเลิก',
    danger = false
} = {}) {
    return new Promise((resolve) => {
        let resolved = false;

        const modal = new Modal({
            title,
            size: 'sm',
            onClose: () => {
                if (!resolved) {
                    resolved = true;
                    resolve(false);
                }
            }
        });

        modal.dialog.classList.add('confirm-dialog');

        const iconBox = document.createElement('span');
        iconBox.className = `confirm-icon ${danger ? 'confirm-danger' : 'confirm-info'}`;
        iconBox.innerHTML = icon(danger ? 'trash' : 'alert');

        const text = document.createElement('p');
        text.className = 'confirm-message';
        text.textContent = message;

        modal.body.append(iconBox, text);

        const cancelButton = document.createElement('button');
        cancelButton.type = 'button';
        cancelButton.className = 'btn btn-secondary';
        cancelButton.textContent = cancelLabel;
        cancelButton.addEventListener('click', () => modal.close());

        const confirmButton = document.createElement('button');
        confirmButton.type = 'button';
        confirmButton.className = `btn ${danger ? 'btn-danger' : 'btn-primary'}`;
        confirmButton.textContent = confirmLabel;
        confirmButton.addEventListener('click', () => {
            resolved = true;
            modal.close();
            resolve(true);
        });

        modal.setFooter(cancelButton, confirmButton);
        modal.open();
    });
}
