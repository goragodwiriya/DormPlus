import {asset} from '../core/Paths.js';
import {icon} from '../ui/Icons.js';

const navigation = [
    {path: '/dashboard', label: 'หน้าหลัก', icon: 'home'},
    {path: '/rooms', label: 'ห้องพัก', icon: 'building'},
    {path: '/tenants', label: 'ผู้เช่า', icon: 'users'},
    {path: '/contracts', label: 'สัญญาเช่า', icon: 'document'},
    {path: '/payments', label: 'การเงิน', icon: 'money'},
    {
        path: '/maintenance',
        label: 'แจ้งซ่อม / บำรุงรักษา',
        icon: 'wrench'
    },
    {path: '/reports', label: 'รายงาน', icon: 'report'},
    {path: '/messages', label: 'ข้อความ', icon: 'message'},
    {path: '/settings', label: 'ตั้งค่า', icon: 'settings'}
];

const pageTitles = {
    '/dashboard': ['หน้าหลัก', 'ภาพรวมข้อมูลหอพัก'],
    '/rooms': ['ห้องพัก', 'จัดการข้อมูลและสถานะห้องพัก'],
    '/tenants': ['ผู้เช่า', 'จัดการข้อมูลผู้เช่า'],
    '/contracts': ['สัญญาเช่า', 'จัดการสัญญาและวันหมดอายุ'],
    '/payments': ['การเงิน', 'จัดการค่าเช่าและการชำระเงิน'],
    '/maintenance': ['แจ้งซ่อม', 'ติดตามงานซ่อมและบำรุงรักษา'],
    '/reports': ['รายงาน', 'สรุปข้อมูลและผลการดำเนินงาน'],
    '/messages': ['ข้อความ', 'การติดต่อภายในระบบ'],
    '/settings': ['ตั้งค่า', 'ข้อมูลหอพักและการตั้งค่าระบบ']
};

const notificationIcons = {
    maintenance: ['wrench', 'pink'],
    payment: ['money', 'green'],
    contract: ['document', 'blue'],
    room: ['building', 'orange']
};

export class Shell {
    constructor({root, api, state, router, toast}) {
        this.root = root;
        this.api = api;
        this.state = state;
        this.router = router;
        this.toast = toast;
        this.mounted = false;
        this.notificationsLoaded = false;
        this.boundDocumentClick = this.handleDocumentClick.bind(this);
        this.boundKeydown = this.handleKeydown.bind(this);
    }

    mount() {
        const user = this.state.get().user;
        const displayName = `${user.first_name} ${user.last_name}`.trim();
        const role = this.roleLabel(user.role);
        const propertyName = user.property_name || 'หอพักของคุณ';

        this.root.replaceChildren();

        const shell = document.createElement('div');
        shell.className = 'app-shell';
        shell.innerHTML = `
            <aside class="sidebar" id="sidebar" aria-label="เมนูหลัก">
                <div class="sidebar-header">
                    <span class="sidebar-brand-mark">
                        ${icon('home')}
                    </span>
                    <span>
                        <span class="sidebar-brand-name">DormPlus</span>
                        <span class="sidebar-brand-subtitle">
                            ระบบจัดการหอพัก
                        </span>
                    </span>
                    <button
                        class="icon-button sidebar-close"
                        id="sidebar-close"
                        type="button"
                        aria-label="ปิดเมนู"
                    >
                        ${icon('close')}
                    </button>
                </div>

                <nav class="sidebar-nav">
                    <span class="nav-section-label">เมนูหลัก</span>
                    <ul class="nav-list">
                        ${navigation.map((item) => `
                            <li>
                                <a
                                    class="nav-link"
                                    href="#${item.path}"
                                    data-route="${item.path}"
                                >
                                    ${icon(item.icon)}
                                    <span>${item.label}</span>
                                </a>
                            </li>
                        `).join('')}
                    </ul>
                </nav>

                <div class="sidebar-footer">
                    <div class="property-summary">
                        <div class="property-summary-header">
                            <span class="property-icon">
                                ${icon('building')}
                            </span>
                            <span class="property-summary-text">
                                <span class="property-name"></span>
                                <span
                                    class="property-meta"
                                    id="property-meta"
                                >กำลังโหลดข้อมูล...</span>
                            </span>
                        </div>
                    </div>
                </div>
            </aside>

            <button
                class="sidebar-backdrop"
                id="sidebar-backdrop"
                type="button"
                aria-label="ปิดเมนู"
                tabindex="-1"
            ></button>

            <div class="app-area">
                <header class="topbar">
                    <button
                        class="icon-button mobile-menu-button"
                        id="mobile-menu-button"
                        type="button"
                        aria-label="เปิดเมนู"
                        aria-controls="sidebar"
                        aria-expanded="false"
                    >
                        ${icon('menu')}
                    </button>

                    <div class="page-context">
                        <h1
                            class="page-context-title"
                            id="page-context-title"
                        >หน้าหลัก</h1>
                        <p
                            class="page-context-subtitle"
                            id="page-context-subtitle"
                        >ภาพรวมข้อมูลหอพัก</p>
                    </div>

                    <div class="topbar-actions">
                        <div class="notification-menu">
                            <button
                                class="icon-button"
                                id="notification-trigger"
                                type="button"
                                aria-label="การแจ้งเตือน"
                                aria-expanded="false"
                                aria-controls="notification-panel"
                            >
                                ${icon('bell')}
                                <span
                                    class="notification-badge"
                                    id="notification-badge"
                                    hidden
                                ></span>
                            </button>

                            <div
                                class="notification-panel"
                                id="notification-panel"
                                hidden
                            >
                                <header class="notification-panel-header">
                                    <strong>การแจ้งเตือน</strong>
                                    <span id="notification-panel-count"></span>
                                </header>
                                <div
                                    class="notification-list notification-panel-list"
                                    id="notification-panel-list"
                                >
                                    <div class="route-loading compact">
                                        <span class="spinner" aria-hidden="true"></span>
                                    </div>
                                </div>
                                <footer class="notification-panel-footer">
                                    <a href="#/notifications">ดูการแจ้งเตือนทั้งหมด</a>
                                </footer>
                            </div>
                        </div>

                        <button
                            class="icon-button"
                            type="button"
                            aria-label="ข้อความ"
                            data-action="messages"
                        >
                            ${icon('message')}
                            <span
                                class="notification-badge"
                                id="message-badge"
                                hidden
                            ></span>
                        </button>

                        <div class="user-menu">
                            <button
                                class="user-menu-trigger"
                                id="user-menu-trigger"
                                type="button"
                                aria-expanded="false"
                                aria-controls="user-dropdown"
                            >
                                <span class="avatar" id="user-avatar"></span>
                                <span class="user-details">
                                    <span
                                        class="user-name"
                                        id="user-display-name"
                                    ></span>
                                    <span
                                        class="user-role"
                                        id="user-role"
                                    ></span>
                                </span>
                                ${icon('chevronDown', 'user-menu-chevron')}
                            </button>

                            <div
                                class="user-dropdown"
                                id="user-dropdown"
                                hidden
                            >
                                <a
                                    class="dropdown-item"
                                    href="#/settings"
                                >
                                    ${icon('settings')}
                                    <span>ตั้งค่าบัญชี</span>
                                </a>
                                <div class="dropdown-divider"></div>
                                <button
                                    class="dropdown-item dropdown-item-danger"
                                    id="logout-button"
                                    type="button"
                                >
                                    ${icon('logout')}
                                    <span>ออกจากระบบ</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </header>

                <main class="main-content" id="main-content" tabindex="-1">
                    <div class="route-loading">
                        <span class="spinner" aria-hidden="true"></span>
                        <p>กำลังโหลดข้อมูล...</p>
                    </div>
                </main>
            </div>
        `;

        this.root.append(shell);

        shell.querySelector('.property-name').textContent = propertyName;
        shell.querySelector('#user-display-name').textContent =
            `คุณ${displayName}`;
        shell.querySelector('#user-role').textContent = role;
        this.renderAvatar(shell.querySelector('#user-avatar'), user);

        this.bindEvents();
        this.mounted = true;
        this.loadNotifications();
        this.loadMessageCount();
        this.loadPropertySummary();
    }

    async loadPropertySummary() {
        try {
            const response = await this.api.get('/dashboard/statistics');
            this.updatePropertySummary(response.data);
        } catch {
            const meta = this.root.querySelector('#property-meta');

            if (meta) {
                meta.textContent = '';
            }
        }
    }

    /**
     * อัปเดตชื่อ/อวาตาร์ใน topbar หลังแก้ไขโปรไฟล์
     */
    refreshUser() {
        const user = this.state.get().user;

        if (!user || !this.mounted) {
            return;
        }

        this.root.querySelector('#user-display-name').textContent =
            `คุณ${`${user.first_name} ${user.last_name}`.trim()}`;
        this.renderAvatar(this.root.querySelector('#user-avatar'), user);
    }

    setPropertyName(name) {
        const element = this.root.querySelector('.property-name');

        if (element) {
            element.textContent = name || 'หอพักของคุณ';
        }
    }

    setMessageCount(count) {
        const badge = this.root.querySelector('#message-badge');
        const total = Number(count) || 0;

        if (!badge) {
            return;
        }

        badge.hidden = total === 0;
        badge.textContent = total > 99 ? '99+' : String(total);
    }

    async loadMessageCount() {
        try {
            const response = await this.api.get('/messages/unread-count');
            this.setMessageCount(response.data.unread_count);
        } catch {
            // ไม่ต้องแจ้งผู้ใช้ badge จะอัปเดตครั้งถัดไป
        }
    }

    unmount() {
        document.removeEventListener('click', this.boundDocumentClick);
        document.removeEventListener('keydown', this.boundKeydown);
        document.body.classList.remove('sidebar-open');
        this.mounted = false;
        this.notificationsLoaded = false;
    }

    renderAvatar(container, user) {
        container.replaceChildren();

        if (user.avatar) {
            const image = document.createElement('img');
            image.src = asset(user.avatar);
            image.alt = '';
            container.append(image);
            return;
        }

        container.textContent = user.first_name?.charAt(0) || 'ผ';
    }

    setActiveRoute(path) {
        if (!this.mounted) {
            return;
        }

        const basePath = `/${path.split('/').filter(Boolean)[0] || 'dashboard'}`;
        const context = pageTitles[basePath] || ['DormPlus', 'ระบบจัดการหอพัก'];

        this.root.querySelector('#page-context-title').textContent = context[0];
        this.root.querySelector('#page-context-subtitle').textContent = context[1];

        this.root.querySelectorAll('.nav-link').forEach((link) => {
            const active = link.dataset.route === basePath;
            link.classList.toggle('active', active);

            if (active) {
                link.setAttribute('aria-current', 'page');
            } else {
                link.removeAttribute('aria-current');
            }
        });

        this.closeSidebar();
        this.closeUserMenu();
        this.closeNotifications();
    }

    contentElement() {
        return this.root.querySelector('#main-content');
    }

    /**
     * ข้อมูลสรุปใต้ sidebar — Dashboard เรียกหลังโหลดสถิติแล้ว
     */
    updatePropertySummary(statistics) {
        const meta = this.root.querySelector('#property-meta');

        if (!meta || !statistics) {
            return;
        }

        meta.textContent =
            `ห้องพัก ${statistics.rooms.total} ห้อง | ผู้เช่า ${statistics.tenants.total} คน`;
    }

    setNotificationCount(count) {
        const badge = this.root.querySelector('#notification-badge');
        const panelCount = this.root.querySelector(
            '#notification-panel-count'
        );
        const total = Number(count) || 0;

        if (!badge) {
            return;
        }

        badge.hidden = total === 0;
        badge.textContent = total > 99 ? '99+' : String(total);

        if (panelCount) {
            panelCount.textContent = total > 0
                ? `ยังไม่อ่าน ${total} รายการ`
                : 'อ่านครบแล้ว';
        }
    }

    showComingSoon(title) {
        const content = this.contentElement();
        const section = document.createElement('section');

        section.className = 'content-placeholder';

        const wrapper = document.createElement('div');
        const iconBox = document.createElement('span');
        const heading = document.createElement('h2');
        const text = document.createElement('p');
        const link = document.createElement('a');

        iconBox.className = 'placeholder-icon';
        iconBox.innerHTML = icon('inbox');
        heading.textContent = title;
        text.textContent = 'โมดูลนี้อยู่ระหว่างการพัฒนา จะเปิดให้ใช้งานในเวอร์ชันถัดไป';
        link.className = 'btn btn-secondary';
        link.href = '#/dashboard';
        link.textContent = 'กลับหน้าหลัก';

        wrapper.append(iconBox, heading, text, link);
        section.append(wrapper);
        content.replaceChildren(section);
    }

    bindEvents() {
        this.root
            .querySelector('#mobile-menu-button')
            .addEventListener('click', () => this.openSidebar());

        this.root
            .querySelector('#sidebar-close')
            .addEventListener('click', () => this.closeSidebar());

        this.root
            .querySelector('#sidebar-backdrop')
            .addEventListener('click', () => this.closeSidebar());

        this.root
            .querySelector('#user-menu-trigger')
            .addEventListener('click', () => this.toggleUserMenu());

        this.root
            .querySelector('#logout-button')
            .addEventListener('click', () => this.logout());

        this.root
            .querySelector('[data-action="messages"]')
            .addEventListener('click', () => {
                this.router.navigate('/messages');
            });

        this.root
            .querySelector('#notification-trigger')
            .addEventListener('click', () => this.toggleNotifications());

        document.addEventListener('click', this.boundDocumentClick);
        document.addEventListener('keydown', this.boundKeydown);
    }

    handleDocumentClick(event) {
        if (!event.target.closest('.user-menu')) {
            this.closeUserMenu();
        }

        if (!event.target.closest('.notification-menu')) {
            this.closeNotifications();
        }
    }

    handleKeydown(event) {
        if (event.key === 'Escape') {
            this.closeSidebar();
            this.closeUserMenu();
            this.closeNotifications();
        }
    }

    openSidebar() {
        document.body.classList.add('sidebar-open');

        this.root
            .querySelector('#mobile-menu-button')
            .setAttribute('aria-expanded', 'true');
    }

    closeSidebar() {
        document.body.classList.remove('sidebar-open');

        this.root
            .querySelector('#mobile-menu-button')
            ?.setAttribute('aria-expanded', 'false');
    }

    toggleUserMenu() {
        const trigger = this.root.querySelector('#user-menu-trigger');
        const dropdown = this.root.querySelector('#user-dropdown');
        const expanded = trigger.getAttribute('aria-expanded') === 'true';

        this.closeNotifications();
        trigger.setAttribute('aria-expanded', String(!expanded));
        dropdown.hidden = expanded;
    }

    closeUserMenu() {
        const trigger = this.root.querySelector('#user-menu-trigger');
        const dropdown = this.root.querySelector('#user-dropdown');

        if (trigger) {
            trigger.setAttribute('aria-expanded', 'false');
        }

        if (dropdown) {
            dropdown.hidden = true;
        }
    }

    toggleNotifications() {
        const trigger = this.root.querySelector('#notification-trigger');
        const panel = this.root.querySelector('#notification-panel');
        const expanded = trigger.getAttribute('aria-expanded') === 'true';

        this.closeUserMenu();
        trigger.setAttribute('aria-expanded', String(!expanded));
        panel.hidden = expanded;

        if (!expanded && !this.notificationsLoaded) {
            this.loadNotifications();
        }
    }

    closeNotifications() {
        const trigger = this.root.querySelector('#notification-trigger');
        const panel = this.root.querySelector('#notification-panel');

        if (trigger) {
            trigger.setAttribute('aria-expanded', 'false');
        }

        if (panel) {
            panel.hidden = true;
        }
    }

    async loadNotifications() {
        const list = this.root.querySelector('#notification-panel-list');

        try {
            const response = await this.api.get(
                '/dashboard/notifications?limit=8'
            );

            this.renderNotificationList(list, response.data.items);
            this.setNotificationCount(response.data.unread_count);
            this.notificationsLoaded = true;
        } catch (error) {
            list.replaceChildren();

            const message = document.createElement('p');
            message.className = 'dashboard-empty';
            message.textContent = error.message;
            list.append(message);
        }
    }

    renderNotificationList(container, items) {
        container.replaceChildren();

        if (!items.length) {
            const empty = document.createElement('p');
            empty.className = 'dashboard-empty';
            empty.textContent = 'ไม่มีการแจ้งเตือน';
            container.append(empty);
            return;
        }

        for (const notification of items) {
            container.append(this.notificationItem(notification));
        }
    }

    notificationItem(notification) {
        const item = document.createElement('article');
        item.className = 'notification-item notification-item-link';
        item.tabIndex = 0;
        item.setAttribute('role', 'link');

        if (Number(notification.is_read) === 1) {
            item.classList.add('is-read');
        }

        const open = () => this.openNotification(notification);
        item.addEventListener('click', open);
        item.addEventListener('keydown', (event) => {
            if (event.key === 'Enter') {
                open();
            }
        });

        const [iconName, color] =
            notificationIcons[notification.type] || ['bell', 'gray'];

        const iconBox = document.createElement('span');
        iconBox.className = `notification-type notification-${color}`;
        iconBox.innerHTML = icon(iconName);

        const body = document.createElement('div');
        body.className = 'notification-content';

        const title = document.createElement('strong');
        title.textContent = notification.title;

        const description = document.createElement('p');
        description.textContent = notification.description || '';

        body.append(title, description);

        const time = document.createElement('time');
        time.dateTime = notification.created_at;
        time.textContent = Shell.relativeTime(notification.created_at);

        item.append(iconBox, body, time);

        return item;
    }

    /**
     * เปิดหน้าที่เกี่ยวข้องกับการแจ้งเตือน และทำเครื่องหมายว่าอ่านแล้ว
     */
    async openNotification(notification) {
        this.closeNotifications();

        if (Number(notification.is_read) !== 1) {
            try {
                const response = await this.api.put(`/notifications/${notification.id}/read`);
                this.setNotificationCount(response.data.unread_count);
                this.notificationsLoaded = false;
            } catch {
                // เปิดหน้าปลายทางต่อได้แม้ทำเครื่องหมายไม่สำเร็จ
            }
        }

        const routes = {
            maintenance: () => '/maintenance',
            payment: () => '/payments',
            contract: (id) => `/contracts/${id}`,
            room: (id) => `/rooms/${id}`
        };

        const target = routes[notification.related_type];
        this.router.navigate(target ? target(notification.related_id) : '/notifications');
    }

    static relativeTime(value) {
        const date = new Date(String(value).replace(' ', 'T'));
        const seconds = Math.max(
            0,
            Math.floor((Date.now() - date.getTime()) / 1000)
        );

        if (seconds < 60) {
            return 'เมื่อสักครู่';
        }

        if (seconds < 3600) {
            return `${Math.floor(seconds / 60)} นาทีที่แล้ว`;
        }

        if (seconds < 86400) {
            return `${Math.floor(seconds / 3600)} ชม.ที่แล้ว`;
        }

        return `${Math.floor(seconds / 86400)} วันที่แล้ว`;
    }

    async logout() {
        const button = this.root.querySelector('#logout-button');
        button.disabled = true;

        try {
            await this.api.post('/auth/logout');

            this.api.setCsrfToken(null);
            this.state.set({
                user: null,
                authenticated: false
            });

            this.unmount();
            this.toast.success('ออกจากระบบเรียบร้อยแล้ว');
            this.router.navigate('/login', true);
        } catch (error) {
            this.toast.error(error.message);
            button.disabled = false;
        }
    }

    roleLabel(role) {
        const labels = {
            owner: 'เจ้าของหอพัก',
            administrator: 'ผู้ดูแลระบบ',
            staff: 'พนักงาน'
        };

        return labels[role] || 'ผู้ใช้งาน';
    }
}
