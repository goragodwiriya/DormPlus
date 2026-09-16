import {Module} from './Module.js';
import {card, pageHeader} from '../ui/Controls.js';
import {createForm} from '../ui/Form.js';
import {errorState, loadingState} from '../ui/States.js';

export class Settings extends Module {
    async render() {
        const content = this.content();
        content.replaceChildren(loadingState());

        const signal = this.signal();

        try {
            const response = await this.api.get('/settings', {signal});
            this.renderPage(response.data);
        } catch (error) {
            if (this.isAbort(error)) {
                return;
            }

            content.replaceChildren(errorState(error.message, () => this.render()));
        }
    }

    renderPage({property, settings}) {
        const content = this.content();
        content.replaceChildren();

        const page = document.createElement('div');
        page.className = 'page';

        page.append(pageHeader({
            title: 'ตั้งค่า',
            subtitle: 'ข้อมูลหอพัก ค่าเช่าเริ่มต้น และการตั้งค่าระบบ'
        }));

        const grid = document.createElement('div');
        grid.className = 'settings-grid';

        // ข้อมูลหอพัก
        const propertyCard = card({title: 'ข้อมูลหอพัก'});
        propertyCard.body.append(createForm({
            values: property,
            submitLabel: 'บันทึกข้อมูลหอพัก',
            fields: [
                {name: 'name', label: 'ชื่อหอพัก', required: true, span: 2, maxLength: 120},
                {name: 'address', label: 'ที่อยู่', type: 'textarea', span: 2, rows: 2},
                {name: 'phone', label: 'เบอร์โทร', type: 'tel'},
                {name: 'email', label: 'อีเมล', type: 'email'},
                {name: 'description', label: 'คำอธิบาย', type: 'textarea', span: 2, rows: 2}
            ],
            onSubmit: async (values) => {
                const response = await this.api.put('/settings/property', values);
                this.toast.success('บันทึกข้อมูลหอพักเรียบร้อยแล้ว');
                this.shell.setPropertyName(response.data.property.name);
            }
        }).element);

        // ค่าเช่า
        const rentalCard = card({title: 'การตั้งค่าค่าเช่า'});
        rentalCard.body.append(createForm({
            values: settings,
            submitLabel: 'บันทึกการตั้งค่าค่าเช่า',
            fields: [
                {name: 'default_monthly_rent', label: 'ค่าเช่าเริ่มต้น (บาท/เดือน)', type: 'number', required: true, min: 0},
                {name: 'payment_due_day', label: 'วันครบกำหนดชำระ (วันที่ของเดือน)', type: 'number', required: true, min: 1, max: 31, step: 1},
                {name: 'electricity_rate', label: 'ค่าไฟ (บาท/หน่วย)', type: 'number', required: true, min: 0},
                {name: 'water_rate', label: 'ค่าน้ำ (บาท/หน่วย)', type: 'number', required: true, min: 0},
                {name: 'contract_warning_days', label: 'แจ้งเตือนก่อนหมดสัญญา (วัน)', type: 'number', min: 1, max: 365, step: 1}
            ],
            onSubmit: async (values) => {
                await this.api.put('/settings/rental', values);
                this.toast.success('บันทึกการตั้งค่าค่าเช่าเรียบร้อยแล้ว');
            }
        }).element);

        // ระบบ
        const systemCard = card({title: 'การตั้งค่าระบบ'});
        systemCard.body.append(createForm({
            values: settings,
            submitLabel: 'บันทึกการตั้งค่าระบบ',
            fields: [
                {name: 'language', label: 'ภาษา', type: 'select', options: [{value: 'th', label: 'ไทย'}, {value: 'en', label: 'English'}]},
                {name: 'date_format', label: 'รูปแบบวันที่', type: 'select', options: [
                    {value: 'd/m/Y', label: 'วัน/เดือน/ปี (16/09/2569)'},
                    {value: 'Y-m-d', label: 'ปี-เดือน-วัน (2026-09-16)'},
                    {value: 'd M Y', label: 'วัน เดือน ปี (16 ก.ย. 2569)'}
                ]},
                {name: 'currency', label: 'สกุลเงิน', type: 'select', options: [{value: 'THB', label: 'บาท (THB)'}, {value: 'USD', label: 'ดอลลาร์สหรัฐ (USD)'}]}
            ],
            onSubmit: async (values) => {
                await this.api.put('/settings/system', values);
                this.toast.success('บันทึกการตั้งค่าระบบเรียบร้อยแล้ว');
            }
        }).element);

        // บัญชีผู้ใช้
        const user = this.shell.state.get().user;
        const accountCard = card({title: 'บัญชีผู้ใช้'});
        accountCard.body.append(createForm({
            values: user,
            submitLabel: 'บันทึกชื่อ',
            fields: [
                {name: 'first_name', label: 'ชื่อ', required: true},
                {name: 'last_name', label: 'นามสกุล', required: true}
            ],
            onSubmit: async (values) => {
                const response = await this.api.put('/auth/profile', values);
                this.shell.state.set({user: response.data.user});
                this.shell.refreshUser();
                this.toast.success('บันทึกชื่อผู้ใช้เรียบร้อยแล้ว');
            }
        }).element);

        const passwordHeading = document.createElement('h3');
        passwordHeading.className = 'section-title';
        passwordHeading.textContent = 'เปลี่ยนรหัสผ่าน';
        accountCard.body.append(passwordHeading);

        const passwordForm = createForm({
            submitLabel: 'เปลี่ยนรหัสผ่าน',
            fields: [
                {name: 'current_password', label: 'รหัสผ่านปัจจุบัน', type: 'password', required: true, span: 2, autocomplete: 'current-password'},
                {name: 'new_password', label: 'รหัสผ่านใหม่', type: 'password', required: true, autocomplete: 'new-password', help: 'อย่างน้อย 8 ตัวอักษร'},
                {
                    name: 'confirm_password',
                    label: 'ยืนยันรหัสผ่านใหม่',
                    type: 'password',
                    required: true,
                    autocomplete: 'new-password',
                    validate: (value, inputs) => value !== inputs.get('new_password').value ? 'รหัสผ่านใหม่ไม่ตรงกัน' : ''
                }
            ],
            onSubmit: async (values, form) => {
                await this.api.put('/auth/password', values);
                form.form.reset();
                this.toast.success('เปลี่ยนรหัสผ่านเรียบร้อยแล้ว');
            }
        });
        accountCard.body.append(passwordForm.element);

        grid.append(propertyCard, rentalCard, systemCard, accountCard);
        page.append(grid);
        content.append(page);
    }
}
