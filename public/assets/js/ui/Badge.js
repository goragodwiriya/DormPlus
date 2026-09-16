/**
 * ป้ายสถานะ: badge('ชำระแล้ว', 'green') หรือ statusBadge(map, 'paid')
 */
export function badge(text, variant = 'gray') {
    const element = document.createElement('span');
    element.className = `badge badge-${variant}`;
    element.textContent = text;

    return element;
}

export function statusBadge(map, value) {
    const item = map[value];

    return badge(item ? item.label : value || '-', item ? item.variant : 'gray');
}
