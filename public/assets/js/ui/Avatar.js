import {asset} from '../core/Paths.js';
import {initials} from './Formatters.js';

/**
 * วงกลมแสดงรูปหรืออักษรย่อของผู้ใช้/ผู้เช่า
 */
export function avatar({name = '', image = null, gender = null, size = 'md'}) {
    const element = document.createElement('span');
    element.className = `avatar avatar-${size}`;

    if (gender) {
        element.classList.add(`avatar-${gender}`);
    }

    if (image) {
        const img = document.createElement('img');
        img.src = asset(image);
        img.alt = '';
        img.loading = 'lazy';
        element.append(img);
    } else {
        element.textContent = initials(name);
    }

    return element;
}
