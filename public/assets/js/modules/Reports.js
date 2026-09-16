import {Module} from './Module.js';
import {badge, statusBadge} from '../ui/Badge.js';
import {BarChart} from '../ui/Charts.js';
import {
    button, card, createTabs, inputFilter, pageHeader,
    selectFilter, statTile, toolbar
} from '../ui/Controls.js';
import {downloadCsv} from '../ui/Csv.js';
import {
    formatCurrency, formatDate, formatDateTime, monthLabel, today
} from '../ui/Formatters.js';
import * as labels from '../ui/Labels.js';
import {errorState, loadingState} from '../ui/States.js';
import {createTable} from '../ui/Table.js';

const reportTabs = [
    {id: 'profit', label: 'รายรับ-รายจ่าย'},
    {id: 'income', label: 'รายรับ'},
    {id: 'expenses', label: 'รายจ่าย'},
    {id: 'occupancy', label: 'อัตราเข้าพัก'},
    {id: 'tenants', label: 'ผู้เช่า'},
    {id: 'payments', label: 'การชำระเงิน'},
    {id: 'maintenance', label: 'งานซ่อม'},
    {id: 'contracts', label: 'สัญญาใกล้หมดอายุ'}
];

function firstDayOfMonth() {
    return `${today().slice(0, 7)}-01`;
}

export class Reports extends Module {
    constructor(options) {
        super(options);
        this.state = {};
        this.data = null;
    }

    async render({query = {}} = {}) {
        const year = new Date().getFullYear();

        this.state = {
            report: query.report || 'profit',
            year: Number(query.year) || year,
            month: query.month || '',
            from: query.from || firstDayOfMonth(),
            to: query.to || today(),
            status: query.status || '',
            days: Number(query.days) || 30
        };

        const content = this.content();
        content.replaceChildren();

        const page = document.createElement('div');
        page.className = 'page';

        page.append(pageHeader({
            title: 'รายงาน',
            subtitle: 'สรุปผลการดำเนินงานจากข้อมูลจริงในระบบ',
            actions: [button({label: 'ส่งออก CSV', iconName: 'download', variant: 'secondary', onClick: () => this.exportCsv()})]
        }));

        page.append(createTabs({
            tabs: reportTabs,
            active: this.state.report,
            onChange: (report) => {
                this.state.report = report;
                this.syncUrl();
                this.renderToolbar();
                this.load();
            }
        }));

        this.toolbarElement = document.createElement('div');
        this.body = document.createElement('div');
        this.body.className = 'report-body';

        page.append(this.toolbarElement, this.body);
        content.append(page);

        this.renderToolbar();
        await this.load();
    }

    syncUrl() {
        this.router.replaceQuery({
            report: this.state.report,
            year: this.state.year,
            month: this.state.month,
            from: this.state.from,
            to: this.state.to,
            status: this.state.status,
            days: this.state.days
        });
    }

    renderToolbar() {
        const {report} = this.state;
        const controls = [];

        const yearOptions = (years) => {
            const list = years.length ? years : [new Date().getFullYear()];

            if (!list.includes(this.state.year)) {
                list.push(this.state.year);
            }

            return list.sort((a, b) => b - a).map((year) => ({value: String(year), label: `${year + 543} (${year})`}));
        };

        if (['profit', 'income', 'expenses'].includes(report)) {
            controls.push(selectFilter({
                label: 'ปี',
                value: String(this.state.year),
                options: yearOptions(this.data?.years || []),
                onChange: (value) => this.update({year: Number(value)})
            }));
        }

        if (report === 'expenses') {
            controls.push(selectFilter({
                label: 'เดือน',
                value: this.state.month,
                options: [{value: '', label: 'ทั้งปี'}].concat(
                    Array.from({length: 12}, (_, index) => ({value: String(index + 1), label: monthLabel(index + 1, true)}))
                ),
                onChange: (value) => this.update({month: value})
            }));
        }

        if (['payments', 'maintenance'].includes(report)) {
            controls.push(
                inputFilter({label: 'ตั้งแต่', type: 'date', value: this.state.from, onChange: (value) => this.update({from: value})}),
                inputFilter({label: 'ถึง', type: 'date', value: this.state.to, onChange: (value) => this.update({to: value})})
            );
        }

        if (report === 'payments') {
            controls.push(selectFilter({
                label: 'สถานะ',
                value: this.state.status,
                options: labels.toOptions(labels.paymentStatus, 'ทุกสถานะ'),
                onChange: (value) => this.update({status: value})
            }));
        }

        if (report === 'tenants') {
            controls.push(selectFilter({
                label: 'สถานะ',
                value: this.state.status,
                options: labels.toOptions(labels.tenantStatus, 'ทุกสถานะ'),
                onChange: (value) => this.update({status: value})
            }));
        }

        if (report === 'contracts') {
            controls.push(selectFilter({
                label: 'ช่วงเวลา',
                value: String(this.state.days),
                options: [30, 60, 90, 180].map((days) => ({value: String(days), label: `ภายใน ${days} วัน`})),
                onChange: (value) => this.update({days: Number(value)})
            }));
        }

        this.toolbarElement.replaceChildren(controls.length ? toolbar(...controls) : '');
    }

    update(partial) {
        this.state = {...this.state, ...partial};
        this.syncUrl();
        this.load();
    }

    async load() {
        const signal = this.signal();
        this.body.replaceChildren(loadingState('กำลังคำนวณรายงาน...'));

        const {report} = this.state;
        const endpoints = {
            profit: `/reports/profit?year=${this.state.year}`,
            income: `/reports/income?year=${this.state.year}`,
            expenses: `/reports/expenses?year=${this.state.year}&month=${this.state.month}`,
            occupancy: '/reports/occupancy',
            tenants: `/reports/tenants?status=${this.state.status}`,
            payments: `/reports/payments?from=${this.state.from}&to=${this.state.to}&status=${this.state.status}`,
            maintenance: `/reports/maintenance?from=${this.state.from}&to=${this.state.to}`,
            contracts: `/reports/contracts?days=${this.state.days}`
        };

        try {
            const response = await this.api.get(endpoints[report], {signal});
            this.data = response.data;

            if (this.data.years) {
                this.renderToolbar();
            }

            this[`render_${report}`](this.data);
        } catch (error) {
            if (this.isAbort(error)) {
                return;
            }

            this.body.replaceChildren(errorState(error.message, () => this.load()));
        }
    }

    tiles(...items) {
        const box = document.createElement('div');
        box.className = 'stat-tiles';
        box.append(...items);

        return box;
    }

    /* รายงานแต่ละประเภท ----------------------------------------------------- */

    render_profit(data) {
        const totals = data.totals;
        const chartCard = card({title: `รายรับ-รายจ่ายรายเดือน ปี ${data.year + 543}`});
        const chartBox = document.createElement('div');
        chartBox.className = 'bar-chart report-chart';
        chartCard.body.append(chartBox);

        this.body.replaceChildren(
            this.tiles(
                statTile({label: 'รายรับรวม', value: formatCurrency(totals.income), variant: 'green', iconName: 'coins'}),
                statTile({label: 'รายจ่ายรวม', value: formatCurrency(totals.expenses), variant: 'pink', iconName: 'receipt'}),
                statTile({label: 'กำไรสุทธิ', value: formatCurrency(totals.net_profit), variant: totals.net_profit >= 0 ? 'blue' : 'pink', iconName: totals.net_profit >= 0 ? 'trendUp' : 'trendDown'})
            ),
            chartCard,
            card({
                title: 'ตารางสรุปรายเดือน',
                content: createTable({
                    compact: true,
                    rows: data.months,
                    rowKey: 'period',
                    columns: [
                        {key: 'month', label: 'เดือน', render: (row) => monthLabel(row.month, true)},
                        {key: 'income', label: 'รายรับ', align: 'right', render: (row) => formatCurrency(row.income)},
                        {key: 'expenses', label: 'รายจ่าย', align: 'right', render: (row) => formatCurrency(row.expenses)},
                        {key: 'net_profit', label: 'กำไรสุทธิ', align: 'right', render: (row) => {
                            const value = document.createElement('strong');
                            value.className = row.net_profit < 0 ? 'text-danger' : 'text-success';
                            value.textContent = formatCurrency(row.net_profit);
                            return value;
                        }}
                    ]
                })
            })
        );

        const currentPeriod = today().slice(0, 7);
        new BarChart(chartBox).render(data.months.map((row) => ({
            period: row.period,
            income: row.income,
            expenses: row.expenses,
            is_current: row.period === currentPeriod
        })));
    }

    render_income(data) {
        const totals = data.totals;

        this.body.replaceChildren(
            this.tiles(
                statTile({label: 'รายรับรวมทั้งปี', value: formatCurrency(totals.total), variant: 'green', iconName: 'coins'}),
                statTile({label: 'ค่าเช่า', value: formatCurrency(totals.rent), variant: 'blue', iconName: 'home'}),
                statTile({label: 'ค่าน้ำ-ค่าไฟ', value: formatCurrency(totals.electricity + totals.water), variant: 'orange', iconName: 'receipt'}),
                statTile({label: 'อื่น ๆ', value: formatCurrency(totals.other), variant: 'gray', iconName: 'tag'})
            ),
            card({
                title: `รายรับแยกตามประเภท ปี ${data.year + 543}`,
                content: createTable({
                    compact: true,
                    rows: data.months,
                    rowKey: 'period',
                    columns: [
                        {key: 'month', label: 'เดือน', render: (row) => monthLabel(row.month, true)},
                        {key: 'rent', label: 'ค่าเช่า', align: 'right', render: (row) => formatCurrency(row.rent)},
                        {key: 'electricity', label: 'ค่าไฟ', align: 'right', render: (row) => formatCurrency(row.electricity)},
                        {key: 'water', label: 'ค่าน้ำ', align: 'right', render: (row) => formatCurrency(row.water)},
                        {key: 'other', label: 'อื่น ๆ', align: 'right', render: (row) => formatCurrency(row.other)},
                        {key: 'total', label: 'รวม', align: 'right', render: (row) => {
                            const value = document.createElement('strong');
                            value.textContent = formatCurrency(row.total);
                            return value;
                        }},
                        {key: 'payment_count', label: 'จำนวนรายการ', align: 'right'}
                    ]
                })
            })
        );
    }

    render_expenses(data) {
        const period = data.month ? `${monthLabel(data.month, true)} ${data.year + 543}` : `ปี ${data.year + 543}`;
        const categoryTotal = data.categories.reduce((sum, row) => sum + row.total, 0);

        this.body.replaceChildren(
            this.tiles(
                statTile({label: `รายจ่ายรวม ${period}`, value: formatCurrency(data.month ? categoryTotal : data.total), variant: 'pink', iconName: 'receipt'}),
                statTile({label: 'จำนวนรายการ', value: `${data.categories.reduce((sum, row) => sum + row.expense_count, 0)} รายการ`, variant: 'gray', iconName: 'tag'})
            ),
            card({
                title: `รายจ่ายแยกตามหมวดหมู่ (${period})`,
                content: createTable({
                    compact: true,
                    rows: data.categories,
                    rowKey: 'category',
                    emptyMessage: 'ไม่มีรายจ่ายในช่วงเวลานี้',
                    columns: [
                        {key: 'category', label: 'หมวดหมู่', render: (row) => labels.expenseCategory[row.category] || row.category},
                        {key: 'expense_count', label: 'จำนวนรายการ', align: 'right'},
                        {key: 'total', label: 'ยอดรวม', align: 'right', render: (row) => formatCurrency(row.total)},
                        {key: 'share', label: 'สัดส่วน', align: 'right', render: (row) => categoryTotal ? `${((row.total / categoryTotal) * 100).toFixed(1)}%` : '-'}
                    ]
                })
            }),
            card({
                title: `รายจ่ายรายเดือน ปี ${data.year + 543}`,
                content: createTable({
                    compact: true,
                    rows: data.months,
                    rowKey: 'period',
                    columns: [
                        {key: 'month', label: 'เดือน', render: (row) => monthLabel(row.month, true)},
                        {key: 'expense_count', label: 'จำนวนรายการ', align: 'right'},
                        {key: 'total', label: 'ยอดรวม', align: 'right', render: (row) => formatCurrency(row.total)}
                    ]
                })
            })
        );
    }

    render_occupancy(data) {
        const summary = data.summary;

        this.body.replaceChildren(
            this.tiles(
                statTile({label: 'อัตราการเข้าพัก', value: `${summary.occupancy_rate}%`, meta: `${summary.occupied}/${summary.total} ห้อง`, variant: 'green', iconName: 'home'}),
                statTile({label: 'ห้องว่าง', value: `${summary.available} ห้อง`, variant: 'blue', iconName: 'doorOpen'}),
                statTile({label: 'ค่าเช่าที่เก็บได้/เดือน', value: formatCurrency(summary.occupied_rent), meta: `จากศักยภาพ ${formatCurrency(summary.potential_rent)}`, variant: 'orange', iconName: 'coins'})
            ),
            card({
                title: 'อัตราการเข้าพักแยกตามชั้น',
                content: createTable({
                    compact: true,
                    rows: data.floors,
                    rowKey: 'floor',
                    emptyMessage: 'ยังไม่มีข้อมูลห้องพัก',
                    columns: [
                        {key: 'floor', label: 'ชั้น', render: (row) => `ชั้น ${row.floor}`},
                        {key: 'total', label: 'ห้องทั้งหมด', align: 'right'},
                        {key: 'occupied', label: 'มีผู้เช่า', align: 'right'},
                        {key: 'available', label: 'ว่าง', align: 'right'},
                        {key: 'maintenance', label: 'ซ่อม/จอง', align: 'right', render: (row) => row.maintenance + row.reserved},
                        {key: 'occupancy_rate', label: 'อัตราเข้าพัก', align: 'right', render: (row) => {
                            const box = document.createElement('div');
                            box.className = 'progress-cell';
                            const bar = document.createElement('span');
                            bar.className = 'progress-bar';
                            bar.style.setProperty('--value', `${row.occupancy_rate}%`);
                            const text = document.createElement('span');
                            text.textContent = `${row.occupancy_rate}%`;
                            box.append(bar, text);
                            return box;
                        }},
                        {key: 'occupied_rent', label: 'ค่าเช่า/เดือน', align: 'right', render: (row) => formatCurrency(row.occupied_rent)}
                    ]
                })
            })
        );
    }

    render_tenants(data) {
        this.body.replaceChildren(
            this.tiles(
                statTile({label: 'ผู้เช่าทั้งหมด', value: `${data.summary.count} คน`, variant: 'green', iconName: 'users'}),
                statTile({label: 'มีห้องพัก', value: `${data.summary.with_room} คน`, variant: 'blue', iconName: 'home'})
            ),
            card({
                title: 'รายงานผู้เช่า',
                content: createTable({
                    compact: true,
                    rows: data.items,
                    emptyMessage: 'ไม่พบผู้เช่า',
                    onRowClick: (row) => this.router.navigate(`/tenants/${row.id}`),
                    columns: [
                        {key: 'tenant_name', label: 'ผู้เช่า'},
                        {key: 'phone', label: 'เบอร์โทร'},
                        {key: 'room_number', label: 'ห้อง', render: (row) => row.room_number ? `ห้อง ${row.room_number}` : '-'},
                        {key: 'end_date', label: 'สัญญาถึง', render: (row) => row.end_date ? formatDate(row.end_date) : '-'},
                        {key: 'monthly_rent', label: 'ค่าเช่า', align: 'right', render: (row) => row.monthly_rent !== null ? formatCurrency(row.monthly_rent) : '-'},
                        {key: 'total_paid', label: 'ชำระแล้วรวม', align: 'right', render: (row) => formatCurrency(row.total_paid)},
                        {key: 'total_pending', label: 'ค้างชำระ', align: 'right', render: (row) => row.total_pending > 0 ? badge(formatCurrency(row.total_pending), 'orange') : '-'},
                        {key: 'status', label: 'สถานะ', render: (row) => statusBadge(labels.tenantStatus, row.status)}
                    ]
                })
            })
        );
    }

    render_payments(data) {
        this.body.replaceChildren(
            this.tiles(
                statTile({label: 'ชำระแล้ว', value: formatCurrency(data.summary.paid), variant: 'green', iconName: 'checkCircle'}),
                statTile({label: 'รอชำระ', value: formatCurrency(data.summary.pending), variant: 'orange', iconName: 'clock'}),
                statTile({label: 'ยกเลิก', value: formatCurrency(data.summary.cancelled), variant: 'gray', iconName: 'xCircle'}),
                statTile({label: 'จำนวนรายการ', value: `${data.summary.count} รายการ`, variant: 'blue', iconName: 'receipt'})
            ),
            card({
                title: `การชำระเงิน ${formatDate(data.from)} - ${formatDate(data.to)}`,
                content: createTable({
                    compact: true,
                    rows: data.items,
                    emptyMessage: 'ไม่มีรายการในช่วงเวลานี้',
                    columns: [
                        {key: 'payment_date', label: 'วันที่', render: (row) => row.payment_date ? formatDateTime(row.payment_date) : '-'},
                        {key: 'tenant_name', label: 'ผู้เช่า'},
                        {key: 'room_number', label: 'ห้อง', render: (row) => `ห้อง ${row.room_number}`},
                        {key: 'payment_type', label: 'ประเภท', render: (row) => labels.paymentType[row.payment_type] || row.payment_type},
                        {key: 'period', label: 'งวด', render: (row) => `${row.period_month}/${row.period_year + 543}`},
                        {key: 'amount', label: 'จำนวน', align: 'right', render: (row) => formatCurrency(row.amount)},
                        {key: 'reference', label: 'อ้างอิง', render: (row) => row.reference || '-'},
                        {key: 'status', label: 'สถานะ', render: (row) => statusBadge(labels.paymentStatus, row.status)}
                    ]
                })
            })
        );
    }

    render_maintenance(data) {
        this.body.replaceChildren(
            this.tiles(
                statTile({label: 'งานทั้งหมด', value: `${data.summary.count} รายการ`, variant: 'blue', iconName: 'wrench'}),
                statTile({label: 'ค้างดำเนินการ', value: `${data.summary.pending + data.summary.in_progress} รายการ`, variant: 'orange', iconName: 'clock'}),
                statTile({label: 'เสร็จสิ้น', value: `${data.summary.completed} รายการ`, variant: 'green', iconName: 'checkCircle'}),
                statTile({label: 'ค่าใช้จ่ายรวม', value: formatCurrency(data.summary.total_cost), variant: 'pink', iconName: 'coins'})
            ),
            card({
                title: `งานซ่อม ${formatDate(data.from)} - ${formatDate(data.to)}`,
                content: createTable({
                    compact: true,
                    rows: data.items,
                    emptyMessage: 'ไม่มีงานซ่อมในช่วงเวลานี้',
                    columns: [
                        {key: 'reported_at', label: 'แจ้งเมื่อ', render: (row) => formatDateTime(row.reported_at)},
                        {key: 'room_number', label: 'ห้อง', render: (row) => `ห้อง ${row.room_number}`},
                        {key: 'title', label: 'รายการ'},
                        {key: 'priority', label: 'ความสำคัญ', render: (row) => statusBadge(labels.maintenancePriority, row.priority)},
                        {key: 'assigned_to', label: 'ผู้รับผิดชอบ', render: (row) => row.assigned_to || '-'},
                        {key: 'completed_at', label: 'เสร็จเมื่อ', render: (row) => row.completed_at ? formatDate(row.completed_at) : '-'},
                        {key: 'cost', label: 'ค่าใช้จ่าย', align: 'right', render: (row) => formatCurrency(row.cost)},
                        {key: 'status', label: 'สถานะ', render: (row) => statusBadge(labels.maintenanceStatus, row.status)}
                    ]
                })
            })
        );
    }

    render_contracts(data) {
        this.body.replaceChildren(
            this.tiles(
                statTile({label: `หมดอายุภายใน ${data.days} วัน`, value: `${data.summary.count} ฉบับ`, variant: 'orange', iconName: 'calendarCheck'}),
                statTile({label: 'เลยกำหนดแล้ว', value: `${data.summary.overdue} ฉบับ`, variant: 'pink', iconName: 'alert'})
            ),
            card({
                title: 'สัญญาที่ต้องติดตาม',
                content: createTable({
                    compact: true,
                    rows: data.items,
                    emptyMessage: 'ไม่มีสัญญาที่ใกล้หมดอายุในช่วงนี้',
                    onRowClick: (row) => this.router.navigate(`/contracts/${row.id}`),
                    columns: [
                        {key: 'room_number', label: 'ห้อง', render: (row) => `ห้อง ${row.room_number}`},
                        {key: 'tenant_name', label: 'ผู้เช่า'},
                        {key: 'tenant_phone', label: 'เบอร์โทร', render: (row) => row.tenant_phone || '-'},
                        {key: 'end_date', label: 'วันสิ้นสุด', render: (row) => formatDate(row.end_date)},
                        {key: 'days_remaining', label: 'คงเหลือ', render: (row) => row.days_remaining < 0
                            ? badge(`เลยกำหนด ${Math.abs(row.days_remaining)} วัน`, 'red')
                            : badge(`${row.days_remaining} วัน`, row.days_remaining <= 15 ? 'orange' : 'blue')},
                        {key: 'monthly_rent', label: 'ค่าเช่า', align: 'right', render: (row) => formatCurrency(row.monthly_rent)}
                    ]
                })
            })
        );
    }

    /* ส่งออก CSV ------------------------------------------------------------- */

    exportCsv() {
        if (!this.data) {
            this.toast.warning('ยังไม่มีข้อมูลสำหรับส่งออก');
            return;
        }

        const {report} = this.state;
        const data = this.data;
        const stamp = today();
        let headers = [];
        let rows = [];

        switch (report) {
            case 'profit':
                headers = ['เดือน', 'รายรับ', 'รายจ่าย', 'กำไรสุทธิ'];
                rows = data.months.map((row) => [row.period, row.income, row.expenses, row.net_profit]);
                break;
            case 'income':
                headers = ['เดือน', 'ค่าเช่า', 'ค่าไฟ', 'ค่าน้ำ', 'อื่น ๆ', 'รวม', 'จำนวนรายการ'];
                rows = data.months.map((row) => [row.period, row.rent, row.electricity, row.water, row.other, row.total, row.payment_count]);
                break;
            case 'expenses':
                headers = ['หมวดหมู่', 'จำนวนรายการ', 'ยอดรวม'];
                rows = data.categories.map((row) => [labels.expenseCategory[row.category] || row.category, row.expense_count, row.total]);
                break;
            case 'occupancy':
                headers = ['ชั้น', 'ห้องทั้งหมด', 'มีผู้เช่า', 'ว่าง', 'ซ่อมบำรุง', 'จองแล้ว', 'อัตราเข้าพัก (%)', 'ค่าเช่าที่เก็บได้'];
                rows = data.floors.map((row) => [row.floor, row.total, row.occupied, row.available, row.maintenance, row.reserved, row.occupancy_rate, row.occupied_rent]);
                break;
            case 'tenants':
                headers = ['ผู้เช่า', 'เบอร์โทร', 'เพศ', 'ห้อง', 'เริ่มสัญญา', 'สิ้นสุดสัญญา', 'ค่าเช่า', 'ชำระแล้วรวม', 'ค้างชำระ', 'สถานะ'];
                rows = data.items.map((row) => [row.tenant_name, row.phone, labels.gender[row.gender] || '', row.room_number || '', row.start_date || '', row.end_date || '', row.monthly_rent ?? '', row.total_paid, row.total_pending, labels.tenantStatus[row.status]?.label || row.status]);
                break;
            case 'payments':
                headers = ['วันที่ชำระ', 'ผู้เช่า', 'ห้อง', 'ประเภท', 'งวดเดือน', 'งวดปี', 'จำนวน', 'อ้างอิง', 'สถานะ'];
                rows = data.items.map((row) => [row.payment_date || '', row.tenant_name, row.room_number, labels.paymentType[row.payment_type] || row.payment_type, row.period_month, row.period_year, row.amount, row.reference || '', labels.paymentStatus[row.status]?.label || row.status]);
                break;
            case 'maintenance':
                headers = ['แจ้งเมื่อ', 'ห้อง', 'รายการ', 'ความสำคัญ', 'ผู้รับผิดชอบ', 'เสร็จเมื่อ', 'ค่าใช้จ่าย', 'สถานะ'];
                rows = data.items.map((row) => [row.reported_at, row.room_number, row.title, labels.maintenancePriority[row.priority]?.label || row.priority, row.assigned_to || '', row.completed_at || '', row.cost, labels.maintenanceStatus[row.status]?.label || row.status]);
                break;
            case 'contracts':
                headers = ['ห้อง', 'ผู้เช่า', 'เบอร์โทร', 'เริ่มสัญญา', 'สิ้นสุดสัญญา', 'คงเหลือ (วัน)', 'ค่าเช่า'];
                rows = data.items.map((row) => [row.room_number, row.tenant_name, row.tenant_phone || '', row.start_date, row.end_date, row.days_remaining, row.monthly_rent]);
                break;
            default:
                return;
        }

        downloadCsv(`dormplus-${report}-${stamp}.csv`, headers, rows);
        this.toast.success('ส่งออกไฟล์ CSV เรียบร้อยแล้ว');
    }
}
