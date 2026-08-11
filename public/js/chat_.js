/*   
|--------------------------------------------------------------------------   
| Professional AI Chat System JS (Streaming + File Support)
|--------------------------------------------------------------------------   
*/ 

// متغیرهای سراسری
let selectedModel = document.getElementById("model")?.value || "deepseek"; 
let currentConversationId = null; 
let isFirstAssistantMessage = true; 
let shouldAutoScroll = true;
let selectedFiles = []; 

// المان‌های DOM 
const chatBox             = document.getElementById("chatBox"); 
const questionInput       = document.getElementById("question"); 
const fileInput           = document.getElementById("fileInput"); 
const fileInputBtn        = document.getElementById("fileInputBtn");
const fileUploadContainer = document.getElementById("fileUploadContainer");
const filePreviewContainer= document.getElementById("filePreviewContainer");
const fileNameDisplay     = document.getElementById("fileNameDisplay");
const removeFileBtn       = document.getElementById("removeFileBtn");

// ثابت‌ها برای هشدارها
const firstHeaderDisclaimer = `
<div class="text-xs leading-relaxed text-amber-800 bg-amber-50/90 border border-amber-200 rounded-2xl p-3 mb-3">
⚠ این متن با کمک هوش مصنوعی تولید شده و برای اطمینان از دقت و کیفیت، نیاز به بررسی دقیق شما دارد.
</div>`;
const footerDisclaimer = ` 
<div class="text-[11px] text-slate-400 mt-4 border-t border-slate-100 pt-2"> 
این متن توسط هوش مصنوعی تولید شده است.</div>`; 


/* -------------------------------------------------------------------------- 
| ۱. مدیریت فایل و پیش‌نمایش 
----------------------------------------------------------------------------*/ 

// اتصال دکمه گیره کاغذ به اینپوت مخفی فایل
if (fileInputBtn && fileInput) {
    fileInputBtn.addEventListener("click", () => {
        fileInput.click();
    });
}

function updateFileUI() {
    if (!fileInputBtn || !filePreviewContainer) return;
    
    if (selectedFiles.length > 0) {
        // تغییر رنگ دکمه گیره کاغذ
        fileInputBtn.classList.add('bg-indigo-100', 'text-indigo-600', 'border-indigo-300');
        fileInputBtn.classList.remove('bg-slate-50', 'text-slate-500');
        
        // نمایش باکس پیش‌نمایش نام فایل
        filePreviewContainer.classList.remove('hidden');
        if (selectedFiles.length === 1) {
            fileNameDisplay.textContent = selectedFiles[0].name;
        } else {
            fileNameDisplay.textContent = `${selectedFiles.length} فایل انتخاب شد`;
        }
    } else {
        // برگرداندن به حالت اولیه
        fileInputBtn.classList.remove('bg-indigo-100', 'text-indigo-600', 'border-indigo-300');
        fileInputBtn.classList.add('bg-slate-50', 'text-slate-500');
        
        filePreviewContainer.classList.add('hidden');
        if (fileNameDisplay) fileNameDisplay.textContent = "";
        if (fileInput) fileInput.value = ""; 
    }
}

if (fileInput) {
    fileInput.addEventListener("change", function(e) {
        const files = Array.from(e.target.files);
        files.forEach(file => {
            // برای سادگی، فعلا فقط فایل‌های جدید را اضافه می‌کنیم
            selectedFiles.push(file);
        });
        updateFileUI();
    });
}

if (removeFileBtn) {
    removeFileBtn.addEventListener("click", () => {
        selectedFiles = [];
        updateFileUI();
    });
}


/* -------------------------------------------------------------------------- 
| ۲. رابط کاربری (UI) و رندر پیام‌ها
----------------------------------------------------------------------------*/ 
function selectModel(model, btn) { 
    selectedModel = model; 
    
    // ۱. تغییر ظاهر تمام دکمه‌های مدل به حالت غیرفعال
    document.querySelectorAll(".modelBtn").forEach(b => {
        b.classList.remove("active", "bg-indigo-500", "text-white", "opacity-100", "ring-2", "ring-indigo-300"); 
        b.classList.add("bg-slate-100", "text-slate-600", "opacity-60"); // کمرنگ کردن دکمه‌های غیرفعال
    });

    // ۲. اعمال ظاهر فعال برای دکمه انتخاب شده
    if (btn) {
        btn.classList.add("active", "bg-indigo-500", "text-white", "opacity-100", "ring-2", "ring-indigo-300"); 
        btn.classList.remove("bg-slate-100", "text-slate-600", "opacity-60");
    }
    
    // ۳. بروزرسانی مقدار اینپوت مخفی
    const modelInput = document.getElementById("model");
    if (modelInput) modelInput.value = model;

    // ۴. نمایش/مخفی کردن دکمه آپلود فایل (فقط برای Gemini)
    if (fileUploadContainer) {
        if (model === 'gemini') {
            fileUploadContainer.classList.remove('hidden');
            fileUploadContainer.style.display = 'block'; 
        } else {
            fileUploadContainer.classList.add('hidden');
            fileUploadContainer.style.display = 'none';
            // پاک کردن فایل‌های انتخاب شده هنگام خروج از مدل جمنای
            selectedFiles = [];
            updateFileUI();
        }
    }
} 

function scrollChat(force = false) {
    if (!chatBox) return;
    if (force || shouldAutoScroll) {
        chatBox.scrollTop = chatBox.scrollHeight + 100;
    }
}

function addUserMessage(text, filesUrls = []) { 
    if (!chatBox) return; 

    let filesHtml = "";
    if (filesUrls && filesUrls.length > 0) {
        filesHtml = `<div class="flex flex-wrap gap-2 mb-2 justify-end">`;
        filesUrls.forEach(url => {
            filesHtml += `<img src="${url}" class="max-w-[150px] max-h-[150px] rounded-xl object-cover border border-indigo-200 shadow-sm">`;
        });
        filesHtml += `</div>`;
    }

    chatBox.innerHTML += ` 
        <div class="mb-4 flex justify-end w-full"> 
            <div class="max-w-[85%] flex flex-col items-end">
                ${filesHtml}
                ${text ? `<div class="rounded-2xl bg-indigo-50 border border-indigo-100 px-4 py-3 text-sm leading-relaxed text-slate-800 shadow-sm text-right rtl break-words">${text.replace(/\n/g, "<br>")}</div>` : ''}
            </div> 
        </div>`; 

    scrollChat(true);
} 

function addAiTyping() { 
    const id = "ai_" + Date.now(); 
    chatBox.innerHTML += ` 
        <div class="mb-4 flex justify-start w-full" id="${id}"> 
            <div class="max-w-[85%] rounded-2xl bg-white border border-slate-200 px-5 py-4 shadow-sm"> 
                <div class="flex items-center gap-3"> 
                    <div class="typing-indicator flex gap-1">
                        <span class="w-1.5 h-1.5 bg-slate-400 rounded-full animate-bounce"></span>
                        <span class="w-1.5 h-1.5 bg-slate-400 rounded-full animate-bounce" style="animation-delay: 0.1s"></span>
                        <span class="w-1.5 h-1.5 bg-slate-400 rounded-full animate-bounce" style="animation-delay: 0.2s"></span>
                    </div> 
                    <span class="text-xs text-slate-500">در حال پردازش...</span> 
                </div> 
            </div> 
        </div>`; 
    scrollChat(true);
    return id; 
} 

function renderAssistantMessage(containerId, content) { 
    const container = document.getElementById(containerId); 
    if (!container) return; 

    let html = ""; 
    if (isFirstAssistantMessage) { 
        html += firstHeaderDisclaimer; 
    } 
    html += marked.parse(content || ""); 
    html += footerDisclaimer; 

    container.innerHTML = ` 
        <div class="markdown-body prose prose-sm max-w-[85%] text-slate-800 bg-white border border-slate-200 rounded-2xl p-4 shadow-sm rtl break-words"> 
            ${html} 
        </div>`; 

    scrollChat();
} 


/* -------------------------------------------------------------------------- 
| ۳. ارسال پیام و مدیریت Stream (هسته اصلی)
----------------------------------------------------------------------------*/ 
async function sendMessage() { 
    if (!questionInput || !chatBox) return; 

    const text = questionInput.value.trim(); 
    if (!text && selectedFiles.length === 0) return; 

    // پیش‌نمایش آنی پیام کاربر
    const tempFileUrls = selectedFiles.map(file => URL.createObjectURL(file));
    addUserMessage(text, tempFileUrls); 
    
    // ریست کردن فرم و فایل‌ها
    questionInput.value = ""; 
    if (questionInput.style) questionInput.style.height = "auto"; 
    
    const filesToSend = [...selectedFiles];
    selectedFiles = [];
    updateFileUI(); 

    const aiId = addAiTyping();
    await new Promise(r => setTimeout(r, 100)); // مکث کوتاه

    const formData = new FormData(); 
    formData.append("question", text); 
    formData.append("model", selectedModel); 
    
    if (selectedModel === 'gemini') {
        filesToSend.forEach(file => formData.append("files[]", file));
    }
    
    if (currentConversationId) formData.append("conversation_id", currentConversationId); 
    

    try { 
        const res = await fetch("/chat/send", { 
            method: "POST", 
            headers: { 
                "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.content || "", 
                "X-Requested-With": "XMLHttpRequest" 
            }, 
            body: formData 
        }); 

        if (!res.ok) {
            let errText = "❌ متاسفانه خطایی در ارتباط با سرور رخ داد.";
            try {
                const data = await res.json();
                if (data?.error) errText = data.error;
            } catch {}
            renderAssistantMessage(aiId, errText);
            return;
        }

        const headerConvoId = res.headers.get("X-Conversation-Id"); 
        if (headerConvoId) currentConversationId = headerConvoId; 

        const contentType = res.headers.get("content-type");
        if (contentType && contentType.includes("application/json")) {
            const data = await res.json();
            if (data.conversation_id) currentConversationId = data.conversation_id;
            
            let finalAnswer = data.answer || "پاسخی دریافت نشد.";
            if (!data.answer && data.messages && data.messages.length) {
                finalAnswer = data.messages[data.messages.length - 1].content;
            }
            renderAssistantMessage(aiId, finalAnswer);
            isFirstAssistantMessage = false;
            return;
        }

        if (!res.body) throw new Error("استریم پشتیبانی نمی‌شود."); 
        const reader  = res.body.getReader(); 
        const decoder = new TextDecoder("utf-8"); 

        let fullAnswer = ""; 
        let buffer = "";
        let metaParsed = false;

        const container = document.getElementById(aiId);
        container.innerHTML = `<div class="markdown-body prose prose-sm max-w-[85%] text-slate-800 bg-white border border-slate-200 rounded-2xl p-4 shadow-sm rtl break-words"></div>`;
        const contentBody = container.querySelector(".markdown-body");

        while (true) { 
            const { value, done } = await reader.read(); 
            if (done) break; 

            const chunk = decoder.decode(value, { stream: true }); 
            if (!chunk) continue; 
            buffer += chunk;

            if (!metaParsed) {
                const sep = buffer.indexOf("\n\n");
                if (sep !== -1) {
                    const possibleJson = buffer.slice(0, sep).trim();
                    if (possibleJson.startsWith("{") && possibleJson.endsWith("}")) {
                        try {
                            const data = JSON.parse(possibleJson);
                            if (data.conversation_id) currentConversationId = data.conversation_id;
                        } catch {}
                        buffer = buffer.slice(sep + 2);
                    }
                    metaParsed = true;
                    fullAnswer += buffer;
                    buffer = "";
                } else if (buffer.length > 250) {
                    metaParsed = true;
                    fullAnswer += buffer;
                    buffer = "";
                }
            } else {
                fullAnswer += buffer;
                buffer = "";
            }

            if (fullAnswer.trim().length > 0) {
                let liveHtml = isFirstAssistantMessage ? firstHeaderDisclaimer : "";
                liveHtml += marked.parse(fullAnswer);
                
                contentBody.innerHTML = liveHtml;
                if (shouldAutoScroll) scrollChat();
            }
        } 

        contentBody.innerHTML += footerDisclaimer;
        isFirstAssistantMessage = false;

    } catch (e) { 
        console.error("Fetch/Stream Error:", e); 
        renderAssistantMessage(aiId, "❌ ارتباط قطع شد یا خطایی در پردازش رخ داد."); 
    } 
} 


/* -------------------------------------------------------------------------- 
| ۴. بارگذاری و مدیریت تاریخچه مکالمات 
----------------------------------------------------------------------------*/ 
async function loadConversation(id) {
    if (!id) return;

    currentConversationId = id;
    isFirstAssistantMessage = true;

    chatBox.innerHTML = `
        <div class="flex justify-center py-6 text-xs text-slate-500">
            در حال دریافت اطلاعات مکالمه...
        </div>
    `;

    try {
        const res = await fetch(`/chat/${id}`, {
            headers: {
                "Accept": "application/json",
                "X-Requested-With": "XMLHttpRequest"
            }
        });

        let errorText = '';

        if (!res.ok) {
            try {
                const errJson = await res.json();
                errorText = errJson.error || JSON.stringify(errJson);
            } catch {
                errorText = await res.text();
            }

            throw new Error(`HTTP ${res.status} → ${errorText}`);
        }

        const convo = await res.json();

        if (!convo || !Array.isArray(convo.messages)) {
            throw new Error('فرمت پیام‌ها نامعتبر است');
        }

        chatBox.innerHTML = "";

        convo.messages.forEach(msg => {
            if (msg.role === "user") {
                const fileUrls = (msg.files || []).map(f => `/chat/file/${f.id}`);
                addUserMessage(msg.content, fileUrls);
            } else {
                const cid = "msg_" + msg.id;
                chatBox.innerHTML += `<div class="mb-4 flex justify-start w-full" id="${cid}"></div>`;
                renderAssistantMessage(cid, msg.content);
            }
        });

        scrollChat(true);

    } catch (e) {
        console.error('🔥 Load conversation FULL error:', e);

        chatBox.innerHTML = `
            <div class="text-center text-red-500 py-6 text-sm">
                ❌ خطا در لود مکالمه<br><br>
                <div class="text-xs text-gray-600 break-words px-4">
                    ${e.message}
                </div>
            </div>
        `;
    }
}

// 👇 اینا باید بیرون تابع باشن (خیلی مهم)

function newConversation() { 
    currentConversationId = null; 
    isFirstAssistantMessage = true; 
    chatBox.innerHTML = `
        <div class="flex justify-center py-6 text-xs text-slate-400">
            یک مکالمه جدید آغاز شد. سؤال خود را بپرسید...
        </div>
    `; 
}

async function deleteConversation(id) { 
    if (!confirm("آیا از حذف این مکالمه اطمینان دارید؟")) return; 
    
    try {
        await fetch(`/chat/${id}`, { 
            method: "DELETE", 
            headers: { 
                "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]').content 
            } 
        }); 
        
        const item = document.querySelector(`.conversation-item[data-id="${id}"]`); 
        if (item) item.remove(); 
        
        if (currentConversationId === id) {
            newConversation();
        }

    } catch (e) {
        alert("خطا در حذف مکالمه");
    }
}
async function renameConversation(id) { 
    const newTitle = prompt("عنوان جدید مکالمه:"); 
    if (!newTitle) return; 
    
    try {
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
    } catch (e) {
        alert("خطا در تغییر نام مکالمه");
    }
} 

/* -------------------------------------------------------------------------- 
| ۵. رویدادها (Event Listeners)
----------------------------------------------------------------------------*/ 
if (questionInput) {
    questionInput.addEventListener("keydown", e => { 
        if (e.key === "Enter" && !e.shiftKey) { 
            e.preventDefault(); 
            sendMessage(); 
        } 
    }); 
    
    // تنظیم ارتفاع خودکار
    questionInput.addEventListener("input", function() {
        this.style.height = "auto";
        this.style.height = (this.scrollHeight) + "px";
    });
}

if (chatBox) {
    chatBox.addEventListener("scroll", () => {
        shouldAutoScroll = chatBox.scrollHeight - chatBox.scrollTop - chatBox.clientHeight < 120;
    });
}

window.addEventListener("DOMContentLoaded", () => { 
    const first = document.querySelector(".conversation-item"); 
    if (first) loadConversation(first.getAttribute("data-id")); 
    else newConversation(); 
    
    // انتخاب اولیه مدل بر اساس دکمه فعال در HTML
    const activeBtn = document.querySelector(".modelBtn.active") || document.querySelector(".modelBtn");
    if (activeBtn) {
        // گرفتن مدل از خصوصیت دیتا (اگر در HTML تنظیم شده باشد، در غیر اینصورت از selectedModel استفاده می‌کند)
        const modelVal = activeBtn.dataset.model || selectedModel;
        selectModel(modelVal, activeBtn);
    }
}); 

function toggleSidebar() {
    const sidebar = document.getElementById("sidebar");
    const backdrop = document.getElementById("sidebarBackdrop");
    if (!sidebar || !backdrop) return;
    
    const open = sidebar.classList.contains("translate-x-0");
    if (open) {
        sidebar.classList.remove("translate-x-0"); sidebar.classList.add("translate-x-full"); backdrop.classList.add("hidden");
    } else {
        sidebar.classList.remove("translate-x-full"); sidebar.classList.add("translate-x-0"); backdrop.classList.remove("hidden");
    }
}
