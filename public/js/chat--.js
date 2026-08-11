/*   
|--------------------------------------------------------------------------   
| Chat System JS – Stable Version (Clean & Stable)   
|--------------------------------------------------------------------------   
*/ 
 
let selectedModel = "gpt"; 
let currentConversationId = null; 
let isFirstAssistantMessage = true; 
let shouldAutoScroll = true;

// DOM elements 
const chatBox       = document.getElementById("chatBox"); 
const questionInput = document.getElementById("question"); 
const fileInput     = document.getElementById("fileInput"); 

// ثابت‌ها 
const firstHeaderDisclaimer = `
<div class="text-xs leading-relaxed text-amber-800 bg-amber-50/90 border border-amber-200 rounded-2xl p-3 mb-3">
⚠ این متن با کمک هوش مصنوعی تولید شده و برای اطمینان از دقت و کیفیت، نیاز به بررسی دقیق شما دارد.
</div>`;
const footerDisclaimer = ` 
<div class="text-[11px] text-slate-400 mt-4 border-t border-slate-100 pt-2"> 
این متن توسط هوش مصنوعی تولید شده است.</div>`; 


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
                ${(text || "").replace(/\n/g, "<br>")} 
                ${attachments} 
            </div> 
        </div>`; 

    scrollChat(true);

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

    scrollChat(true);

    return id; 
} 


/* -------------------------------------------------------------------------- 
| رندر پاسخ AI — بدون مشکل Markdown 
----------------------------------------------------------------------------*/ 
function renderAssistantMessage(containerId, content, files = []) { 
    const container = document.getElementById(containerId); 
    if (!container) return; 

    let isHtml = (content || "").trim().startsWith("<"); 
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

   if (shouldAutoScroll) {
    scrollChat();
}

} 


/* -------------------------------------------------------------------------- 
| اسکرول 
----------------------------------------------------------------------------*/ 
function scrollChat(force = false) {
    if (!chatBox) return;
    if (!force && !shouldAutoScroll) return;
    chatBox.scrollTop = chatBox.scrollHeight + 100; /* +100 برای اطمینان کامل از دیده شدن آخرین خط */
}


/* -------------------------------------------------------------------------- 
| تایپ‌نویس پایدار (یک صف برای هر پیام)
|  - مشکل بهم‌ریختگی از setInterval های متعدد را حل می‌کند
----------------------------------------------------------------------------*/ 
const typewriterState = new Map(); // containerId -> {queue:[], timer:null, pre:null}

function ensurePreContainer(containerId) {
    const container = document.getElementById(containerId); 
    if (!container) return null;

    let pre = container.querySelector("pre");
    if (!pre) {
        container.innerHTML = `
          <pre style="
    white-space: pre-wrap;
    word-break: break-word;
    direction: rtl;
    unicode-bidi: plaintext;
    font-family: inherit;
    line-height: 1.9;
    margin: 0;
"></pre>

        `;
        pre = container.querySelector("pre");
    }
    return pre;
}

function streamText(containerId, text) { 
    if (!text) return;

    const pre = ensurePreContainer(containerId);
    if (!pre) return;

    if (!typewriterState.has(containerId)) {
        typewriterState.set(containerId, { queue: [], timer: null, pre });
    }

    const st = typewriterState.get(containerId);
    st.pre = pre;

    // به صف اضافه کن
    for (let i = 0; i < text.length; i++) {
        st.queue.push(text[i]);
    }

    // اگر تایمر فعال نیست، شروع کن
 if (!st.timer) {
    st.timer = setInterval(() => {
        const livePre = ensurePreContainer(containerId);
        if (!livePre) {
            clearInterval(st.timer);
            st.timer = null;
            return;
        }

        if (st.queue.length === 0) {
            clearInterval(st.timer);
            st.timer = null;
            return;
        }

        // 🎯 سرعت تطبیقی (ChatGPT-like)
        let batch = 1;

        if (st.queue.length > 300) batch = 4;
        else if (st.queue.length > 180) batch = 3;
        else if (st.queue.length > 80)  batch = 2;

        for (let i = 0; i < batch; i++) {
            if (st.queue.length === 0) break;
            livePre.textContent += st.queue.shift();
        }

       if (shouldAutoScroll) {
    scrollChat();
}


    }, 18); // ریتم انسانی‌تر از 12ms
}

}


/* -------------------------------------------------------------------------- 
| ارسال پیام 
----------------------------------------------------------------------------*/ 
async function sendMessage() { 
    if (!questionInput || !chatBox) return; 

    const text = questionInput.value.trim(); 
    const hasFiles = fileInput.files && fileInput.files.length > 0; 
    if (!text && !hasFiles) return; 

    // پیام کاربر 
    addUserMessage(text || "📎 فایل ارسال شد"); 
    questionInput.value = ""; 

    // placeholder پاسخ AI 
    const aiId = addAiTyping();

// مکث کوتاه مثل ChatGPT
    await new Promise(r => setTimeout(r, 450));


    const formData = new FormData(); 
    formData.append("question", text); 
    formData.append("model", selectedModel); 

    if (currentConversationId) { 
        formData.append("conversation_id", currentConversationId); 
    } 

    if (hasFiles) { 
        [...fileInput.files].forEach(f => formData.append("files[]", f)); 
    } 

    // 🔹 جمع‌کننده متن کامل پاسخ 
    let fullAnswer = ""; 

    try { 
        const res = await fetch("/chat/send", { 
            method: "POST", 
            headers: { 
                "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content, 
                "X-Requested-With": "XMLHttpRequest" 
            }, 
            body: formData 
        }); 

        // اگر خطا بود JSON خطا را نشان بده
        if (!res.ok) {
            let errText = "❌ خطا در ارتباط با سرور";
            try {
                const data = await res.json();
                if (data?.error) errText = data.error;
            } catch {}
            renderAssistantMessage(aiId, errText);
            return;
        }

        if (!res.body) { 
            throw new Error("Stream failed"); 
        } 

        // اگر conversation_id از header آمد 
        const convoId = res.headers.get("X-Conversation-Id"); 
        if (convoId) { 
            currentConversationId = convoId; 
        } 

        const reader  = res.body.getReader(); 
        const decoder = new TextDecoder("utf-8"); 

        // ظرف خالی برای استریم
        streamText(aiId, "");

        // برای حذف JSON اول مثل {"conversation_id":8}\n\n اگر سرور وسط stream فرستاد
        let firstMetaHandled = false;
        let pending = "";

        while (true) { 
            const { value, done } = await reader.read(); 
            if (done) break; 

            const chunk = decoder.decode(value, { stream: true }); 
            if (!chunk) continue; 

            pending += chunk;

            // فقط یک‌بار تلاش کن meta را جدا کنی
            if (!firstMetaHandled) {
                // دنبال جداکننده \n\n بگرد
                const sep = pending.indexOf("\n\n");
                if (sep !== -1) {
                    const possibleJson = pending.slice(0, sep).trim();
                    const rest = pending.slice(sep + 2);

                    // اگر واقعاً JSON بود و conversation_id داشت، حذفش کن
                    if (possibleJson.startsWith("{") && possibleJson.endsWith("}")) {
                        try {
                            const data = JSON.parse(possibleJson);
                            if (data?.conversation_id && !currentConversationId) {
                                currentConversationId = data.conversation_id;
                            }
                            // meta را دور ریختیم، ادامه از rest
                            pending = rest;
                        } catch {
                            // JSON نبود → همان را هم استریم کن
                        }
                    }

                    firstMetaHandled = true;
                }
            }

            // اگر pending خیلی بزرگ شد یا meta هندل شد، استریم کن
            if (firstMetaHandled && pending.length) {
               const CHUNK_SIZE = 24;

for (let i = 0; i < pending.length; i += CHUNK_SIZE) {
    const slice = pending.slice(i, i + CHUNK_SIZE);
    fullAnswer += slice;
    streamText(aiId, slice);
}
pending = "";

            } else if (!firstMetaHandled && pending.length > 4096) {
                // اگر جداکننده نیامد، برای گیر نکردن:
                fullAnswer += pending;
                streamText(aiId, pending);
                pending = "";
                firstMetaHandled = true;
            }
        } 

        // هر چی ته مانده
        if (pending) {
            fullAnswer += pending;
            streamText(aiId, pending);
            pending = "";
        }

        // ⏳ صبر کوتاه تا صف تایپ خالی‌تر شود، بعد رندر نهایی Markdown
        // (اگر نخواهی، این خط را حذف کن و مستقیم رندر کن)
const finalize = () => {
    if (isTypewriterIdle(aiId)) {
        renderAssistantMessage(aiId, fullAnswer);
    } else {
        setTimeout(finalize, 60);
    }
};

finalize();



        if (fileInput) fileInput.value = ""; 

    } catch (e) { 
        console.error(e); 
        renderAssistantMessage(aiId, "❌ خطا در ارتباط با سرور"); 
    } 
} 


/* -------------------------------------------------------------------------- 
| بارگذاری مکالمه 
----------------------------------------------------------------------------*/ 
/* در فایل /js/chat.js ، تابع loadConversation را دقیقاً با این کد جایگزین کنید */
async function loadConversation(id) {
    currentConversationId = id;
    isFirstAssistantMessage = true;
    chatBox.innerHTML = `
        <div class="flex justify-center py-6 text-xs text-slate-500">
            در حال بارگذاری مکالمه...
        </div>`;

    try {
        const res = await fetch(`/chat/${id}`, { 
            headers: { 
                "Accept": "application/json",
                "X-Requested-With": "XMLHttpRequest"  // مهم برای تشخیص AJAX در Laravel
            } 
        });

        // اگر وضعیت 404 یا 500 یا هر خطایی بود، مستقیم مکالمه جدید بساز
        if (!res.ok) {
            throw new Error(`HTTP ${res.status}`);
        }

        const convo = await res.json();

        // اگر ساختار JSON اشتباه بود (مثلاً messages وجود نداشت)
        if (!Array.isArray(convo.messages)) {
            throw new Error('Invalid response format');
        }

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

        scrollChat(true);
    } catch (e) {
        console.error('Error loading conversation:', e);
        // به جای پیام قرمز "مکالمه جدید"، مستقیم مکالمه جدید بساز و پیام خوش‌آمدگویی نشون بده
        newConversation();
    }
}

/* همچنین تابع newConversation را کمی بهتر کن (اختیاری اما توصیه می‌شه) */
function newConversation() {
    currentConversationId = null;
    isFirstAssistantMessage = true;
    chatBox.innerHTML = `
        <div class="text-center py-10 text-slate-600">
            <p class="text-lg font-medium mb-2">به وکیل هوش مصنوعی خوش آمدید! 👋</p>
            <p class="text-sm">سؤال حقوقی خود را بنویسید و ارسال کنید.</p>
        </div>`;
    scrollChat(true);
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

chatBox.addEventListener("scroll", () => {
    const threshold = 120;
    shouldAutoScroll =
        chatBox.scrollHeight - chatBox.scrollTop - chatBox.clientHeight < threshold;
});

/* -------------------------------------------------------------------------- 
| بارگذاری اتوماتیک آخرین مکالمه 
----------------------------------------------------------------------------*/ 
window.addEventListener("DOMContentLoaded", () => { 
    const first = document.querySelector(".conversation-item"); 
    if (first) loadConversation(first.getAttribute("data-id")); 
    else newConversation(); 
}); 
function isTypewriterIdle(containerId) {
    const st = typewriterState.get(containerId);
    return !st || (st.queue.length === 0 && !st.timer);
}
/* --------------------------------------------------------------------------
| Toggle Sidebar (Mobile Hamburger Menu)
----------------------------------------------------------------------------*/
function toggleSidebar() {
  const sidebar = document.getElementById("sidebar");
  const backdrop = document.getElementById("sidebarBackdrop");
  if (!sidebar || !backdrop) return;

  const open = sidebar.classList.contains("translate-x-0");

  if (open) {
    sidebar.classList.remove("translate-x-0");
    sidebar.classList.add("translate-x-full");
    backdrop.classList.add("hidden");
  } else {
    sidebar.classList.remove("translate-x-full");
    sidebar.classList.add("translate-x-0");
    backdrop.classList.remove("hidden");
  }
}
