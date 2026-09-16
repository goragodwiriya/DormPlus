const SVG_NAMESPACE = 'http://www.w3.org/2000/svg';

function svgElement(name, attributes = {}) {
    const element = document.createElementNS(SVG_NAMESPACE, name);

    for (const [key, value] of Object.entries(attributes)) {
        element.setAttribute(key, String(value));
    }

    return element;
}

export class BarChart {
    constructor(container) {
        this.container = container;
        this.resizeObserver = new ResizeObserver(() => {
            if (this.data) {
                this.render(this.data);
            }
        });

        this.resizeObserver.observe(container);
    }

    render(data) {
        this.data = data;
        this.container.replaceChildren();

        const width = Math.max(this.container.clientWidth, 320);
        const height = 250;
        const margin = {
            top: 18,
            right: 12,
            bottom: 42,
            left: 54
        };

        const plotWidth = width - margin.left - margin.right;
        const plotHeight = height - margin.top - margin.bottom;
        const maximumValue = Math.max(
            ...data.flatMap((item) => [item.income, item.expenses]),
            1
        );

        const tick = maximumValue > 400000 ? 100000 : 50000;
        const roundedMaximum = Math.max(
            Math.ceil(maximumValue / tick) * tick,
            tick
        );
        const svg = svgElement('svg', {
            viewBox: `0 0 ${width} ${height}`,
            role: 'img',
            'aria-label': 'กราฟรายรับและรายจ่ายรายเดือน'
        });

        const steps = roundedMaximum / tick;

        for (let index = 0; index <= steps; index++) {
            const value = (roundedMaximum / steps) * index;
            const y = margin.top + plotHeight - (plotHeight / steps) * index;

            svg.append(
                svgElement('line', {
                    x1: margin.left,
                    y1: y,
                    x2: width - margin.right,
                    y2: y,
                    stroke: '#e8e0c9',
                    'stroke-width': 1
                })
            );

            const label = svgElement('text', {
                x: margin.left - 9,
                y: y + 4,
                'text-anchor': 'end',
                fill: '#8d8371',
                'font-size': 11
            });

            label.textContent = new Intl.NumberFormat('th-TH', {
                maximumFractionDigits: 0
            }).format(value);

            svg.append(label);
        }

        const groupWidth = plotWidth / Math.max(data.length, 1);
        const barWidth = Math.min(30, groupWidth * 0.28);

        data.forEach((item, index) => {
            const centerX = margin.left + groupWidth * index + groupWidth / 2;
            const incomeHeight = (item.income / roundedMaximum) * plotHeight;
            const expenseHeight = (item.expenses / roundedMaximum) * plotHeight;

            const incomeBar = svgElement('rect', {
                x: centerX - barWidth - 3,
                y: margin.top + plotHeight - incomeHeight,
                width: barWidth,
                height: incomeHeight,
                rx: 4,
                fill: item.is_current ? '#4a723c' : '#7d9763'
            });

            const expenseBar = svgElement('rect', {
                x: centerX + 3,
                y: margin.top + plotHeight - expenseHeight,
                width: barWidth,
                height: expenseHeight,
                rx: 4,
                fill: item.is_current ? '#b3a78c' : '#cfc4a9'
            });

            const incomeTitle = svgElement('title');
            incomeTitle.textContent =
                `รายรับ ${this.monthLabel(item.period)}: ${this.money(item.income)} บาท`;
            incomeBar.append(incomeTitle);

            const expenseTitle = svgElement('title');
            expenseTitle.textContent =
                `รายจ่าย ${this.monthLabel(item.period)}: ${this.money(item.expenses)} บาท`;
            expenseBar.append(expenseTitle);

            const label = svgElement('text', {
                x: centerX,
                y: height - 13,
                'text-anchor': 'middle',
                fill: item.is_current ? '#3d6131' : '#9a8f78',
                'font-size': 12,
                'font-weight': item.is_current ? 700 : 500
            });

            label.textContent = this.monthLabel(item.period);

            svg.append(incomeBar, expenseBar, label);
        });

        this.container.append(svg);
    }

    monthLabel(period) {
        const [year, month] = period.split('-').map(Number);

        return new Intl.DateTimeFormat('th-TH', {
            month: 'short'
        }).format(new Date(year, month - 1, 1));
    }

    money(value) {
        return new Intl.NumberFormat('th-TH', {
            maximumFractionDigits: 0
        }).format(Number(value));
    }
}

export class DonutChart {
    constructor(container) {
        this.container = container;
    }

    render(data) {
        this.container.replaceChildren();

        const total = Math.max(Number(data.total), 1);
        const occupied = Number(data.occupied);
        const available = Number(data.available);
        const maintenance = Number(data.maintenance);
        const occupiedEnd = (occupied / total) * 100;
        const availableEnd = occupiedEnd + (available / total) * 100;
        const maintenanceEnd =
            availableEnd + (maintenance / total) * 100;

        const chart = document.createElement('div');
        chart.className = 'donut-chart';
        chart.setAttribute(
            'aria-label',
            `ห้องทั้งหมด ${data.total} ห้อง มีผู้เช่า ${occupied} ห้อง`
        );
        chart.setAttribute('role', 'img');
        chart.style.background = `
            conic-gradient(
                #557d3e 0% ${occupiedEnd}%,
                #c2cad1 ${occupiedEnd}% ${availableEnd}%,
                #c8891f ${availableEnd}% ${maintenanceEnd}%,
                #8a6bb8 ${maintenanceEnd}% 100%
            )
        `;

        const center = document.createElement('div');
        center.className = 'donut-center';

        const label = document.createElement('span');
        label.textContent = 'ทั้งหมด';

        const value = document.createElement('strong');
        value.textContent = `${data.total} ห้อง`;

        center.append(label, value);
        chart.append(center);
        this.container.append(chart);
    }
}