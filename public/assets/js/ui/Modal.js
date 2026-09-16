import {icon} from './Icons.js';

let openModals = [];

/**
 * หน้าต่าง modal ทั่วไป
 *
 * const modal = new Modal({title: 'เพิ่มห้องพัก', size: 'lg'});
 * modal.body.append(form);
 * modal.open();
 */
export class Modal {
    constructor({title = '', size = 'md', description = '', onClose = null} = {}) {
        this.onClose = onClose;
        this.previousFocus = null;
        this.boundKeydown = this.handleKeydown.bind(this);

        this.backdrop = document.createElement('div');
        this.backdrop.className = 'modal-backdrop';

        this.dialog = document.createElement('div');
        this.dialog.className = `modal modal-${size}`;
        this.dialog.setAttribute('role', 'dialog');
        this.dialog.setAttribute('aria-modal', 'true');

        const header = document.createElement('header');
        header.className = 'modal-header';

        const titles = document.createElement('div');
        this.titleElement = document.createElement('h2');
        this.titleElement.className = 'modal-title';
        this.titleElement.id = `modal-title-${Math.random().toString(36).slice(2, 8)}`;
        this.titleElement.textContent = title;
        this.dialog.setAttribute('aria-labelledby', this.titleElement.id);
        titles.append(this.titleElement);

        if (description) {
            const text = document.createElement('p');
            text.className = 'modal-description';
            text.textContent = description;
            titles.append(text);
        }

        const closeButton = document.createElement('button');
        closeButton.type = 'button';
        closeButton.className = 'icon-button modal-close';
        closeButton.setAttribute('aria-label', 'ปิดหน้าต่าง');
        closeButton.innerHTML = icon('close');
        closeButton.addEventListener('click', () => this.close());

        header.append(titles, closeButton);

        this.body = document.createElement('div');
        this.body.className = 'modal-body';

        this.footer = document.createElement('footer');
        this.footer.className = 'modal-footer';
        this.footer.hidden = true;

        this.dialog.append(header, this.body, this.footer);
        this.backdrop.append(this.dialog);

        this.backdrop.addEventListener('mousedown', (event) => {
            if (event.target === this.backdrop) {
                this.close();
            }
        });
    }

    setTitle(title) {
        this.titleElement.textContent = title;
    }

    setFooter(...nodes) {
        this.footer.replaceChildren(...nodes);
        this.footer.hidden = nodes.length === 0;
    }

    open() {
        this.previousFocus = document.activeElement;
        document.body.append(this.backdrop);
        document.body.classList.add('modal-open');
        document.addEventListener('keydown', this.boundKeydown);
        openModals.push(this);

        requestAnimationFrame(() => {
            this.backdrop.classList.add('is-open');

            const focusable = this.dialog.querySelector(
                'input:not([type=hidden]), select, textarea, button:not(.modal-close)'
            );

            (focusable || this.dialog).focus();
        });

        return this;
    }

    close() {
        if (!this.backdrop.isConnected) {
            return;
        }

        document.removeEventListener('keydown', this.boundKeydown);
        openModals = openModals.filter((modal) => modal !== this);
        this.backdrop.remove();

        if (openModals.length === 0) {
            document.body.classList.remove('modal-open');
        }

        if (this.previousFocus && typeof this.previousFocus.focus === 'function') {
            this.previousFocus.focus({preventScroll: true});
        }

        if (typeof this.onClose === 'function') {
            this.onClose();
        }
    }

    handleKeydown(event) {
        if (event.key !== 'Escape' || openModals[openModals.length - 1] !== this) {
            return;
        }

        event.preventDefault();
        this.close();
    }
}
