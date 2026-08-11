<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>وکیل هوش مصنوعی</title>

    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#8b5cf6">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="/css/chat.css">
</head>

<body class="min-h-screen h-screen" style="font-family: 'Vazirmatn', sans-serif;">

<!-- Backdrop موبایل -->
<div id="sidebarBackdrop"
     class="fixed inset-0 bg-black/40 backdrop-blur-sm z-30 hidden md:hidden"
     onclick="toggleSidebar()"></div>

@php
    $subscription = auth()->user()->activeSubscription();
    $remaining = $subscription ? $subscription->remainingQuestions() : null;
@endphp

<!-- ========== HEADER همیشه بالای صفحه ========== -->
<header class="p-3 md:p-4 bg-white/80 backdrop-blur-xl border-b border-slate-200/80 sticky top-0 z-50 shadow-sm">
    <div class="max-w-4xl mx-auto flex items-center justify-between gap-3">

        <div class="flex items-center gap-3">

            <!-- دکمه همبرگری با Z بالا -->
            <button onclick="toggleSidebar()"
                    class="md:hidden p-2 rounded-xl bg-indigo-100 text-indigo-600 border border-indigo-200 z-50">
                ☰
            </button>

            <a href="/" class="flex items-center gap-3">
                <div class="relative">
                    <img src="/logo.webp" alt="وکیل هوش مصنوعی"
                         class="w-10 h-10 md:w-11 md:h-11 rounded-2xl shadow-md select-none object-cover">
                    <span class="absolute -bottom-1 -left-1 w-4 h-4 rounded-full bg-emerald-400 border-2 border-white shadow"></span>
                </div>
                <div class="hidden sm:flex flex-col">
                    <span class="text-sm md:text-base font-semibold text-slate-800">وکیل هوش مصنوعی</span>
                    <span class="text-[11px] md:text-xs text-slate-500">همراه حقوقی ۲۴ ساعته شما</span>
                </div>
            </a>
        </div>

        <div class="flex items-center gap-3">
            <div class="px-2.5 py-1.5 rounded-xl bg-indigo-50 border border-indigo-100 text-xs md:text-sm flex items-center gap-1.5">
                <span class="text-slate-600">سؤالات</span>
                <span class="w-px h-4 bg-indigo-200"></span>
                <span class="font-bold
                    @if(!$subscription) text-gray-400
                    @elseif($remaining == 99999) text-yellow-500
                    @elseif($remaining <= 3) text-red-500
                    @else text-emerald-500 @endif">
                    @if(!$subscription) —
                    @elseif($remaining == 99999) ∞
                    @else {{ $remaining }}
                    @endif
                </span>
            </div>
            <form method="POST" action="/logout">@csrf
                <button class="text-xs md:text-sm text-rose-500 hover:text-rose-600 font-medium">خروج</button>
            </form>
        </div>
    </div>
</header>


<!-- ===========================================================
      لایه‌بندی اصلی – سایدبار + چت 
=========================================================== -->
<div class="flex h-[calc(100vh-72px)]"> <!-- ارتفاع واقعی زیر هدر -->


    <!-- ========== SIDEBAR بعد از Header قرار گرفت (اصلاح اصلی) ========== -->
    <aside id="sidebar"
           class="fixed md:static top-0 right-0 h-full z-40
                  translate-x-full md:translate-x-0
                  w-72 flex flex-col bg-white/95 border-l border-slate-200 shadow-xl
                  transition-transform duration-300">

        <div class="p-4 border-b border-slate-200 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <div class="w-9 h-9 rounded-2xl bg-indigo-50 flex items-center justify-center text-indigo-500">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none"
                         viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.7">
                        <path stroke-linecap="round" stroke-linejoin="round"
                              d="M8 9l4-4 4 4M12 5v14" />
                    </svg>
                </div>
                <div class="flex flex-col">
                    <span class="text-sm font-semibold text-slate-800">مکالمات</span>
                    <span class="text-[11px] text-slate-500">لیست گفتگوهای قبلی شما</span>
                </div>
            </div>
            <button onclick="newConversation()"
                    class="px-3 py-1.5 rounded-xl bg-indigo-600 text-white text-xs font-medium hover:bg-indigo-700">
                مکالمه جدید
            </button>
        </div>

        <div id="conversationList"
             class="flex-1 overflow-y-auto p-2 space-y-1">
            @foreach($conversations as $conv)
                <div class="conversation-item group cursor-pointer p-3 rounded-2xl bg-slate-50 hover:bg-indigo-50 border border-slate-100 flex items-center justify-between transition"
                     data-id="{{ $conv->id }}"
                     onclick="loadConversation({{ $conv->id }})">
                    <div class="flex-1 min-w-0">
                        <div class="text-[13px] font-semibold text-slate-800 truncate">
                            {{ $conv->title }}
                        </div>
                        <div class="text-[11px] text-slate-500">
                            {{ optional($conv->latestMessage)->created_at?->diffForHumans() ?? 'بدون پیام' }}
                        </div>
                    </div>
                    <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition">
                        <button class="p-1 rounded-lg hover:bg-slate-200"
                                onclick="event.stopPropagation(); renameConversation({{ $conv->id }})">
                            ✏️
                        </button>
                        <button class="p-1 rounded-lg hover:bg-rose-100"
                                onclick="event.stopPropagation(); deleteConversation({{ $conv->id }})">
                            ❌
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    </aside>


    <!-- ===========================================================
        ناحیه چت
    ============================================================ -->
    <div class="flex-1 flex flex-col min-w-0">

        <main class="flex-1 w-full overflow-hidden">
            <div class="max-w-4xl mx-auto px-3 md:px-4 pt-3 pb-24 md:pb-28 h-full">
                <div class="bg-white/90 border border-slate-200 rounded-3xl shadow-lg overflow-hidden h-full flex flex-col">

                    <div class="px-4 md:px-6 py-3.5 border-b border-slate-100 flex items-center justify-between gap-2 bg-gradient-to-l from-indigo-50 to-violet-50">
                        <div class="flex items-center gap-2">
                            <div class="w-8 h-8 rounded-2xl bg-white shadow flex items-center justify-center text-indigo-500">
                                💬
                            </div>
                            <div class="flex flex-col">
                                <span class="text-sm md:text-base font-semibold text-slate-800">شروع گفت‌وگو با وکیل هوش مصنوعی</span>
                                <span class="text-[11px] md:text-xs text-slate-500">سؤال خود را ارسال کنید.</span>
                            </div>
                        </div>
                    </div>

                    <div id="chatBox"
                         class="flex-1 overflow-y-auto p-3 md:p-4 space-y-4 bg-gradient-to-b from-white via-white to-indigo-50/40">
                    </div>

                </div>
            </div>
        </main>

        <!-- Footer (Input Area) -->
        <footer class="fixed bottom-0 left-0 right-0 md:right-auto md:left-auto md:static z-30 bg-white/80 backdrop-blur-2xl border-t border-slate-200/70 shadow-2xl">
            <div class="max-w-4xl mx-auto px-3 md:px-5 py-3">

                <div class="bg-white/90 border border-slate-200/80 rounded-3xl shadow-xl overflow-hidden">

                    <div class="flex gap-2 px-3 pt-3 overflow-x-auto no-scrollbar text-xs">
    <button class="modelBtn active px-4 py-2 rounded-xl" 
            onclick="selectModel('gpt', this)">GPT</button>
    <button class="modelBtn px-4 py-2 rounded-xl" 
            onclick="selectModel('claude', this)">Claude</button>
    <button class="modelBtn px-4 py-2 rounded-xl" 
            onclick="selectModel('grok', this)">Grok</button>
    <button class="modelBtn px-4 py-2 rounded-xl" 
            onclick="selectModel('deepseek', this)">DeepSeek</button>
</div>

                    <div class="flex items-end gap-3 p-3 md:p-4">
                        <div class="flex items-end gap-2 flex-1">

                            <input type="file" id="fileInput" name="files[]" multiple class="hidden" accept="image/*">

                            <button type="button"
                                    onclick="document.getElementById('fileInput').click()"
                                    class="w-11 h-11 bg-slate-50 border border-slate-300 rounded-2xl flex items-center justify-center text-slate-500">
                                📎
                            </button>

                            <div class="flex-1 relative">
                                <textarea id="question" rows="1"
                                          class="w-full px-4 py-3.5 rounded-2xl bg-slate-50/70 border border-slate-300 text-sm md:text-base resize-none focus:ring-2 focus:ring-indigo-500 min-h-14 max-h-40"
                                          placeholder="سؤال حقوقی خود را بنویسید..."></textarea>
                            </div>
                        </div>

                        <button id="sendBtn" onclick="sendMessage()"
                                class="w-11 h-11 bg-indigo-500 text-white rounded-2xl font-bold flex items-center justify-center shadow-lg active:scale-95">
                            ➤
                        </button>

                    </div>

                </div>

            </div>
        </footer>

    </div>
</div>

<script src="/js/chat.js"></script>
</body>
</html>
