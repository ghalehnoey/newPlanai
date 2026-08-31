// ==================== متغیرهای سراسری ====================
let currentModel = null;
let currentModelSupportsFiles = false;
let currentConversationId = null;
let isFirstAssistantMessage = true;
let shouldAutoScroll = true;
let selectedFiles = [];
let isSendingMessage = false;
let activeRequest = null;
let conversationLoader = null;
let viewRevision = 0;
let retryIntent = null;
const CHAT_TIMEOUT_MS = 180000;
const MAX_UPLOAD_FILES = 5;
const MAX_UPLOAD_FILE_BYTES = 8 * 1024 * 1024;
const ALLOWED_DOCUMENT_EXTENSIONS = ['pdf', 'txt', 'doc', 'docx'];
const MESSAGE_PAGE_SIZE = 30;
let oldestLoadedMessageId = null;
let hasOlderMessages = false;
let isLoadingOlderMessages = false;

// DOM elements
const chatBox = document.getElementById('chatBox');
const questionInput = document.getElementById('question');
const sendBtn = document.getElementById('sendBtn');
const fileInput = document.getElementById('fileInput');
const fileInputBtn = document.getElementById('fileInputBtn');
const fileUploadContainer = document.getElementById('fileUploadContainer');
const filePreviewContainer = document.getElementById('filePreviewContainer');
const fileNameDisplay = document.getElementById('fileNameDisplay');
const removeFileBtn = document.getElementById('removeFileBtn');
const chatMain = document.getElementById('chatMain');
const selectedModelButton = document.getElementById('selectedModelButton');
const selectedModelName = document.getElementById('selectedModelName');
const selectedModelIcon = document.getElementById('selectedModelIcon');
const modelMenu = document.getElementById('modelMenu');
const modelMenuBackdrop = document.getElementById('modelMenuBackdrop');

// ==================== توابع کمکی ====================
function updateFileUI() {
    if (selectedFiles.length > 0) {
        filePreviewContainer.classList.remove('hidden');
        const fileLabels = selectedFiles.map(file => {
            const icon = attachmentExtension(file.name) === 'pdf'
                ? 'PDF'
                : (String(file.type || '').startsWith('image/') ? 'تصویر' : 'فایل');

            return `${icon}: ${file.name} (${formatFileSize(file.size)})`;
        });

        fileNameDisplay.textContent = fileLabels.join(' • ');
        fileNameDisplay.title = fileLabels.join('\n');
        if (fileInputBtn) fileInputBtn.classList.add('bg-indigo-100', 'text-indigo-600');
    } else {
        filePreviewContainer.classList.add('hidden');
        if (fileInputBtn) fileInputBtn.classList.remove('bg-indigo-100', 'text-indigo-600');
        if (fileInput) fileInput.value = '';
    }
}

function selectModel(modelKey, supportsFiles) {
    currentModel = modelKey;
    currentModelSupportsFiles = supportsFiles === 'true' || supportsFiles === true;
    const selectedBtn = document.querySelector(`.model-btn[data-model="${modelKey}"]`);
    
    // به‌روزرسانی استایل دکمه‌ها
    document.querySelectorAll('.model-btn').forEach(btn => {
        if (btn.dataset.model === modelKey) {
            btn.classList.remove('bg-slate-50', 'bg-slate-100', 'text-slate-700', 'hover:bg-slate-100', 'hover:bg-slate-200');
            btn.classList.add('bg-indigo-500', 'text-white', 'shadow-md');
        } else {
            btn.classList.remove('bg-indigo-500', 'text-white', 'shadow-md');
            btn.classList.add('bg-slate-50', 'text-slate-700', 'hover:bg-slate-100');
        }
    });

    if (selectedBtn) {
        const icon = selectedBtn.querySelector('.model-btn-icon')?.textContent?.trim() || '🧠';
        if (selectedModelName) selectedModelName.textContent = selectedBtn.dataset.modelName || selectedBtn.textContent.trim();
        if (selectedModelIcon) selectedModelIcon.textContent = icon;
    }
    
    // نمایش یا مخفی کردن دکمه آپلود فایل
    if (fileUploadContainer) {
        if (currentModelSupportsFiles) {
            fileUploadContainer.classList.remove('hidden');
            console.log('✅ فایل آپلود فعال شد برای مدل:', currentModel);
        } else {
            fileUploadContainer.classList.add('hidden');
            // اگر مدل از فایل پشتیبانی نمی‌کند، فایل‌های انتخاب شده را پاک کن
            if (selectedFiles.length > 0) {
                selectedFiles = [];
                updateFileUI();
            }
        }
    }
    
    console.log('✅ مدل انتخاب شد:', currentModel, '| پشتیبانی از فایل:', currentModelSupportsFiles);
}

function formatFileSize(bytes) {
    if (bytes < 1024) return `${bytes} بایت`;
    if (bytes < 1024 * 1024) return `${Math.ceil(bytes / 1024)} کیلوبایت`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} مگابایت`;
}

function setSendingState(isSending) {
    isSendingMessage = isSending;
    const stop = document.getElementById('stopChatBtn');
    if (stop) stop.hidden = !isSending;
    if (sendBtn) sendBtn.setAttribute('aria-busy', String(isSending));

    [sendBtn, fileInputBtn].forEach(button => {
        if (!button) return;
        button.disabled = isSending;
        button.classList.toggle('opacity-50', isSending);
        button.classList.toggle('cursor-not-allowed', isSending);
    });
}


function closeChatSidebar() {
    document.getElementById('sidebar')?.classList.remove('translate-x-0');
    document.getElementById('sidebar')?.classList.add('translate-x-full');
    document.getElementById('sidebarBackdrop')?.classList.add('hidden');
}

function showChatNotice(message = '', kind = 'info', action = null) {
    const box = document.getElementById('chatStatus');
    if (!box) return;
    box.replaceChildren();
    box.hidden = !message;
    box.dataset.kind = kind;
    box.setAttribute('role', kind === 'error' ? 'alert' : 'status');
    if (!message) return;
    const label = document.createElement('span');
    label.textContent = message;
    box.append(label);
    if (action) {
        const control = document.createElement(action.href ? 'a' : 'button');
        control.textContent = action.label;
        control.className = 'chat-status-action';
        if (action.href) control.href = action.href;
        else {
            control.type = 'button';
            control.addEventListener('click', action.run);
        }
        box.append(control);
    }
}

function draftPrefix() {
    return 'chat-draft:' + (document.querySelector('meta[name="chat-user-id"]')?.content || 'guest') + ':';
}

function saveChatDraft(text, conversationId = currentConversationId, hasFiles = false) {
    try {
        const key = draftPrefix() + (conversationId || 'new');
        if (!text && !hasFiles) { sessionStorage.removeItem(key); return; }
        sessionStorage.setItem(key, JSON.stringify({text, conversationId, model: currentModel, hasFiles, at: Date.now()}));
        sessionStorage.setItem(draftPrefix() + 'last', key);
    } catch (_) { /* Private browsing/storage limits must not block chat. */ }
}

function readChatDraft(conversationId, last = false) {
    try {
        const key = last ? sessionStorage.getItem(draftPrefix() + 'last') : draftPrefix() + (conversationId || 'new');
        const draft = key ? JSON.parse(sessionStorage.getItem(key) || 'null') : null;
        return draft && Date.now() - draft.at < 86400000 ? draft : null;
    } catch (_) { return null; }
}

function clearMatchingDraft(text, conversationId) {
    try {
        const key = draftPrefix() + (conversationId || 'new');
        const saved = JSON.parse(sessionStorage.getItem(key) || 'null');
        if (saved?.text === text) sessionStorage.removeItem(key);
    } catch (_) {}
}

function restoreRequestDraft(request) {
    if (!questionInput.value.trim()) questionInput.value = request.text;
    if (!selectedFiles.length) selectedFiles = request.files;
    updateFileUI();
    saveChatDraft(questionInput.value, request.conversationId, selectedFiles.length > 0);
}

function cancelChatRequests(restore = false) {
    viewRevision++;
    if (activeRequest) {
        const previous = activeRequest;
        activeRequest = null;
        if (restore) restoreRequestDraft(previous);
        previous.controller.abort();
        clearTimeout(previous.timeout);
        clearTimeout(previous.waiting);
        if (!previous.answer) document.getElementById(previous.typingId)?.remove();
    }
    conversationLoader?.abort();
    conversationLoader = null;
    setSendingState(false);
}

function stopChatResponse() {
    const id = currentConversationId;
    cancelChatRequests(true);
    retryIntent = null; // Cancellation does not prove whether the server finished.
    showChatNotice('دریافت پاسخ متوقف شد. متن شما محفوظ است؛ پیش از ارسال دوباره، وضعیت مکالمه را بررسی کنید.', 'info',
        id ? {label: 'بازبینی مکالمه', run: () => loadConversation(id)} : null);
}

function httpChatError(status, data = {}) {
    if (status === 401 || status === 419) return {
        message: 'نشست ورود شما نیاز به تمدید دارد. متن سؤال در همین مرورگر محفوظ است؛ دوباره وارد شوید.',
        action: {label: 'ورود دوباره', href: '/login'}
    };
    if (status === 403) return {
        message: data.code === 'quota_exceeded' ? 'سهمیهٔ پرسش شما تمام شده است. وضعیت اشتراک را بررسی کنید؛ متن سؤال محفوظ است.' : 'اجازهٔ انجام این درخواست را ندارید. وضعیت حساب خود را بررسی کنید.',
        action: {label: 'بررسی اشتراک', href: '/plans'}
    };
    if (status === 422) return {message: String(data.error || Object.values(data.errors || {}).flat()[0] || 'متن، فایل یا مدل انتخاب‌شده نیاز به اصلاح دارد.')};
    if (status === 413) return {message: 'حجم فایل‌ها زیاد است. هر فایل حداکثر ۸ مگابایت و هر پیام حداکثر ۵ فایل باشد.'};
    if (status === 409) return {message: String(data.error || 'این پیام در حال پردازش است. کمی صبر کنید و مکالمه را بازبینی کنید.')};
    if (status === 429) return {message: 'درخواست‌ها زیاد شده است. کمی صبر کنید و دوباره بفرستید؛ متن شما محفوظ است.'};
    return {message: 'این بار درخواست کامل نشد. متن شما محفوظ است؛ کمی بعد دوباره تلاش کنید.'};
}

function upsertConversation(id, title) {
    const list = document.getElementById('conversationList');
    if (!list || !/^\d+$/.test(String(id))) return;
    let item = list.querySelector('.conversation-item[data-id="' + id + '"]');
    if (!item) {
        item = document.createElement('div');
        item.dataset.id = id;
        item.className = 'conversation-item group cursor-pointer p-3 rounded-xl hover:bg-slate-50 transition-smooth flex justify-between items-center';
        const label = document.createElement('div');
        label.className = 'text-sm font-medium text-slate-700 truncate';
        item.append(label);
        item.addEventListener('click', () => loadConversation(id));
        const actions = document.createElement('div');
        actions.className = 'flex gap-1';
        for (const [text, hint, handler] of [['✏️', 'تغییر نام', renameConversation], ['🗑️', 'حذف مکالمه', deleteConversation]]) {
            const button = document.createElement('button');
            button.type = 'button';
            button.textContent = text;
            button.title = hint;
            button.className = 'p-1.5 rounded-lg text-slate-500';
            button.addEventListener('click', event => {event.stopPropagation(); handler(id);});
            actions.append(button);
        }
        item.append(actions);
    }
    const label = item.querySelector('.text-sm');
    if (label) label.textContent = title || 'مکالمه جدید';
    list.prepend(item);
}

function createChatEventParser(onEvent) {
    let buffer = '';
    return {
        push(text) {
            buffer += text;
            if (buffer.length > 1024 * 1024) throw new Error('invalid_stream');
            let end;
            while ((end = buffer.indexOf('\n\n')) !== -1) {
                const block = buffer.slice(0, end);
                buffer = buffer.slice(end + 2);
                let event = 'message';
                const data = [];
                for (const line of block.split('\n')) {
                    if (line.startsWith('event:')) event = line.slice(6).trim();
                    if (line.startsWith('data:')) data.push(line.slice(5).trimStart());
                }
                if (data.length) onEvent(event, JSON.parse(data.join('\n')));
            }
        }
    };
}

function attachmentExtension(fileName) {
    return String(fileName || '').split('.').pop().toLowerCase();
}

function isAllowedAttachment(file) {
    return String(file.type || '').startsWith('image/')
        || ALLOWED_DOCUMENT_EXTENSIONS.includes(attachmentExtension(file.name));
}

function toggleModelMenu(forceOpen = null) {
    if (!modelMenu) return;
    const shouldOpen = forceOpen === null ? modelMenu.classList.contains('hidden') : forceOpen;
    modelMenu.classList.toggle('hidden', !shouldOpen);
    if (modelMenuBackdrop) modelMenuBackdrop.classList.toggle('hidden', !shouldOpen);
    if (selectedModelButton) selectedModelButton.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
}

function scrollChat(force = false) {
    if (force || shouldAutoScroll) {
        if (chatMain) chatMain.scrollTop = chatMain.scrollHeight;
    }
}

function escapeHtml(text) {
    if (!text) return '';
    return text.replace(/[&<>]/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        return m;
    }).replace(/\n/g, '<br>');
}

function assistantActionsHtml() {
    return `
        <button type="button"
                data-copy-answer
                class="not-prose inline-flex items-center gap-1.5 rounded-lg px-2 py-1 text-[11px] font-medium text-slate-500 hover:bg-slate-100 hover:text-indigo-600 focus:outline-none focus:ring-2 focus:ring-indigo-200 transition-colors"
                aria-label="کپی کامل پاسخ"
                title="کپی کامل پاسخ">
            <svg aria-hidden="true" class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <rect x="8" y="8" width="11" height="11" rx="2"></rect>
                <path d="M16 8V6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v8a2 2 0 0 0 2 2h2"></path>
            </svg>
            <span data-copy-label>کپی پاسخ</span>
        </button>
    `;
}

async function copyTextToClipboard(text) {
    if (navigator.clipboard && window.isSecureContext) {
        await navigator.clipboard.writeText(text);
        return;
    }

    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.setAttribute('readonly', '');
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();
    const copied = document.execCommand('copy');
    textarea.remove();

    if (!copied) throw new Error('Copy command failed');
}

if (chatBox) {
    chatBox.addEventListener('click', async event => {
        const button = event.target.closest('[data-copy-answer]');
        if (!button || !chatBox.contains(button)) return;

        const answer = button.closest('.chat-message-ai')?.querySelector('.assistant-answer-content');
        const text = answer?.innerText?.trim() || '';
        if (!text) return;

        const label = button.querySelector('[data-copy-label]');
        button.disabled = true;

        try {
            await copyTextToClipboard(text);
            if (label) label.textContent = 'کپی شد ✓';
            button.classList.add('text-emerald-600');
        } catch (error) {
            console.error('Copy answer error:', error);
            if (label) label.textContent = 'کپی نشد';
            button.classList.add('text-rose-600');
        }

        window.setTimeout(() => {
            if (label) label.textContent = 'کپی پاسخ';
            button.classList.remove('text-emerald-600', 'text-rose-600');
            button.disabled = false;
        }, 1800);
    });
}

function filesPreviewHtml(filesData = []) {
    if (!filesData.length) return '';

    let filesHtml = '<div class="flex flex-wrap gap-2 justify-end mb-2">';
    filesData.forEach(f => {
        if (f.isImage) {
            filesHtml += `<img src="${f.url}" class="max-w-[100px] max-h-[100px] rounded-xl border border-indigo-200 object-cover">`;
        } else {
            filesHtml += `<div class="bg-white px-3 py-1.5 rounded-full text-xs shadow-sm border border-slate-200">📄 ${escapeHtml(f.name)}</div>`;
        }
    });
    filesHtml += '</div>';

    return filesHtml;
}

function userMessageHtml(text, filesData = []) {
    return `
        <div class="flex justify-end">
            <div class="chat-message-user max-w-[85%] bg-indigo-50 text-slate-800 rounded-2xl px-4 py-2.5 shadow-sm">
                ${filesPreviewHtml(filesData)}
                ${text ? `<div class="whitespace-pre-wrap break-words text-sm">${escapeHtml(text)}</div>` : ''}
            </div>
        </div>
    `;
}

function assistantInnerHtml(content, showNotice = false, showActions = true) {
    const firstHeader = '<div class="text-xs text-amber-800 bg-amber-50 rounded-xl p-3 mb-3">⚠️ این متن توسط هوش مصنوعی تولید شده و نیاز به بررسی دارد.</div>';
    const footer = '<div class="text-[10px] text-slate-400 mt-3 pt-2 border-t border-slate-100">🤖 تولید شده توسط هوش مصنوعی</div>';

    let html = showNotice ? firstHeader : '';
    html += `<div class="assistant-answer-content">${marked.parse(content || '')}</div>`;
    html += footer;
    if (showActions) {
        html += `<div class="not-prose flex justify-end mt-1">${assistantActionsHtml()}</div>`;
    }

    return html;
}

function assistantMessageHtml(containerId, content, showNotice = false) {
    return `
        <div class="flex justify-start" id="${containerId}">
            <div class="chat-message-ai bg-white border border-slate-200 rounded-2xl px-4 py-2.5 shadow-sm prose prose-sm max-w-full">
                ${assistantInnerHtml(content, showNotice)}
            </div>
        </div>
    `;
}

function storedMessageHtml(msg, showAssistantNotice = false) {
    if (msg.role === 'user') {
        const filesData = (msg.files || []).map(f => ({
            url: `/file/${f.id}`,
            name: f.original_name,
            isImage: f.mime_type?.startsWith('image/') || /\.(jpg|jpeg|png|gif|webp)$/i.test(f.original_name || '')
        }));

        return userMessageHtml(msg.content, filesData);
    }

    return assistantMessageHtml(`msg-${msg.id}`, msg.content, showAssistantNotice);
}

function storedMessagesHtml(messages = [], showFirstAssistantNotice = true) {
    let shouldShowNotice = showFirstAssistantNotice;

    return messages.map(msg => {
        const html = storedMessageHtml(msg, msg.role === 'assistant' && shouldShowNotice);
        if (msg.role === 'assistant') shouldShowNotice = false;
        return html;
    }).join('');
}

function olderMessagesButtonHtml(isLoading = false) {
    return `
        <div id="loadOlderWrap" class="flex justify-center py-2">
            <button id="loadOlderBtn" type="button" class="text-xs font-bold text-indigo-600 bg-indigo-50 border border-indigo-100 rounded-full px-4 py-2 hover:bg-indigo-100 transition-smooth">
                ${isLoading ? 'در حال بارگذاری...' : 'نمایش پیام‌های قدیمی‌تر'}
            </button>
        </div>
    `;
}

function setOlderMessagesButtonLoading(isLoading) {
    const btn = document.getElementById('loadOlderBtn');
    if (!btn) return;
    btn.disabled = isLoading;
    btn.textContent = isLoading ? 'در حال بارگذاری...' : 'نمایش پیام‌های قدیمی‌تر';
}

// ==================== رندر پیام‌ها ====================
function addUserMessage(text, filesData = []) {
    chatBox.insertAdjacentHTML('beforeend', userMessageHtml(text, filesData));
    scrollChat(true);
}

function addAiTyping() {
    const id = 'ai-typing-' + Date.now() + '-' + Math.random().toString(36).slice(2, 8);
    chatBox.insertAdjacentHTML('beforeend', `
        <div class="flex justify-start" id="${id}">
            <div class="bg-white border border-slate-200 rounded-2xl px-4 py-2.5 shadow-sm flex items-center gap-2">
                <div class="flex gap-1">
                    <span class="w-2 h-2 bg-slate-400 rounded-full animate-bounce"></span>
                    <span class="w-2 h-2 bg-slate-400 rounded-full animate-bounce" style="animation-delay: 0.1s"></span>
                    <span class="w-2 h-2 bg-slate-400 rounded-full animate-bounce" style="animation-delay: 0.2s"></span>
                </div>
                <span class="text-xs text-slate-500">در حال پاسخگویی...</span>
            </div>
        </div>
    `);
    scrollChat(true);
    return id;
}

function renderAssistantMessage(containerId, content, showActions = true) {
    const container = document.getElementById(containerId);
    if (!container) return;
    
    container.innerHTML = `<div class="chat-message-ai bg-white border border-slate-200 rounded-2xl px-4 py-2.5 shadow-sm prose prose-sm max-w-full">${assistantInnerHtml(content, isFirstAssistantMessage, showActions)}</div>`;
    scrollChat();
}

function friendlyAiUnavailableMessage() {
    return 'در حال حاضر پاسخ‌دهی این مدل با اختلال موقت روبه‌رو شد. لطفاً چند لحظه بعد دوباره تلاش کنید یا یک مدل دیگر را انتخاب کنید.';
}

// ==================== ارسال پیام با استریم ====================
async function sendMessage() {
    if (isSendingMessage) return;
    if (!currentModel) { showChatNotice('ابتدا یک مدل پاسخ‌دهنده انتخاب کنید.', 'error'); return; }
    if (navigator.onLine === false) { showChatNotice('ارتباط اینترنت شما قطع است. متن را نگه داشته‌ایم؛ پس از اتصال دوباره ارسال کنید.', 'error'); return; }
    const text = questionInput.value.trim();
    if (!text && selectedFiles.length === 0) return;
    conversationLoader?.abort();
    conversationLoader = null;
    const revision = ++viewRevision;
    const request = {
        controller: new AbortController(), text, files: [...selectedFiles],
        conversationId: currentConversationId, model: currentModel,
        originalConversationId: currentConversationId, answer: '', done: false, knownFailure: false,
        timeout: null, waiting: null, typingId: null, timedOut: false, contextTrimmed: false
    };
    const retry = retryIntent && retryIntent.text === text
        && String(retryIntent.conversationId) === String(currentConversationId)
        && retryIntent.files.length === selectedFiles.length
        && retryIntent.files.every((file, index) => file === selectedFiles[index]) ? retryIntent : null;
    retryIntent = null;
    activeRequest = request;
    const isCurrent = () => activeRequest === request && viewRevision === revision;
    setSendingState(true);
    saveChatDraft(text, currentConversationId, request.files.length > 0);
    showChatNotice('در حال آماده‌سازی پاسخ…');
    try {
        if (!retry) {
            addUserMessage(text, request.files.map(file => ({
                url: URL.createObjectURL(file), name: file.name, isImage: file.type.startsWith('image/')
            })));
        }
        questionInput.value = '';
        questionInput.style.height = 'auto';
        selectedFiles = [];
        updateFileUI();
        request.typingId = addAiTyping();
        const formData = new FormData();
        formData.append('question', text);
        formData.append('model', request.model);
        if (request.conversationId) formData.append('conversation_id', request.conversationId);
        if (retry) formData.append('retry_message_id', retry.id);
        else if (currentModelSupportsFiles) request.files.forEach(file => formData.append('files[]', file));
        request.timeout = setTimeout(() => {
            if (isCurrent()) { request.timedOut = true; request.controller.abort(); }
        }, CHAT_TIMEOUT_MS);
        request.waiting = setTimeout(() => {
            if (isCurrent() && !request.answer) showChatNotice('پاسخ کمی زمان‌بر شده است؛ هنوز در حال تلاش هستیم. می‌توانید دریافت را متوقف کنید.');
        }, 20000);
        const response = await fetch('/chat/send', {
            method: 'POST', signal: request.controller.signal,
            headers: {
                'Accept': 'text/event-stream, application/json',
                'X-Chat-Protocol': '2',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        });
        if (!isCurrent()) return;
        if (!response.ok || response.redirected) {
            const data = await response.json().catch(() => ({}));
            if (!isCurrent()) return;
            const details = httpChatError(response.redirected ? 401 : response.status, data);
            restoreRequestDraft(request);
            document.getElementById(request.typingId)?.remove();
            showChatNotice(details.message, 'error', details.action || null);
            return;
        }
        if (response.headers.get('X-Chat-Protocol') !== '2' || !response.body) throw new Error('unexpected_response');
        const parser = createChatEventParser((event, data) => {
            if (!isCurrent()) return;
            if (event === 'meta') {
                request.conversationId = data.conversation_id;
                request.messageId = data.user_message_id;
                currentConversationId = data.conversation_id;
                clearMatchingDraft(request.text, request.originalConversationId);
                saveChatDraft(request.text, request.conversationId, request.files.length > 0);
                upsertConversation(data.conversation_id, data.title);
            } else if (event === 'delta') {
                request.answer += String(data.text || '');
                renderAssistantMessage(request.typingId, request.answer, false);
            } else if (event === 'notice') {
                if (data.code === 'context_trimmed') {
                    request.contextTrimmed = true;
                    showChatNotice('برای این پاسخ از بخش‌های اخیر مکالمه استفاده می‌شود؛ سابقهٔ کامل شما حذف نشده است.');
                } else if (data.code === 'retrying') {
                    showChatNotice('ارتباط با مدل لحظه‌ای قطع شد؛ در حال تلاش مجدد هستیم…');
                } else if (data.code === 'model_fallback') {
                    request.fallbackName = String(data.name || 'جایگزین');
                    showChatNotice('مدل انتخاب‌شده فعلاً در دسترس نیست؛ در حال تلاش با مدل ' + request.fallbackName + ' هستیم.');
                }
            } else if (event === 'error') {
                request.knownFailure = true;
                restoreRequestDraft(request);
                if (!request.answer) document.getElementById(request.typingId)?.remove();
                if (data.retry_message_id) retryIntent = {
                    id: data.retry_message_id, text: request.text, files: request.files,
                    conversationId: request.conversationId
                };
                const reference = data.request_id ? ' کد پیگیری: ' + String(data.request_id).slice(0, 8) : '';
                showChatNotice(String(data.message || friendlyAiUnavailableMessage()) + reference, 'error',
                    data.code === 'context_too_large' ? null : {label: 'تلاش دوباره', run: () => sendMessage()});
            } else if (event === 'done') {
                request.done = true;
                renderAssistantMessage(request.typingId, request.answer, true);
                isFirstAssistantMessage = false;
                clearMatchingDraft(request.text, request.conversationId);
                clearMatchingDraft(request.text, request.originalConversationId);
                showChatNotice([
                    request.fallbackName ? 'پاسخ با مدل جایگزین «' + request.fallbackName + '» آماده شد.' : '',
                    request.contextTrimmed ? 'برای این پاسخ از بخش‌های اخیر مکالمه استفاده شد؛ سابقهٔ کامل محفوظ است.' : ''
                ].filter(Boolean).join(' '));
            }
        });
        const reader = response.body.getReader();
        const decoder = new TextDecoder('utf-8');
        while (isCurrent()) {
            const {value, done} = await reader.read();
            if (!isCurrent()) return;
            if (done) { parser.push(decoder.decode()); break; }
            parser.push(decoder.decode(value, {stream: true}));
        }
        if (!request.done && !request.knownFailure) throw new Error('incomplete_stream');
    } catch (error) {
        if (!isCurrent()) return;
        restoreRequestDraft(request);
        retryIntent = null;
        if (!request.answer) document.getElementById(request.typingId)?.remove();
        const message = request.timedOut
            ? 'پاسخ بیش از حد طول کشید. متن شما محفوظ است؛ پیش از ارسال دوباره، وضعیت مکالمه را بررسی کنید.'
            : (navigator.onLine === false ? 'ارتباط اینترنت قطع شد. متن شما محفوظ است؛ بعد از اتصال، مکالمه را بازبینی کنید.' : 'دریافت پاسخ کامل نشد. متن شما محفوظ است؛ پیش از ارسال دوباره، مکالمه را بازبینی کنید.');
        showChatNotice(message, 'error', request.conversationId
            ? {label: 'بازبینی مکالمه', run: () => loadConversation(request.conversationId)} : null);
    } finally {
        clearTimeout(request.timeout);
        clearTimeout(request.waiting);
        if (isCurrent()) { activeRequest = null; setSendingState(false); }
    }
}

// ==================== مدیریت مکالمات ====================
async function loadConversation(id) {
    if (questionInput.value.trim()) saveChatDraft(questionInput.value, currentConversationId, selectedFiles.length > 0);
    cancelChatRequests();
    const revision = viewRevision;
    const controller = new AbortController();
    conversationLoader = controller;
    const timeout = setTimeout(() => controller.abort(), 20000);
    currentConversationId = String(id);
    isFirstAssistantMessage = true;
    oldestLoadedMessageId = null;
    hasOlderMessages = false;
    isLoadingOlderMessages = false;
    retryIntent = null;
    questionInput.value = '';
    selectedFiles = [];
    updateFileUI();
    closeChatSidebar();
    showChatNotice();
    chatBox.innerHTML = '<div class="text-center text-slate-400 py-8">در حال بارگذاری مکالمه…</div>';
    try {
        const response = await fetch('/chat/' + id + '?limit=' + MESSAGE_PAGE_SIZE, {
            signal: controller.signal,
            headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}
        });
        if (revision !== viewRevision) return;
        if (!response.ok) {
            const data = await response.json().catch(() => ({}));
            if (revision !== viewRevision) return;
            const details = httpChatError(response.status, data);
            showChatNotice(details.message, 'error', details.action || {label: 'بارگذاری دوباره', run: () => loadConversation(id)});
            chatBox.innerHTML = '';
            return;
        }
        const data = await response.json();
        if (revision !== viewRevision) return;
        const messages = data.messages || [];
        oldestLoadedMessageId = messages[0]?.id || null;
        hasOlderMessages = Boolean(data.has_more);
        isFirstAssistantMessage = !messages.some(msg => msg.role === 'assistant');
        chatBox.innerHTML = (hasOlderMessages ? olderMessagesButtonHtml() : '') + storedMessagesHtml(messages, true);
        const draft = readChatDraft(id);
        if (draft && !questionInput.value.trim()) {
            questionInput.value = draft.text;
            showChatNotice('پیش‌نویس قبلی بازیابی شد. پیش از ارسال، پیام‌های ثبت‌شده را بررسی کنید.' + (draft.hasFiles ? ' برای ارسال مجدد فایل، آن را دوباره انتخاب کنید.' : ''));
        }
        scrollChat(true);
    } catch (_) {
        if (revision !== viewRevision) return;
        chatBox.innerHTML = '';
        showChatNotice('مکالمه بارگذاری نشد؛ چیزی حذف نشده است. اتصال خود را بررسی و دوباره تلاش کنید.', 'error',
            {label: 'بارگذاری دوباره', run: () => loadConversation(id)});
    } finally {
        clearTimeout(timeout);
        if (conversationLoader === controller) conversationLoader = null;
    }
}

async function loadOlderMessages() {
    if (!currentConversationId || !oldestLoadedMessageId || !hasOlderMessages || isLoadingOlderMessages) return;

    const revision = viewRevision;
    const conversationId = currentConversationId;
    isLoadingOlderMessages = true;
    setOlderMessagesButtonLoading(true);

    const previousScrollHeight = chatMain ? chatMain.scrollHeight : 0;
    const previousScrollTop = chatMain ? chatMain.scrollTop : 0;

    try {
        const response = await fetch(`/chat/${currentConversationId}?limit=${MESSAGE_PAGE_SIZE}&before_id=${oldestLoadedMessageId}`, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        if (revision !== viewRevision || conversationId !== currentConversationId) return;
        if (!response.ok) throw new Error();

        const data = await response.json();
        if (revision !== viewRevision || conversationId !== currentConversationId) return;
        const messages = data.messages || [];
        const loadOlderWrap = document.getElementById('loadOlderWrap');
        if (loadOlderWrap) loadOlderWrap.remove();

        hasOlderMessages = Boolean(data.has_more);
        if (messages.length) oldestLoadedMessageId = messages[0].id;

        chatBox.insertAdjacentHTML('afterbegin', (hasOlderMessages ? olderMessagesButtonHtml() : '') + storedMessagesHtml(messages, false));

        requestAnimationFrame(() => {
            if (!chatMain) return;
            chatMain.scrollTop = chatMain.scrollHeight - previousScrollHeight + previousScrollTop;
        });
    } catch (error) {
        if (revision !== viewRevision) return;
        showChatNotice('پیام‌های قدیمی‌تر بارگذاری نشدند؛ دوباره روی نمایش پیام‌ها بزنید.', 'error');
        console.error('Load older messages error:', error);
        setOlderMessagesButtonLoading(false);
    } finally {
        if (revision === viewRevision) isLoadingOlderMessages = false;
    }
}

function newConversation() {
    if (questionInput.value.trim()) saveChatDraft(questionInput.value, currentConversationId, selectedFiles.length > 0);
    cancelChatRequests();
    currentConversationId = null;
    isFirstAssistantMessage = true;
    oldestLoadedMessageId = null;
    hasOlderMessages = false;
    isLoadingOlderMessages = false;
    retryIntent = null;
    questionInput.value = '';
    selectedFiles = [];
    updateFileUI();
    closeChatSidebar();
    showChatNotice();
    chatBox.innerHTML = '<div class="text-center text-slate-400 py-8">یک مکالمه جدید را شروع کنید. سؤال حقوقی خود را بپرسید…</div>';
    questionInput.focus();
}

async function deleteConversation(id) {
    if (!confirm('آیا از حذف این مکالمه اطمینان دارید؟')) return;
    try {
        const response = await fetch(`/chat/${id}`, {
            method: 'DELETE',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
        });
        if (!response.ok || response.redirected) {
            const details = httpChatError(response.redirected ? 401 : response.status, await response.json().catch(() => ({})));
            showChatNotice(details.message, 'error', details.action);
            return;
        }
        document.querySelector(`.conversation-item[data-id="${id}"]`)?.remove();
        if (currentConversationId == id) newConversation();
    } catch (error) {
        showChatNotice('مکالمه حذف نشد. اتصال را بررسی و دوباره تلاش کنید.', 'error');
    }
}

async function renameConversation(id) {
    const newTitle = prompt('عنوان جدید مکالمه را وارد کنید:');
    if (!newTitle?.trim()) return;
    try {
        const response = await fetch(`/chat/${id}/rename`, {
            method: 'PATCH',
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ title: newTitle.trim() })
        });
        if (!response.ok || response.redirected) {
            const details = httpChatError(response.redirected ? 401 : response.status, await response.json().catch(() => ({})));
            showChatNotice(details.message, 'error', details.action);
            return;
        }
        const titleSpan = document.querySelector(`.conversation-item[data-id="${id}"] .text-sm`);
        if (titleSpan) titleSpan.textContent = newTitle.trim();
    } catch (error) {
        showChatNotice('نام مکالمه تغییر نکرد. کمی بعد دوباره تلاش کنید.', 'error');
    }
}

// ==================== رویدادها ====================
document.querySelectorAll('.model-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        const modelKey = btn.dataset.model;
        const supportsFiles = btn.dataset.supportsFiles;
        selectModel(modelKey, supportsFiles);
        toggleModelMenu(false);
    });
});

if (selectedModelButton) {
    selectedModelButton.addEventListener('click', () => toggleModelMenu());
}

if (modelMenuBackdrop) {
    modelMenuBackdrop.addEventListener('click', () => toggleModelMenu(false));
}

if (chatBox) {
    chatBox.addEventListener('click', (e) => {
        if (e.target.closest('#loadOlderBtn')) {
            loadOlderMessages();
        }
    });
}

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') toggleModelMenu(false);
});

if (fileInput) {
    fileInput.addEventListener('change', (e) => {
        const files = Array.from(e.target.files);
        const rejected = [];

        files.forEach(file => {
            if (selectedFiles.length >= MAX_UPLOAD_FILES) {
                rejected.push(`حداکثر ${MAX_UPLOAD_FILES} فایل را می‌توانید هم‌زمان بفرستید.`);
                return;
            }

            if (!isAllowedAttachment(file)) {
                rejected.push(`فرمت فایل «${file.name}» پشتیبانی نمی‌شود.`);
                return;
            }

            if (file.size > MAX_UPLOAD_FILE_BYTES) {
                rejected.push(`حجم فایل «${file.name}» باید حداکثر ۸ مگابایت باشد.`);
                return;
            }

            selectedFiles.push(file);
        });

        updateFileUI();
        e.target.value = '';

        if (rejected.length > 0) {
            showChatNotice([...new Set(rejected)].join(' '), 'error');
        }
    });
}

if (fileInputBtn) {
    fileInputBtn.addEventListener('click', () => {
        if (fileUploadContainer && !fileUploadContainer.classList.contains('hidden')) {
            fileInput.click();
        }
    });
}

if (removeFileBtn) {
    removeFileBtn.addEventListener('click', () => {
        selectedFiles = [];
        updateFileUI();
    });
}

if (sendBtn) {
    sendBtn.addEventListener('click', sendMessage);
}

if (questionInput) {
    questionInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage();
        }
    });
    questionInput.addEventListener('input', function() {
        saveChatDraft(this.value, currentConversationId, selectedFiles.length > 0);
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 160) + 'px';
    });
}

if (chatMain) {
    chatMain.addEventListener('scroll', () => {
        shouldAutoScroll = chatMain.scrollHeight - chatMain.scrollTop - chatMain.clientHeight < 80;
        if (chatMain.scrollTop < 120 && hasOlderMessages && !isLoadingOlderMessages) {
            loadOlderMessages();
        }
    });
}

document.getElementById('stopChatBtn')?.addEventListener('click', stopChatResponse);

window.addEventListener('DOMContentLoaded', async () => {
    const firstModelBtn = document.querySelector('.model-btn');
    if (firstModelBtn) {
        selectModel(firstModelBtn.dataset.model, firstModelBtn.dataset.supportsFiles);
    }
    
    const draft = readChatDraft(null, true);
    const firstConv = document.querySelector('.conversation-item');
    if (draft?.conversationId) await loadConversation(draft.conversationId);
    else if (!draft && firstConv) await loadConversation(firstConv.dataset.id);
    else newConversation();
    if (draft && !questionInput.value.trim() && String(currentConversationId || '') === String(draft.conversationId || '')) {
        questionInput.value = draft.text;
        showChatNotice('پیش‌نویس شما بازیابی شد.' + (draft.hasFiles ? ' فایل‌ها را دوباره انتخاب کنید.' : ''));
    }
});
