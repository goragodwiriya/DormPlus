import {asset} from '../core/Paths.js';
import {icon} from '../ui/Icons.js';
import {BarChart, DonutChart} from '../ui/Charts.js';

const roomStatusLabels = {
    available: 'ว่าง',
    occupied: 'มีผู้เช่า',
    maintenance: 'ซ่อมบำรุง',
    reserved: 'จองแล้ว'
};

const paymentStatusLabels = {
    paid: 'ชำระแล้ว',
    pending: 'รอชำระ',
    cancelled: 'ยกเลิก'
};

export class Dashboard {
    constructor({shell, api, router, toast}) {
        this.shell = shell;
        this.api = api;
        this.router = router;
        this.toast = toast;
        this.clockTimer = null;
        this.requestController = null;
    }

    async render() {
        const content = this.shell.contentElement();

        this.stop();
        this.requestController = new AbortController();
        this.renderStructure(content);
        this.startClock();

        try {
            const response = await this.api.get('/dashboard', {
                signal: this.requestController.signal
            });

            this.populate(response.data);
        } catch (error) {
            if (error.name === 'AbortError') {
                return;
            }

            this.renderError(content, error.message);
        }
    }

    stop() {
        if (this.clockTimer) {
            window.clearInterval(this.clockTimer);
            this.clockTimer = null;
        }

        if (this.requestController) {
            this.requestController.abort();
            this.requestController = null;
        }
    }

    renderStructure(content) {
        content.innerHTML = `
            <div class="dashboard-layout">
                <div class="dashboard-main-column">
                    <section class="dashboard-welcome">
                        <div class="welcome-copy">
                            <h2>
                                สวัสดีครับ คุณเจ้าของหอ
                                <span aria-hidden="true">👋</span>
                            </h2>
                            <p>
                                ตรวจสอบข้อมูลหอพัก
                                และจัดการทุกอย่างได้ในที่เดียว
                            </p>
                            <div class="welcome-datetime">
                                ${icon('calendar')}
                                <span id="dashboard-date"></span>
                                <span class="datetime-divider"></span>
                                <span id="dashboard-time"></span>
                            </div>
                        </div>
                    </section>

                    <section
                        class="statistics-grid"
                        aria-label="ข้อมูลสรุป"
                    >
                        ${this.statisticCard(
            'rooms',
            'home',
            'ห้องทั้งหมด',
            'stat-card-green'
        )}
                        ${this.statisticCard(
            'tenants',
            'users',
            'ผู้เช่าทั้งหมด',
            'stat-card-blue'
        )}
                        ${this.statisticCard(
            'income',
            'coins',
            'รายได้เดือนนี้',
            'stat-card-orange'
        )}
                        ${this.statisticCard(
            'maintenance',
            'alert',
            'แจ้งซ่อมค้าง',
            'stat-card-pink'
        )}
                    </section>

                    <div class="dashboard-chart-grid">
                        <section class="dashboard-card financial-card">
                            <header class="card-header">
                                <h2>รายรับ - รายจ่าย (เดือนนี้)</h2>
                                <div class="chart-legend">
                                    <span>
                                        <i class="legend-dot income-dot"></i>
                                        รายรับ
                                    </span>
                                    <span>
                                        <i class="legend-dot expense-dot"></i>
                                        รายจ่าย
                                    </span>
                                </div>
                            </header>

                            <div class="financial-body">
                                <div
                                    class="bar-chart"
                                    id="financial-chart"
                                ></div>
                                <div
                                    class="financial-summary"
                                    id="financial-summary"
                                ></div>
                            </div>
                        </section>

                        <section class="dashboard-card room-status-card">
                            <header class="card-header">
                                <h2>สถานะห้องพัก</h2>
                            </header>

                            <div class="room-status-body">
                                <div id="room-status-chart"></div>
                                <div
                                    class="room-status-legend"
                                    id="room-status-legend"
                                ></div>
                            </div>

                            <button
                                class="room-detail-link"
                                type="button"
                                data-route="/rooms"
                            >
                                ดูรายละเอียดห้องพัก
                                ${icon('chevronRight')}
                            </button>
                        </section>
                    </div>

                    <div class="dashboard-list-grid">
                        <section class="dashboard-card">
                            <header class="card-header">
                                <h2>ห้องพักล่าสุด</h2>
                                <button
                                    class="text-button"
                                    type="button"
                                    data-route="/rooms"
                                >ดูทั้งหมด</button>
                            </header>
                            <div
                                class="dashboard-list"
                                id="latest-rooms"
                            ></div>
                        </section>

                        <section class="dashboard-card">
                            <header class="card-header">
                                <h2>การชำระค่าเช่าล่าสุด</h2>
                                <button
                                    class="text-button"
                                    type="button"
                                    data-route="/payments"
                                >ดูทั้งหมด</button>
                            </header>
                            <div
                                class="dashboard-list"
                                id="latest-payments"
                            ></div>
                        </section>
                    </div>
                </div>

                <aside class="dashboard-right-column">
                    <section class="dashboard-card notification-card">
                        <header class="card-header">
                            <h2>แจ้งเตือน</h2>
                            <button
                                class="text-button"
                                type="button"
                                data-action="all-notifications"
                            >ดูทั้งหมด</button>
                        </header>
                        <div
                            class="notification-list"
                            id="dashboard-notifications"
                        ></div>
                    </section>

                    <section class="dashboard-card quick-action-card">
                        <header class="card-header">
                            <h2>เมนูด่วน</h2>
                        </header>
                        <div class="quick-action-grid">
                            ${this.quickAction(
            'userPlus',
            'เพิ่มผู้เช่า',
            '/tenants',
            'green',
            'new=1'
        )}
                            ${this.quickAction(
            'receipt',
            'บันทึกค่าเช่า',
            '/payments',
            'blue',
            'new=1'
        )}
                            ${this.quickAction(
            'wrench',
            'แจ้งซ่อม',
            '/maintenance',
            'purple',
            'new=1'
        )}
                            ${this.quickAction(
            'contract',
            'สร้างสัญญาเช่า',
            '/contracts',
            'orange',
            'new=1'
        )}
                            ${this.quickAction(
            'chart',
            'ดูรายงาน',
            '/reports',
            'cyan'
        )}
                            ${this.quickAction(
            'settings',
            'ตั้งค่า',
            '/settings',
            'gray'
        )}
                        </div>
                    </section>

                    <section class="dashboard-promotion">
                        <div class="promotion-content">
                            <span class="promotion-icon">
                                ${icon('home')}
                            </span>
                            <h2>หอพักของคุณ<br>จัดการได้ง่ายขึ้น</h2>
                            <span class="promotion-tag">ด้วย DormPlus</span>
                            <button
                                class="promotion-button"
                                type="button"
                                data-action="help"
                            >
                                ดูวิธีใช้งาน
                                ${icon('chevronRight')}
                            </button>
                        </div>
                    </section>
                </aside>
            </div>
        `;

        content.querySelectorAll('[data-route]').forEach((button) => {
            button.addEventListener('click', () => {
                const query = button.dataset.query
                    ? Object.fromEntries(new URLSearchParams(button.dataset.query))
                    : {};

                this.router.navigate(button.dataset.route, false, query);
            });
        });

        content
            .querySelector('[data-action="all-notifications"]')
            .addEventListener('click', () => {
                this.router.navigate('/notifications');
            });

        content
            .querySelector('[data-action="help"]')
            .addEventListener('click', () => {
                this.toast.success(
                    'เลือกเมนูทางด้านซ้ายเพื่อเริ่มจัดการหอพัก'
                );
            });
    }

    populate(data) {
        this.populateStatistics(data.statistics);
        this.populateFinancial(
            data.financial_chart,
            data.financial_summary
        );
        this.populateRoomStatus(data.room_status);
        this.populateRooms(data.latest_rooms);
        this.populatePayments(data.latest_payments);
        this.populateNotifications(data.notifications);

        this.shell.updatePropertySummary?.(data.statistics);
        this.shell.setNotificationCount?.(
            data.notifications.unread_count
        );
    }

    populateStatistics(statistics) {
        this.setStatistic(
            'rooms',
            [statistics.rooms.total, 'ห้อง'],
            `ว่าง ${statistics.rooms.available} ห้อง | มีผู้เช่า ${statistics.rooms.occupied} ห้อง`
        );

        this.setStatistic(
            'tenants',
            [statistics.tenants.total, 'คน'],
            `ชาย ${statistics.tenants.male} คน | หญิง ${statistics.tenants.female} คน`
        );

        this.setStatistic(
            'income',
            this.currency(statistics.monthly_income.total),
            this.changeLabel(
                statistics.monthly_income.change_percentage,
                'จากเดือนที่แล้ว'
            ),
            Number(statistics.monthly_income.change_percentage) < 0
                ? 'change-negative'
                : 'change-positive'
        );

        this.setStatistic(
            'maintenance',
            [statistics.maintenance.total, 'รายการ'],
            `กำลังดำเนินการ ${statistics.maintenance.in_progress} | รอรับ ${statistics.maintenance.pending}`
        );
    }

    populateFinancial(chartData, summary) {
        const chartContainer = document.querySelector('#financial-chart');
        const summaryContainer = document.querySelector(
            '#financial-summary'
        );

        new BarChart(chartContainer).render(chartData);

        const rows = [
            {
                label: 'รายรับรวม',
                value: summary.income.total,
                change: summary.income.change_percentage,
                className: 'income-summary'
            },
            {
                label: 'รายจ่ายรวม',
                value: summary.expenses.total,
                change: summary.expenses.change_percentage,
                className: 'expense-summary'
            },
            {
                label: 'กำไรสุทธิ',
                value: summary.net_profit.total,
                change: summary.net_profit.change_percentage,
                className: 'profit-summary'
            }
        ];

        summaryContainer.replaceChildren();

        for (const row of rows) {
            const item = document.createElement('article');
            item.className = `summary-item ${row.className}`;

            const label = document.createElement('span');
            label.textContent = row.label;

            const bottom = document.createElement('div');
            const value = document.createElement('strong');
            const change = document.createElement('small');

            value.textContent = this.currency(row.value);
            change.textContent = this.changeLabel(row.change, '');
            change.className = Number(row.change) < 0
                ? 'change-negative'
                : 'change-positive';

            bottom.append(value, change);
            item.append(label, bottom);
            summaryContainer.append(item);
        }
    }

    populateRoomStatus(status) {
        new DonutChart(
            document.querySelector('#room-status-chart')
        ).render(status);

        const legend = document.querySelector('#room-status-legend');
        legend.replaceChildren();

        const items = [
            [
                'มีผู้เช่า',
                status.occupied,
                status.occupied_percentage,
                'occupied'
            ],
            [
                'ว่าง',
                status.available,
                status.available_percentage,
                'available'
            ]
        ];

        if (status.maintenance > 0) {
            items.push([
                'ซ่อมบำรุง',
                status.maintenance,
                Math.round((status.maintenance / status.total) * 100),
                'maintenance'
            ]);
        }

        if (status.reserved > 0) {
            items.push([
                'จองแล้ว',
                status.reserved,
                Math.round((status.reserved / status.total) * 100),
                'reserved'
            ]);
        }

        for (const [labelText, count, percentage, type] of items) {
            const row = document.createElement('div');
            row.className = 'status-legend-row';

            const label = document.createElement('span');
            label.className = 'status-legend-label';

            const dot = document.createElement('i');
            dot.className = `status-dot status-dot-${type}`;

            const text = document.createElement('span');
            text.textContent = labelText;

            const value = document.createElement('strong');
            value.textContent = `${count} (${Math.round(percentage)}%)`;

            label.append(dot, text);
            row.append(label, value);
            legend.append(row);
        }
    }

    populateRooms(rooms) {
        const container = document.querySelector('#latest-rooms');
        container.replaceChildren();

        if (!rooms.length) {
            container.append(this.emptyState('ยังไม่มีข้อมูลห้องพัก'));
            return;
        }

        for (const room of rooms) {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'room-list-item';
            item.addEventListener('click', () => {
                this.router.navigate(`/rooms/${room.id}`);
            });

            const image = document.createElement('img');
            image.className = 'room-thumbnail';
            image.src = asset(
                room.image || 'assets/images/room-placeholder.svg'
            );
            image.alt = `ห้อง ${room.room_number}`;
            image.loading = 'lazy';

            const roomInfo = document.createElement('div');
            roomInfo.className = 'room-primary';

            const roomNumber = document.createElement('strong');
            roomNumber.textContent = `ห้อง ${room.room_number}`;

            const roomMeta = document.createElement('span');
            roomMeta.textContent =
                `ชั้น ${room.floor} · ${this.number(room.monthly_rent)} บาท/เดือน`;

            roomInfo.append(roomNumber, roomMeta);

            const tenantInfo = document.createElement('div');
            tenantInfo.className = 'room-tenant';

            const tenantName = document.createElement('strong');
            tenantName.textContent = room.first_name
                ? `${room.first_name} ${room.last_name}`
                : '-';

            const expiration = document.createElement('span');
            expiration.textContent = room.contract_end_date
                ? `หมดสัญญา ${this.date(room.contract_end_date)}`
                : 'ยังไม่มีสัญญาเช่า';

            tenantInfo.append(tenantName, expiration);

            const status = document.createElement('span');
            status.className = `status-badge status-${room.status}`;
            status.textContent =
                roomStatusLabels[room.status] || room.status;

            const arrow = document.createElement('span');
            arrow.className = 'list-arrow';
            arrow.innerHTML = icon('chevronRight');

            item.append(
                image,
                roomInfo,
                tenantInfo,
                status,
                arrow
            );

            container.append(item);
        }
    }

    populatePayments(payments) {
        const container = document.querySelector('#latest-payments');
        container.replaceChildren();

        if (!payments.length) {
            container.append(
                this.emptyState('ยังไม่มีข้อมูลการชำระเงิน')
            );
            return;
        }

        for (const payment of payments) {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'payment-list-item';
            item.addEventListener('click', () => {
                this.router.navigate('/payments');
            });

            const avatar = document.createElement('span');
            avatar.className = `payment-avatar avatar-${payment.gender}`;
            avatar.textContent = payment.first_name.charAt(0);

            const info = document.createElement('div');
            info.className = 'payment-info';

            const name = document.createElement('strong');
            name.textContent =
                `${payment.first_name} ${payment.last_name}`;

            const detail = document.createElement('span');
            detail.textContent =
                `ห้อง ${payment.room_number} · ${this.number(payment.amount)} บาท`;

            const time = document.createElement('small');
            time.textContent = this.dateTime(payment.payment_date);

            info.append(name, detail, time);

            const status = document.createElement('span');
            status.className =
                `status-badge payment-status-${payment.status}`;
            status.textContent =
                paymentStatusLabels[payment.status] || payment.status;

            item.append(avatar, info, status);
            container.append(item);
        }
    }

    populateNotifications(data) {
        const container = document.querySelector(
            '#dashboard-notifications'
        );

        container.replaceChildren();

        if (!data.items.length) {
            container.append(this.emptyState('ไม่มีการแจ้งเตือน'));
            return;
        }

        const icons = {
            maintenance: ['wrench', 'pink'],
            payment: ['money', 'green'],
            contract: ['document', 'blue'],
            room: ['building', 'orange']
        };

        for (const notification of data.items) {
            const item = document.createElement('article');
            item.className = 'notification-item notification-item-link';
            item.tabIndex = 0;
            item.setAttribute('role', 'link');
            item.addEventListener('click', () => this.shell.openNotification(notification));
            item.addEventListener('keydown', (event) => {
                if (event.key === 'Enter') {
                    this.shell.openNotification(notification);
                }
            });

            const [iconName, color] =
                icons[notification.type] || ['bell', 'gray'];

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
            time.textContent = this.relativeTime(
                notification.created_at
            );

            item.append(iconBox, body, time);
            container.append(item);
        }
    }

    statisticCard(key, iconName, title, className) {
        return `
            <article class="stat-card ${className}">
                <div class="stat-top">
                    <span class="stat-icon">${icon(iconName)}</span>
                    <div class="stat-content">
                        <span class="stat-label">${title}</span>
                        <strong
                            class="stat-value"
                            id="stat-${key}-value"
                        >—</strong>
                    </div>
                    <span class="stat-arrow">${icon('chevronRight')}</span>
                </div>
                <span
                    class="stat-meta"
                    id="stat-${key}-meta"
                >กำลังโหลดข้อมูล...</span>
            </article>
        `;
    }

    quickAction(iconName, label, route, color, query = '') {
        return `
            <button
                class="quick-action"
                type="button"
                data-route="${route}"
                data-query="${query}"
            >
                <span class="quick-icon quick-${color}">
                    ${icon(iconName)}
                </span>
                <span>${label}</span>
            </button>
        `;
    }

    /**
     * value เป็นข้อความ หรือ [ตัวเลข, หน่วย] เพื่อแสดงหน่วยตัวเล็กกว่า
     */
    setStatistic(key, value, meta, metaClass = '') {
        const valueElement = document.querySelector(`#stat-${key}-value`);
        const metaElement = document.querySelector(`#stat-${key}-meta`);

        valueElement.replaceChildren();

        if (Array.isArray(value)) {
            const unit = document.createElement('small');
            unit.textContent = value[1];
            valueElement.append(this.number(value[0]), ' ', unit);
        } else {
            valueElement.textContent = value;
        }

        metaElement.textContent = meta;
        metaElement.className = `stat-meta ${metaClass}`.trim();
    }

    startClock() {
        const update = () => {
            const now = new Date();
            const dateElement = document.querySelector('#dashboard-date');
            const timeElement = document.querySelector('#dashboard-time');

            if (!dateElement || !timeElement) {
                return;
            }

            dateElement.textContent = `วันนี้ ${new Intl.DateTimeFormat(
                'th-TH',
                {
                    day: 'numeric',
                    month: 'long',
                    year: 'numeric'
                }
            ).format(now)}`;

            timeElement.textContent = `เวลา ${new Intl.DateTimeFormat(
                'th-TH',
                {
                    hour: '2-digit',
                    minute: '2-digit',
                    hour12: false
                }
            ).format(now)} น.`;
        };

        update();
        this.clockTimer = window.setInterval(update, 30000);
    }

    renderError(content, message) {
        const section = document.createElement('section');
        section.className = 'dashboard-error';

        const heading = document.createElement('h2');
        heading.textContent = 'ไม่สามารถโหลดข้อมูลหน้าหลักได้';

        const description = document.createElement('p');
        description.textContent =
            message || 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง';

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'btn btn-primary';
        button.textContent = 'ลองใหม่';
        button.addEventListener('click', () => this.render());

        section.append(heading, description, button);
        content.replaceChildren(section);
    }

    emptyState(message) {
        const element = document.createElement('div');
        element.className = 'dashboard-empty';
        element.textContent = message;
        return element;
    }

    currency(value) {
        return new Intl.NumberFormat('th-TH', {
            style: 'currency',
            currency: 'THB',
            maximumFractionDigits: 0
        }).format(Number(value));
    }

    number(value) {
        return new Intl.NumberFormat('th-TH', {
            maximumFractionDigits: 0
        }).format(Number(value));
    }

    date(value) {
        if (!value) {
            return '-';
        }

        return new Intl.DateTimeFormat('th-TH', {
            day: 'numeric',
            month: 'short',
            year: 'numeric'
        }).format(new Date(`${value}T00:00:00`));
    }

    dateTime(value) {
        if (!value) {
            return '-';
        }

        return new Intl.DateTimeFormat('th-TH', {
            day: 'numeric',
            month: 'short',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        }).format(new Date(value.replace(' ', 'T'))) + ' น.';
    }

    relativeTime(value) {
        const date = new Date(value.replace(' ', 'T'));
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

    changeLabel(value, suffix) {
        if (value === null || value === undefined) {
            return 'ยังไม่มีข้อมูลเปรียบเทียบ';
        }

        const number = Number(value);
        const arrow = number >= 0 ? '↑' : '↓';
        const text = `${arrow} ${Math.abs(number)}%`;

        return suffix ? `${text} ${suffix}` : text;
    }
}