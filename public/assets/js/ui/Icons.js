const icons = {
    home: `
        <path d="M3 11.5 12 4l9 7.5"/>
        <path d="M5.5 10.5V20h13v-9.5"/>
        <path d="M9.5 20v-6h5v6"/>
    `,
    building: `
        <path d="M4 21V5a2 2 0 0 1 2-2h9v18"/>
        <path d="M15 9h3a2 2 0 0 1 2 2v10"/>
        <path d="M8 7h3M8 11h3M8 15h3M3 21h19"/>
    `,
    users: `
        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
        <circle cx="9" cy="7" r="4"/>
        <path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>
    `,
    document: `
        <path d="M6 2h8l4 4v16H6z"/>
        <path d="M14 2v5h5M9 13h6M9 17h6"/>
    `,
    money: `
        <circle cx="12" cy="12" r="9"/>
        <path d="M15.5 8.5c-.8-.7-1.8-1-3-1-1.7 0-3 .8-3 2s1.1 1.8 3 2.2 3 1 3 2.3-1.3 2.2-3 2.2c-1.2 0-2.4-.4-3.3-1.2M12.5 5.5v13"/>
    `,
    wrench: `
        <path d="M14.7 6.3a4 4 0 0 0-5-5L12 3.6 9.6 6 7.3 3.7a4 4 0 0 0 5 5L20.5 17a2.1 2.1 0 0 1-3 3l-8.2-8.3"/>
    `,
    report: `
        <path d="M4 3h16v18H4z"/>
        <path d="M8 17v-4M12 17V8M16 17v-7"/>
    `,
    message: `
        <path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"/>
        <path d="M8 10h.01M12 10h.01M16 10h.01"/>
    `,
    settings: `
        <circle cx="12" cy="12" r="3"/>
        <path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6v.2h-4V21a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9A1.7 1.7 0 0 0 3 14H2.8v-4H3a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9L4.2 7 7 4.2l.1.1A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-1.6v-.2h4V3a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.2v4H21a1.7 1.7 0 0 0-1.6 1z"/>
    `,
    bell: `
        <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/>
        <path d="M13.7 21a2 2 0 0 1-3.4 0"/>
    `,
    menu: `<path d="M4 6h16M4 12h16M4 18h16"/>`,
    close: `<path d="m6 6 12 12M18 6 6 18"/>`,
    chevronDown: `<path d="m7 9 5 5 5-5"/>`,
    user: `
        <circle cx="12" cy="8" r="4"/>
        <path d="M4 21a8 8 0 0 1 16 0"/>
    `,
    logout: `
        <path d="M10 17l5-5-5-5M15 12H3"/>
        <path d="M14 3h5a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-5"/>
    `,
    lock: `
        <rect x="4" y="10" width="16" height="11" rx="2"/>
        <path d="M8 10V7a4 4 0 0 1 8 0v3"/>
    `,
    eye: `
        <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12"/>
        <circle cx="12" cy="12" r="2.5"/>
    `,
    check: `<path d="m5 12 4 4L19 6"/>`,
    chevronRight: `<path d="m9 6 6 6-6 6"/>`,
    chevronLeft: `<path d="m15 6-6 6 6 6"/>`,
    arrowLeft: `<path d="M19 12H5M11 18l-6-6 6-6"/>`,
    search: `
        <circle cx="11" cy="11" r="7"/>
        <path d="m20 20-3.5-3.5"/>
    `,
    plus: `<path d="M12 5v14M5 12h14"/>`,
    edit: `
        <path d="M4 20h4l10.5-10.5a2.1 2.1 0 0 0-3-3L5 17z"/>
        <path d="m13.5 6.5 3 3"/>
    `,
    trash: `
        <path d="M4 7h16M10 11v6M14 11v6"/>
        <path d="M6 7l1 13h10l1-13M9 7V4h6v3"/>
    `,
    refresh: `
        <path d="M20 12a8 8 0 0 1-14.3 4.9M4 12a8 8 0 0 1 14.3-4.9"/>
        <path d="M18 3v4h-4M6 21v-4h4"/>
    `,
    download: `
        <path d="M12 4v11M7 10l5 5 5-5"/>
        <path d="M4 19h16"/>
    `,
    send: `<path d="m3 11 18-8-8 18-2-8z"/>`,
    phone: `
        <path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/>
    `,
    mail: `
        <rect x="3" y="5" width="18" height="14" rx="2"/>
        <path d="m3 7 9 6 9-6"/>
    `,
    key: `
        <circle cx="8" cy="15" r="4"/>
        <path d="m11 12 9-9M17 6l2 2M14 9l2 2"/>
    `,
    clock: `
        <circle cx="12" cy="12" r="9"/>
        <path d="M12 7v5l3 2"/>
    `,
    checkCircle: `
        <circle cx="12" cy="12" r="9"/>
        <path d="m8.5 12 2.5 2.5 4.5-5"/>
    `,
    xCircle: `
        <circle cx="12" cy="12" r="9"/>
        <path d="m9 9 6 6M15 9l-6 6"/>
    `,
    filter: `<path d="M4 5h16l-6 8v6l-4-2v-4z"/>`,
    tag: `
        <path d="M3 12V4h8l9 9-8 8z"/>
        <circle cx="7.5" cy="8.5" r="1.5"/>
    `,
    calendarCheck: `
        <rect x="3" y="5" width="18" height="16" rx="3"/>
        <path d="M3 10h18M8 3v4M16 3v4M9 15l2 2 4-4"/>
    `,
    doorOpen: `
        <path d="M13 4v16M13 4l6 2v14l-6-2M5 20h8M5 4h8"/>
        <circle cx="16" cy="12" r="0.8"/>
    `,
    star: `<path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1 6.2L12 17.3 6.5 20.2l1-6.2L3 9.6l6.2-.9z"/>`,
    calendar: `
        <rect x="3" y="5" width="18" height="16" rx="3"/>
        <path d="M3 10h18M8 3v4M16 3v4"/>
    `,
    userPlus: `
        <circle cx="10" cy="8" r="4"/>
        <path d="M2 21a8 8 0 0 1 16 0M19 8v6M16 11h6"/>
    `,
    receipt: `
        <path d="M5 3h14v18l-2.5-1.5L14 21l-2-1.5L10 21l-2.5-1.5L5 21z"/>
        <path d="M9 8h6M9 12h6M9 16h3"/>
    `,
    contract: `
        <path d="M6 2h8l4 4v16H6z"/>
        <path d="M14 2v5h5M9 12h6M9 16h4"/>
        <path d="m15 19 1.5 1.5L20 17"/>
    `,
    chart: `
        <path d="M3 21h18"/>
        <path d="M6 17V11M11 17V5M16 17V9M21 17v-4"/>
    `,
    coins: `
        <ellipse cx="9" cy="6" rx="6" ry="3"/>
        <path d="M3 6v6c0 1.7 2.7 3 6 3s6-1.3 6-3V6"/>
        <path d="M3 12v6c0 1.7 2.7 3 6 3s6-1.3 6-3v-6"/>
        <path d="M21 10v8c0 1.4-2 2.5-4.5 2.9M21 10c0-1.4-2-2.6-4.5-2.9"/>
    `,
    tool: `
        <path d="M14.7 6.3a4.5 4.5 0 0 0-6 5.7L3 17.7a1.8 1.8 0 0 0 2.6 2.6l5.7-5.7a4.5 4.5 0 0 0 5.7-6l-2.6 2.6-2.3-.6-.6-2.3z"/>
    `,
    trendUp: `<path d="m3 17 6-6 4 4 8-8M15 7h6v6"/>`,
    trendDown: `<path d="m3 7 6 6 4-4 8 8M15 17h6v-6"/>`,
    inbox: `
        <path d="M4 4h16v16H4z"/>
        <path d="M4 14h5l1.5 2h3L15 14h5"/>
    `,
    alert: `
        <path d="M10.3 3.7 2.4 18a2 2 0 0 0 1.8 3h15.6a2 2 0 0 0 1.8-3L13.7 3.7a2 2 0 0 0-3.4 0z"/>
        <path d="M12 9v4M12 17h.01"/>
    `
};

export function icon(name, className = '') {
    const body = icons[name] || icons.alert;
    const safeClass = String(className).replace(/[^a-zA-Z0-9 _-]/g, '');

    return `
        <svg
            class="icon ${safeClass}"
            viewBox="0 0 24 24"
            aria-hidden="true"
            focusable="false"
        >${body}</svg>
    `;
}