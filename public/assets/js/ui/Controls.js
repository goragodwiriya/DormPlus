import {icon} from './Icons.js';
import {appendContent} from './Table.js';

/**
 * ส่วนหัวของหน้า: ชื่อ คำอธิบาย และปุ่มการทำงาน
 */
export function pageHeader({title, subtitle = '', actions = [], backTo = null}) {
    const header = document.createElement('header');
    header.className = 'page-header';

    const titles = document.createElement('div');
    titles.className = 'page-header-titles';

    if (backTo) {
        const back = document.createElement('a');
        back.className = 'back-link';
        back.href = `#${backTo.path}`;
        back.innerHTML = `${icon('arrowLeft')}<span></span>`;
        back.querySelector('span').textContent = backTo.label;
        titles.append(back);
    }

    const heading = document.createElement('h1');
    heading.className = 'page-title';
    appendContent(heading, title);
    titles.append(heading);

    if (subtitle) {
        const text = document.createElement('p');
        text.className = 'page-subtitle';
        appendContent(text, subtitle);
        titles.append(text);
    }

    header.append(titles);

    if (actions.length) {
        const box = document.createElement('div');
        box.className = 'page-header-actions';
        box.append(...actions);
        header.append(box);
    }

    return header;
}

/**
 * ปุ่มมาตรฐาน: button({label, iconName, variant, onClick})
 */
export function button({
    label,
    iconName = null,
    variant = 'primary',
    onClick = null,
    type = 'button',
    size = ''
}) {
    const element = document.createElement('button');
    element.type = type;
    element.className = `btn btn-${variant} ${size ? `btn-${size}` : ''}`.trim();

    if (iconName) {
        element.innerHTML = icon(iconName);
    }

    if (label) {
        const text = document.createElement('span');
        text.textContent = label;
        element.append(text);
    } else if (iconName) {
        element.classList.add('btn-icon-only');
    }

    if (onClick) {
        element.addEventListener('click', onClick);
    }

    return element;
}

/**
 * ปุ่มไอคอนเล็กสำหรับ action ในตาราง
 */
export function iconButton({iconName, label, onClick, variant = ''}) {
    const element = document.createElement('button');
    element.type = 'button';
    element.className = `table-action ${variant ? `table-action-${variant}` : ''}`.trim();
    element.setAttribute('aria-label', label);
    element.title = label;
    element.innerHTML = icon(iconName);
    element.addEventListener('click', (event) => {
        event.stopPropagation();
        onClick(event);
    });

    return element;
}

/**
 * แถบเครื่องมือ (ค้นหา + ตัวกรอง)
 */
export function toolbar(...children) {
    const element = document.createElement('div');
    element.className = 'toolbar';
    element.append(...children.filter(Boolean));

    return element;
}

/**
 * ช่องค้นหาแบบหน่วงเวลา
 */
export function searchBox({placeholder = 'ค้นหา...', value = '', onSearch, delay = 300}) {
    const label = document.createElement('label');
    label.className = 'search-box';

    const iconBox = document.createElement('span');
    iconBox.innerHTML = icon('search');

    const input = document.createElement('input');
    input.type = 'search';
    input.className = 'form-control';
    input.placeholder = placeholder;
    input.value = value;
    input.setAttribute('aria-label', placeholder);

    let timer = null;

    input.addEventListener('input', () => {
        window.clearTimeout(timer);
        timer = window.setTimeout(() => onSearch(input.value.trim()), delay);
    });

    input.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
            event.preventDefault();
            window.clearTimeout(timer);
            onSearch(input.value.trim());
        }
    });

    label.append(iconBox, input);

    return label;
}

/**
 * ตัวกรองแบบ select
 */
export function selectFilter({label, options, value = '', onChange, name = ''}) {
    const wrapper = document.createElement('label');
    wrapper.className = 'filter-select';

    const text = document.createElement('span');
    text.textContent = label;

    const select = document.createElement('select');
    select.className = 'form-control';
    select.name = name;
    select.setAttribute('aria-label', label);

    for (const option of options) {
        const element = document.createElement('option');
        element.value = option.value;
        element.textContent = option.label;
        select.append(element);
    }

    select.value = value;
    select.addEventListener('change', () => onChange(select.value));

    wrapper.append(text, select);

    return wrapper;
}

/**
 * ตัวกรองแบบ input (เช่น type=month, date)
 */
export function inputFilter({label, type = 'month', value = '', onChange, name = ''}) {
    const wrapper = document.createElement('label');
    wrapper.className = 'filter-select';

    const text = document.createElement('span');
    text.textContent = label;

    const input = document.createElement('input');
    input.type = type;
    input.className = 'form-control';
    input.name = name;
    input.value = value;
    input.setAttribute('aria-label', label);
    input.addEventListener('change', () => onChange(input.value));

    wrapper.append(text, input);

    return wrapper;
}

/**
 * แท็บ
 */
export function createTabs({tabs, active, onChange}) {
    const nav = document.createElement('div');
    nav.className = 'tabs';
    nav.setAttribute('role', 'tablist');

    for (const tab of tabs) {
        const element = document.createElement('button');
        element.type = 'button';
        element.className = 'tab';
        element.setAttribute('role', 'tab');
        element.dataset.tab = tab.id;

        const text = document.createElement('span');
        text.textContent = tab.label;
        element.append(text);

        if (tab.count !== undefined && tab.count !== null) {
            const count = document.createElement('span');
            count.className = 'tab-count';
            count.textContent = String(tab.count);
            element.append(count);
        }

        const isActive = tab.id === active;
        element.classList.toggle('is-active', isActive);
        element.setAttribute('aria-selected', String(isActive));

        element.addEventListener('click', () => {
            nav.querySelectorAll('.tab').forEach((item) => {
                const selected = item === element;
                item.classList.toggle('is-active', selected);
                item.setAttribute('aria-selected', String(selected));
            });

            onChange(tab.id);
        });

        nav.append(element);
    }

    return nav;
}

/**
 * การ์ดตัวเลขสรุปเล็ก ๆ ด้านบนของหน้า
 */
export function statTile({label, value, meta = '', variant = 'green', iconName = null}) {
    const tile = document.createElement('article');
    tile.className = `stat-tile stat-tile-${variant}`;

    if (iconName) {
        const iconBox = document.createElement('span');
        iconBox.className = 'stat-tile-icon';
        iconBox.innerHTML = icon(iconName);
        tile.append(iconBox);
    }

    const body = document.createElement('div');

    const labelElement = document.createElement('span');
    labelElement.className = 'stat-tile-label';
    labelElement.textContent = label;

    const valueElement = document.createElement('strong');
    valueElement.className = 'stat-tile-value';
    appendContent(valueElement, value);

    body.append(labelElement, valueElement);

    if (meta) {
        const metaElement = document.createElement('span');
        metaElement.className = 'stat-tile-meta';
        appendContent(metaElement, meta);
        body.append(metaElement);
    }

    tile.append(body);

    return tile;
}

/**
 * รายการ label: value สำหรับหน้ารายละเอียด
 */
export function detailList(items) {
    const list = document.createElement('dl');
    list.className = 'detail-list';

    for (const [label, value] of items) {
        const term = document.createElement('dt');
        term.textContent = label;

        const description = document.createElement('dd');
        appendContent(description, value);

        list.append(term, description);
    }

    return list;
}

/**
 * การ์ดพร้อมหัวข้อ
 */
export function card({title = '', actions = [], content = null, className = ''}) {
    const section = document.createElement('section');
    section.className = `card ${className}`.trim();

    if (title || actions.length) {
        const header = document.createElement('header');
        header.className = 'card-header';

        const heading = document.createElement('h2');
        heading.textContent = title;
        header.append(heading);

        if (actions.length) {
            const box = document.createElement('div');
            box.className = 'card-actions';
            box.append(...actions);
            header.append(box);
        }

        section.append(header);
    }

    const body = document.createElement('div');
    body.className = 'card-body';

    if (content) {
        appendContent(body, content);
    }

    section.append(body);
    section.body = body;

    return section;
}
