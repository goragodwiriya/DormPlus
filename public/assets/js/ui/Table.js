/**
 * ตารางข้อมูลแบบ responsive (บนมือถือแต่ละแถวกลายเป็นการ์ด)
 *
 * createTable({
 *     columns: [
 *         {key: 'room_number', label: 'ห้อง'},
 *         {key: 'monthly_rent', label: 'ค่าเช่า', align: 'right', render: (row) => formatCurrency(row.monthly_rent)}
 *     ],
 *     rows,
 *     onRowClick: (row) => ...
 * })
 */
export function createTable({
    columns,
    rows,
    rowKey = 'id',
    onRowClick = null,
    emptyMessage = 'ไม่พบข้อมูล',
    compact = false
}) {
    const wrapper = document.createElement('div');
    wrapper.className = 'table-wrap';

    const table = document.createElement('table');
    table.className = `data-table ${compact ? 'data-table-compact' : ''}`;

    const head = document.createElement('thead');
    const headRow = document.createElement('tr');

    for (const column of columns) {
        const cell = document.createElement('th');
        cell.scope = 'col';
        cell.textContent = column.label;

        if (column.align) {
            cell.classList.add(`align-${column.align}`);
        }

        if (column.width) {
            cell.style.width = column.width;
        }

        if (column.className) {
            cell.classList.add(column.className);
        }

        headRow.append(cell);
    }

    head.append(headRow);

    const body = document.createElement('tbody');

    if (!rows.length) {
        const row = document.createElement('tr');
        const cell = document.createElement('td');
        cell.colSpan = columns.length;
        cell.className = 'table-empty';
        cell.textContent = emptyMessage;
        row.append(cell);
        body.append(row);
    }

    for (const item of rows) {
        const row = document.createElement('tr');

        if (item[rowKey] !== undefined) {
            row.dataset.key = item[rowKey];
        }

        if (onRowClick) {
            row.classList.add('is-clickable');
            row.tabIndex = 0;
            row.addEventListener('click', (event) => {
                if (event.target.closest('button, a, input, select')) {
                    return;
                }

                onRowClick(item);
            });
            row.addEventListener('keydown', (event) => {
                if (event.key === 'Enter' && event.target === row) {
                    onRowClick(item);
                }
            });
        }

        for (const column of columns) {
            const cell = document.createElement('td');
            cell.dataset.label = column.label;

            if (column.align) {
                cell.classList.add(`align-${column.align}`);
            }

            if (column.className) {
                cell.classList.add(column.className);
            }

            const content = column.render
                ? column.render(item)
                : item[column.key];

            appendContent(cell, content);
            row.append(cell);
        }

        body.append(row);
    }

    table.append(head, body);
    wrapper.append(table);

    return wrapper;
}

/**
 * ใส่ข้อความ/Node/array ลงใน element โดยไม่ใช้ innerHTML
 */
export function appendContent(element, content) {
    if (content === null || content === undefined) {
        element.textContent = '-';
        return;
    }

    if (Array.isArray(content)) {
        for (const part of content) {
            appendContent(element, part);
        }

        return;
    }

    if (content instanceof Node) {
        element.append(content);
        return;
    }

    element.append(document.createTextNode(String(content)));
}

/**
 * ข้อความสองบรรทัด (หลัก + รอง) สำหรับใช้ในเซลล์
 */
export function stacked(primary, secondary = '') {
    const box = document.createElement('div');
    box.className = 'cell-stack';

    const main = document.createElement('strong');
    appendContent(main, primary);
    box.append(main);

    if (secondary) {
        const sub = document.createElement('span');
        appendContent(sub, secondary);
        box.append(sub);
    }

    return box;
}
