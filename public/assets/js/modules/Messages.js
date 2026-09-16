import {Module} from './Module.js';
import {avatar} from '../ui/Avatar.js';
import {button, pageHeader, searchBox} from '../ui/Controls.js';
import {createForm} from '../ui/Form.js';
import {formatDateTime, formatRelative} from '../ui/Formatters.js';
import {icon} from '../ui/Icons.js';
import {userRole} from '../ui/Labels.js';
import {Modal} from '../ui/Modal.js';
import {emptyState, errorState, loadingState} from '../ui/States.js';

export class Messages extends Module {
    constructor(options) {
        super(options);
        this.activeThreadId = null;
        this.search = '';
    }

    async render({params = {}, query = {}} = {}) {
        this.activeThreadId = params.id ? Number(params.id) : null;
        this.search = query.q || '';

        const content = this.content();
        content.replaceChildren();

        const page = document.createElement('div');
        page.className = 'page';

        page.append(pageHeader({
            title: 'ข้อความ',
            subtitle: 'การติดต่อภายในระหว่างเจ้าของหอพักและเจ้าหน้าที่',
            actions: [button({label: 'ข้อความใหม่', iconName: 'plus', onClick: () => this.openCompose()})]
        }));

        this.layout = document.createElement('div');
        this.layout.className = `messages-layout ${this.activeThreadId ? 'show-thread' : ''}`;

        this.sidebar = document.createElement('aside');
        this.sidebar.className = 'messages-sidebar card';

        this.sidebar.append(searchBox({
            placeholder: 'ค้นหาหัวข้อ',
            value: this.search,
            onSearch: (value) => {
                this.search = value;
                this.loadThreads();
            }
        }));

        this.threadList = document.createElement('div');
        this.threadList.className = 'thread-list';
        this.sidebar.append(this.threadList);

        this.conversation = document.createElement('section');
        this.conversation.className = 'messages-conversation card';

        this.layout.append(this.sidebar, this.conversation);
        page.append(this.layout);
        content.append(page);

        await this.loadThreads();

        if (this.activeThreadId) {
            await this.openThread(this.activeThreadId, false);
        } else {
            this.conversation.replaceChildren(emptyState({
                iconName: 'message',
                title: 'เลือกบทสนทนา',
                message: 'เลือกจากรายการด้านซ้าย หรือเริ่มข้อความใหม่'
            }));
        }
    }

    async loadThreads() {
        this.threadList.replaceChildren(loadingState('กำลังโหลด...'));

        try {
            const query = this.search ? `?q=${encodeURIComponent(this.search)}` : '';
            const response = await this.api.get(`/messages/threads${query}`);

            this.shell.setMessageCount(response.data.unread_count);
            this.renderThreads(response.data.items);
        } catch (error) {
            if (!this.isAbort(error)) {
                this.threadList.replaceChildren(errorState(error.message, () => this.loadThreads()));
            }
        }
    }

    renderThreads(threads) {
        this.threadList.replaceChildren();

        if (!threads.length) {
            this.threadList.append(emptyState({
                iconName: 'message',
                title: this.search ? 'ไม่พบบทสนทนา' : 'ยังไม่มีข้อความ',
                actionLabel: this.search ? '' : 'ข้อความใหม่',
                onAction: () => this.openCompose()
            }));

            return;
        }

        for (const thread of threads) {
            const item = document.createElement('button');
            item.type = 'button';
            item.className = 'thread-item';
            item.classList.toggle('is-active', thread.id === this.activeThreadId);
            item.classList.toggle('is-unread', thread.unread_count > 0);
            item.dataset.id = thread.id;

            const top = document.createElement('div');
            top.className = 'thread-top';

            const subject = document.createElement('strong');
            subject.textContent = thread.subject;

            const time = document.createElement('time');
            time.textContent = formatRelative(thread.last_message_at || thread.created_at);

            top.append(subject, time);

            const participants = document.createElement('span');
            participants.className = 'thread-participants';
            participants.textContent = thread.participants || 'เฉพาะคุณ';

            const preview = document.createElement('p');
            preview.className = 'thread-preview';
            preview.textContent = thread.last_message
                ? `${thread.last_sender_name ? `${thread.last_sender_name}: ` : ''}${thread.last_message}`
                : '';

            item.append(top, participants, preview);

            if (thread.unread_count > 0) {
                const count = document.createElement('span');
                count.className = 'thread-unread';
                count.textContent = String(thread.unread_count);
                item.append(count);
            }

            item.addEventListener('click', () => this.openThread(thread.id));
            this.threadList.append(item);
        }
    }

    async openThread(id, updateUrl = true) {
        this.activeThreadId = id;
        this.layout.classList.add('show-thread');

        this.threadList.querySelectorAll('.thread-item').forEach((item) => {
            item.classList.toggle('is-active', Number(item.dataset.id) === id);
        });

        if (updateUrl) {
            history.replaceState(null, '', this.router.buildHash(`/messages/${id}`));
        }

        this.conversation.replaceChildren(loadingState());

        try {
            const response = await this.api.get(`/messages/threads/${id}`);
            this.renderConversation(response.data);
            this.shell.setMessageCount(response.data.unread_count);

            const item = this.threadList.querySelector(`.thread-item[data-id="${id}"]`);

            if (item) {
                item.classList.remove('is-unread');
                item.querySelector('.thread-unread')?.remove();
            }
        } catch (error) {
            if (!this.isAbort(error)) {
                this.conversation.replaceChildren(errorState(error.message, () => this.openThread(id)));
            }
        }
    }

    renderConversation({thread, participants, messages}) {
        this.conversation.replaceChildren();

        const header = document.createElement('header');
        header.className = 'conversation-header';

        const back = document.createElement('button');
        back.type = 'button';
        back.className = 'icon-button conversation-back';
        back.setAttribute('aria-label', 'กลับไปรายการข้อความ');
        back.innerHTML = icon('arrowLeft');
        back.addEventListener('click', () => {
            this.layout.classList.remove('show-thread');
            this.activeThreadId = null;
            history.replaceState(null, '', this.router.buildHash('/messages'));
        });

        const titles = document.createElement('div');
        const subject = document.createElement('h2');
        subject.textContent = thread.subject;

        const people = document.createElement('p');
        people.textContent = participants.map((person) => `${person.first_name} ${person.last_name}`).join(', ');

        titles.append(subject, people);
        header.append(back, titles);

        const list = document.createElement('div');
        list.className = 'message-list';

        for (const message of messages) {
            const bubble = document.createElement('article');
            bubble.className = `message-bubble ${message.is_mine ? 'is-mine' : ''}`;

            if (!message.is_mine) {
                bubble.append(avatar({name: message.first_name, image: message.avatar, size: 'sm'}));
            }

            const body = document.createElement('div');
            body.className = 'message-body';

            const meta = document.createElement('span');
            meta.className = 'message-meta';
            meta.textContent = `${message.is_mine ? 'คุณ' : `${message.first_name} ${message.last_name}`} · ${formatDateTime(message.created_at)}`;

            const text = document.createElement('p');
            text.textContent = message.body;

            body.append(meta, text);
            bubble.append(body);
            list.append(bubble);
        }

        const reply = createForm({
            columns: 1,
            inline: true,
            submitLabel: 'ส่ง',
            fields: [{name: 'body', label: 'ตอบกลับ', type: 'textarea', required: true, rows: 2, placeholder: 'พิมพ์ข้อความ...'}],
            onSubmit: async (values) => {
                const response = await this.api.post(`/messages/threads/${thread.id}`, values);
                this.renderConversation(response.data);
                this.loadThreads();
            }
        });

        reply.element.classList.add('message-reply');
        reply.submitButton.innerHTML = `${icon('send')}<span>ส่ง</span>`;

        this.conversation.append(header, list, reply.element);
        list.scrollTop = list.scrollHeight;
    }

    async openCompose() {
        const modal = new Modal({title: 'ข้อความใหม่', size: 'md'});
        modal.body.append(loadingState('กำลังโหลดรายชื่อผู้รับ...'));
        modal.open();

        let recipients = [];

        try {
            recipients = (await this.api.get('/messages/recipients')).data;
        } catch (error) {
            modal.body.replaceChildren(errorState(error.message));
            return;
        }

        if (!recipients.length) {
            modal.body.replaceChildren(emptyState({
                iconName: 'users',
                title: 'ยังไม่มีผู้ใช้อื่นในระบบ',
                message: 'เพิ่มบัญชีเจ้าหน้าที่เพื่อเริ่มส่งข้อความ'
            }));
            return;
        }

        const form = createForm({
            columns: 1,
            submitLabel: 'ส่งข้อความ',
            onCancel: () => modal.close(),
            fields: [
                {name: 'subject', label: 'หัวข้อ', required: true, maxLength: 160},
                {name: 'body', label: 'ข้อความ', type: 'textarea', required: true, rows: 4}
            ],
            onSubmit: async (values) => {
                const selected = [...recipientList.querySelectorAll('input:checked')].map((input) => Number(input.value));

                if (!selected.length) {
                    recipientError.textContent = 'กรุณาเลือกผู้รับอย่างน้อย 1 คน';
                    return;
                }

                const response = await this.api.post('/messages/threads', {...values, participant_ids: selected});
                modal.close();
                this.toast.success('ส่งข้อความเรียบร้อยแล้ว');
                await this.loadThreads();
                this.openThread(response.data.thread.id);
            }
        });

        const recipientGroup = document.createElement('div');
        recipientGroup.className = 'form-group';

        const label = document.createElement('span');
        label.className = 'form-label';
        label.textContent = 'ผู้รับ *';

        const recipientList = document.createElement('div');
        recipientList.className = 'recipient-list';

        for (const person of recipients) {
            const row = document.createElement('label');
            row.className = 'recipient-item';

            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.value = String(person.id);
            checkbox.className = 'form-checkbox';

            const text = document.createElement('span');
            text.textContent = `${person.name} · ${userRole[person.role] || person.role}`;

            row.append(checkbox, avatar({name: person.first_name, image: person.avatar, size: 'sm'}), text);
            recipientList.append(row);
        }

        const recipientError = document.createElement('p');
        recipientError.className = 'field-error';

        recipientList.addEventListener('change', () => {
            recipientError.textContent = '';
        });

        recipientGroup.append(label, recipientList, recipientError);
        form.element.querySelector('.form-grid').prepend(recipientGroup);

        modal.body.replaceChildren(form.element);
    }
}
