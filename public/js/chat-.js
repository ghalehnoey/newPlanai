/* |--------------------------------------------------------------------------
| Chat System JS – با پشتیبانی از Streaming (کاراکتر به کاراکتر)
|--------------------------------------------------------------------------
*/

let selectedModel = "gpt";
let currentConversationId = null;
let streamingActive = false; // وضعیت استریم

// DOM elements
const chatBox = document.getElementById("chatBox");
const questionInput = document.getElementById("question");
const fileInput = document.getElementById("fileInput");

// هشدار حقوقی
const footerDisclaimer = `
<div class="text-[11px] text-slate-400 mt-4 border-t border-slate-100 pt-2">
پاسخ بالا توسط هوش مصنوعی تهیه شده است. در صورت **حساس بودن یا پیچیدگی موضوع حقوقی خود**، بهتر است به وکیل مراجعه فرمایید.
</div>`;

/* --------------------------------------------------------------------------
| انتخاب مدل
----------------------------------------------------------------------------*/
function selectModel(model, btn) {
    selectedModel = model;

    document.querySelectorAll(".modelBtn").forEach(b => {
        b.classList.remove("active");
    });

    if (btn) {
        btn.classList.add("active");
    }
}

/* --------------------------------------------------------------------------
| پیام کاربر
----------------------------------------------------------------------------*/
function addUserMessage(text, files = []) {
    if (!chatBox) return;

    let attachments = "";
    if (files.length) {
        attachments = `<div class="mt-2 space-y-1 text-xs">`;
        files.forEach(f => {
            attachments += `
                <div>
                    <a href="/chat/file/${f.id}" target="_blank" class="text-indigo-600 underline">
                        📎 ${f.original_name}
                    </a>
                </div>`;
        });
        attachments += "</div>";
    }

    chatBox.innerHTML += `
        <div class="mb-4 flex justify-end">
            <div class="max-w-[80%] rounded-2xl bg-indigo-50 border border-indigo-100 px-4 py-3 text-sm leading-relaxed text-slate-800 shadow-sm">
                ${text.replace(/\n/g, "<br>")}
                ${attachments}
            </div>
        </div>`;

    scrollChat();
}

/* --------------------------------------------------------------------------
| شروع استریم (انیمیشن تایپ)
----------------------------------------------------------------------------*/
function startStreaming() {
    if (streamingActive) return null;

    const id = "stream_" + Date.now();
    chatBox.innerHTML += `
        <div class="mb-4 flex justify-start" id="${id}">
            <div class="max-w-[85%] rounded-2xl bg-white border border-slate-200 px-5 py-4 shadow-sm">
                <div class="flex items-center gap-3">
                    <div class="typing-indicator"><span></span><span></span><span></span></div>
                    <span class="text-sm text-slate-500">در حال تایپ...</span>
                </div>
            </div>
        </div>`;
    scrollChat();
    streamingActive = true;
    return id;
}

/* --------------------------------------------------------------------------
| به‌روزرسانی استریم (کاراکتر به کاراکتر)
----------------------------------------------------------------------------*/
function updateStreaming(containerId, content) {
    const container = document.getElementById(containerId);
    if (!container) return;

    const html = marked.parse(content || " ") + footerDisclaimer;

    container.innerHTML = `
        <div class="markdown-body prose max-w-none text-slate-800">
            ${html}
        </div>`;
    scrollChat();
}

/* --------------------------------------------------------------------------
| پایان استریم و رندر نهایی
----------------------------------------------------------------------------*/
function finishStreaming(containerId, finalContent, files = []) {
    // از تابع قدیمی استفاده می‌کنیم تا فایل‌ها و markdown درست رندر بشن
    renderAssistantMessage(containerId, finalContent, files);
    streamingActive = false;
}

/* --------------------------------------------------------------------------
| رندر پاسخ AI (برای چت‌های قبلی و پایان استریم)
----------------------------------------------------------------------------*/
function renderAssistantMessage(containerId, content, files = []) {
    const container = document.getElementById(containerId);
    if (!container) return;

    let html = marked.parse(content || "");
    html += footerDisclaimer;

    if (files.length) {
        html += `<div class="mt-3 space-y-1">`;
        files.forEach(f => {
            html += `
                <div>
                    <a href="/chat/file/${f.id}" target="_blank" class="text-indigo-600 underline">
                        📎 ${f.original_name}
                    </a>
                </div>`;
        });
        html += `</div>`;
    }

    container.innerHTML = `
        <div class="markdown-body prose max-w-none text-slate-800">
            ${html}
        </div>`;

    scrollChat();
}

/* --------------------------------------------------------------------------
| اسکرول
----------------------------------------------------------------------------*/
function scrollChat() {
    setTimeout(() => {
        chatBox.scrollTo({ top: chatBox.scrollHeight, behavior: 'smooth' });
    }, 60);
}

/* --------------------------------------------------------------------------
| ارسال پیام (با پشتیبانی از استریم)
----------------------------------------------------------------------------*/
async function sendMessage() {
    if (!questionInput || !chatBox) return;

    const text = questionInput.value.trim();
    const hasFiles = fileInput.files.length > 0;

    if (!text && !hasFiles) return;

    addUserMessage(text || "📎 فایل ارسال شد");
    questionInput.value = "";

    const streamId = startStreaming();
    const isStreaming = !!streamId;

    const formData = new FormData();
    formData.append("question", text);
    formData.append("model", selectedModel);
    if (currentConversationId) formData.append("conversation_id", currentConversationId);
    if (hasFiles) [...fileInput.files].forEach(f => formData.append("files[]", f));

    try {
        const headers = {
            "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content,
            "X-Requested-With": "XMLHttpRequest"
        };

        // اگر استریم فعال باشه، هدر Accept رو برای SSE تنظیم کن
        if (isStreaming) {
            headers["Accept"] = "text/event-stream";
        }

        const res = await fetch("/chat/send", {
            method: "POST",
            headers,
            body: formData
        });

        if (!res.ok) {
            throw new Error(`HTTP error! status: ${res.status}`);
        }

        // حالت استریم
        if (isStreaming && res.headers.get("content-type")?.includes("text/event-stream")) {
            const reader = res.body.getReader();
            const decoder = new TextDecoder();
            let buffer = "";
            let fullContent = "";

            while (true) {
                const { done, value } = await reader.read();
                if (done) break;

                buffer += decoder.decode(value, { stream: true });
                const lines = buffer.split("\n");
                buffer = lines.pop() || "";

                for (let line of lines) {
                    line = line.trim();
                    if (line.startsWith("data: ")) {
                        const data = line.slice(6);
                        if (data === "[DONE]") continue;

                        try {
                            const json = JSON.parse(data);
                            fullContent += json.chunk || json.text || data;
                        } catch {
                            fullContent += data + "\n";
                        }
                        updateStreaming(streamId, fullContent);
                    }
                }
            }

            // فرض می‌کنیم بک‌اند conversation_id و فایل‌ها رو در JSON آخر می‌فرسته (بعداً اضافه می‌شه)
            finishStreaming(streamId, fullContent.trim());
        } 
        // حالت عادی (غیر استریم)
        else {
            const data = await res.json();

            if (data.error) {
                const html = `...`; // همون کد خطای قبلی (کپی کن)
                renderAssistantMessage(streamId || "temp_" + Date.now(), html);
                return;
            }

            if (data.conversation_id) {
                currentConversationId = data.conversation_id;
            }

            renderAssistantMessage(streamId || "temp_" + Date.now(), data.answer, data.files || []);
        }

        fileInput.value = "";

    } catch (e) {
        console.error("Fetch Error:", e);
        const fallbackId = streamId || "temp_" + Date.now();
        renderAssistantMessage(fallbackId, "❌ خطا در ارتباط با سرور: " + (e.message || "لطفاً دوباره تلاش کنید."));
        streamingActive = false;
    }
}

/* --------------------------------------------------------------------------
| بارگذاری مکالمه (بدون استریم)
----------------------------------------------------------------------------*/
async function loadConversation(id) {
    currentConversationId = id;

    chatBox.innerHTML = `<div class="flex justify-center py-6 text-xs text-slate-500">در حال بارگذاری...</div>`;

    try {
        const res = await fetch(`/chat/${id}`, { headers: { "Accept": "application/json" } });
        const convo = await res.json();

        chatBox.innerHTML = "";

        convo.messages.forEach(msg => {
            if (msg.role === "user") {
                addUserMessage(msg.content, msg.files || []);
            } else {
                const cid = "msg_" + msg.id;
                chatBox.innerHTML += `<div class="mb-4 flex justify-start" id="${cid}"></div>`;
                renderAssistantMessage(cid, msg.content, msg.files || []);
            }
        });
    } catch (e) {
        chatBox.innerHTML = `<div class="text-center text-rose-500 py-6 text-xs">خطا در بارگذاری مکالمه.</div>`;
    }
}

/* --------------------------------------------------------------------------
| بقیه توابع (newConversation, delete, rename, etc.)
----------------------------------------------------------------------------*/
function newConversation() {
    currentConversationId = null;
    chatBox.innerHTML = `<div class="flex justify-center py-6 text-xs text-slate-400">مکالمه جدید شروع شد. سؤال خود را بپرسید.</div>`;
}

async function deleteConversation(id) {
    if (!confirm("مطمئن هستید؟")) return;

    await fetch(`/chat/${id}`, {
        method: "DELETE",
        headers: { "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content }
    });

    document.querySelector(`.conversation-item[data-id="${id}"]`)?.remove();
    if (currentConversationId === id) newConversation();
}

async function renameConversation(id) {
    const newTitle = prompt("عنوان جدید:");
    if (!newTitle) return;

    await fetch(`/chat/${id}/rename`, {
        method: "PATCH",
        headers: {
            "Content-Type": "application/json",
            "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({ title: newTitle })
    });

    const item = document.querySelector(`.conversation-item[data-id="${id}"] .text-[13px]`);
    if (item) item.textContent = newTitle;
}

questionInput.addEventListener("keydown", e => {
    if (e.key === "Enter" && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
});

window.addEventListener("DOMContentLoaded", () => {
    const first = document.querySelector(".conversation-item");
    if (first) loadConversation(first.getAttribute("data-id"));
    else newConversation();
});

function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    sidebar.classList.toggle('translate-x-full');
    sidebar.classList.toggle('translate-x-0');
    backdrop.classList.toggle('hidden');
}