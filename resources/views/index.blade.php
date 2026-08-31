<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>وکیل هوش مصنوعی | مشاور حقوقی هوشمند</title>
    <meta name="chat-user-id" content="{{ auth()->id() }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script>(function(){if(localStorage.getItem("darkMode")==="true")document.documentElement.classList.add("dark");})()</script>
    
    <link href="{{ asset('css/fonts.css') }}" rel="stylesheet">
    <script src="{{ asset('js/tailwind.min.js') }}"></script>
    <script src="{{ asset('js/marked.min.js') }}"></script>
    
    <style>
        .chat-status { border:1px solid #c7d2fe; background:#eef2ff; color:#3730a3; border-radius:12px; padding:10px 12px; margin-bottom:10px; font-size:12px; line-height:1.9; overflow-wrap:anywhere; }
        .chat-status[data-kind="error"] { background:#fff7ed; color:#9a3412; border-color:#fed7aa; }
        .chat-status-action { display:inline-block; font-weight:700; text-decoration:underline; margin-inline-start:12px; cursor:pointer; }
        .dark .chat-status { background:#1e293b; color:#c7d2fe; border-color:#475569; }
        .dark .chat-status[data-kind="error"] { color:#fed7aa; }
        [hidden] { display:none !important; }
        * { font-family: 'Vazirmatn', sans-serif; }
        
        ::-webkit-scrollbar { width: 6px; height: 6px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; border-radius: 10px; }
        ::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 10px; }
        ::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
        
        @keyframes bounce { 0%,60%,100% { transform: translateY(0); } 30% { transform: translateY(-5px); } }
        .animate-bounce { animation: bounce 1.4s infinite; }
        
        .prose { max-width: none; font-size: 0.875rem; line-height: 1.6; color: #334155; }
        .prose p { margin-bottom: 0.75rem; }
        .prose pre { background: #0f172a; color: #e2e8f0; padding: 0.75rem; border-radius: 0.75rem; overflow-x: auto; }
        .prose code { background: #f1f5f9; padding: 0.125rem 0.375rem; border-radius: 0.375rem; font-size: 0.8rem; }
        
        .transition-smooth { transition: all 0.2s ease; }
        .no-scrollbar::-webkit-scrollbar { display: none; }
        .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

        .chat-shell { min-height: 100dvh; }
        .model-menu-panel {
            position: absolute;
            right: 0;
            bottom: calc(100% + 10px);
            width: min(420px, calc(100vw - 32px));
            max-height: 320px;
            overflow-y: auto;
            background: rgba(255,255,255,.98);
            border: 1px solid #e2e8f0;
            box-shadow: 0 20px 55px rgba(15,23,42,.18);
            border-radius: 22px;
            padding: 8px;
            z-index: 60;
        }
        .model-menu-panel.hidden { display: none; }
        .model-menu-backdrop { display: none; }
        .model-btn { width: 100%; }
        .composer-wrap {
            padding-bottom: max(12px, env(safe-area-inset-bottom));
        }
        @media (max-width: 640px) {
            body { background: #fff; }
            header .max-w-3xl, main .max-w-3xl, footer .max-w-3xl { max-width: 100%; }
            header { position: sticky; top: 0; }
            #chatMain { background: #fff; }
            #chatBox { padding-bottom: 6px; }
            footer { position: sticky; bottom: 0; }
            .composer-wrap { padding: 10px 12px max(10px, env(safe-area-inset-bottom)); }
            .model-menu-backdrop {
                display: block;
                position: fixed;
                inset: 0;
                background: rgba(15,23,42,.28);
                backdrop-filter: blur(2px);
                z-index: 50;
            }
            .model-menu-backdrop.hidden { display: none; }
            .model-menu-panel {
                position: fixed;
                right: 10px;
                left: 10px;
                bottom: max(88px, calc(env(safe-area-inset-bottom) + 88px));
                width: auto;
                max-height: min(58vh, 440px);
                border-radius: 24px;
                padding: 10px;
            }
            .chat-message-user, .chat-message-ai { max-width: 92% !important; }
        }

        /* ===== Dark Mode ===== */
        html.dark body { background: #0f172a !important; color: #e2e8f0; }
        html.dark header { background: rgba(15,23,42,0.97) !important; border-color: #1e293b !important; }
        html.dark main, html.dark #chatMain { background: #0f172a !important; }
        html.dark footer { background: rgba(15,23,42,0.97) !important; border-color: #1e293b !important; }
        html.dark #sidebar { background: #1e293b !important; border-color: #334155 !important; }
        html.dark #sidebar .border-b { border-color: #334155 !important; }
        html.dark #sidebar h2 { color: #f1f5f9 !important; }
        html.dark .conversation-item { color: #cbd5e1 !important; }
        html.dark .conversation-item:hover { background: #334155 !important; }
        html.dark .bg-white { background: #0f172a !important; }
        html.dark .bg-slate-50 { background: #1e293b !important; }
        html.dark .bg-slate-100 { background: #1e293b !important; }
        html.dark .border-slate-100 { border-color: #1e293b !important; }
        html.dark .border-slate-200 { border-color: #334155 !important; }
        html.dark .text-slate-800 { color: #f1f5f9 !important; }
        html.dark .text-slate-700 { color: #cbd5e1 !important; }
        html.dark .text-slate-600 { color: #94a3b8 !important; }
        html.dark .text-slate-500 { color: #64748b !important; }
        html.dark .text-slate-400 { color: #475569 !important; }
        html.dark .model-menu-panel { background: #1e293b !important; border-color: #334155 !important; }
        html.dark .rounded-2xl { border-color: #334155; }
        html.dark #question { color: #e2e8f0 !important; }
        html.dark #question::placeholder { color: #475569 !important; }
        html.dark .prose { color: #cbd5e1 !important; }
        html.dark .prose code { background: #1e293b !important; color: #93c5fd !important; }
        html.dark .prose pre { background: #020617 !important; }
        html.dark ::-webkit-scrollbar-track { background: #1e293b; }
        html.dark ::-webkit-scrollbar-thumb { background: #475569; }
        html.dark .chat-message-user { background: #1e3a5f !important; color: #e2e8f0 !important; }
        html.dark .chat-message-ai { background: #1e293b !important; border-color: #334155 !important; }
        html.dark .chat-message-ai .text-slate-400 { color: #64748b !important; }
        html.dark .chat-message-ai .border-t { border-color: #334155 !important; }
        html.dark .bg-indigo-50 { background: #1e3a5f !important; }
        html.dark .border-indigo-100 { border-color: #2d4a7a !important; }
        html.dark #question { background: transparent !important; color: #e2e8f0 !important; }
        html.dark div.bg-white.rounded-2xl { background: #1a2744 !important; border-color: #334155 !important; }
    </style>
</head>
<body class="bg-white h-screen overflow-hidden flex flex-col chat-shell">

    @php
        $subscription = auth()->user()?->activeSubscription;
        $remaining    = $subscription ? $subscription->remainingQuestions() : null;
        $daysLeft     = $subscription ? max(0, (int) now()->diffInDays($subscription->expires_at, false)) : null;
        $expiringSoon = $daysLeft !== null && $daysLeft <= 3;
    @endphp

    <header class="bg-white/95 backdrop-blur-sm border-b border-slate-100 sticky top-0 z-20">
        <div class="max-w-3xl mx-auto px-4 py-3 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <button onclick="toggleSidebar()" class="p-2 rounded-xl hover:bg-slate-100 transition-smooth">
                    <svg class="w-5 h-5 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
                <a href="/" class="flex items-center gap-2">
                    <img src="/logo.webp" class="w-8 h-8 rounded-xl shadow-sm" alt="logo">
                    <span class="font-semibold text-slate-800 hidden sm:inline">وکیل هوش مصنوعی</span>
                </a>
            </div>
            <div class="flex items-center gap-3">
                <div class="flex items-center gap-2">
                    {{-- subscription info --}}
                    <div class="bg-slate-50 border border-slate-200 px-3 py-1.5 rounded-full text-xs flex items-center gap-2">
                        <span class="text-slate-500 hidden sm:inline">سؤال:</span>
                        <span class="font-bold text-indigo-600">
                            @if(!$subscription) — @elseif($remaining == 99999) ∞ @else {{ $remaining }} @endif
                        </span>
                        @if($daysLeft !== null)
                        <span class="text-slate-300 hidden sm:inline">|</span>
                        <span class="hidden sm:inline {{ $expiringSoon ? 'text-rose-500 font-bold' : 'text-slate-500' }}">
                            {{ $daysLeft }} روز
                            @if($expiringSoon) ⚠️ @endif
                        </span>
                        @endif
                    </div>
                    <a href="https://static.hakimisadr.com/plans"
                       class="flex items-center gap-1 bg-indigo-500 hover:bg-indigo-600 text-white text-xs font-bold px-3 py-1.5 rounded-full transition-smooth whitespace-nowrap">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>
                        <span>ارتقا پلن</span>
                    </a>
                </div>
                <button id="darkToggle" onclick="toggleDark()" class="p-2 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-800 transition-smooth" title="حالت شب/روز">
                    <svg id="darkIconMoon" class="w-5 h-5 text-slate-600 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
                    <svg id="darkIconSun" class="w-5 h-5 text-amber-500 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707M17.657 17.657l-.707-.707M6.343 6.343l-.707-.707M12 8a4 4 0 100 8 4 4 0 000-8z"/></svg>
                </button>
                <form method="POST" action="/logout">@csrf
                    <button class="text-sm text-rose-500 hover:text-rose-600 transition">خروج</button>
                </form>
            </div>
        </div>
    </header>

    <div id="sidebarBackdrop" class="fixed inset-0 bg-black/30 backdrop-blur-sm z-30 hidden" onclick="toggleSidebar()"></div>
    <aside id="sidebar" class="fixed top-0 right-0 h-full z-40 w-80 bg-white border-r border-slate-200 shadow-xl translate-x-full transition-transform duration-300 flex flex-col">
        <div class="p-4 border-b flex justify-between items-center">
            <h2 class="font-bold text-slate-800">مکالمات اخیر</h2>
            <button onclick="newConversation()" class="bg-indigo-500 hover:bg-indigo-600 text-white text-sm px-3 py-1.5 rounded-xl transition-smooth">مکالمه جدید</button>
        </div>
        <div id="conversationList" class="flex-1 overflow-y-auto p-2 space-y-1">
            @foreach($conversations as $conv)
            <div class="conversation-item group cursor-pointer p-3 rounded-xl hover:bg-slate-50 transition-smooth flex justify-between items-center" data-id="{{ $conv->id }}" onclick="loadConversation({{ $conv->id }})">
                <div class="flex-1 min-w-0">
                    <div class="text-sm font-medium text-slate-700 truncate">{{ $conv->title }}</div>
                    <div class="text-xs text-slate-400 mt-0.5">{{ optional($conv->latestMessage)->created_at?->diffForHumans() ?? 'جدید' }}</div>
                </div>
                <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition">
                    <button onclick="event.stopPropagation(); renameConversation({{ $conv->id }})" class="p-1.5 rounded-lg hover:bg-slate-200 text-slate-500">✏️</button>
                    <button onclick="event.stopPropagation(); deleteConversation({{ $conv->id }})" class="p-1.5 rounded-lg hover:bg-rose-100 text-rose-500">🗑️</button>
                </div>
            </div>
            @endforeach
        </div>
    </aside>

    <main class="flex-1 overflow-y-auto" id="chatMain">
        <div class="max-w-3xl mx-auto px-4 py-6">
            <div id="chatBox" class="space-y-5">
                <div class="text-center text-slate-400 py-8">در حال بارگذاری...</div>
            </div>
        </div>
    </main>

    <footer class="border-t bg-white/95 backdrop-blur-sm">
        <div class="max-w-3xl mx-auto px-4 py-3 composer-wrap">
            <div id="chatStatus" class="chat-status" role="status" aria-live="polite" hidden></div>
            <button id="stopChatBtn" type="button" class="text-xs text-slate-600 border rounded-xl px-3 py-1 mb-2" hidden>توقف دریافت پاسخ</button>
            <div class="flex items-center justify-between gap-3 mb-3 relative">
                <div class="relative flex-1 min-w-0">
                    <button id="selectedModelButton" type="button" class="w-full sm:w-auto max-w-full flex items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-3 py-2 text-sm text-slate-700 shadow-sm hover:bg-white transition-smooth">
                        <span class="flex items-center gap-2 min-w-0">
                            <span id="selectedModelIcon" class="w-7 h-7 rounded-xl bg-indigo-100 text-indigo-600 flex items-center justify-center text-sm">🧠</span>
                            <span class="min-w-0 text-right">
                                <span class="block text-[11px] text-slate-400 leading-4">مدل پاسخ‌دهنده</span>
                                <span id="selectedModelName" class="block font-bold text-slate-800 truncate">انتخاب مدل</span>
                            </span>
                        </span>
                        <svg class="w-4 h-4 text-slate-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <div id="modelMenuBackdrop" class="model-menu-backdrop hidden"></div>
                    <div id="modelMenu" class="model-menu-panel hidden">
                        <div class="px-2 py-2 border-b border-slate-100 mb-1">
                            <div class="text-sm font-bold text-slate-800">انتخاب مدل</div>
                            <div class="text-xs text-slate-400 mt-0.5">مدل را بدون خروج از صفحه چت تغییر دهید</div>
                        </div>
                        <div class="space-y-1">
                    @foreach($models as $mdl)
                    <button type="button" 
                        class="model-btn px-3 py-2.5 rounded-2xl text-sm font-medium transition-smooth flex items-center gap-2 text-right
                        {{ $loop->first ? 'bg-indigo-500 text-white shadow-md' : 'bg-slate-50 text-slate-700 hover:bg-slate-100' }}"
                        data-model="{{ $mdl->key }}"
                        data-supports-files="{{ $mdl->supports_files ? 'true' : 'false' }}"
                        data-model-name="{{ $mdl->name }}"
                        data-model-title="{{ $mdl->title ?? '' }}">
                        <span class="model-btn-icon w-8 h-8 rounded-xl bg-white/20 flex items-center justify-center shrink-0">
                            @if($mdl->key == 'qwen') 🤖
                            @elseif($mdl->key == 'gemini') 🚀
                            @elseif($mdl->key == 'gemini_flash') ⚡
                            @elseif($mdl->key == 'claude') 🎨
                            @else 🧠
                            @endif
                        </span>
                        <span class="min-w-0">
                            <span class="block font-bold truncate">{{ $mdl->name }}</span>
                            <span class="block text-[11px] opacity-70 truncate">{{ $mdl->title ?? $mdl->key }}</span>
                        </span>
                    </button>
                    @endforeach
                        </div>
                    </div>
                </div>
                <div id="fileUploadContainer" class="hidden">
                    <input type="file" id="fileInput" class="hidden" multiple accept="image/*,application/pdf,.pdf,.txt,.doc,.docx">
                    <button id="fileInputBtn" class="p-2 rounded-xl bg-slate-100 text-slate-600 hover:bg-slate-200 transition-smooth" title="ارسال تصویر، PDF یا فایل متنی">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" />
                        </svg>
                    </button>
                </div>
            </div>

            <div id="filePreviewContainer" class="hidden mb-2 p-2 bg-indigo-50/50 rounded-xl flex justify-between items-center gap-3 text-sm border border-indigo-100">
                <div class="min-w-0">
                    <span id="fileNameDisplay" class="block text-indigo-700 truncate"></span>
                    <span class="block text-[10px] text-indigo-500 mt-0.5">PDF تا ۲۰۰ صفحه؛ فایل اسکن‌شده مجموعاً تا ۱۲ صفحه؛ هر فایل حداکثر ۸ مگابایت</span>
                </div>
                <button id="removeFileBtn" class="text-rose-500 text-xs font-bold px-2">حذف</button>
            </div>

            <div class="flex items-end gap-2 bg-white rounded-2xl border border-slate-200 shadow-sm focus-within:ring-2 focus-within:ring-indigo-300 transition-smooth">
                <textarea id="question" rows="1" class="flex-1 p-3 resize-none bg-transparent text-slate-700 placeholder-slate-400 focus:outline-none text-sm" placeholder="سؤال حقوقی خود را بپرسید..."></textarea>
                <button id="sendBtn" type="button" aria-label="ارسال پیام" class="m-2 p-2 bg-indigo-500 text-white rounded-xl hover:bg-indigo-600 transition-smooth">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                    </svg>
                </button>
            </div>
            <p class="text-[11px] text-slate-400 text-center mt-2">پاسخ‌ها توسط هوش مصنوعی تولید می‌شوند. لطفاً با احتیاط استفاده کنید.</p>
        </div>
    </footer>

    <script>
        function toggleDark() {
            const isDark = document.documentElement.classList.toggle('dark');
            localStorage.setItem('darkMode', isDark ? 'true' : 'false');
            updateDarkIcons();
        }
        function updateDarkIcons() {
            const isDark = document.documentElement.classList.contains('dark');
            document.getElementById('darkIconMoon').classList.toggle('hidden', isDark);
            document.getElementById('darkIconSun').classList.toggle('hidden', !isDark);
        }
        document.addEventListener('DOMContentLoaded', updateDarkIcons);

        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const backdrop = document.getElementById('sidebarBackdrop');
            if (sidebar.classList.contains('translate-x-0')) {
                sidebar.classList.remove('translate-x-0');
                sidebar.classList.add('translate-x-full');
                backdrop.classList.add('hidden');
            } else {
                sidebar.classList.remove('translate-x-full');
                sidebar.classList.add('translate-x-0');
                backdrop.classList.remove('hidden');
            }
        }
    </script>
    <script src="{{ asset('/js/chat.js') }}?v={{ time() }}"></script>
</body>
</html>
