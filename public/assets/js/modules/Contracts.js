import {Module} from './Module.js';
import {badge, statusBadge} from '../ui/Badge.js';
import {confirmDialog} from '../ui/ConfirmDialog.js';
import {
    button, card, detailList, iconButton, pageHeader,
    searchBox, selectFilter, statTile, toolbar
} from '../ui/Controls.js';
import {createForm} from '../ui/Form.js';
import {formatCurrency, formatDate, formatDateTime, today} from '../ui/Formatters.js';
import * as labels from '../ui/Labels.js';
import {Modal} from '../ui/Modal.js';
import {createPagination} from '../ui/Pagination.js';
import {emptyState, errorState, loadingState} from '../ui/States.js';
import {createTable, stacked} from '../ui/Table.js';

const defaults = {q: '', status: '', expiring_within: '', sort: 'latest', page: 1};

function addYears(dateString, years) {
    const date = new Date(`${dateString}T00:00:00`);
    date.setFullYear(date.getFullYear() + years);
    date.setDate(date.getDate() - 1);

    const pad = (number) => String(number).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
}

function remainingLabel(contract) {
    if (contract.status !== 'active') {
        return '-';
    }

    const days = contract.days_remaining;

    if (days < 0) {
        return badge(`เลยกำหนด ${Math.abs(days)} วัน`, 'red');
    }

    if (days <= 30) {
        return badge(`เหลือ ${days} วัน`, 'orange');
    }

    return `เหลือ ${days} วัน`;
}

export class Contracts extends Module {
    constructor(options) {
        super(options);
        this.state = {...defaults};
    }

    async render({params = {}, query = {}} = {}) {
        if (params.id) {
            await this.renderDetail(Number(params.id));
            return;
        }

        this.state = {...defaults, ...query, page: Number(query.page) || 1};
        await this.renderList();

        if (query.new === '1') {
            this.router.replaceQuery(this.queryFrom(this.state, defaults));
            this.openForm({
                tenantId: query.tenant_id ? Number(query.tenant_id) : null,
                roomId: query.room_id ? Number(query.room_id) : null
            });
        }
    }

    /* รายการสัญญา ------------------------------------------------------- */

    async renderList() {
        const content = this.content();
        content.replaceChildren();

        const page = document.createElement('div');
        page.className = 'page';

        page.append(pageHeader({
            title: 'สัญญาเช่า',
            subtitle: 'จัดการสัญญา วันหมดอายุ และการต่อสัญญา',
            actions: [button({label: 'สร้างสัญญาเช่า', iconName: 'plus', onClick: () => this.openForm()})]
        }));

        this.tiles = document.createElement('div');
        this.tiles.className = 'stat-tiles';
        page.append(this.tiles);

        page.append(toolbar(
            searchBox({
                placeholder: 'ค้นหาเลขห้องหรือชื่อผู้เช่า',
                value: this.state.q,
                onSearch: (value) => this.applyFilter({q: value})
            }),
            selectFilter({
                label: 'สถานะ',
                value: this.state.status,
                options: labels.toOptions(labels.contractStatus, 'ทุกสถานะ'),
                onChange: (value) => this.applyFilter({status: value})
            }),
            selectFilter({
                label: 'ใกล้หมดอายุ',
                value: this.state.expiring_within,
                options: [
                    {value: '', label: 'ทั้งหมด'},
                    {value: '30', label: 'ภายใน 30 วัน'},
                    {value: '60', label: 'ภายใน 60 วัน'},
                    {value: '90', label: 'ภายใน 90 วัน'}
                ],
                onChange: (value) => this.applyFilter({expiring_within: value})
            }),
            selectFilter({
                label: 'เรียงตาม',
                value: this.state.sort,
                options: [
                    {value: 'latest', label: 'สร้างล่าสุด'},
                    {value: 'end_date', label: 'วันหมดอายุ'},
                    {value: 'room', label: 'เลขห้อง'}
                ],
                onChange: (value) => this.applyFilter({sort: value})
            })
        ));

        this.listElement = document.createElement('div');
        this.listElement.className = 'card';
        page.append(this.listElement);
        content.append(page);

        await this.loadContracts();
    }

    applyFilter(partial) {
        this.state = {...this.state, ...partial, page: partial.page || 1};
        this.router.replaceQuery(this.queryFrom(this.state, defaults));
        this.loadContracts();
    }

    async loadContracts() {
        const signal = this.signal();
        this.listElement.replaceChildren(loadingState());

        try {
            const query = new URLSearchParams({
                q: this.state.q,
                status: this.state.status,
                expiring_within: this.state.expiring_within,
                sort: this.state.sort,
                page: String(this.state.page),
                per_page: '12'
            });

            const response = await this.api.get(`/contracts?${query}`, {signal});
            this.renderTiles(response.data.status_counts);
            this.renderContracts(response.data.items, response.pagination);
        } catch (error) {
            if (this.isAbort(error)) {
                return;
            }

            this.listElement.replaceChildren(errorState(error.message, () => this.loadContracts()));
        }
    }

    renderTiles(counts) {
        this.tiles.replaceChildren(
            statTile({label: 'สัญญาที่ใช้งาน', value: `${counts.active || 0} ฉบับ`, variant: 'green', iconName: 'contract'}),
            statTile({label: 'รอเริ่มสัญญา', value: `${counts.pending || 0} ฉบับ`, variant: 'orange', iconName: 'clock'}),
            statTile({label: 'หมดอายุ', value: `${counts.expired || 0} ฉบับ`, variant: 'gray', iconName: 'calendarCheck'}),
            statTile({label: 'ยกเลิก', value: `${counts.terminated || 0} ฉบับ`, variant: 'pink', iconName: 'xCircle'})
        );
    }

    renderContracts(contracts, pagination) {
        this.listElement.replaceChildren();

        if (!contracts.length) {
            const filtered = this.state.q || this.state.status || this.state.expiring_within;

            this.listElement.append(emptyState({
                iconName: 'contract',
                title: filtered ? 'ไม่พบสัญญาที่ตรงกับเงื่อนไข' : 'ยังไม่มีสัญญาเช่า',
                message: filtered ? 'ลองเปลี่ยนคำค้นหาหรือตัวกรอง' : 'สร้างสัญญาเช่าเพื่อจัดผู้เช่าเข้าห้อง',
                actionLabel: filtered ? '' : 'สร้างสัญญาเช่า',
                onAction: () => this.openForm()
            }));

            return;
        }

        this.listElement.append(createTable({
            rows: contracts,
            onRowClick: (contract) => this.router.navigate(`/contracts/${contract.id}`),
            columns: [
                {key: 'room_number', label: 'ห้อง', render: (row) => stacked(`ห้อง ${row.room_number}`, `ชั้น ${row.floor}`)},
                {key: 'tenant_name', label: 'ผู้เช่า', render: (row) => stacked(row.tenant_name, row.tenant_phone || '')},
                {key: 'period', label: 'ระยะสัญญา', render: (row) => stacked(`${formatDate(row.start_date)} - ${formatDate(row.end_date)}`, remainingLabel(row))},
                {key: 'monthly_rent', label: 'ค่าเช่า', align: 'right', render: (row) => formatCurrency(row.monthly_rent)},
                {key: 'status', label: 'สถานะ', render: (row) => statusBadge(labels.contractStatus, row.status)},
                {
                    key: 'actions',
                    label: 'จัดการ',
                    align: 'right',
                    className: 'cell-actions',
                    render: (row) => {
                        const actions = [
                            iconButton({iconName: 'eye', label: 'ดูรายละเอียด', onClick: () => this.router.navigate(`/contracts/${row.id}`)})
                        ];

                        if (row.status === 'active') {
                            actions.push(
                                iconButton({iconName: 'refresh', label: 'ต่อสัญญา', onClick: () => this.openRenewForm(row)}),
                                iconButton({iconName: 'xCircle', label: 'สิ้นสุดสัญญา', variant: 'danger', onClick: () => this.endContract(row)})
                            );
                        }

                        return actions;
                    }
                }
            ]
        }), createPagination({
            ...pagination,
            perPage: pagination.per_page,
            totalPages: pagination.total_pages,
            onChange: (page) => this.applyFilter({page})
        }));
    }

    /* ฟอร์มสร้าง/แก้ไขสัญญา ---------------------------------------------- */

    async openForm({contract = null, tenantId = null, roomId = null, onSaved = null} = {}) {
        const editing = contract !== null;
        const modal = new Modal({
            title: editing ? `แก้ไขสัญญาห้อง ${contract.room_number}` : 'สร้างสัญญาเช่า',
            size: 'lg'
        });

        modal.body.append(loadingState('กำลังโหลดข้อมูลผู้เช่าและห้องพัก...'));
        modal.open();

        let tenants = [];
        let rooms = [];
        let settings = {};

        try {
            const [tenantResponse, roomResponse, settingsResponse] = await Promise.all([
                this.api.get('/tenants/options'),
                this.api.get('/rooms/options'),
                this.api.get('/settings')
            ]);

            tenants = tenantResponse.data;
            rooms = roomResponse.data.rooms;
            settings = settingsResponse.data.settings;
        } catch (error) {
            modal.body.replaceChildren(errorState(error.message));
            return;
        }

        const tenantOptions = [{value: '', label: '— เลือกผู้เช่า —'}].concat(
            tenants
                .filter((tenant) => editing
                    ? tenant.id === contract.tenant_id
                    : (!tenant.contract_id && tenant.status !== 'blacklisted') || tenant.id === tenantId)
                .map((tenant) => ({
                    value: String(tenant.id),
                    label: tenant.room_number ? `${tenant.name} (ห้อง ${tenant.room_number})` : tenant.name
                }))
        );

        const roomOptions = [{value: '', label: '— เลือกห้องพัก —'}].concat(
            rooms
                .filter((room) => editing
                    ? room.id === contract.room_id
                    : ['available', 'reserved'].includes(room.status) || room.id === roomId)
                .map((room) => ({
                    value: String(room.id),
                    label: `ห้อง ${room.room_number} · ชั้น ${room.floor} · ${formatCurrency(room.monthly_rent)}`
                }))
        );

        const start = today();
        const values = editing
            ? {...contract, tenant_id: String(contract.tenant_id), room_id: String(contract.room_id)}
            : {
                tenant_id: tenantId ? String(tenantId) : '',
                room_id: roomId ? String(roomId) : '',
                start_date: start,
                end_date: addYears(start, 1),
                monthly_rent: roomId ? (rooms.find((room) => room.id === roomId)?.monthly_rent ?? '') : '',
                deposit: roomId ? (rooms.find((room) => room.id === roomId)?.monthly_rent ?? '') : '',
                electricity_rate: settings.electricity_rate ?? 8,
                water_rate: settings.water_rate ?? 18,
                status: 'active'
            };

        const form = createForm({
            values,
            submitLabel: editing ? 'บันทึก' : 'สร้างสัญญา',
            onCancel: () => modal.close(),
            fields: [
                {name: 'tenant_id', label: 'ผู้เช่า', type: 'select', required: true, options: tenantOptions, disabled: editing},
                {
                    name: 'room_id',
                    label: 'ห้องพัก',
                    type: 'select',
                    required: true,
                    options: roomOptions,
                    disabled: editing,
                    onChange: (value, api) => {
                        const room = rooms.find((item) => String(item.id) === value);

                        if (room && !editing) {
                            api.setValues({monthly_rent: room.monthly_rent, deposit: room.monthly_rent});
                        }
                    }
                },
                {
                    name: 'start_date',
                    label: 'วันเริ่มสัญญา',
                    type: 'date',
                    required: true,
                    onChange: (value, api) => {
                        if (value && !editing) {
                            api.setValues({end_date: addYears(value, 1)});
                        }
                    }
                },
                {name: 'end_date', label: 'วันสิ้นสุดสัญญา', type: 'date', required: true},
                {name: 'monthly_rent', label: 'ค่าเช่ารายเดือน (บาท)', type: 'number', required: true, min: 0},
                {name: 'deposit', label: 'เงินประกัน (บาท)', type: 'number', min: 0},
                {name: 'electricity_rate', label: 'ค่าไฟ (บาท/หน่วย)', type: 'number', min: 0},
                {name: 'water_rate', label: 'ค่าน้ำ (บาท/หน่วย)', type: 'number', min: 0},
                {
                    name: 'status',
                    label: 'สถานะ',
                    type: 'select',
                    options: editing
                        ? labels.toOptions(labels.contractStatus)
                        : [{value: 'active', label: 'ใช้งานทันที'}, {value: 'pending', label: 'รอเริ่มสัญญา (จองห้อง)'}],
                    help: editing ? 'การเปลี่ยนเป็นหมดอายุ/ยกเลิกจะคืนห้องเป็นว่าง' : ''
                },
                {name: 'notes', label: 'หมายเหตุ', type: 'textarea', span: 2, rows: 2}
            ],
            onSubmit: async (input) => {
                const payload = {
                    ...input,
                    tenant_id: Number(input.tenant_id || (editing ? contract.tenant_id : 0)),
                    room_id: Number(input.room_id || (editing ? contract.room_id : 0))
                };

                const response = editing
                    ? await this.api.put(`/contracts/${contract.id}`, payload)
                    : await this.api.post('/contracts', payload);

                modal.close();
                this.toast.success(editing ? 'บันทึกสัญญาเรียบร้อยแล้ว' : 'สร้างสัญญาเช่าเรียบร้อยแล้ว');

                if (onSaved) {
                    onSaved(response.data);
                } else if (!editing) {
                    this.router.navigate(`/contracts/${response.data.id}`);
                } else {
                    this.loadContracts();
                }
            }
        });

        modal.body.replaceChildren(form.element);
        form.field('tenant_id')?.focus();
    }

    openRenewForm(contract, onSaved = null) {
        const modal = new Modal({
            title: `ต่อสัญญาห้อง ${contract.room_number}`,
            description: `${contract.tenant_name} · สัญญาเดิมสิ้นสุด ${formatDate(contract.end_date)}`
        });

        const nextStart = (() => {
            const date = new Date(`${contract.end_date}T00:00:00`);
            date.setDate(date.getDate() + 1);
            const pad = (number) => String(number).padStart(2, '0');
            return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
        })();

        const form = createForm({
            submitLabel: 'ต่อสัญญา',
            onCancel: () => modal.close(),
            values: {
                start_date: nextStart,
                end_date: addYears(nextStart, 1),
                monthly_rent: contract.monthly_rent,
                deposit: contract.deposit,
                electricity_rate: contract.electricity_rate,
                water_rate: contract.water_rate
            },
            fields: [
                {name: 'start_date', label: 'วันเริ่มสัญญาใหม่', type: 'date', required: true},
                {name: 'end_date', label: 'วันสิ้นสุดสัญญาใหม่', type: 'date', required: true},
                {name: 'monthly_rent', label: 'ค่าเช่ารายเดือน (บาท)', type: 'number', required: true, min: 0},
                {name: 'deposit', label: 'เงินประกัน (บาท)', type: 'number', min: 0},
                {name: 'electricity_rate', label: 'ค่าไฟ (บาท/หน่วย)', type: 'number', min: 0},
                {name: 'water_rate', label: 'ค่าน้ำ (บาท/หน่วย)', type: 'number', min: 0},
                {name: 'notes', label: 'หมายเหตุ', type: 'textarea', span: 2, rows: 2}
            ],
            onSubmit: async (values) => {
                const response = await this.api.post(`/contracts/${contract.id}/renew`, values);
                modal.close();
                this.toast.success('ต่อสัญญาเรียบร้อยแล้ว');

                if (onSaved) {
                    onSaved(response.data);
                } else {
                    this.router.navigate(`/contracts/${response.data.id}`);
                }
            }
        });

        modal.body.append(form.element);
        modal.open();
    }

    async endContract(contract, afterEnd = null) {
        const modal = new Modal({
            title: `สิ้นสุดสัญญาห้อง ${contract.room_number}`,
            description: 'ห้องจะกลับเป็นสถานะว่าง และผู้เช่าจะถูกตั้งเป็นย้ายออก'
        });

        const form = createForm({
            submitLabel: 'สิ้นสุดสัญญา',
            onCancel: () => modal.close(),
            values: {status: 'terminated', end_date: today()},
            fields: [
                {
                    name: 'status',
                    label: 'เหตุผล',
                    type: 'select',
                    options: [
                        {value: 'terminated', label: 'ยกเลิกสัญญาก่อนกำหนด'},
                        {value: 'expired', label: 'สัญญาหมดอายุ / ไม่ต่อสัญญา'}
                    ]
                },
                {name: 'end_date', label: 'วันที่สิ้นสุดจริง', type: 'date', required: true}
            ],
            onSubmit: async (values) => {
                await this.api.put(`/contracts/${contract.id}/end`, values);
                modal.close();
                this.toast.success('สิ้นสุดสัญญาเรียบร้อยแล้ว');

                if (afterEnd) {
                    afterEnd();
                } else {
                    this.loadContracts();
                }
            }
        });

        form.submitButton.classList.replace('btn-primary', 'btn-danger');
        modal.body.append(form.element);
        modal.open();
    }

    async deleteContract(contract, afterDelete = null) {
        const confirmed = await confirmDialog({
            title: 'ลบสัญญาเช่า',
            message: `คุณต้องการลบสัญญาของ ${contract.tenant_name} (ห้อง ${contract.room_number}) หรือไม่? ประวัติการชำระเงินจะยังคงอยู่แต่ไม่ผูกกับสัญญานี้`,
            confirmLabel: 'ลบข้อมูล',
            danger: true
        });

        if (!confirmed) {
            return;
        }

        try {
            await this.api.delete(`/contracts/${contract.id}`);
            this.toast.success('ลบข้อมูลเรียบร้อยแล้ว');

            if (afterDelete) {
                afterDelete();
            } else {
                this.loadContracts();
            }
        } catch (error) {
            this.toast.error(error.message);
        }
    }

    /* รายละเอียด ------------------------------------------------------------ */

    async renderDetail(id) {
        const content = this.content();
        content.replaceChildren(loadingState());

        const signal = this.signal();

        try {
            const response = await this.api.get(`/contracts/${id}`, {signal});
            this.renderDetailPage(response.data);
        } catch (error) {
            if (this.isAbort(error)) {
                return;
            }

            content.replaceChildren(
                pageHeader({title: 'สัญญาเช่า', backTo: {path: '/contracts', label: 'กลับไปหน้าสัญญาเช่า'}}),
                errorState(error.message, () => this.renderDetail(id))
            );
        }
    }

    renderDetailPage(contract) {
        const content = this.content();
        content.replaceChildren();

        const page = document.createElement('div');
        page.className = 'page';

        const title = document.createElement('span');
        title.className = 'title-with-badge';
        title.append(
            document.createTextNode(`สัญญาเช่าห้อง ${contract.room_number}`),
            statusBadge(labels.contractStatus, contract.status)
        );

        const actions = [];

        if (contract.status === 'active') {
            actions.push(
                button({label: 'ต่อสัญญา', iconName: 'refresh', onClick: () => this.openRenewForm(contract)}),
                button({label: 'สิ้นสุดสัญญา', iconName: 'xCircle', variant: 'danger-outline', onClick: () => this.endContract(contract, () => this.renderDetail(contract.id))})
            );
        }

        actions.push(
            button({label: 'แก้ไข', iconName: 'edit', variant: 'secondary', onClick: () => this.openForm({contract, onSaved: () => this.renderDetail(contract.id)})}),
            button({label: 'ลบ', iconName: 'trash', variant: 'danger-outline', onClick: () => this.deleteContract(contract, () => this.router.navigate('/contracts'))})
        );

        page.append(pageHeader({
            title,
            subtitle: `${contract.tenant_name} · ${formatDate(contract.start_date)} - ${formatDate(contract.end_date)}`,
            backTo: {path: '/contracts', label: 'กลับไปหน้าสัญญาเช่า'},
            actions
        }));

        const grid = document.createElement('div');
        grid.className = 'detail-grid';

        const termsCard = card({title: 'เงื่อนไขสัญญา'});
        termsCard.body.append(detailList([
            ['เลขที่สัญญา', `#${String(contract.id).padStart(5, '0')}`],
            ['วันเริ่มสัญญา', formatDate(contract.start_date)],
            ['วันสิ้นสุดสัญญา', formatDate(contract.end_date)],
            ['ระยะเวลาคงเหลือ', remainingLabel(contract)],
            ['ค่าเช่ารายเดือน', formatCurrency(contract.monthly_rent)],
            ['เงินประกัน', formatCurrency(contract.deposit)],
            ['ค่าไฟ', `${contract.electricity_rate} บาท/หน่วย`],
            ['ค่าน้ำ', `${contract.water_rate} บาท/หน่วย`],
            ['หมายเหตุ', contract.notes || '-'],
            ['สร้างเมื่อ', formatDateTime(contract.created_at)]
        ]));

        const partiesCard = card({title: 'ผู้เช่าและห้องพัก'});
        partiesCard.body.append(detailList([
            ['ผู้เช่า', contract.tenant_name],
            ['เบอร์โทร', contract.tenant_phone || '-'],
            ['อีเมล', contract.tenant_email || '-'],
            ['ห้อง', `ห้อง ${contract.room_number} (ชั้น ${contract.floor})`],
            ['ประเภทห้อง', labels.roomType[contract.room_type] || contract.room_type || '-']
        ]));

        const links = document.createElement('div');
        links.className = 'button-row';
        links.append(
            button({label: 'ดูข้อมูลผู้เช่า', iconName: 'user', variant: 'secondary', size: 'sm', onClick: () => this.router.navigate(`/tenants/${contract.tenant_id}`)}),
            button({label: 'ดูห้องพัก', iconName: 'building', variant: 'secondary', size: 'sm', onClick: () => this.router.navigate(`/rooms/${contract.room_id}`)}),
            button({label: 'บันทึกค่าเช่า', iconName: 'receipt', size: 'sm', onClick: () => this.router.navigate('/payments', false, {new: '1', tenant_id: contract.tenant_id})})
        );
        partiesCard.body.append(links);

        grid.append(termsCard, partiesCard);
        page.append(grid);
        content.append(page);
    }
}
