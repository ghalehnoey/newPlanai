/*  
|--------------------------------------------------------------------------  
| Chat System JS – Stable Version (Clean & Stable)  
|--------------------------------------------------------------------------  
*/

let selectedModel = "gpt";
let currentConversationId = null;
let isFirstAssistantMessage = true;

// DOM elements
const chatBox       = document.getElementById("chatBox");
const questionInput = document.getElementById("question");
const fileInput     = document.getElementById("fileInput");

// ثابت‌ها
const firstHeaderDisclaimer = `  
<div class="text-xs leading-relaxed text-amber-800 bg-amber-50/90 border border-amber-200 rounded-2xl p-3 mb-3">  
⚠ پاسخ ها توسط هوش مصنوعی است. لطفاً برای اطمینان بیشتر با وکیل متخصص مشورت کنید.</div>`;  

const footerDisclaimer = `  
<div class="text-[11px] text-slate-400 mt-4 border-t border-slate-100 pt-2">  
برای اطمینان بیشتر، لطفاً با وکیل متخصص مشورت کنید.</div>`;

/* --------------------------------------------------------------------------
| انتخاب مدل
----------------------------------------------------------------------------*/
function selectModel(model, btn) {
    selectedModel = model;
    document.querySelectorAll(".modelBtn").forEach(b => b.classList.remove("active"));
    if (btn) btn.classList.add("active");
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
| تایپ AI
----------------------------------------------------------------------------*/
function addAiTyping() {
    const id = "ai_" + Date.now();

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
    return id;
}


/* --------------------------------------------------------------------------
| رندر پاسخ AI — بدون مشکل Markdown
----------------------------------------------------------------------------*/
function renderAssistantMessage(containerId, content, files = []) {
    const container = document.getElementById(containerId);
    if (!container) return;

    let isHtml = content.trim().startsWith("<");
    let html = "";

    if (!isHtml) {
        if (isFirstAssistantMessage) {
            html += firstHeaderDisclaimer;
            isFirstAssistantMessage = false;
        }
        html += marked.parse(content || "");
        html += footerDisclaimer;
    } else {
        // اگر HTML خام است → markdown نکن
        html = content;
    }

    // فایل‌ها
    if (files.length && !isHtml) {
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
        <div class="markdown-body prose prose-sm max-w-none text-slate-800">
            ${html}
        </div>`;

    scrollChat();
}


/* --------------------------------------------------------------------------
| اسکرول
----------------------------------------------------------------------------*/
function scrollChat() {
    setTimeout(() => {
        chatBox.scrollTop = chatBox.scrollHeight;
    }, 60);
}


/* --------------------------------------------------------------------------
| ارسال پیام
----------------------------------------------------------------------------*/
async function sendMessage() {
    if (!questionInput || !chatBox) return;

    const text = questionInput.value.trim();
    const hasFiles = fileInput.files.length > 0;

    if (!text && !hasFiles) return;

    addUserMessage(text || "📎 فایل ارسال شد");
    questionInput.value = "";
    const aiId = addAiTyping();

    const formData = new FormData();
    formData.append("question", text);
    formData.append("model", selectedModel);

    if (currentConversationId) {
        formData.append("conversation_id", currentConversationId);
    }

    if (hasFiles) {
        [...fileInput.files].forEach(f => formData.append("files[]", f));
    }

    try {
        const res = await fetch("/chat/send", {
            method: "POST",
            headers: {
                "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content,
                "X-Requested-With": "XMLHttpRequest"
            },
            body: formData
        });

        const data = await res.json();

        /* -----------------------------  
           مدیریت تمام شدن سوال / زمان  
        ------------------------------*/
        if (data.error) {
            const html = `
                <div class="w-full max-w-[85%] bg-rose-50 border border-rose-200 
                            rounded-2xl p-4 shadow-sm">

                    <div class="flex items-center gap-2">
                        <span class="text-rose-600 text-lg">❌</span>
                        <span class="text-rose-600 font-semibold text-sm">
                            ${data.error}
                        </span>
                    </div>

                    <p class="text-slate-600 text-xs mt-2 leading-relaxed">
                        برای ادامه استفاده، لطفاً پلن خود را تمدید کنید.
                    </p>

                    <div class="mt-3 text-center">
                        <a href="${data.upgrade_url ?? '/plans'}"
                           class="inline-block px-5 py-2.5 bg-indigo-600 hover:bg-indigo-700 
                                  text-white rounded-xl text-sm font-medium shadow-md transition">
                            🔄 مشاهده پلن‌ها و تمدید اشتراک
                        </a>
                    </div>
                </div>`;

            renderAssistantMessage(aiId, html);
            return;
        }

        if (data.conversation_id) {
            currentConversationId = data.conversation_id;
        }

        renderAssistantMessage(aiId, data.answer);
        fileInput.value = "";

    } catch (e) {
        renderAssistantMessage(aiId, "❌ خطا در ارتباط با سرور");
    }
}


/* --------------------------------------------------------------------------
| بارگذاری مکالمه
----------------------------------------------------------------------------*/
async function loadConversation(id) {
    currentConversationId = id;
    isFirstAssistantMessage = true;

    chatBox.innerHTML = `
        <div class="flex justify-center py-6 text-xs text-slate-500">
            در حال بارگذاری مکالمه...
        </div>`;

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

    } catch {
        chatBox.innerHTML = `
            <div class="text-center text-rose-500 py-6 text-xs">
                خطا در بارگذاری مکالمه.
            </div>`;
    }
}


/* --------------------------------------------------------------------------
| مکالمه جدید
----------------------------------------------------------------------------*/
function newConversation() {
    currentConversationId = null;
    isFirstAssistantMessage = true;
    chatBox.innerHTML = `
        <div class="flex justify-center py-6 text-xs text-slate-400">
            مکالمه جدید شروع شد. سؤال خود را بپرسید.
        </div>`;
}


/* --------------------------------------------------------------------------
| حذف مکالمه
----------------------------------------------------------------------------*/
async function deleteConversation(id) {
    if (!confirm("مطمئن هستید؟")) return;

    await fetch(`/chat/${id}`, {
        method: "DELETE",
        headers: {
            "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content
        }
    });

    const item = document.querySelector(`.conversation-item[data-id="${id}"]`);
    if (item) item.remove();

    if (currentConversationId === id) newConversation();
}


/* --------------------------------------------------------------------------
| تغییر عنوان
----------------------------------------------------------------------------*/
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


/* --------------------------------------------------------------------------
| Enter → Send
----------------------------------------------------------------------------*/
questionInput.addEventListener("keydown", e => {
    if (e.key === "Enter" && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
    }
});


/* --------------------------------------------------------------------------
| بارگذاری اتوماتیک آخرین مکالمه
----------------------------------------------------------------------------*/
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