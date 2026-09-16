/**
 * สร้างฟอร์มจากรายการฟิลด์ พร้อมตรวจสอบ แสดงข้อผิดพลาด และสถานะกำลังบันทึก
 *
 * const form = createForm({
 *     fields: [
 *         {name: 'room_number', label: 'เลขห้อง', required: true},
 *         {name: 'status', label: 'สถานะ', type: 'select', options: [...]}
 *     ],
 *     values: room,
 *     onSubmit: async (values) => api.post('/rooms', values)
 * });
 * modal.body.append(form.element);
 */
let fieldSequence = 0;

export function createForm({
    fields,
    values = {},
    submitLabel = 'บันทึก',
    cancelLabel = 'ยกเลิก',
    onSubmit,
    onCancel = null,
    columns = 2,
    inline = false
}) {
    const form = document.createElement('form');
    form.className = `form ${inline ? 'form-inline' : ''}`;
    form.noValidate = true;

    const grid = document.createElement('div');
    grid.className = `form-grid form-columns-${columns}`;

    const alert = document.createElement('div');
    alert.className = 'form-alert';
    alert.setAttribute('role', 'alert');
    alert.hidden = true;

    const inputs = new Map();
    const errors = new Map();
    const specs = new Map();

    for (const spec of fields) {
        specs.set(spec.name, spec);
        grid.append(renderField(spec, values[spec.name], inputs, errors));
    }

    const actions = document.createElement('div');
    actions.className = 'form-actions';

    const submitButton = document.createElement('button');
    submitButton.type = 'submit';
    submitButton.className = 'btn btn-primary';
    submitButton.textContent = submitLabel;

    if (onCancel) {
        const cancelButton = document.createElement('button');
        cancelButton.type = 'button';
        cancelButton.className = 'btn btn-secondary';
        cancelButton.textContent = cancelLabel;
        cancelButton.addEventListener('click', () => onCancel());
        actions.append(cancelButton);
    }

    actions.append(submitButton);
    form.append(alert, grid, actions);

    let busy = false;

    const api = {
        element: form,
        form,
        submitButton,

        field(name) {
            return inputs.get(name) || null;
        },

        getValues() {
            const result = {};

            for (const [name, input] of inputs) {
                result[name] = readValue(specs.get(name), input);
            }

            return result;
        },

        setValues(partial) {
            for (const [name, value] of Object.entries(partial)) {
                const input = inputs.get(name);

                if (!input) {
                    continue;
                }

                if (input.type === 'checkbox') {
                    input.checked = Boolean(value);
                } else {
                    input.value = value ?? '';
                }
            }
        },

        setOptions(name, options, selected = null) {
            const input = inputs.get(name);

            if (!input || input.tagName !== 'SELECT') {
                return;
            }

            input.replaceChildren(...options.map((option) => {
                const element = document.createElement('option');
                element.value = option.value;
                element.textContent = option.label;
                element.disabled = Boolean(option.disabled);
                return element;
            }));

            if (selected !== null) {
                input.value = selected;
            }
        },

        setErrors(fieldErrors = {}) {
            api.clearErrors();

            for (const [name, message] of Object.entries(fieldErrors)) {
                const error = errors.get(name);
                const input = inputs.get(name);

                if (error) {
                    error.textContent = message;
                }

                if (input) {
                    input.setAttribute('aria-invalid', 'true');
                }
            }

            const first = Object.keys(fieldErrors).find((name) => inputs.has(name));

            if (first) {
                inputs.get(first).focus();
            }
        },

        clearErrors() {
            for (const error of errors.values()) {
                error.textContent = '';
            }

            for (const input of inputs.values()) {
                input.removeAttribute('aria-invalid');
            }

            alert.hidden = true;
            alert.textContent = '';
        },

        showAlert(message) {
            alert.textContent = message;
            alert.hidden = !message;
        },

        setBusy(state) {
            busy = state;
            submitButton.disabled = state;
            form.classList.toggle('is-busy', state);
            submitButton.textContent = state ? 'กำลังบันทึก...' : submitLabel;
        }
    };

    form.addEventListener('input', (event) => {
        const name = event.target.name;

        if (errors.has(name)) {
            errors.get(name).textContent = '';
            event.target.removeAttribute('aria-invalid');
        }
    });

    form.addEventListener('change', (event) => {
        const spec = specs.get(event.target.name);

        if (spec && typeof spec.onChange === 'function') {
            spec.onChange(readValue(spec, event.target), api);
        }
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        if (busy) {
            return;
        }

        api.clearErrors();

        const fieldErrors = validate(specs, inputs);

        if (Object.keys(fieldErrors).length > 0) {
            api.setErrors(fieldErrors);
            return;
        }

        api.setBusy(true);

        try {
            await onSubmit(api.getValues(), api);
        } catch (error) {
            if (error?.fields && Object.keys(error.fields).length > 0) {
                api.setErrors(error.fields);
                api.showAlert(error.message);
            } else {
                api.showAlert(error?.message || 'เกิดข้อผิดพลาด กรุณาลองใหม่อีกครั้ง');
            }
        } finally {
            api.setBusy(false);
        }
    });

    return api;
}

function renderField(spec, value, inputs, errors) {
    const group = document.createElement('div');
    group.className = 'form-group';

    if (spec.span === 2) {
        group.classList.add('form-span-2');
    }

    const id = `field-${spec.name}-${++fieldSequence}`;
    const label = document.createElement('label');
    label.className = 'form-label';
    label.htmlFor = id;
    label.textContent = spec.label;

    if (spec.required) {
        const mark = document.createElement('span');
        mark.className = 'form-required';
        mark.setAttribute('aria-hidden', 'true');
        mark.textContent = ' *';
        label.append(mark);
    }

    let input;
    const type = spec.type || 'text';

    if (type === 'select') {
        input = document.createElement('select');
        input.className = 'form-control';

        for (const option of spec.options || []) {
            const element = document.createElement('option');
            element.value = option.value;
            element.textContent = option.label;
            element.disabled = Boolean(option.disabled);
            input.append(element);
        }
    } else if (type === 'textarea') {
        input = document.createElement('textarea');
        input.className = 'form-control';
        input.rows = spec.rows || 3;
    } else if (type === 'checkbox') {
        input = document.createElement('input');
        input.type = 'checkbox';
        input.className = 'form-checkbox';
    } else {
        input = document.createElement('input');
        input.type = type;
        input.className = 'form-control';

        if (type === 'number') {
            input.inputMode = 'decimal';
            input.step = spec.step ?? 'any';
        }

        if (spec.min !== undefined) {
            input.min = spec.min;
        }

        if (spec.max !== undefined) {
            input.max = spec.max;
        }

        if (spec.maxLength) {
            input.maxLength = spec.maxLength;
        }
    }

    input.id = id;
    input.name = spec.name;
    input.disabled = Boolean(spec.disabled);
    input.readOnly = Boolean(spec.readonly);

    if (spec.placeholder) {
        input.placeholder = spec.placeholder;
    }

    if (spec.autocomplete) {
        input.autocomplete = spec.autocomplete;
    }

    if (spec.required) {
        input.setAttribute('aria-required', 'true');
    }

    if (type === 'checkbox') {
        input.checked = Boolean(value ?? spec.value);
    } else {
        input.value = value ?? spec.value ?? '';
    }

    const error = document.createElement('p');
    error.className = 'field-error';
    error.id = `${id}-error`;
    input.setAttribute('aria-describedby', error.id);

    if (type === 'checkbox') {
        const row = document.createElement('label');
        row.className = 'form-checkbox-row';
        row.append(input, document.createTextNode(spec.label));
        group.append(row, error);
    } else {
        group.append(label, input);

        if (spec.help) {
            const help = document.createElement('p');
            help.className = 'form-help';
            help.textContent = spec.help;
            group.append(help);
        }

        group.append(error);
    }

    inputs.set(spec.name, input);
    errors.set(spec.name, error);

    return group;
}

function readValue(spec, input) {
    if (!spec) {
        return input.value;
    }

    if (input.type === 'checkbox') {
        return input.checked;
    }

    const raw = input.value;

    if (spec.type === 'number') {
        return raw === '' ? null : Number(raw);
    }

    if (spec.type === 'datetime-local') {
        return raw === '' ? null : raw.replace('T', ' ');
    }

    return typeof raw === 'string' ? raw.trim() : raw;
}

function validate(specs, inputs) {
    const fieldErrors = {};

    for (const [name, spec] of specs) {
        const input = inputs.get(name);
        const value = readValue(spec, input);

        if (spec.required && (value === null || value === '' || value === false)) {
            fieldErrors[name] = `กรุณากรอก${spec.label}`;
            continue;
        }

        if (spec.type === 'number' && value !== null) {
            if (Number.isNaN(value)) {
                fieldErrors[name] = 'ต้องเป็นตัวเลข';
            } else if (spec.min !== undefined && value < Number(spec.min)) {
                fieldErrors[name] = `ต้องไม่น้อยกว่า ${spec.min}`;
            } else if (spec.max !== undefined && value > Number(spec.max)) {
                fieldErrors[name] = `ต้องไม่เกิน ${spec.max}`;
            }
        }

        if (spec.type === 'email' && value && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value)) {
            fieldErrors[name] = 'รูปแบบอีเมลไม่ถูกต้อง';
        }

        if (typeof spec.validate === 'function' && !fieldErrors[name]) {
            const message = spec.validate(value, inputs);

            if (message) {
                fieldErrors[name] = message;
            }
        }
    }

    return fieldErrors;
}
