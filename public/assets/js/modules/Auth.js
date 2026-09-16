import {icon} from '../ui/Icons.js';

export class Auth {
    constructor({root, api, state, router, toast}) {
        this.root = root;
        this.api = api;
        this.state = state;
        this.router = router;
        this.toast = toast;
        this.submitting = false;
    }

    render() {
        document.body.classList.remove('sidebar-open');

        this.root.innerHTML = `
            <main class="auth-page">
                <section class="auth-panel" aria-labelledby="login-title">
                    <div class="auth-content">
                        <div class="auth-brand">
                            <span class="auth-brand-mark">
                                ${icon('home')}
                            </span>
                            <span>
                                <span class="auth-brand-name">DormPlus</span>
                                <span class="auth-brand-subtitle">
                                    จัดการหอพักอย่างเป็นระบบ
                                </span>
                            </span>
                        </div>

                        <h1 id="login-title" class="auth-title">
                            ยินดีต้อนรับกลับ
                        </h1>
                        <p class="auth-description">
                            เข้าสู่ระบบเพื่อจัดการห้องพัก ผู้เช่า
                            และข้อมูลการเงินของคุณ
                        </p>

                        <form id="login-form" novalidate>
                            <div class="form-group">
                                <label class="form-label" for="username">
                                    ชื่อผู้ใช้
                                </label>
                                <div class="input-with-icon">
                                    ${icon('user')}
                                    <input
                                        id="username"
                                        class="form-control"
                                        name="username"
                                        type="text"
                                        autocomplete="username"
                                        maxlength="80"
                                        required
                                    >
                                </div>
                                <p
                                    class="field-error"
                                    id="username-error"
                                ></p>
                            </div>

                            <div class="form-group">
                                <label class="form-label" for="password">
                                    รหัสผ่าน
                                </label>
                                <div class="input-with-icon input-with-toggle">
                                    ${icon('lock')}
                                    <input
                                        id="password"
                                        class="form-control"
                                        name="password"
                                        type="password"
                                        autocomplete="current-password"
                                        required
                                    >
                                    <button
                                        class="password-toggle"
                                        id="password-toggle"
                                        type="button"
                                        aria-label="แสดงรหัสผ่าน"
                                    >
                                        ${icon('eye')}
                                    </button>
                                </div>
                                <p
                                    class="field-error"
                                    id="password-error"
                                ></p>
                            </div>

                            <button
                                class="btn btn-primary auth-submit"
                                type="submit"
                            >
                                เข้าสู่ระบบ
                            </button>
                        </form>

                        <p class="auth-help">
                            หากไม่สามารถเข้าสู่ระบบได้
                            กรุณาติดต่อผู้ดูแลระบบของหอพัก
                        </p>
                    </div>
                </section>

                <section class="auth-visual" aria-label="แนะนำ DormPlus">
                    <div class="auth-visual-content">
                        <p class="auth-visual-brand">
                            Dorm<strong>Plus</strong>
                            <span>ระบบจัดการหอพัก</span>
                        </p>
                        <h2>ทุกเรื่องหอพัก จัดการได้ในที่เดียว</h2>
                        <p>
                            ดูสถานะห้องพัก ติดตามค่าเช่า จัดการสัญญา
                            และรับแจ้งซ่อมได้อย่างสะดวกด้วย DormPlus
                        </p>
                        <ul class="auth-features">
                            <li>
                                ${icon('building')}
                                <span>ห้องพักและสถานะการเข้าพักแบบเรียลไทม์</span>
                            </li>
                            <li>
                                ${icon('money')}
                                <span>ออกใบแจ้งหนี้และติดตามค่าเช่าได้ทันที</span>
                            </li>
                            <li>
                                ${icon('document')}
                                <span>สัญญาเช่าครบถ้วน พร้อมแจ้งเตือนก่อนหมดอายุ</span>
                            </li>
                            <li>
                                ${icon('wrench')}
                                <span>รับแจ้งซ่อมและติดตามงานช่างจนจบ</span>
                            </li>
                        </ul>
                    </div>
                </section>
            </main>
        `;

        this.bindEvents();
        this.root.querySelector('#username')?.focus();
    }

    bindEvents() {
        const form = this.root.querySelector('#login-form');
        const passwordInput = form.elements.password;
        const passwordToggle = this.root.querySelector('#password-toggle');

        passwordToggle.addEventListener('click', () => {
            const showing = passwordInput.type === 'text';
            passwordInput.type = showing ? 'password' : 'text';
            passwordToggle.setAttribute(
                'aria-label',
                showing ? 'แสดงรหัสผ่าน' : 'ซ่อนรหัสผ่าน'
            );
        });

        form.addEventListener('submit', (event) => {
            this.handleSubmit(event);
        });

        form.addEventListener('input', (event) => {
            if (event.target.matches('input')) {
                this.clearFieldError(event.target.name);
            }
        });
    }

    async handleSubmit(event) {
        event.preventDefault();

        if (this.submitting) {
            return;
        }

        const form = event.currentTarget;
        const username = form.elements.username.value.trim();
        const password = form.elements.password.value;
        const errors = {};

        if (!username) {
            errors.username = 'กรุณากรอกชื่อผู้ใช้';
        }

        if (!password) {
            errors.password = 'กรุณากรอกรหัสผ่าน';
        }

        this.showErrors(errors);

        if (Object.keys(errors).length > 0) {
            return;
        }

        const submitButton = form.querySelector('[type="submit"]');
        this.submitting = true;
        submitButton.disabled = true;
        submitButton.textContent = 'กำลังเข้าสู่ระบบ...';

        try {
            const response = await this.api.post('/auth/login', {
                username,
                password
            });

            this.api.setCsrfToken(response.data.csrf_token);
            this.state.set({
                user: response.data.user,
                authenticated: true
            });

            this.toast.success('เข้าสู่ระบบเรียบร้อยแล้ว');
            this.router.navigate('/dashboard', true);
        } catch (error) {
            if (error.fields && Object.keys(error.fields).length > 0) {
                this.showErrors(error.fields);
            } else {
                this.toast.error(error.message);
            }
        } finally {
            this.submitting = false;
            submitButton.disabled = false;
            submitButton.textContent = 'เข้าสู่ระบบ';
        }
    }

    showErrors(errors) {
        for (const field of ['username', 'password']) {
            const input = this.root.querySelector(`[name="${field}"]`);
            const error = this.root.querySelector(`#${field}-error`);
            const message = errors[field] || '';

            input.setAttribute(
                'aria-invalid',
                message ? 'true' : 'false'
            );
            input.setAttribute('aria-describedby', `${field}-error`);
            error.textContent = message;
        }
    }

    clearFieldError(field) {
        const input = this.root.querySelector(`[name="${field}"]`);
        const error = this.root.querySelector(`#${field}-error`);

        if (input) {
            input.setAttribute('aria-invalid', 'false');
        }

        if (error) {
            error.textContent = '';
        }
    }
}