import {Module} from './Module.js';
import {statusBadge} from '../ui/Badge.js';
import {confirmDialog} from '../ui/ConfirmDialog.js';
import {
    button, detailList, iconButton, pageHeader,
    searchBox, selectFilter, statTile, toolbar
} from '../ui/Controls.js';
import {createForm} from '../ui/Form.js';
import {formatCurrency, formatDateTime, formatRelative} from '../ui/Formatters.js';
import * as labels from '../ui/Labels.js';
import {Modal} from '../ui/Modal.js';
import {createPagination} from '../ui/Pagination.js';
import {emptyState, errorState, loadingState} from '../ui/States.js';
import {createTable, stacked} from '../ui/Table.js';

const defaults = {q: '', status: '', priority: '', sort: 'latest', page: 1};

export class Maintenance extends Module {
    constructor(options) {
        super(options);
        this.state = {...defaults};
    }

    async render({params = {}, query = {}} = {}) {
        this.state = {...defaults, ...query, page: Number(query.page) || 1};
        await this.renderList();

        if (query.new === '1') {
            this.router.replaceQuery(this.queryFrom(this.state, defaults));
            this.openForm({roomId: query.room_id ? Number(query.room_id) : null});
        } else if (params.id) {
            this.openDetail(Number(params.id));
        }
    }

    async renderList() {
        const content = this.content();
        content.replaceChildren();

        const page = document.createElement('div');
        page.className = 'page';

        page.append(pageHeader({
            title: 'แจ้งซ่อม / บำรุงรักษา',
            subtitle: 'ติดตามงานซ่อม มอบหมายช่าง และบันทึกค่าใช้จ่าย',
            actions: [button({label: 'แจ้งซ่อม', iconName: 'plus', onClick: () => this.openForm()})]
        }));

        this.tiles = document.createElement('div');
        this.tiles.className = 'stat-tiles';
        page.append(this.tiles);

        page.append(toolbar(
            searchBox({
                placeholder: 'ค้นหาหัวข้อ เลขห้อง หรือชื่อช่าง',
                value: this.state.q,
                onSearch: (value) => this.applyFilter({q: value})
            }),
            selectFilter({
                label: 'สถานะ',
                value: this.state.status,
                options: labels.toOptions(labels.maintenanceStatus, 'ทุกสถานะ'),
                onChange: (value) => this.applyFilter({status: value})
            }),
            selectFilter({
                label: 'ความสำคัญ',
                value: this.state.priority,
                options: labels.toOptions(labels.maintenancePriority, 'ทุกระดับ'),
                onChange: (value) => this.applyFilter({priority: value})
            }),
            selectFilter({
                label: 'เรียงตาม',
                value: this.state.sort,
                options: [
                    {value: 'latest', label: 'แจ้งล่าสุด'},
                    {value: 'priority', label: 'ความสำคัญ'},
                    {value: 'oldest', label: 'ค้างนานที่สุด'}
                ],
                onChange: (value) => this.applyFilter({sort: value})
            })
        ));

        this.listElement = document.createElement('div');
        this.listElement.className = 'card';
        page.append(this.listElement);
        content.append(page);

        await this.loadRequests();
    }

    applyFilter(partial) {
        this.state = {...this.state, ...partial, page: partial.page || 1};
        this.router.replaceQuery(this.queryFrom(this.state, defaults));
        this.loadRequests();
    }

    async loadRequests() {
        const signal = this.signal();
        this.listElement.replaceChildren(loadingState());

        try {
            const query = new URLSearchParams({
                q: this.state.q,
                status: this.state.status,
                priority: this.state.priority,
                sort: this.state.sort,
                page: String(this.state.page),
                per_page: '12'
            });

            const response = await this.api.get(`/maintenance?${query}`, {signal});
            this.renderTiles(response.data.status_counts);
            this.renderRequests(response.data.items, response.pagination);
        } catch (error) {
            if (this.isAbort(error)) {
                return;
            }

            this.listElement.replaceChildren(errorState(error.message, () => this.loadRequests()));
        }
    }

    renderTiles(counts) {
        this.tiles.replaceChildren(
            statTile({label: 'รอรับงาน', value: `${counts.pending} รายการ`, variant: 'orange', iconName: 'clock'}),
            statTile({label: 'กำลังดำเนินการ', value: `${counts.in_progress} รายการ`, variant: 'blue', iconName: 'wrench'}),
            statTile({label: 'เสร็จสิ้น', value: `${counts.completed} รายการ`, variant: 'green', iconName: 'checkCircle'}),
            statTile({label: 'ยกเลิก', value: `${counts.cancelled} รายการ`, variant: 'gray', iconName: 'xCircle'})
        );
    }

    renderRequests(requests, pagination) {
        this.listElement.replaceChildren();

        if (!requests.length) {
            const filtered = this.state.q || this.state.status || this.state.priority;

            this.listElement.append(emptyState({
                iconName: 'wrench',
                title: filtered ? 'ไม่พบงานซ่อมที่ตรงกับเงื่อนไข' : 'ยังไม่มีรายการแจ้งซ่อม',
                message: filtered ? 'ลองเปลี่ยนคำค้นหาหรือตัวกรอง' : 'เมื่อมีการแจ้งซ่อม รายการจะแสดงที่นี่',
                actionLabel: filtered ? '' : 'แจ้งซ่อม',
                onAction: () => this.openForm()
            }));

            return;
        }

        this.listElement.append(createTable({
            rows: requests,
            onRowClick: (request) => this.openDetail(request.id, request),
            columns: [
                {key: 'title', label: 'รายการ', render: (row) => stacked(row.title, row.description)},
                {key: 'room_number', label: 'ห้อง', render: (row) => stacked(`ห้อง ${row.room_number}`, row.tenant_name || '')},
                {key: 'priority', label: 'ความสำคัญ', render: (row) => statusBadge(labels.maintenancePriority, row.priority)},
                {key: 'assigned_to', label: 'ผู้รับผิดชอบ', render: (row) => row.assigned_to || '-'},
                {key: 'reported_at', label: 'แจ้งเมื่อ', render: (row) => stacked(formatRelative(row.reported_at), formatDateTime(row.reported_at))},
                {key: 'status', label: 'สถานะ', render: (row) => statusBadge(labels.maintenanceStatus, row.status)},
                {
                    key: 'actions',
                    label: 'จัดการ',
                    align: 'right',
                    className: 'cell-actions',
                    render: (row) => [
                        iconButton({iconName: 'eye', label: 'ดูรายละเอียด', onClick: () => this.openDetail(row.id, row)}),
                        iconButton({iconName: 'edit', label: 'แก้ไข', onClick: () => this.openForm({request: row})}),
                        iconButton({iconName: 'trash', label: 'ลบ', variant: 'danger', onClick: () => this.deleteRequest(row)})
                    ]
                }
            ]
        }), createPagination({
            ...pagination,
            perPage: pagination.per_page,
            totalPages: pagination.total_pages,
            onChange: (page) => this.applyFilter({page})
        }));
    }

    /* รายละเอียด + อัปเดตสถานะ ------------------------------------------- */

    async openDetail(id, preloaded = null) {
        const modal = new Modal({title: 'รายละเอียดงานซ่อม', size: 'lg'});
        modal.body.append(loadingState());
        modal.open();

        let request = preloaded;

        try {
            request = (await this.api.get(`/maintenance/${id}`)).data;
        } catch (error) {
            if (!request) {
                modal.body.replaceChildren(errorState(error.message));
                return;
            }
        }

        modal.setTitle(request.title);

        const layout = document.createElement('div');
        layout.className = 'detail-grid';

        const info = document.createElement('div');
        info.append(detailList([
            ['ห้อง', `ห้อง ${request.room_number} (ชั้น ${request.floor})`],
            ['ผู้แจ้ง', request.tenant_name || 'เจ้าหน้าที่'],
            ['รายละเอียด', request.description],
            ['ความสำคัญ', statusBadge(labels.maintenancePriority, request.priority)],
            ['สถานะ', statusBadge(labels.maintenanceStatus, request.status)],
            ['แจ้งเมื่อ', formatDateTime(request.reported_at)],
            ['เริ่มดำเนินการ', request.started_at ? formatDateTime(request.started_at) : '-'],
            ['เสร็จสิ้น', request.completed_at ? formatDateTime(request.completed_at) : '-'],
            ['ค่าใช้จ่าย', formatCurrency(request.cost)],
            ['บันทึกเพิ่มเติม', request.notes || '-']
        ]));

        const update = document.createElement('div');
        const heading = document.createElement('h3');
        heading.className = 'section-title';
        heading.textContent = 'อัปเดตงาน';
        update.append(heading);

        const form = createForm({
            columns: 1,
            submitLabel: 'บันทึกการอัปเดต',
            values: {
                status: request.status,
                priority: request.priority,
                assigned_to: request.assigned_to || '',
                cost: request.cost,
                notes: request.notes || ''
            },
            fields: [
                {name: 'status', label: 'สถานะ', type: 'select', options: labels.toOptions(labels.maintenanceStatus)},
                {name: 'priority', label: 'ความสำคัญ', type: 'select', options: labels.toOptions(labels.maintenancePriority)},
                {name: 'assigned_to', label: 'มอบหมายช่าง / ผู้รับผิดชอบ', placeholder: 'เช่น สมชาย ช่างดี'},
                {name: 'cost', label: 'ค่าใช้จ่าย (บาท)', type: 'number', min: 0},
                {name: 'notes', label: 'บันทึกเพิ่มเติม', type: 'textarea', rows: 3}
            ],
            onSubmit: async (values) => {
                await this.api.put(`/maintenance/${request.id}/status`, values);
                modal.close();
                this.toast.success('อัปเดตงานซ่อมเรียบร้อยแล้ว');
                this.loadRequests();
            }
        });

        update.append(form.element);
        layout.append(info, update);
        modal.body.replaceChildren(layout);

        modal.setFooter(
            button({label: 'แก้ไขรายละเอียด', iconName: 'edit', variant: 'secondary', onClick: () => {
                modal.close();
                this.openForm({request});
            }}),
            button({label: 'ลบ', iconName: 'trash', variant: 'danger-outline', onClick: () => {
                modal.close();
                this.deleteRequest(request);
            }})
        );
    }

    /* ฟอร์มแจ้งซ่อม ----------------------------------------------------------- */

    async openForm({request = null, roomId = null} = {}) {
        const editing = request !== null;
        const modal = new Modal({title: editing ? 'แก้ไขรายการแจ้งซ่อม' : 'แจ้งซ่อม', size: 'md'});
        modal.body.append(loadingState('กำลังโหลดรายการห้อง...'));
        modal.open();

        let rooms = [];

        try {
            rooms = (await this.api.get('/rooms/options')).data.rooms;
        } catch (error) {
            modal.body.replaceChildren(errorState(error.message));
            return;
        }

        const roomOptions = [{value: '', label: '— เลือกห้อง —'}].concat(
            rooms.map((room) => ({value: String(room.id), label: `ห้อง ${room.room_number} · ชั้น ${room.floor}`}))
        );

        const form = createForm({
            values: editing
                ? {...request, room_id: String(request.room_id)}
                : {room_id: roomId ? String(roomId) : '', priority: 'normal'},
            submitLabel: editing ? 'บันทึก' : 'ส่งแจ้งซ่อม',
            onCancel: () => modal.close(),
            fields: [
                {name: 'room_id', label: 'ห้อง', type: 'select', required: true, options: roomOptions},
                {name: 'priority', label: 'ความสำคัญ', type: 'select', options: labels.toOptions(labels.maintenancePriority)},
                {name: 'title', label: 'หัวข้อ', required: true, span: 2, maxLength: 120, placeholder: 'เช่น น้ำรั่วในห้องน้ำ'},
                {name: 'description', label: 'รายละเอียด', type: 'textarea', required: true, span: 2, rows: 3},
                {name: 'assigned_to', label: 'มอบหมายให้', span: 2, placeholder: 'ไม่บังคับ'}
            ],
            onSubmit: async (values) => {
                const payload = {...values, room_id: Number(values.room_id)};

                if (editing) {
                    await this.api.put(`/maintenance/${request.id}`, {...request, ...payload});
                } else {
                    await this.api.post('/maintenance', payload);
                }

                modal.close();
                this.toast.success(editing ? 'บันทึกข้อมูลเรียบร้อยแล้ว' : 'ส่งแจ้งซ่อมเรียบร้อยแล้ว');
                this.shell.loadNotifications?.();
                this.loadRequests();
            }
        });

        modal.body.replaceChildren(form.element);
        form.field('room_id')?.focus();
    }

    async deleteRequest(request) {
        const confirmed = await confirmDialog({
            title: 'ลบรายการแจ้งซ่อม',
            message: `ลบรายการ "${request.title}" (ห้อง ${request.room_number}) หรือไม่?`,
            confirmLabel: 'ลบข้อมูล',
            danger: true
        });

        if (!confirmed) {
            return;
        }

        try {
            await this.api.delete(`/maintenance/${request.id}`);
            this.toast.success('ลบข้อมูลเรียบร้อยแล้ว');
            this.loadRequests();
        } catch (error) {
            this.toast.error(error.message);
        }
    }
}
