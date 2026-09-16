import {Module} from './Module.js';
import {button, pageHeader, selectFilter, toolbar} from '../ui/Controls.js';
import {formatRelative} from '../ui/Formatters.js';
import {icon} from '../ui/Icons.js';
import {createPagination} from '../ui/Pagination.js';
import {emptyState, errorState, loadingState} from '../ui/States.js';

const notificationIcons = {
    maintenance: ['wrench', 'pink'],
    payment: ['money', 'green'],
    contract: ['document', 'blue'],
    room: ['building', 'orange']
};

const routes = {
    maintenance: (id) => ['/maintenance', {}],
    payment: () => ['/payments', {}],
    contract: (id) => [`/contracts/${id}`, {}],
    room: (id) => [`/rooms/${id}`, {}]
};

const defaults = {unread: '', type: '', page: 1};

export class Notifications extends Module {
    constructor(options) {
        super(options);
        this.state = {...defaults};
    }

    async render({query = {}} = {}) {
        this.state = {...defaults, ...query, page: Number(query.page) || 1};

        const content = this.content();
        content.replaceChildren();

        const page = document.createElement('div');
        page.className = 'page';

        page.append(pageHeader({
            title: 'การแจ้งเตือน',
            subtitle: 'เหตุการณ์ล่าสุดในหอพักของคุณ',
            actions: [button({label: 'อ่านทั้งหมดแล้ว', iconName: 'checkCircle', variant: 'secondary', onClick: () => this.markAllRead()})]
        }));

        page.append(toolbar(
            selectFilter({
                label: 'แสดง',
                value: this.state.unread,
                options: [{value: '', label: 'ทั้งหมด'}, {value: '1', label: 'ยังไม่อ่าน'}],
                onChange: (value) => this.applyFilter({unread: value})
            }),
            selectFilter({
                label: 'ประเภท',
                value: this.state.type,
                options: [
                    {value: '', label: 'ทุกประเภท'},
                    {value: 'maintenance', label: 'แจ้งซ่อม'},
                    {value: 'payment', label: 'การชำระเงิน'},
                    {value: 'contract', label: 'สัญญาเช่า'},
                    {value: 'room', label: 'ห้องพัก'}
                ],
                onChange: (value) => this.applyFilter({type: value})
            })
        ));

        this.listElement = document.createElement('div');
        this.listElement.className = 'card';
        page.append(this.listElement);
        content.append(page);

        await this.load();
    }

    applyFilter(partial) {
        this.state = {...this.state, ...partial, page: partial.page || 1};
        this.router.replaceQuery(this.queryFrom(this.state, defaults));
        this.load();
    }

    async load() {
        const signal = this.signal();
        this.listElement.replaceChildren(loadingState());

        try {
            const query = new URLSearchParams({
                unread: this.state.unread,
                type: this.state.type,
                page: String(this.state.page),
                per_page: '15'
            });

            const response = await this.api.get(`/notifications?${query}`, {signal});
            this.shell.setNotificationCount(response.data.unread_count);
            this.renderList(response.data.items, response.pagination);
        } catch (error) {
            if (this.isAbort(error)) {
                return;
            }

            this.listElement.replaceChildren(errorState(error.message, () => this.load()));
        }
    }

    renderList(items, pagination) {
        this.listElement.replaceChildren();

        if (!items.length) {
            this.listElement.append(emptyState({
                iconName: 'bell',
                title: this.state.unread ? 'อ่านครบทุกรายการแล้ว' : 'ยังไม่มีการแจ้งเตือน'
            }));

            return;
        }

        const list = document.createElement('div');
        list.className = 'notification-list notification-page-list';

        for (const item of items) {
            const row = document.createElement('article');
            row.className = `notification-item ${item.is_read ? 'is-read' : ''}`;

            const [iconName, color] = notificationIcons[item.type] || ['bell', 'gray'];
            const iconBox = document.createElement('span');
            iconBox.className = `notification-type notification-${color}`;
            iconBox.innerHTML = icon(iconName);

            const body = document.createElement('div');
            body.className = 'notification-content';

            const title = document.createElement('strong');
            title.textContent = item.title;

            const description = document.createElement('p');
            description.textContent = item.description || '';

            const actions = document.createElement('div');
            actions.className = 'notification-actions';

            if (item.related_type && routes[item.related_type]) {
                actions.append(button({
                    label: 'เปิดดู',
                    variant: 'link',
                    size: 'sm',
                    onClick: async () => {
                        if (!item.is_read) {
                            await this.markRead(item, false);
                        }

                        const [path, query] = routes[item.related_type](item.related_id);
                        this.router.navigate(path, false, query);
                    }
                }));
            }

            if (!item.is_read) {
                actions.append(button({label: 'ทำเครื่องหมายว่าอ่านแล้ว', variant: 'link', size: 'sm', onClick: () => this.markRead(item)}));
            }

            body.append(title, description, actions);

            const time = document.createElement('time');
            time.dateTime = item.created_at;
            time.textContent = formatRelative(item.created_at);

            row.append(iconBox, body, time);
            list.append(row);
        }

        this.listElement.append(list, createPagination({
            ...pagination,
            perPage: pagination.per_page,
            totalPages: pagination.total_pages,
            onChange: (page) => this.applyFilter({page})
        }));
    }

    async markRead(item, reload = true) {
        try {
            const response = await this.api.put(`/notifications/${item.id}/read`);
            this.shell.setNotificationCount(response.data.unread_count);
            this.shell.notificationsLoaded = false;

            if (reload) {
                this.load();
            }
        } catch (error) {
            this.toast.error(error.message);
        }
    }

    async markAllRead() {
        try {
            await this.api.put('/notifications/read-all');
            this.shell.setNotificationCount(0);
            this.shell.notificationsLoaded = false;
            this.toast.success('ทำเครื่องหมายว่าอ่านแล้วทั้งหมด');
            this.load();
        } catch (error) {
            this.toast.error(error.message);
        }
    }
}
