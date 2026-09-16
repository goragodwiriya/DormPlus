export class Router {
    constructor() {
        this.routes = [];
        this.notFoundHandler = null;
        this.boundHandleChange = this.handleChange.bind(this);
    }

    add(pattern, handler, options = {}) {
        this.routes.push({
            pattern: this.normalize(pattern),
            handler,
            title: options.title || ''
        });

        return this;
    }

    setNotFound(handler) {
        this.notFoundHandler = handler;
        return this;
    }

    start() {
        window.addEventListener('hashchange', this.boundHandleChange);
        this.handleChange();
    }

    stop() {
        window.removeEventListener('hashchange', this.boundHandleChange);
    }

    navigate(path, replace = false, query = {}) {
        const target = this.buildHash(path, query);

        if (replace) {
            history.replaceState(null, '', target);
            this.handleChange();
            return;
        }

        if (window.location.hash === target) {
            this.handleChange();
            return;
        }

        window.location.hash = target;
    }

    currentPath() {
        const hash = window.location.hash.replace(/^#/, '').split('?')[0];
        return this.normalize(hash || '/dashboard');
    }

    /**
     * query string หลัง ? ใน hash เช่น #/tenants?new=1 → {new: '1'}
     */
    currentQuery() {
        const hash = window.location.hash.replace(/^#/, '');
        const index = hash.indexOf('?');

        if (index === -1) {
            return {};
        }

        return Object.fromEntries(new URLSearchParams(hash.slice(index + 1)));
    }

    /**
     * สร้าง hash พร้อม query: buildHash('/rooms', {status: 'available'})
     */
    buildHash(path, query = {}) {
        const parameters = new URLSearchParams();

        for (const [key, value] of Object.entries(query)) {
            if (value !== '' && value !== null && value !== undefined) {
                parameters.set(key, String(value));
            }
        }

        const search = parameters.toString();

        return `#${this.normalize(path)}${search ? `?${search}` : ''}`;
    }

    /**
     * แทนที่ query ของหน้าปัจจุบันโดยไม่ trigger route ใหม่
     */
    replaceQuery(query = {}) {
        history.replaceState(null, '', this.buildHash(this.currentPath(), query));
    }

    async handleChange() {
        const currentPath = this.currentPath();
        const query = this.currentQuery();

        for (const route of this.routes) {
            const match = this.match(route.pattern, currentPath);

            if (!match) {
                continue;
            }

            if (route.title) {
                document.title = `${route.title} — DormPlus`;
            }

            await route.handler({
                path: currentPath,
                params: match.params,
                query
            });

            return;
        }

        if (this.notFoundHandler) {
            await this.notFoundHandler({path: currentPath});
        }
    }

    match(pattern, path) {
        const keys = [];
        const expression = pattern
            .replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
            .replace(/:([a-zA-Z0-9_]+)/g, (_, key) => {
                keys.push(key);
                return '([^/]+)';
            });

        const result = path.match(new RegExp(`^${expression}/?$`));

        if (!result) {
            return null;
        }

        const params = {};

        keys.forEach((key, index) => {
            params[key] = decodeURIComponent(result[index + 1]);
        });

        return {params};
    }

    normalize(path) {
        const normalized = `/${String(path || '').replace(/^#?\/?/, '')}`;
        return normalized.length > 1
            ? normalized.replace(/\/+$/, '')
            : normalized;
    }
}