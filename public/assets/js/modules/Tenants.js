import {Module} from './Module.js';
import {statusBadge} from '../ui/Badge.js';
import {avatar} from '../ui/Avatar.js';
import {confirmDialog} from '../ui/ConfirmDialog.js';
import {
    button, card, detailList, iconButton, pageHeader,
    searchBox, selectFilter, statTile, toolbar
} from '../ui/Controls.js';
import {createForm} from '../ui/Form.js';
import {formatCurrency, formatDate, formatDateTime} from '../ui/Formatters.js';
import * as labels from '../ui/Labels.js';
import {Modal} from '../ui/Modal.js';
import {createPagination} from '../ui/Pagination.js';
import {emptyState, errorState, loadingState} from '../ui/States.js';
import {createTable, stacked} from '../ui/Table.js';

const defaults = {q: '', status: '', gender: '', sort: 'name', page: 1};

export class Tenants extends Module {
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
            this.openForm();
        }
    }

    /* รายการผู้เช่า ------------------------------------------------------ */

    async renderList() {
        const content = this.content();
        content.replaceChildren();

        const page = document.createElement('div');
        page.className = 'page';

        page.append(pageHeader({
            title: 'ผู้เช่า',
            subtitle: 'จัดการข้อมูลผู้เช่าและการเข้าพัก',
            actions: [button({label: 'เพิ่มผู้เช่า', iconName: 'userPlus', onClick: () => this.openForm()})]
        }));

        this.tiles = document.createElement('div');
        this.tiles.className = 'stat-tiles';
        page.append(this.tiles);

        page.append(toolbar(
            searchBox({
                placeholder: 'ค้นหาชื่อ เบอร์โทร อีเมล หรือเลขห้อง',
                value: this.state.q,
                onSearch: (value) => this.applyFilter({q: value})
            }),
            selectFilter({
                label: 'สถานะ',
                value: this.state.status,
                options: labels.toOptions(labels.tenantStatus, 'ทุกสถานะ'),
                onChange: (value) => this.applyFilter({status: value})
            }),
            selectFilter({
                label: 'เพศ',
                value: this.state.gender,
                options: labels.toOptions(labels.gender, 'ทุกเพศ'),
                onChange: (value) => this.applyFilter({gender: value})
            }),
            selectFilter({
                label: 'เรียงตาม',
                value: this.state.sort,
                options: [
                    {value: 'name', label: 'ชื่อ'},
                    {value: 'room', label: 'เลขห้อง'},
                    {value: 'latest', label: 'เพิ่มล่าสุด'}
                ],
                onChange: (value) => this.applyFilter({sort: value})
            })
        ));

        this.listElement = document.createElement('div');
        this.listElement.className = 'card';
        page.append(this.listElement);
        content.append(page);

        await Promise.all([this.loadSummary(), this.loadTenants()]);
    }

    applyFilter(partial) {
        this.state = {...this.state, ...partial, page: partial.page || 1};
        this.router.replaceQuery(this.queryFrom(this.state, defaults));
        this.loadTenants();
    }

    async loadSummary() {
        try {
            const response = await this.api.get('/dashboard/statistics');
            const tenants = response.data.tenants;

            this.tiles.replaceChildren(
                statTile({label: 'ผู้เช่าที่พักอยู่', value: `${tenants.total} คน`, variant: 'green', iconName: 'users'}),
                statTile({label: 'ชาย', value: `${tenants.male} คน`, variant: 'blue', iconName: 'user'}),
                statTile({label: 'หญิง', value: `${tenants.female} คน`, variant: 'pink', iconName: 'user'})
            );
        } catch (error) {
            if (!this.isAbort(error)) {
                this.tiles.replaceChildren();
            }
        }
    }

    async loadTenants() {
        const signal = this.signal();
        this.listElement.replaceChildren(loadingState());

        try {
            const query = new URLSearchParams({
                q: this.state.q,
                status: this.state.status,
                gender: this.state.gender,
                sort: this.state.sort,
                page: String(this.state.page),
                per_page: '12'
            });

            const response = await this.api.get(`/tenants?${query}`, {signal});
            this.renderTenants(response.data, response.pagination);
        } catch (error) {
            if (this.isAbort(error)) {
                return;
            }

            this.listElement.replaceChildren(errorState(error.message, () => this.loadTenants()));
        }
    }

    renderTenants(tenants, pagination) {
        this.listElement.replaceChildren();

        if (!tenants.length) {
            const filtered = this.state.q || this.state.status || this.state.gender;

            this.listElement.append(emptyState({
                iconName: 'users',
                title: filtered ? 'ไม่พบผู้เช่าที่ตรงกับเงื่อนไข' : 'ยังไม่มีข้อมูลผู้เช่า',
                message: filtered ? 'ลองเปลี่ยนคำค้นหาหรือตัวกรอง' : 'เพิ่มผู้เช่ารายแรกเพื่อเริ่มต้นใช้งาน',
                actionLabel: filtered ? '' : 'เพิ่มผู้เช่า',
                onAction: () => this.openForm()
            }));

            return;
        }

        this.listElement.append(createTable({
            rows: tenants,
            onRowClick: (tenant) => this.router.navigate(`/tenants/${tenant.id}`),
            columns: [
                {
                    key: 'name',
                    label: 'ผู้เช่า',
                    render: (tenant) => {
                        const box = document.createElement('div');
                        box.className = 'cell-person';
                        box.append(
                            avatar({name: tenant.first_name, gender: tenant.gender}),
                            stacked(tenant.name, labels.gender[tenant.gender] || '-')
                        );
                        return box;
                    }
                },
                {key: 'phone', label: 'เบอร์โทร'},
                {key: 'email', label: 'อีเมล', render: (tenant) => tenant.email || '-'},
                {
                    key: 'room',
                    label: 'ห้อง',
                    render: (tenant) => tenant.room_number
                        ? stacked(`ห้อง ${tenant.room_number}`, `หมดสัญญา ${formatDate(tenant.contract_end_date)}`)
                        : stacked('-', 'ยังไม่มีห้อง')
                },
                {key: 'status', label: 'สถานะ', render: (tenant) => statusBadge(labels.tenantStatus, tenant.status)},
                {
                    key: 'actions',
                    label: 'จัดการ',
                    align: 'right',
                    className: 'cell-actions',
                    render: (tenant) => [
                        iconButton({iconName: 'eye', label: 'ดูรายละเอียด', onClick: () => this.router.navigate(`/tenants/${tenant.id}`)}),
                        iconButton({iconName: 'edit', label: 'แก้ไข', onClick: () => this.openForm(tenant)}),
                        iconButton({iconName: 'trash', label: 'ลบ', variant: 'danger', onClick: () => this.deleteTenant(tenant)})
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

    /* ฟอร์ม ---------------------------------------------------------------- */

    openForm(tenant = null, onSaved = null) {
        const editing = tenant !== null;
        const modal = new Modal({
            title: editing ? `แก้ไขข้อมูล ${tenant.name || tenant.first_name}` : 'เพิ่มผู้เช่า',
            size: 'lg'
        });

        const form = createForm({
            values: tenant || {status: 'active', gender: 'male'},
            onCancel: () => modal.close(),
            fields: [
                {name: 'first_name', label: 'ชื่อ', required: true, maxLength: 80},
                {name: 'last_name', label: 'นามสกุล', required: true, maxLength: 80},
                {name: 'gender', label: 'เพศ', type: 'select', options: labels.toOptions(labels.gender)},
                {name: 'phone', label: 'เบอร์โทร', type: 'tel', required: true, placeholder: '08xxxxxxxx'},
                {name: 'email', label: 'อีเมล', type: 'email'},
                {name: 'national_id', label: 'เลขบัตรประชาชน', maxLength: 13, help: 'ตัวเลข 13 หลัก (ไม่บังคับ)'},
                {name: 'address', label: 'ที่อยู่', type: 'textarea', span: 2, rows: 2},
                {name: 'emergency_contact', label: 'ผู้ติดต่อฉุกเฉิน'},
                {name: 'emergency_phone', label: 'เบอร์ติดต่อฉุกเฉิน', type: 'tel'},
                {name: 'status', label: 'สถานะ', type: 'select', options: labels.toOptions(labels.tenantStatus)}
            ],
            onSubmit: async (values) => {
                const response = editing
                    ? await this.api.put(`/tenants/${tenant.id}`, values)
                    : await this.api.post('/tenants', values);

                modal.close();
                this.toast.success(editing ? 'บันทึกข้อมูลผู้เช่าเรียบร้อยแล้ว' : 'เพิ่มผู้เช่าเรียบร้อยแล้ว');

                if (onSaved) {
                    onSaved(response.data);
                } else if (!editing) {
                    this.router.navigate(`/tenants/${response.data.id}`);
                } else {
                    this.loadTenants();
                }
            }
        });

        modal.body.append(form.element);
        modal.open();
    }

    async deleteTenant(tenant, afterDelete = null) {
        const confirmed = await confirmDialog({
            title: 'ลบข้อมูลผู้เช่า',
            message: `คุณต้องการลบข้อมูลของ ${tenant.name || `${tenant.first_name} ${tenant.last_name}`} หรือไม่?`,
            confirmLabel: 'ลบข้อมูล',
            danger: true
        });

        if (!confirmed) {
            return;
        }

        try {
            await this.api.delete(`/tenants/${tenant.id}`);
            this.toast.success('ลบข้อมูลเรียบร้อยแล้ว');

            if (afterDelete) {
                afterDelete();
            } else {
                this.loadSummary();
                this.loadTenants();
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
            const response = await this.api.get(`/tenants/${id}`, {signal});
            this.renderDetailPage(response.data);
        } catch (error) {
            if (this.isAbort(error)) {
                return;
            }

            content.replaceChildren(
                pageHeader({title: 'ผู้เช่า', backTo: {path: '/tenants', label: 'กลับไปหน้าผู้เช่า'}}),
                errorState(error.message, () => this.renderDetail(id))
            );
        }
    }

    renderDetailPage({tenant, contracts, payments}) {
        const content = this.content();
        content.replaceChildren();

        const page = document.createElement('div');
        page.className = 'page';

        const title = document.createElement('span');
        title.className = 'title-with-badge';
        title.append(document.createTextNode(tenant.name), statusBadge(labels.tenantStatus, tenant.status));

        const actions = [
            button({label: 'แก้ไข', iconName: 'edit', variant: 'secondary', onClick: () => this.openForm(tenant, () => this.renderDetail(tenant.id))}),
            button({label: 'ลบ', iconName: 'trash', variant: 'danger-outline', onClick: () => this.deleteTenant(tenant, () => this.router.navigate('/tenants'))})
        ];

        if (tenant.contract_id) {
            actions.unshift(button({
                label: 'บันทึกค่าเช่า',
                iconName: 'receipt',
                onClick: () => this.router.navigate('/payments', false, {new: '1', tenant_id: tenant.id})
            }));
        } else if (tenant.status !== 'blacklisted') {
            actions.unshift(button({
                label: 'จัดห้องพัก',
                iconName: 'contract',
                onClick: () => this.router.navigate('/contracts', false, {new: '1', tenant_id: tenant.id})
            }));
        }

        page.append(pageHeader({
            title,
            subtitle: tenant.room_number
                ? `พักอยู่ห้อง ${tenant.room_number} · สัญญาถึง ${formatDate(tenant.contract_end_date)}`
                : 'ยังไม่มีห้องพัก',
            backTo: {path: '/tenants', label: 'กลับไปหน้าผู้เช่า'},
            actions
        }));

        const grid = document.createElement('div');
        grid.className = 'detail-grid';

        const infoCard = card({title: 'ข้อมูลส่วนตัว'});
        const person = document.createElement('div');
        person.className = 'person-row';
        person.append(
            avatar({name: tenant.first_name, gender: tenant.gender, size: 'lg'}),
            stacked(tenant.name, labels.gender[tenant.gender] || '-')
        );

        infoCard.body.append(person, detailList([
            ['เบอร์โทร', tenant.phone],
            ['อีเมล', tenant.email || '-'],
            ['เลขบัตรประชาชน', tenant.national_id_masked || '-'],
            ['ที่อยู่', tenant.address || '-'],
            ['ผู้ติดต่อฉุกเฉิน', tenant.emergency_contact ? `${tenant.emergency_contact} (${tenant.emergency_phone || '-'})` : '-'],
            ['เพิ่มเมื่อ', formatDateTime(tenant.created_at)]
        ]));

        const roomCard = card({title: 'ห้องพักและสัญญาปัจจุบัน'});

        if (tenant.contract_id) {
            roomCard.body.append(detailList([
                ['ห้อง', `ห้อง ${tenant.room_number} (ชั้น ${tenant.floor})`],
                ['เริ่มสัญญา', formatDate(tenant.contract_start_date)],
                ['สิ้นสุดสัญญา', formatDate(tenant.contract_end_date)],
                ['ค่าเช่า', `${formatCurrency(tenant.contract_rent)}/เดือน`]
            ]));

            const links = document.createElement('div');
            links.className = 'button-row';
            links.append(
                button({label: 'ดูห้องพัก', iconName: 'building', variant: 'secondary', size: 'sm', onClick: () => this.router.navigate(`/rooms/${tenant.room_id}`)}),
                button({label: 'ดูสัญญา', iconName: 'contract', variant: 'secondary', size: 'sm', onClick: () => this.router.navigate(`/contracts/${tenant.contract_id}`)})
            );
            roomCard.body.append(links);
        } else {
            roomCard.body.append(emptyState({
                iconName: 'building',
                title: 'ยังไม่มีห้องพัก',
                message: 'สร้างสัญญาเช่าเพื่อจัดห้องให้ผู้เช่ารายนี้',
                actionLabel: tenant.status === 'blacklisted' ? '' : 'จัดห้องพัก',
                onAction: () => this.router.navigate('/contracts', false, {new: '1', tenant_id: tenant.id})
            }));
        }

        grid.append(infoCard, roomCard);
        page.append(grid);

        page.append(card({
            title: 'ประวัติสัญญาเช่า',
            content: createTable({
                compact: true,
                rows: contracts,
                emptyMessage: 'ยังไม่มีประวัติสัญญาเช่า',
                onRowClick: (contract) => this.router.navigate(`/contracts/${contract.id}`),
                columns: [
                    {key: 'room_number', label: 'ห้อง', render: (row) => `ห้อง ${row.room_number}`},
                    {key: 'start_date', label: 'เริ่ม', render: (row) => formatDate(row.start_date)},
                    {key: 'end_date', label: 'สิ้นสุด', render: (row) => formatDate(row.end_date)},
                    {key: 'monthly_rent', label: 'ค่าเช่า', align: 'right', render: (row) => formatCurrency(row.monthly_rent)},
                    {key: 'status', label: 'สถานะ', render: (row) => statusBadge(labels.contractStatus, row.status)}
                ]
            })
        }));

        page.append(card({
            title: 'ประวัติการชำระเงิน',
            actions: [button({label: 'ดูทั้งหมด', variant: 'link', onClick: () => this.router.navigate('/payments', false, {tenant_id: tenant.id})})],
            content: createTable({
                compact: true,
                rows: payments,
                emptyMessage: 'ยังไม่มีการชำระเงิน',
                columns: [
                    {key: 'period', label: 'งวด', render: (row) => `${labels.paymentType[row.payment_type] || row.payment_type} ${row.period_month}/${row.period_year + 543}`},
                    {key: 'room_number', label: 'ห้อง', render: (row) => `ห้อง ${row.room_number}`},
                    {key: 'amount', label: 'จำนวน', align: 'right', render: (row) => formatCurrency(row.amount)},
                    {key: 'payment_date', label: 'วันที่ชำระ', render: (row) => formatDateTime(row.payment_date)},
                    {key: 'reference', label: 'อ้างอิง', render: (row) => row.reference || '-'},
                    {key: 'status', label: 'สถานะ', render: (row) => statusBadge(labels.paymentStatus, row.status)}
                ]
            })
        }));

        content.append(page);
    }
}
