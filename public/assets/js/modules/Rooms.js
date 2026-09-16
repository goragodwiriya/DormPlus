import {asset} from '../core/Paths.js';
import {Module} from './Module.js';
import {statusBadge} from '../ui/Badge.js';
import {avatar} from '../ui/Avatar.js';
import {confirmDialog} from '../ui/ConfirmDialog.js';
import {
    button, card, detailList, iconButton, pageHeader,
    searchBox, selectFilter, statTile, toolbar
} from '../ui/Controls.js';
import {createForm} from '../ui/Form.js';
import {formatCurrency, formatDate, formatDateTime, formatNumber} from '../ui/Formatters.js';
import * as labels from '../ui/Labels.js';
import {Modal} from '../ui/Modal.js';
import {createPagination} from '../ui/Pagination.js';
import {emptyState, errorState, loadingState} from '../ui/States.js';
import {createTable, stacked} from '../ui/Table.js';

const defaults = {q: '', status: '', floor: '', sort: 'room_number', page: 1};

export class Rooms extends Module {
    constructor(options) {
        super(options);
        this.state = {...defaults};
        this.floors = [];
    }

    async render({params = {}, query = {}} = {}) {
        if (params.id) {
            await this.renderDetail(Number(params.id));
            return;
        }

        this.state = {
            ...defaults,
            ...query,
            page: Number(query.page) || 1
        };

        await this.renderList();

        if (query.new === '1') {
            this.router.replaceQuery(this.queryFrom(this.state, defaults));
            this.openForm();
        }
    }

    /* ------------------------------------------------------------------ */
    /* รายการห้อง                                                          */
    /* ------------------------------------------------------------------ */

    async renderList() {
        const content = this.content();
        content.replaceChildren();

        const page = document.createElement('div');
        page.className = 'page';

        page.append(pageHeader({
            title: 'ห้องพัก',
            subtitle: 'จัดการข้อมูลและสถานะห้องพักทั้งหมด',
            actions: [
                button({
                    label: 'เพิ่มห้องพัก',
                    iconName: 'plus',
                    onClick: () => this.openForm()
                })
            ]
        }));

        this.tiles = document.createElement('div');
        this.tiles.className = 'stat-tiles';
        page.append(this.tiles);

        this.toolbarElement = document.createElement('div');
        page.append(this.toolbarElement);

        this.listElement = document.createElement('div');
        this.listElement.className = 'card';
        page.append(this.listElement);

        content.append(page);

        this.renderToolbar();
        await Promise.all([this.loadSummary(), this.loadRooms()]);
    }

    renderToolbar() {
        const floorOptions = [{value: '', label: 'ทุกชั้น'}].concat(
            this.floors.map((floor) => ({value: String(floor), label: `ชั้น ${floor}`}))
        );

        this.toolbarElement.replaceChildren(toolbar(
            searchBox({
                placeholder: 'ค้นหาเลขห้องหรือชื่อผู้เช่า',
                value: this.state.q,
                onSearch: (value) => this.applyFilter({q: value})
            }),
            selectFilter({
                label: 'สถานะ',
                value: this.state.status,
                options: labels.toOptions(labels.roomStatus, 'ทุกสถานะ'),
                onChange: (value) => this.applyFilter({status: value})
            }),
            selectFilter({
                label: 'ชั้น',
                value: this.state.floor,
                options: floorOptions,
                onChange: (value) => this.applyFilter({floor: value})
            }),
            selectFilter({
                label: 'เรียงตาม',
                value: this.state.sort,
                options: [
                    {value: 'room_number', label: 'เลขห้อง'},
                    {value: 'latest', label: 'อัปเดตล่าสุด'},
                    {value: 'rent_asc', label: 'ค่าเช่าน้อย → มาก'},
                    {value: 'rent_desc', label: 'ค่าเช่ามาก → น้อย'},
                    {value: 'status', label: 'สถานะ'}
                ],
                onChange: (value) => this.applyFilter({sort: value})
            })
        ));
    }

    applyFilter(partial) {
        this.state = {...this.state, ...partial, page: partial.page || 1};
        this.router.replaceQuery(this.queryFrom(this.state, defaults));
        this.loadRooms();
    }

    async loadSummary() {
        try {
            const [status, options] = await Promise.all([
                this.api.get('/dashboard/room-status'),
                this.api.get('/rooms/options')
            ]);

            const data = status.data;

            this.tiles.replaceChildren(
                statTile({label: 'ห้องทั้งหมด', value: `${data.total} ห้อง`, variant: 'green', iconName: 'home'}),
                statTile({label: 'มีผู้เช่า', value: `${data.occupied} ห้อง`, meta: `${data.occupied_percentage}%`, variant: 'green', iconName: 'users'}),
                statTile({label: 'ว่าง', value: `${data.available} ห้อง`, meta: `${data.available_percentage}%`, variant: 'blue', iconName: 'doorOpen'}),
                statTile({label: 'ซ่อมบำรุง / จอง', value: `${data.maintenance + data.reserved} ห้อง`, variant: 'orange', iconName: 'wrench'})
            );

            this.floors = options.data.floors;
            this.renderToolbar();
        } catch (error) {
            if (!this.isAbort(error)) {
                this.tiles.replaceChildren();
            }
        }
    }

    async loadRooms() {
        const signal = this.signal();
        this.listElement.replaceChildren(loadingState());

        try {
            const query = new URLSearchParams({
                q: this.state.q,
                status: this.state.status,
                floor: this.state.floor,
                sort: this.state.sort,
                page: String(this.state.page),
                per_page: '12'
            });

            const response = await this.api.get(`/rooms?${query}`, {signal});
            this.renderRooms(response.data, response.pagination);
        } catch (error) {
            if (this.isAbort(error)) {
                return;
            }

            this.listElement.replaceChildren(errorState(error.message, () => this.loadRooms()));
        }
    }

    renderRooms(rooms, pagination) {
        this.listElement.replaceChildren();

        if (!rooms.length) {
            const filtered = this.state.q || this.state.status || this.state.floor;

            this.listElement.append(emptyState({
                iconName: 'building',
                title: filtered ? 'ไม่พบห้องที่ตรงกับเงื่อนไข' : 'ยังไม่มีข้อมูลห้องพัก',
                message: filtered ? 'ลองเปลี่ยนคำค้นหาหรือตัวกรอง' : 'เริ่มต้นด้วยการเพิ่มห้องพักห้องแรกของคุณ',
                actionLabel: filtered ? '' : 'เพิ่มห้องพัก',
                onAction: () => this.openForm()
            }));

            return;
        }

        const table = createTable({
            rows: rooms,
            onRowClick: (room) => this.router.navigate(`/rooms/${room.id}`),
            columns: [
                {
                    key: 'room_number',
                    label: 'ห้อง',
                    render: (room) => {
                        const box = document.createElement('div');
                        box.className = 'cell-room';

                        const image = document.createElement('img');
                        image.className = 'room-thumbnail room-thumbnail-sm';
                        image.src = asset(room.image || 'assets/images/room-placeholder.svg');
                        image.alt = '';
                        image.loading = 'lazy';

                        box.append(image, stacked(
                            `ห้อง ${room.room_number}`,
                            labels.roomType[room.type] || room.type
                        ));

                        return box;
                    }
                },
                {key: 'floor', label: 'ชั้น', render: (room) => `ชั้น ${room.floor}`},
                {
                    key: 'monthly_rent',
                    label: 'ค่าเช่า/เดือน',
                    align: 'right',
                    render: (room) => formatCurrency(room.monthly_rent)
                },
                {
                    key: 'tenant_name',
                    label: 'ผู้เช่า',
                    render: (room) => room.tenant_name
                        ? stacked(room.tenant_name, `หมดสัญญา ${formatDate(room.contract_end_date)}`)
                        : stacked('-', 'ยังไม่มีผู้เช่า')
                },
                {
                    key: 'status',
                    label: 'สถานะ',
                    render: (room) => statusBadge(labels.roomStatus, room.status)
                },
                {
                    key: 'actions',
                    label: 'จัดการ',
                    align: 'right',
                    className: 'cell-actions',
                    render: (room) => [
                        iconButton({iconName: 'eye', label: 'ดูรายละเอียด', onClick: () => this.router.navigate(`/rooms/${room.id}`)}),
                        iconButton({iconName: 'edit', label: 'แก้ไข', onClick: () => this.openForm(room)}),
                        iconButton({iconName: 'trash', label: 'ลบ', variant: 'danger', onClick: () => this.deleteRoom(room)})
                    ]
                }
            ]
        });

        this.listElement.append(table, createPagination({
            ...pagination,
            perPage: pagination.per_page,
            totalPages: pagination.total_pages,
            onChange: (page) => this.applyFilter({page})
        }));
    }

    /* ------------------------------------------------------------------ */
    /* ฟอร์มเพิ่ม/แก้ไข                                                     */
    /* ------------------------------------------------------------------ */

    openForm(room = null, onSaved = null) {
        const editing = room !== null;
        const modal = new Modal({
            title: editing ? `แก้ไขห้อง ${room.room_number}` : 'เพิ่มห้องพัก',
            size: 'md'
        });

        const form = createForm({
            values: room || {status: 'available', type: 'standard', floor: 1},
            onCancel: () => modal.close(),
            fields: [
                {name: 'room_number', label: 'เลขห้อง', required: true, placeholder: 'เช่น 101', maxLength: 20},
                {name: 'floor', label: 'ชั้น', type: 'number', required: true, min: 0, step: 1},
                {name: 'type', label: 'ประเภทห้อง', type: 'select', options: labels.toOptions(labels.roomType)},
                {name: 'area', label: 'พื้นที่ (ตร.ม.)', type: 'number', min: 0},
                {name: 'monthly_rent', label: 'ค่าเช่ารายเดือน (บาท)', type: 'number', required: true, min: 0},
                {
                    name: 'status',
                    label: 'สถานะ',
                    type: 'select',
                    options: labels.toOptions(labels.roomStatus),
                    help: editing ? '' : 'ห้องจะเป็น "มีผู้เช่า" อัตโนมัติเมื่อสร้างสัญญาเช่า'
                },
                {name: 'description', label: 'รายละเอียด', type: 'textarea', span: 2, rows: 3}
            ],
            onSubmit: async (values) => {
                const response = editing
                    ? await this.api.put(`/rooms/${room.id}`, values)
                    : await this.api.post('/rooms', values);

                modal.close();
                this.toast.success(editing ? 'บันทึกข้อมูลห้องเรียบร้อยแล้ว' : `เพิ่มห้อง ${values.room_number} เรียบร้อยแล้ว`);

                if (onSaved) {
                    onSaved(response.data);
                } else {
                    this.loadSummary();
                    this.loadRooms();
                }
            }
        });

        modal.body.append(form.element);
        modal.open();
    }

    async deleteRoom(room, afterDelete = null) {
        const confirmed = await confirmDialog({
            title: `ลบห้อง ${room.room_number}`,
            message: `คุณต้องการลบห้อง ${room.room_number} หรือไม่? การลบไม่สามารถย้อนกลับได้`,
            confirmLabel: 'ลบข้อมูล',
            danger: true
        });

        if (!confirmed) {
            return;
        }

        try {
            await this.api.delete(`/rooms/${room.id}`);
            this.toast.success('ลบข้อมูลเรียบร้อยแล้ว');

            if (afterDelete) {
                afterDelete();
            } else {
                this.loadSummary();
                this.loadRooms();
            }
        } catch (error) {
            this.toast.error(error.message);
        }
    }

    /* ------------------------------------------------------------------ */
    /* รายละเอียดห้อง                                                       */
    /* ------------------------------------------------------------------ */

    async renderDetail(id) {
        const content = this.content();
        content.replaceChildren(loadingState());

        const signal = this.signal();

        try {
            const response = await this.api.get(`/rooms/${id}`, {signal});
            this.renderDetailPage(response.data);
        } catch (error) {
            if (this.isAbort(error)) {
                return;
            }

            content.replaceChildren(
                pageHeader({title: 'ห้องพัก', backTo: {path: '/rooms', label: 'กลับไปหน้าห้องพัก'}}),
                errorState(error.message, () => this.renderDetail(id))
            );
        }
    }

    renderDetailPage({room, contracts, payments, maintenance}) {
        const content = this.content();
        content.replaceChildren();

        const page = document.createElement('div');
        page.className = 'page';

        const title = document.createElement('span');
        title.className = 'title-with-badge';
        title.append(document.createTextNode(`ห้อง ${room.room_number}`), statusBadge(labels.roomStatus, room.status));

        const actions = [
            button({label: 'แก้ไข', iconName: 'edit', variant: 'secondary', onClick: () => this.openForm(room, () => this.renderDetail(room.id))}),
            button({label: 'ลบ', iconName: 'trash', variant: 'danger-outline', onClick: () => this.deleteRoom(room, () => this.router.navigate('/rooms'))})
        ];

        if (!room.contract_id) {
            actions.unshift(button({
                label: 'สร้างสัญญาเช่า',
                iconName: 'contract',
                onClick: () => this.router.navigate('/contracts', false, {new: '1', room_id: room.id})
            }));
        }

        page.append(pageHeader({
            title,
            subtitle: `ชั้น ${room.floor} · ${labels.roomType[room.type] || room.type} · ${formatCurrency(room.monthly_rent)}/เดือน`,
            backTo: {path: '/rooms', label: 'กลับไปหน้าห้องพัก'},
            actions
        }));

        const grid = document.createElement('div');
        grid.className = 'detail-grid';

        // ข้อมูลห้อง
        const infoCard = card({title: 'ข้อมูลห้องพัก'});
        const image = document.createElement('img');
        image.className = 'detail-image';
        image.src = asset(room.image || 'assets/images/room-placeholder.svg');
        image.alt = `ห้อง ${room.room_number}`;

        infoCard.body.append(image, detailList([
            ['เลขห้อง', room.room_number],
            ['ชั้น', `ชั้น ${room.floor}`],
            ['ประเภท', labels.roomType[room.type] || room.type],
            ['พื้นที่', room.area ? `${formatNumber(room.area)} ตร.ม.` : '-'],
            ['ค่าเช่า', `${formatCurrency(room.monthly_rent)}/เดือน`],
            ['สถานะ', statusBadge(labels.roomStatus, room.status)],
            ['รายละเอียด', room.description || '-'],
            ['อัปเดตล่าสุด', formatDateTime(room.updated_at)]
        ]));

        // เปลี่ยนสถานะ
        const statusForm = createForm({
            inline: true,
            submitLabel: 'เปลี่ยนสถานะ',
            columns: 1,
            values: {status: room.status},
            fields: [{
                name: 'status',
                label: 'เปลี่ยนสถานะห้อง',
                type: 'select',
                options: labels.toOptions(labels.roomStatus)
            }],
            onSubmit: async (values) => {
                await this.api.put(`/rooms/${room.id}/status`, values);
                this.toast.success('เปลี่ยนสถานะห้องเรียบร้อยแล้ว');
                this.renderDetail(room.id);
            }
        });
        infoCard.body.append(statusForm.element);

        // ผู้เช่าปัจจุบัน
        const tenantCard = card({title: 'ผู้เช่าปัจจุบัน'});

        if (room.tenant_id) {
            const person = document.createElement('div');
            person.className = 'person-row';
            person.append(
                avatar({name: room.first_name, gender: room.tenant_gender, size: 'lg'}),
                stacked(`${room.first_name} ${room.last_name}`, room.tenant_phone || '')
            );

            tenantCard.body.append(person, detailList([
                ['เริ่มสัญญา', formatDate(room.contract_start_date)],
                ['สิ้นสุดสัญญา', formatDate(room.contract_end_date)],
                ['ค่าเช่าตามสัญญา', `${formatCurrency(room.contract_rent)}/เดือน`],
                ['เงินประกัน', formatCurrency(room.contract_deposit)]
            ]));

            const links = document.createElement('div');
            links.className = 'button-row';
            links.append(
                button({label: 'ดูข้อมูลผู้เช่า', iconName: 'user', variant: 'secondary', size: 'sm', onClick: () => this.router.navigate(`/tenants/${room.tenant_id}`)}),
                button({label: 'ดูสัญญา', iconName: 'contract', variant: 'secondary', size: 'sm', onClick: () => this.router.navigate(`/contracts/${room.contract_id}`)}),
                button({label: 'บันทึกค่าเช่า', iconName: 'receipt', size: 'sm', onClick: () => this.router.navigate('/payments', false, {new: '1', tenant_id: room.tenant_id})})
            );
            tenantCard.body.append(links);
        } else {
            tenantCard.body.append(emptyState({
                iconName: 'users',
                title: 'ห้องนี้ยังไม่มีผู้เช่า',
                message: 'สร้างสัญญาเช่าเพื่อจัดผู้เช่าเข้าห้องนี้',
                actionLabel: 'สร้างสัญญาเช่า',
                onAction: () => this.router.navigate('/contracts', false, {new: '1', room_id: room.id})
            }));
        }

        grid.append(infoCard, tenantCard);
        page.append(grid);

        // ประวัติ
        page.append(card({
            title: 'ประวัติสัญญาเช่า',
            content: createTable({
                compact: true,
                rows: contracts,
                emptyMessage: 'ยังไม่มีประวัติสัญญาเช่า',
                onRowClick: (contract) => this.router.navigate(`/contracts/${contract.id}`),
                columns: [
                    {key: 'tenant', label: 'ผู้เช่า', render: (row) => `${row.first_name} ${row.last_name}`},
                    {key: 'start_date', label: 'เริ่ม', render: (row) => formatDate(row.start_date)},
                    {key: 'end_date', label: 'สิ้นสุด', render: (row) => formatDate(row.end_date)},
                    {key: 'monthly_rent', label: 'ค่าเช่า', align: 'right', render: (row) => formatCurrency(row.monthly_rent)},
                    {key: 'status', label: 'สถานะ', render: (row) => statusBadge(labels.contractStatus, row.status)}
                ]
            })
        }));

        const historyGrid = document.createElement('div');
        historyGrid.className = 'detail-grid';

        historyGrid.append(
            card({
                title: 'การชำระเงินล่าสุด',
                actions: [button({label: 'ดูทั้งหมด', variant: 'link', onClick: () => this.router.navigate('/payments', false, {room_id: room.id})})],
                content: createTable({
                    compact: true,
                    rows: payments,
                    emptyMessage: 'ยังไม่มีการชำระเงิน',
                    columns: [
                        {key: 'period', label: 'งวด', render: (row) => `${labels.paymentType[row.payment_type] || row.payment_type} ${row.period_month}/${row.period_year + 543}`},
                        {key: 'amount', label: 'จำนวน', align: 'right', render: (row) => formatCurrency(row.amount)},
                        {key: 'payment_date', label: 'วันที่ชำระ', render: (row) => formatDate(row.payment_date)},
                        {key: 'status', label: 'สถานะ', render: (row) => statusBadge(labels.paymentStatus, row.status)}
                    ]
                })
            }),
            card({
                title: 'งานซ่อม',
                actions: [button({label: 'แจ้งซ่อม', variant: 'link', onClick: () => this.router.navigate('/maintenance', false, {new: '1', room_id: room.id})})],
                content: createTable({
                    compact: true,
                    rows: maintenance,
                    emptyMessage: 'ยังไม่มีงานซ่อม',
                    columns: [
                        {key: 'title', label: 'รายการ'},
                        {key: 'priority', label: 'ความสำคัญ', render: (row) => statusBadge(labels.maintenancePriority, row.priority)},
                        {key: 'reported_at', label: 'แจ้งเมื่อ', render: (row) => formatDate(row.reported_at)},
                        {key: 'status', label: 'สถานะ', render: (row) => statusBadge(labels.maintenanceStatus, row.status)}
                    ]
                })
            })
        );

        page.append(historyGrid);
        content.append(page);
    }
}
