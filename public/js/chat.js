// ==================== متغیرهای سراسری ====================
let currentModel = null;
let currentModelSupportsFiles = false;
let currentConversationId = null;
let isFirstAssistantMessage = true;
let shouldAutoScroll = true;
let selectedFiles = [];
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
        fileNameDisplay.textContent = selectedFiles.length === 1 ? selectedFiles[0].name : `${selectedFiles.length} فایل`;
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

function assistantInnerHtml(content, showNotice = false) {
    const firstHeader = '<div class="text-xs text-amber-800 bg-amber-50 rounded-xl p-3 mb-3">⚠️ این متن توسط هوش مصنوعی تولید شده و نیاز به بررسی دارد.</div>';
    const footer = '<div class="text-[10px] text-slate-400 mt-3 pt-2 border-t border-slate-100">🤖 تولید شده توسط هوش مصنوعی</div>';

    let html = showNotice ? firstHeader : '';
    html += marked.parse(content || '');
    html += footer;

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
    const id = 'ai-typing-' + Date.now();
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

function renderAssistantMessage(containerId, content) {
    const container = document.getElementById(containerId);
    if (!container) return;
    
    container.innerHTML = `<div class="chat-message-ai bg-white border border-slate-200 rounded-2xl px-4 py-2.5 shadow-sm prose prose-sm max-w-full">${assistantInnerHtml(content, isFirstAssistantMessage)}</div>`;
    scrollChat();
}

function friendlyAiUnavailableMessage() {
    return 'در حال حاضر پاسخ‌دهی این مدل با اختلال موقت روبه‌رو شد. لطفاً چند لحظه بعد دوباره تلاش کنید یا یک مدل دیگر را انتخاب کنید.';
}

// ==================== ارسال پیام با استریم ====================
async function sendMessage() {
    if (!currentModel) {
        alert('لطفاً یک مدل انتخاب کنید.');
        return;
    }
    
    const text = questionInput.value.trim();
    if (!text && selectedFiles.length === 0) return;
    
    // نمایش پیام کاربر
    const tempFiles = selectedFiles.map(f => ({
        url: URL.createObjectURL(f),
        name: f.name,
        isImage: f.type.startsWith('image/')
    }));
    addUserMessage(text, tempFiles);
    
    // پاک کردن فرم
    questionInput.value = '';
    questionInput.style.height = 'auto';
    const filesToSend = [...selectedFiles];
    selectedFiles = [];
    updateFileUI();
    
    // نمایش تایپینگ
    const typingId = addAiTyping();
    await new Promise(r => setTimeout(r, 50));
    
    // آماده‌سازی FormData
    const formData = new FormData();
    formData.append('question', text);
    formData.append('model', currentModel);
    if (filesToSend.length && currentModelSupportsFiles) {
        filesToSend.forEach(f => formData.append('files[]', f));
    }
    if (currentConversationId) formData.append('conversation_id', currentConversationId);
    
    try {
        const response = await fetch('/chat/send', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        });
        
        if (!response.ok) {
            let errorData = await response.json().catch(() => ({}));
            const serverMessage = String(errorData.error || '');
            const shouldShowServerMessage = response.status === 403 || response.status === 422;
            renderAssistantMessage(typingId, shouldShowServerMessage ? serverMessage : friendlyAiUnavailableMessage());
            return;
        }
        
        const newConvId = response.headers.get('X-Conversation-Id');
        if (newConvId) currentConversationId = newConvId;
        
        const contentType = response.headers.get('content-type');
        
        if (contentType && contentType.includes('application/json')) {
            const data = await response.json();
            if (data.conversation_id) currentConversationId = data.conversation_id;
            renderAssistantMessage(typingId, data.answer || 'پاسخی دریافت نشد');
            isFirstAssistantMessage = false;
            return;
        }
        
        const reader = response.body.getReader();
        const decoder = new TextDecoder('utf-8');
        let fullAnswer = '';
        
        const container = document.getElementById(typingId);
        container.innerHTML = `<div class="chat-message-ai bg-white border border-slate-200 rounded-2xl px-4 py-2.5 shadow-sm prose prose-sm max-w-full"></div>`;
        const contentDiv = container.querySelector('div');
        
        while (true) {
            const { value, done } = await reader.read();
            if (done) break;
            const chunk = decoder.decode(value, { stream: true });
            fullAnswer += chunk;
            
            let html = isFirstAssistantMessage ? '<div class="text-xs text-amber-800 bg-amber-50 rounded-xl p-3 mb-3">⚠️ این متن توسط هوش مصنوعی تولید شده و نیاز به بررسی دارد.</div>' : '';
            html += marked.parse(fullAnswer);
            contentDiv.innerHTML = html;
            if (shouldAutoScroll) scrollChat();
        }
        
        contentDiv.innerHTML += '<div class="text-[10px] text-slate-400 mt-3 pt-2 border-t border-slate-100">🤖 تولید شده توسط هوش مصنوعی</div>';
        isFirstAssistantMessage = false;
        
    } catch (error) {
        console.error('Send message error:', error);
        renderAssistantMessage(typingId, friendlyAiUnavailableMessage());
    }
}

// ==================== مدیریت مکالمات ====================
async function loadConversation(id) {
    currentConversationId = id;
    isFirstAssistantMessage = true;
    oldestLoadedMessageId = null;
    hasOlderMessages = false;
    isLoadingOlderMessages = false;
    chatBox.innerHTML = '<div class="text-center text-slate-400 py-8">در حال بارگذاری مکالمه...</div>';
    
    try {
        const response = await fetch(`/chat/${id}?limit=${MESSAGE_PAGE_SIZE}`, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        if (!response.ok) throw new Error();
        
        const data = await response.json();
        const messages = data.messages || [];
        oldestLoadedMessageId = messages[0]?.id || null;
        hasOlderMessages = Boolean(data.has_more);
        isFirstAssistantMessage = !messages.some(msg => msg.role === 'assistant');

        chatBox.innerHTML = (hasOlderMessages ? olderMessagesButtonHtml() : '') + storedMessagesHtml(messages, true);
        scrollChat(true);
    } catch (error) {
        console.error('Load conversation error:', error);
        newConversation();
    }
}

async function loadOlderMessages() {
    if (!currentConversationId || !oldestLoadedMessageId || !hasOlderMessages || isLoadingOlderMessages) return;

    isLoadingOlderMessages = true;
    setOlderMessagesButtonLoading(true);

    const previousScrollHeight = chatMain ? chatMain.scrollHeight : 0;
    const previousScrollTop = chatMain ? chatMain.scrollTop : 0;

    try {
        const response = await fetch(`/chat/${currentConversationId}?limit=${MESSAGE_PAGE_SIZE}&before_id=${oldestLoadedMessageId}`, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        if (!response.ok) throw new Error();

        const data = await response.json();
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
        console.error('Load older messages error:', error);
        setOlderMessagesButtonLoading(false);
    } finally {
        isLoadingOlderMessages = false;
    }
}

function newConversation() {
    currentConversationId = null;
    isFirstAssistantMessage = true;
    oldestLoadedMessageId = null;
    hasOlderMessages = false;
    isLoadingOlderMessages = false;
    chatBox.innerHTML = '<div class="text-center text-slate-400 py-8">یک مکالمه جدید را شروع کنید. سؤال حقوقی خود را بپرسید...</div>';
}

async function deleteConversation(id) {
    if (!confirm('آیا از حذف این مکالمه اطمینان دارید؟')) return;
    try {
        await fetch(`/chat/${id}`, {
            method: 'DELETE',
            headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
        });
        document.querySelector(`.conversation-item[data-id="${id}"]`)?.remove();
        if (currentConversationId == id) newConversation();
    } catch (error) {
        alert('خطا در حذف مکالمه');
    }
}

async function renameConversation(id) {
    const newTitle = prompt('عنوان جدید مکالمه را وارد کنید:');
    if (!newTitle?.trim()) return;
    try {
        await fetch(`/chat/${id}/rename`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
            },
            body: JSON.stringify({ title: newTitle.trim() })
        });
        const titleSpan = document.querySelector(`.conversation-item[data-id="${id}"] .text-sm`);
        if (titleSpan) titleSpan.textContent = newTitle.trim();
    } catch (error) {
        alert('خطا در تغییر نام');
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
        files.forEach(f => selectedFiles.push(f));
        updateFileUI();
        e.target.value = '';
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

window.addEventListener('DOMContentLoaded', () => {
    const firstModelBtn = document.querySelector('.model-btn');
    if (firstModelBtn) {
        selectModel(firstModelBtn.dataset.model, firstModelBtn.dataset.supportsFiles);
    }
    
    const firstConv = document.querySelector('.conversation-item');
    if (firstConv) {
        loadConversation(firstConv.dataset.id);
    } else {
        newConversation();
    }
});
