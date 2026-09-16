import {ApiClient} from './core/ApiClient.js';
import {basePath} from './core/Paths.js';
import {Router} from './core/Router.js';
import {StateManager} from './core/StateManager.js';
import {Auth} from './modules/Auth.js';
import {Contracts} from './modules/Contracts.js';
import {Dashboard} from './modules/Dashboard.js';
import {Maintenance} from './modules/Maintenance.js';
import {Messages} from './modules/Messages.js';
import {Notifications} from './modules/Notifications.js';
import {Payments} from './modules/Payments.js';
import {Reports} from './modules/Reports.js';
import {Rooms} from './modules/Rooms.js';
import {Settings} from './modules/Settings.js';
import {Shell} from './modules/Shell.js';
import {Tenants} from './modules/Tenants.js';
import {Toast} from './ui/Toast.js';

/**
 * เส้นทางของแอป: path → [ชื่อหน้า, โมดูล]
 * โมดูลเดียวกันรับผิดชอบทั้งหน้ารายการและหน้ารายละเอียด (/rooms และ /rooms/:id)
 */
class App {
    constructor() {
        this.root = document.querySelector('#app');
        this.api = new ApiClient(`${basePath}api`);
        this.router = new Router();
        this.state = new StateManager({
            user: null,
            authenticated: false,
            initialized: false
        });

        this.toast = new Toast(
            document.querySelector('#toast-container')
        );

        const context = {
            root: this.root,
            api: this.api,
            state: this.state,
            router: this.router,
            toast: this.toast
        };

        this.auth = new Auth(context);
        this.shell = new Shell(context);

        const moduleContext = {...context, shell: this.shell};

        this.modules = {
            dashboard: new Dashboard(moduleContext),
            rooms: new Rooms(moduleContext),
            tenants: new Tenants(moduleContext),
            contracts: new Contracts(moduleContext),
            payments: new Payments(moduleContext),
            maintenance: new Maintenance(moduleContext),
            reports: new Reports(moduleContext),
            messages: new Messages(moduleContext),
            notifications: new Notifications(moduleContext),
            settings: new Settings(moduleContext)
        };

        this.activeModule = null;
    }

    async start() {
        await this.restoreSession();
        this.configureRoutes();

        if (!window.location.hash) {
            this.router.navigate(
                this.state.get().authenticated ? '/dashboard' : '/login',
                true
            );
        } else {
            this.router.start();
        }
    }

    async restoreSession() {
        try {
            const response = await this.api.get('/auth/me');

            this.api.setCsrfToken(response.data.csrf_token);
            this.state.set({
                user: response.data.user,
                authenticated: true,
                initialized: true
            });
        } catch (error) {
            if (error.status !== 401 && error.status !== 0) {
                this.toast.error(error.message);
            }

            this.api.setCsrfToken(null);
            this.state.set({
                user: null,
                authenticated: false,
                initialized: true
            });
        }
    }

    configureRoutes() {
        this.router.add('/login', () => {
            if (this.state.get().authenticated) {
                this.router.navigate('/dashboard', true);
                return;
            }

            document.title = 'เข้าสู่ระบบ — DormPlus';
            this.leaveModule();

            if (this.shell.mounted) {
                this.shell.unmount();
            }

            this.auth.render();
        });

        const pages = [
            ['/dashboard', 'หน้าหลัก', 'dashboard'],
            ['/rooms', 'ห้องพัก', 'rooms'],
            ['/rooms/:id', 'รายละเอียดห้องพัก', 'rooms'],
            ['/tenants', 'ผู้เช่า', 'tenants'],
            ['/tenants/:id', 'รายละเอียดผู้เช่า', 'tenants'],
            ['/contracts', 'สัญญาเช่า', 'contracts'],
            ['/contracts/:id', 'รายละเอียดสัญญาเช่า', 'contracts'],
            ['/payments', 'การเงิน', 'payments'],
            ['/maintenance', 'แจ้งซ่อม', 'maintenance'],
            ['/maintenance/:id', 'รายละเอียดงานซ่อม', 'maintenance'],
            ['/reports', 'รายงาน', 'reports'],
            ['/messages', 'ข้อความ', 'messages'],
            ['/messages/:id', 'ข้อความ', 'messages'],
            ['/notifications', 'การแจ้งเตือน', 'notifications'],
            ['/settings', 'ตั้งค่า', 'settings']
        ];

        for (const [path, title, moduleName] of pages) {
            this.router.add(path, async ({path: currentPath, params, query}) => {
                if (!this.requireAuthentication()) {
                    return;
                }

                this.ensureShell();
                this.shell.setActiveRoute(currentPath);
                document.title = `${title} — DormPlus`;

                const module = this.modules[moduleName];

                if (this.activeModule && this.activeModule !== module) {
                    this.activeModule.stop();
                }

                this.activeModule = module;

                try {
                    await module.render({params, query});
                } catch (error) {
                    console.error(error);
                    this.toast.error('ไม่สามารถแสดงหน้านี้ได้ กรุณาลองใหม่อีกครั้ง');
                }

                this.shell.contentElement()?.focus({preventScroll: true});
                window.scrollTo({top: 0});
            });
        }

        this.router.setNotFound(() => {
            if (!this.state.get().authenticated) {
                this.router.navigate('/login', true);
                return;
            }

            this.ensureShell();
            this.leaveModule();
            this.shell.setActiveRoute('/dashboard');
            this.shell.showComingSoon('ไม่พบหน้าที่ต้องการ');
        });
    }

    requireAuthentication() {
        if (!this.state.get().authenticated) {
            this.router.navigate('/login', true);
            return false;
        }

        return true;
    }

    ensureShell() {
        if (!this.shell.mounted) {
            this.shell.mount();
        }
    }

    leaveModule() {
        if (this.activeModule) {
            this.activeModule.stop();
            this.activeModule = null;
        }
    }
}

const app = new App();

app.start().catch((error) => {
    console.error(error);

    const root = document.querySelector('#app');
    root.replaceChildren();

    const message = document.createElement('main');
    message.className = 'app-loading';

    const heading = document.createElement('h1');
    const description = document.createElement('p');

    heading.textContent = 'ไม่สามารถเปิด DormPlus ได้';
    description.textContent = 'กรุณารีเฟรชหน้าและลองใหม่อีกครั้ง';

    message.append(heading, description);
    root.append(message);
});
