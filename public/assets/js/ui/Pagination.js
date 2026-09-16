import {icon} from './Icons.js';

/**
 * แถบแบ่งหน้า
 */
export function createPagination({page, totalPages, total, perPage, onChange}) {
    const nav = document.createElement('nav');
    nav.className = 'pagination';
    nav.setAttribute('aria-label', 'การแบ่งหน้า');

    const info = document.createElement('span');
    info.className = 'pagination-info';

    const start = total === 0 ? 0 : (page - 1) * perPage + 1;
    const end = Math.min(total, page * perPage);
    info.textContent = `แสดง ${start}-${end} จาก ${total} รายการ`;

    const controls = document.createElement('div');
    controls.className = 'pagination-controls';

    const button = (label, target, disabled, current = false) => {
        const element = document.createElement('button');
        element.type = 'button';
        element.className = 'pagination-button';

        if (typeof label === 'string' && label.trimStart().startsWith('<svg')) {
            element.innerHTML = label;
        } else {
            element.textContent = label;
        }

        element.disabled = disabled;

        if (current) {
            element.classList.add('is-current');
            element.setAttribute('aria-current', 'page');
        }

        element.addEventListener('click', () => onChange(target));

        return element;
    };

    const previous = button(icon('chevronLeft'), page - 1, page <= 1);
    previous.setAttribute('aria-label', 'หน้าก่อนหน้า');
    controls.append(previous);

    for (const item of pageNumbers(page, totalPages)) {
        if (item === '…') {
            const gap = document.createElement('span');
            gap.className = 'pagination-gap';
            gap.textContent = '…';
            controls.append(gap);
            continue;
        }

        controls.append(button(String(item), item, false, item === page));
    }

    const next = button(icon('chevronRight'), page + 1, page >= totalPages);
    next.setAttribute('aria-label', 'หน้าถัดไป');
    controls.append(next);

    nav.append(info, controls);

    return nav;
}

function pageNumbers(page, totalPages) {
    if (totalPages <= 7) {
        return Array.from({length: totalPages}, (_, index) => index + 1);
    }

    const pages = new Set([1, totalPages, page - 1, page, page + 1]);
    const list = [...pages]
        .filter((item) => item >= 1 && item <= totalPages)
        .sort((a, b) => a - b);

    const result = [];

    for (let index = 0; index < list.length; index++) {
        if (index > 0 && list[index] - list[index - 1] > 1) {
            result.push('…');
        }

        result.push(list[index]);
    }

    return result;
}
