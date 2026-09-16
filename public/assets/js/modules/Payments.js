import {Module} from './Module.js';
import {statusBadge, badge} from '../ui/Badge.js';
import {avatar} from '../ui/Avatar.js';
import {confirmDialog} from '../ui/ConfirmDialog.js';
import {
    button, createTabs, iconButton, inputFilter, pageHeader,
    searchBox, selectFilter, statTile, toolbar
} from '../ui/Controls.js';
import {createForm} from '../ui/Form.js';
import {
    currentMonth, formatCurrency, formatDate, formatDateTime,
    formatMonth, monthLabel, toDateTimeLocal
} from '../ui/Formatters.js';
import * as labels from '../ui/Labels.js';
import {Modal} from '../ui/Modal.js';
import {createPagination} from '../ui/Pagination.js';
import {emptyState, errorState, loadingState} from '../ui/States.js';
import {createTable, stacked} from '../ui/Table.js';

const defaults = {
    tab: 'payments', q: '', month: '', status: '', type: '',
    tenant_id: '', room_id: '', category: '', page: 1
};

export class Payments extends Module {
    constructor(options) {
        super(options);
        this.state = {...defaults};
    }

    async render({query = {}} = {}) {
        this.state = {...defaults, month: currentMonth(), ...query, page: Number(query.page) || 1};

        // ตัวกรองผู้เช่า/ห้องจากหน้าอื่นไม่ควรจำกัดเดือน
        if ((query.tenant_id || query.room_id) && !query.month) {
            this.state.month = '';
        }

        await this.renderPage();

        if (query.new === '1') {
            this.router.replaceQuery(this.queryFrom(this.state, defaults));
            this.openPaymentForm({tenantId: query.tenant_id ? Number(query.tenant_id) : null});
        }
    }

    async renderPage() {
        const content = this.content();
        content.replaceChildren();

        const page = document.createElement('div');
        page.className = 'page';

        this.headerElement = document.createElement('div');
        this.tiles = document.createElement('div');
        this.tiles.className = 'stat-tiles';
        this.tabsElement = createTabs({
            tabs: [
                {id: 'payments', label: 'การชำระเงิน'},
                {id: 'expenses', label: 'รายจ่าย'}
            ],
            active: this.state.tab,
            onChange: (tab) => this.applyFilter({tab, q: '', status: '', type: '', category: ''})
        });
        this.toolbarElement = document.createElement('div');
        this.listElement = document.createElement('div');
        this.listElement.className = 'card';

        page.append(this.headerElement, this.tiles, this.tabsElement, this.toolbarElement, this.listElement);
        content.append(page);

        this.renderHeader();
        this.renderToolbar();
        await Promise.all([this.loadSummary(), this.loadList()]);
    }

    renderHeader() {
        const isPayments = this.state.tab === 'payments';

        this.headerElement.replaceChildren(pageHeader({
            title: 'การเงิน',
            subtitle: 'ค่าเช่า ค่าสาธารณูปโภค และรายจ่ายของหอพัก',
            actions: [
                isPayments
                    ? button({label: 'บันทึกค่าเช่า', iconName: 'plus', onClick: () => this.openPaymentForm()})
                    : button({label: 'บันทึกรายจ่าย', iconName: 'plus', onClick: () => this.openExpenseForm()})
            ]
        }));
    }

    renderToolbar() {
        const monthFilter = inputFilter({
            label: 'งวดเดือน',
            type: 'month',
            value: this.state.month,
            onChange: (value) => this.applyFilter({month: value})
        });

        if (this.state.tab === 'payments') {
            this.toolbarElement.replaceChildren(toolbar(
                searchBox({
                    placeholder: 'ค้นหาชื่อผู้เช่า เลขห้อง หรือเลขอ้างอิง',
                    value: this.state.q,
                    onSearch: (value) => this.applyFilter({q: value})
                }),
                monthFilter,
                selectFilter({
                    label: 'สถานะ',
                    value: this.state.status,
                    options: labels.toOptions(labels.paymentStatus, 'ทุกสถานะ'),
                    onChange: (value) => this.applyFilter({status: value})
                }),
                selectFilter({
                    label: 'ประเภท',
                    value: this.state.type,
                    options: labels.toOptions(labels.paymentType, 'ทุกประเภท'),
                    onChange: (value) => this.applyFilter({type: value})
                }),
                (this.state.tenant_id || this.state.room_id)
                    ? button({label: 'ล้างตัวกรองผู้เช่า/ห้อง', variant: 'link', iconName: 'close', onClick: () => this.applyFilter({tenant_id: '', room_id: '', month: currentMonth()})})
                    : null
            ));

            return;
        }

        this.toolbarElement.replaceChildren(toolbar(
            searchBox({
                placeholder: 'ค้นหารายการรายจ่าย',
                value: this.state.q,
                onSearch: (value) => this.applyFilter({q: value})
            }),
            monthFilter,
            selectFilter({
                label: 'หมวดหมู่',
                value: this.state.category,
                options: labels.toOptions(labels.expenseCategory, 'ทุกหมวดหมู่'),
                onChange: (value) => this.applyFilter({category: value})
            })
        ));
    }

    applyFilter(partial) {
        const tabChanged = partial.tab && partial.tab !== this.state.tab;
        this.state = {...this.state, ...partial, page: partial.page || 1};
        this.router.replaceQuery(this.queryFrom(this.state, defaults));

        if (tabChanged) {
            this.renderHeader();
            this.renderToolbar();
        }

        this.loadSummary();
        this.loadList();
    }

    async loadSummary() {
        const month = this.state.month || currentMonth();

        try {
            if (this.state.tab === 'payments') {
                const response = await this.api.get(`/payments/summary?month=${month}`);
                const data = response.data;

                this.tiles.replaceChildren(
                    statTile({label: `รับชำระแล้ว (${formatMonth(month)})`, value: formatCurrency(data.paid_total), meta: `${data.paid_count} รายการ`, variant: 'green', iconName: 'coins'}),
                    statTile({label: 'รอชำระ', value: formatCurrency(data.pending_total), meta: `${data.pending_count} รายการ`, variant: 'orange', iconName: 'clock'}),
                    statTile({label: 'ยกเลิก', value: `${data.cancelled_count} รายการ`, variant: 'gray', iconName: 'xCircle'})
                );
            } else {
                const response = await this.api.get(`/expenses?month=${month}&per_page=1`);
                const summary = await this.api.get('/dashboard/financial-summary');

                this.tiles.replaceChildren(
                    statTile({label: `รายจ่าย (${formatMonth(month)})`, value: formatCurrency(response.data.month_total || 0), meta: `${response.pagination.total} รายการ`, variant: 'pink', iconName: 'receipt'}),
                    statTile({label: 'รายรับเดือนนี้', value: formatCurrency(summary.data.income.total), variant: 'green', iconName: 'coins'}),
                    statTile({label: 'กำไรสุทธิเดือนนี้', value: formatCurrency(summary.data.net_profit.total), variant: 'blue', iconName: 'trendUp'})
                );
            }
        } catch (error) {
            if (!this.isAbort(error)) {
                this.tiles.replaceChildren();
            }
        }
    }

    async loadList() {
        const signal = this.signal();
        this.listElement.replaceChildren(loadingState());

        try {
            if (this.state.tab === 'payments') {
                const query = new URLSearchParams({
                    q: this.state.q,
                    month: this.state.month,
                    status: this.state.status,
                    type: this.state.type,
                    tenant_id: this.state.tenant_id,
                    room_id: this.state.room_id,
                    page: String(this.state.page),
                    per_page: '15'
                });

                const response = await this.api.get(`/payments?${query}`, {signal});
                this.renderPayments(response.data, response.pagination);
            } else {
                const query = new URLSearchParams({
                    q: this.state.q,
                    month: this.state.month,
                    category: this.state.category,
                    page: String(this.state.page),
                    per_page: '15'
                });

                const response = await this.api.get(`/expenses?${query}`, {signal});
                this.renderExpenses(response.data.items, response.pagination);
            }
        } catch (error) {
            if (this.isAbort(error)) {
                return;
            }

            this.listElement.replaceChildren(errorState(error.message, () => this.loadList()));
        }
    }

    renderPayments(payments, pagination) {
        this.listElement.replaceChildren();

        if (!payments.length) {
            this.listElement.append(emptyState({
                iconName: 'receipt',
                title: 'ไม่พบรายการชำระเงิน',
                message: this.state.month ? `ยังไม่มีรายการในงวด ${formatMonth(this.state.month)}` : 'ลองเปลี่ยนตัวกรอง',
                actionLabel: 'บันทึกค่าเช่า',
                onAction: () => this.openPaymentForm()
            }));

            return;
        }

        this.listElement.append(createTable({
            rows: payments,
            columns: [
                {
                    key: 'tenant',
                    label: 'ผู้เช่า',
                    render: (row) => {
                        const box = document.createElement('div');
                        box.className = 'cell-person';
                        box.append(avatar({name: row.first_name, gender: row.gender}), stacked(row.tenant_name, `ห้อง ${row.room_number}`));
                        return box;
                    }
                },
                {key: 'payment_type', label: 'ประเภท', render: (row) => labels.paymentType[row.payment_type] || row.payment_type},
                {key: 'period', label: 'งวด', render: (row) => formatMonth(row.period_month, row.period_year)},
                {key: 'amount', label: 'จำนวน', align: 'right', render: (row) => formatCurrency(row.amount)},
                {key: 'payment_date', label: 'วันที่ชำระ', render: (row) => row.payment_date ? formatDateTime(row.payment_date) : '-'},
                {key: 'reference', label: 'อ้างอิง', render: (row) => row.reference || '-'},
                {key: 'status', label: 'สถานะ', render: (row) => statusBadge(labels.paymentStatus, row.status)},
                {
                    key: 'actions',
                    label: 'จัดการ',
                    align: 'right',
                    className: 'cell-actions',
                    render: (row) => {
                        const actions = [
                            iconButton({iconName: 'edit', label: 'แก้ไข', onClick: () => this.openPaymentForm({payment: row})})
                        ];

                        if (row.status === 'pending') {
                            actions.unshift(iconButton({iconName: 'checkCircle', label: 'บันทึกว่าชำระแล้ว', variant: 'success', onClick: () => this.markPaid(row)}));
                        }

                        if (row.status !== 'cancelled') {
                            actions.push(iconButton({iconName: 'xCircle', label: 'ยกเลิกรายการ', onClick: () => this.cancelPayment(row)}));
                        }

                        actions.push(iconButton({iconName: 'trash', label: 'ลบ', variant: 'danger', onClick: () => this.deletePayment(row)}));

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

    renderExpenses(expenses, pagination) {
        this.listElement.replaceChildren();

        if (!expenses.length) {
            this.listElement.append(emptyState({
                iconName: 'receipt',
                title: 'ไม่พบรายการรายจ่าย',
                message: this.state.month ? `ยังไม่มีรายจ่ายในเดือน ${formatMonth(this.state.month)}` : 'ลองเปลี่ยนตัวกรอง',
                actionLabel: 'บันทึกรายจ่าย',
                onAction: () => this.openExpenseForm()
            }));

            return;
        }

        this.listElement.append(createTable({
            rows: expenses,
            columns: [
                {key: 'title', label: 'รายการ', render: (row) => stacked(row.title, row.notes || '')},
                {key: 'category', label: 'หมวดหมู่', render: (row) => badge(labels.expenseCategory[row.category] || row.category, 'gray')},
                {key: 'expense_date', label: 'วันที่', render: (row) => formatDate(row.expense_date)},
                {key: 'amount', label: 'จำนวน', align: 'right', render: (row) => formatCurrency(row.amount)},
                {
                    key: 'actions',
                    label: 'จัดการ',
                    align: 'right',
                    className: 'cell-actions',
                    render: (row) => [
                        iconButton({iconName: 'edit', label: 'แก้ไข', onClick: () => this.openExpenseForm(row)}),
                        iconButton({iconName: 'trash', label: 'ลบ', variant: 'danger', onClick: () => this.deleteExpense(row)})
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

    /* ฟอร์มการชำระเงิน ----------------------------------------------------- */

    async openPaymentForm({payment = null, tenantId = null} = {}) {
        const editing = payment !== null;
        const modal = new Modal({
            title: editing ? 'แก้ไขรายการชำระเงิน' : 'บันทึกการชำระเงิน',
            size: 'lg'
        });

        modal.body.append(loadingState('กำลังโหลดรายชื่อผู้เช่า...'));
        modal.open();

        let tenants = [];

        try {
            tenants = (await this.api.get('/tenants/options')).data;
        } catch (error) {
            modal.body.replaceChildren(errorState(error.message));
            return;
        }

        const withRoom = tenants.filter((tenant) => tenant.room_id);
        const withoutRoom = tenants.filter((tenant) => !tenant.room_id);
        const tenantOptions = [{value: '', label: '— เลือกผู้เช่า —'}]
            .concat(withRoom.map((tenant) => ({value: String(tenant.id), label: `ห้อง ${tenant.room_number} · ${tenant.name}`})))
            .concat(withoutRoom.map((tenant) => ({value: String(tenant.id), label: `${tenant.name} (ไม่มีห้อง)`})));

        const now = new Date();
        const values = editing
            ? {
                ...payment,
                tenant_id: String(payment.tenant_id),
                payment_date: payment.payment_date ? toDateTimeLocal(payment.payment_date) : ''
            }
            : {
                tenant_id: tenantId ? String(tenantId) : '',
                payment_type: 'rent',
                amount: tenantId ? (tenants.find((tenant) => tenant.id === tenantId)?.contract_rent ?? '') : '',
                period_month: now.getMonth() + 1,
                period_year: now.getFullYear(),
                payment_date: toDateTimeLocal(now),
                status: 'paid'
            };

        const monthOptions = Array.from({length: 12}, (_, index) => ({
            value: String(index + 1),
            label: monthLabel(index + 1, true)
        }));

        const form = createForm({
            values,
            onCancel: () => modal.close(),
            fields: [
                {
                    name: 'tenant_id',
                    label: 'ผู้เช่า',
                    type: 'select',
                    required: true,
                    options: tenantOptions,
                    span: 2,
                    onChange: (value, api) => {
                        const tenant = tenants.find((item) => String(item.id) === value);

                        if (tenant?.contract_rent && api.getValues().payment_type === 'rent') {
                            api.setValues({amount: tenant.contract_rent});
                        }
                    }
                },
                {
                    name: 'payment_type',
                    label: 'ประเภท',
                    type: 'select',
                    options: labels.toOptions(labels.paymentType),
                    onChange: (value, api) => {
                        const tenant = tenants.find((item) => String(item.id) === api.getValues().tenant_id);

                        if (value === 'rent' && tenant?.contract_rent) {
                            api.setValues({amount: tenant.contract_rent});
                        }
                    }
                },
                {name: 'amount', label: 'จำนวนเงิน (บาท)', type: 'number', required: true, min: 0},
                {name: 'period_month', label: 'งวดเดือน', type: 'select', options: monthOptions},
                {name: 'period_year', label: 'งวดปี (ค.ศ.)', type: 'number', required: true, min: 2000, max: 2200, step: 1},
                {name: 'status', label: 'สถานะ', type: 'select', options: labels.toOptions(labels.paymentStatus)},
                {name: 'payment_date', label: 'วันที่ชำระ', type: 'datetime-local', help: 'เว้นว่างได้หากยังไม่ชำระ'},
                {name: 'reference', label: 'เลขอ้างอิง', placeholder: 'สร้างอัตโนมัติหากเว้นว่าง', maxLength: 60},
                {name: 'notes', label: 'หมายเหตุ', maxLength: 1000},
            ],
            onSubmit: async (input) => {
                const payload = {
                    ...input,
                    tenant_id: Number(input.tenant_id),
                    period_month: Number(input.period_month),
                    room_id: editing ? payment.room_id : undefined,
                    contract_id: editing ? payment.contract_id : undefined
                };

                if (editing) {
                    await this.api.put(`/payments/${payment.id}`, payload);
                } else {
                    await this.api.post('/payments', payload);
                }

                modal.close();
                this.toast.success('บันทึกข้อมูลเรียบร้อยแล้ว');
                this.loadSummary();
                this.loadList();
            }
        });

        modal.body.replaceChildren(form.element);
        form.field('tenant_id')?.focus();
    }

    async markPaid(payment) {
        try {
            await this.api.put(`/payments/${payment.id}`, {
                ...payment,
                status: 'paid',
                payment_date: null
            });
            this.toast.success('บันทึกการชำระเรียบร้อยแล้ว');
            this.loadSummary();
            this.loadList();
        } catch (error) {
            this.toast.error(error.message);
        }
    }

    async cancelPayment(payment) {
        const confirmed = await confirmDialog({
            title: 'ยกเลิกรายการชำระเงิน',
            message: `ยกเลิกรายการ ${formatCurrency(payment.amount)} ของ ${payment.tenant_name} หรือไม่? รายการจะยังคงอยู่ในประวัติ`,
            confirmLabel: 'ยกเลิกรายการ',
            danger: true
        });

        if (!confirmed) {
            return;
        }

        try {
            await this.api.put(`/payments/${payment.id}/cancel`);
            this.toast.success('ยกเลิกรายการเรียบร้อยแล้ว');
            this.loadSummary();
            this.loadList();
        } catch (error) {
            this.toast.error(error.message);
        }
    }

    async deletePayment(payment) {
        const confirmed = await confirmDialog({
            title: 'ลบรายการชำระเงิน',
            message: `ลบรายการ ${formatCurrency(payment.amount)} ของ ${payment.tenant_name} ออกจากระบบถาวรหรือไม่?`,
            confirmLabel: 'ลบข้อมูล',
            danger: true
        });

        if (!confirmed) {
            return;
        }

        try {
            await this.api.delete(`/payments/${payment.id}`);
            this.toast.success('ลบข้อมูลเรียบร้อยแล้ว');
            this.loadSummary();
            this.loadList();
        } catch (error) {
            this.toast.error(error.message);
        }
    }

    /* ฟอร์มรายจ่าย ---------------------------------------------------------- */

    openExpenseForm(expense = null) {
        const editing = expense !== null;
        const modal = new Modal({title: editing ? 'แก้ไขรายจ่าย' : 'บันทึกรายจ่าย'});

        const form = createForm({
            values: expense || {category: 'utilities', expense_date: new Date().toISOString().slice(0, 10)},
            onCancel: () => modal.close(),
            fields: [
                {name: 'title', label: 'รายการ', required: true, span: 2, maxLength: 120},
                {name: 'category', label: 'หมวดหมู่', type: 'select', options: labels.toOptions(labels.expenseCategory)},
                {name: 'amount', label: 'จำนวนเงิน (บาท)', type: 'number', required: true, min: 0},
                {name: 'expense_date', label: 'วันที่', type: 'date', required: true},
                {name: 'notes', label: 'หมายเหตุ', maxLength: 1000},
            ],
            onSubmit: async (values) => {
                if (editing) {
                    await this.api.put(`/expenses/${expense.id}`, values);
                } else {
                    await this.api.post('/expenses', values);
                }

                modal.close();
                this.toast.success('บันทึกข้อมูลเรียบร้อยแล้ว');
                this.loadSummary();
                this.loadList();
            }
        });

        modal.body.append(form.element);
        modal.open();
    }

    async deleteExpense(expense) {
        const confirmed = await confirmDialog({
            title: 'ลบรายจ่าย',
            message: `ลบรายการ "${expense.title}" (${formatCurrency(expense.amount)}) หรือไม่?`,
            confirmLabel: 'ลบข้อมูล',
            danger: true
        });

        if (!confirmed) {
            return;
        }

        try {
            await this.api.delete(`/expenses/${expense.id}`);
            this.toast.success('ลบข้อมูลเรียบร้อยแล้ว');
            this.loadSummary();
            this.loadList();
        } catch (error) {
            this.toast.error(error.message);
        }
    }
}
