<!DOCTYPE html>
<html lang="fa" dir="rtl" class="h-full" id="htmlRoot">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'پنل مدیریت') | پنل مدیریت</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="{{ asset('css/fonts.css') }}" rel="stylesheet">
    <script src="{{ asset('js/tailwind.min.js') }}"></script>
    <script>
        // Apply dark mode before render to prevent flash
        (function() {
            if (localStorage.getItem('darkMode') === 'true') {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>
    <style>
        * { font-family: 'Vazirmatn', sans-serif !important; }
        ::-webkit-scrollbar { width: 5px; }
        ::-webkit-scrollbar-track { background: #f1f5f9; }
        ::-webkit-scrollbar-thumb { background: #94a3b8; border-radius: 4px; }
        .sidebar-link { display: flex; align-items: center; gap: 0.75rem; padding: 0.625rem 1rem; border-radius: 0.75rem; color: #cbd5e1; font-size: 0.875rem; transition: all 0.2s; }
        .sidebar-link:hover { background: rgba(255,255,255,0.1); color: #fff; }
        .sidebar-link.active { background: rgba(255,255,255,0.15); color: #fff; font-weight: 600; }
        .stat-card { background: white; border-radius: 1rem; padding: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,.06); border: 1px solid #f1f5f9; transition: box-shadow 0.2s; }
        .stat-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,.08); }
        .badge-manager { background:#f3e8ff; color:#7e22ce; font-size:.7rem; padding:.15rem .6rem; border-radius:99px; font-weight:600; }
        .badge-modir2 { background:#dbeafe; color:#1d4ed8; font-size:.7rem; padding:.15rem .6rem; border-radius:99px; font-weight:600; }
        .badge-user { background:#f1f5f9; color:#475569; font-size:.7rem; padding:.15rem .6rem; border-radius:99px; }
        .badge-active { background:#d1fae5; color:#065f46; font-size:.7rem; padding:.15rem .6rem; border-radius:99px; font-weight:600; }
        .badge-expired { background:#fee2e2; color:#991b1b; font-size:.7rem; padding:.15rem .6rem; border-radius:99px; }
        .btn-primary { background:#4f46e5; color:#fff; padding:.5rem 1rem; border-radius:.75rem; font-size:.875rem; font-weight:500; transition:background .2s; cursor:pointer; border:none; }
        .btn-primary:hover { background:#4338ca; }
        .btn-danger { background:#ef4444; color:#fff; padding:.375rem .75rem; border-radius:.5rem; font-size:.875rem; transition:background .2s; cursor:pointer; border:none; }
        .btn-danger:hover { background:#dc2626; }
        .input-field { width:100%; border:1px solid #e2e8f0; border-radius:.75rem; padding:.5rem .75rem; font-size:.875rem; outline:none; background:white; transition:box-shadow .2s; }
        .input-field:focus { box-shadow:0 0 0 3px rgba(99,102,241,.2); border-color:#a5b4fc; }
        /* Dark mode */
        .dark body { background:#0f172a; color:#e2e8f0; }
        .dark .stat-card { background:#1e293b; border-color:#334155; }
        .dark .input-field { background:#1e293b; border-color:#475569; color:#e2e8f0; }
        .dark .input-field:focus { border-color:#818cf8; }
        .dark .bg-white { background:#1e293b !important; }
        .dark .bg-slate-50 { background:#0f172a !important; }
        .dark .border-slate-100 { border-color:#334155 !important; }
        .dark .border-slate-200 { border-color:#475569 !important; }
        .dark .text-slate-800 { color:#f1f5f9 !important; }
        .dark .text-slate-700 { color:#e2e8f0 !important; }
        .dark .text-slate-600 { color:#cbd5e1 !important; }
        .dark .text-slate-500 { color:#94a3b8 !important; }
        .dark .text-slate-400 { color:#64748b !important; }
        .dark .hover\:bg-slate-50:hover { background:#1e293b !important; }
        .dark .hover\:bg-indigo-100:hover { background:#312e81 !important; }
        .dark .bg-emerald-50 { background:#022c22 !important; }
        .dark .text-emerald-800 { color:#6ee7b7 !important; }
        .dark .bg-red-50 { background:#2d0000 !important; }
        .dark .text-red-800 { color:#fca5a5 !important; }
        .dark .divide-slate-50 > * { border-color:#334155 !important; }
        /* Sidebar mobile */
        #sidebar { transition: transform .25s; }
        @media (max-width: 767px) {
            #sidebar { position:fixed; z-index:50; height:100%; transform:translateX(100%); }
            #sidebar.open { transform:translateX(0); }
            #sidebarOverlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.4); z-index:40; }
            #sidebarOverlay.open { display:block; }
        }
        /* Table mobile scroll */
        .table-wrap { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    </style>
</head>
<body class="bg-slate-50 flex h-screen overflow-hidden">

    {{-- Mobile overlay --}}
    <div id="sidebarOverlay" onclick="toggleSidebar()"></div>

    {{-- Sidebar --}}
    <aside id="sidebar" class="w-64 bg-gradient-to-b from-slate-800 to-slate-900 flex flex-col flex-shrink-0 overflow-y-auto">
        <div class="p-5 border-b border-white/10">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-indigo-500 rounded-xl flex items-center justify-center text-white font-bold text-lg flex-shrink-0">P</div>
                <div class="min-w-0">
                    <div class="text-white font-bold text-sm">پنل مدیریت</div>
                    <div class="text-slate-400 text-xs truncate">{{ auth()->user()->name }}</div>
                </div>
                {{-- Mobile close button --}}
                <button onclick="toggleSidebar()" class="md:hidden mr-auto text-slate-400 hover:text-white p-1">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>

        <nav class="flex-1 p-3 space-y-1">
            <a href="{{ route('admin.dashboard') }}" class="sidebar-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                داشبورد
            </a>

            @if(auth()->user()->role === 'manager')
            <a href="{{ route('admin.stats') }}" class="sidebar-link {{ request()->routeIs('admin.stats') ? 'active' : '' }}">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                آمار و گزارش
            </a>

            <div class="pt-2 pb-1 px-4 text-xs text-slate-500 font-semibold uppercase tracking-wider">هوش مصنوعی</div>
            <a href="{{ route('admin.ai-models.index') }}" class="sidebar-link {{ request()->routeIs('admin.ai-models.*') ? 'active' : '' }}">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17H3a2 2 0 01-2-2V5a2 2 0 012-2h16a2 2 0 012 2v10a2 2 0 01-2 2h-2"/></svg>
                مدل‌های AI
            </a>

            <a href="{{ route('admin.ai-proxies.index') }}" class="sidebar-link {{ request()->routeIs('admin.ai-proxies.*') ? 'active' : '' }}">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 9l3 3-3 3m5 0h3M5 5h14a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V7a2 2 0 012-2z"/></svg>
                مدیریت پراکسی‌ها
            </a>

            <a href="{{ route('admin.ai-test') }}" class="sidebar-link {{ request()->routeIs('admin.ai-test*') ? 'active' : '' }}">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                تست اتصال AI
            </a>

            <a href="{{ route('admin.ai-usage') }}" class="sidebar-link {{ request()->routeIs('admin.ai-usage') ? 'active' : '' }}">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                مصرف و هزینه AI
            </a>

            <a href="{{ route('admin.ai-errors') }}" class="sidebar-link {{ request()->routeIs('admin.ai-errors') ? 'active' : '' }}">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                خطاهای هوش مصنوعی
            </a>

            <a href="{{ route('admin.chatbot-settings') }}" class="sidebar-link {{ request()->routeIs('admin.chatbot-settings*') ? 'active' : '' }}">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                تنظیمات چت‌بات
            </a>

            <a href="{{ route('admin.system') }}" class="sidebar-link {{ request()->routeIs('admin.system') ? 'active' : '' }}">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6V4m0 2a2 2 0 100 4m0-4a2 2 0 110 4m-6 8a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4m6 6v10m6-2a2 2 0 100-4m0 4a2 2 0 110-4m0 4v2m0-6V4"/></svg>
                تنظیمات سیستم
            </a>
            @endif

            <div class="pt-2 pb-1 px-4 text-xs text-slate-500 font-semibold uppercase tracking-wider">سایر</div>
            <a href="{{ route('home') }}" class="sidebar-link">
                <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                صفحه چت
            </a>
        </nav>

        <div class="p-3 border-t border-white/10">
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="sidebar-link w-full text-red-400 hover:text-red-300 hover:bg-red-500/10">
                    <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                    خروج
                </button>
            </form>
        </div>
    </aside>

    {{-- Main Content --}}
    <main class="flex-1 overflow-y-auto min-w-0">
        {{-- Top bar (mobile) --}}
        <div class="md:hidden flex items-center justify-between bg-white dark:bg-slate-800 border-b border-slate-100 px-4 py-3 sticky top-0 z-30">
            <button onclick="toggleSidebar()" class="p-1.5 rounded-lg text-slate-600 hover:bg-slate-100">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            <span class="font-bold text-slate-700 text-sm">@yield('title', 'پنل مدیریت')</span>
            <button onclick="toggleDark()" class="p-1.5 rounded-lg text-slate-600 hover:bg-slate-100" title="حالت شب">
                <svg id="sunIcon2" class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                <svg id="moonIcon2" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
            </button>
        </div>

        {{-- Desktop top-right controls --}}
        <div class="hidden md:flex items-center gap-2 absolute top-4 left-4 z-20">
            <button onclick="toggleDark()" class="p-2 rounded-xl bg-white border border-slate-200 text-slate-500 hover:bg-slate-50 shadow-sm" title="حالت شب/روز">
                <svg id="sunIcon" class="w-4 h-4 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"/></svg>
                <svg id="moonIcon" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg>
            </button>
        </div>

        <div class="p-4 md:p-6">
            @if(session('success'))
                <div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-xl text-sm flex items-center gap-2">
                    <svg class="w-5 h-5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    {{ session('success') }}
                </div>
            @endif
            @if(session('error'))
                <div class="mb-4 bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl text-sm flex items-center gap-2">
                    <svg class="w-5 h-5 text-red-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    {{ session('error') }}
                </div>
            @endif
            @yield('content')
        </div>
    </main>

    @stack('modals')
    @stack('scripts')

    <script>
    function toggleSidebar() {
        document.getElementById('sidebar').classList.toggle('open');
        document.getElementById('sidebarOverlay').classList.toggle('open');
    }

    function toggleDark() {
        const html = document.documentElement;
        const isDark = html.classList.toggle('dark');
        localStorage.setItem('darkMode', isDark);
        updateDarkIcons(isDark);
    }

    function updateDarkIcons(isDark) {
        ['sunIcon','sunIcon2'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.classList.toggle('hidden', !isDark);
        });
        ['moonIcon','moonIcon2'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.classList.toggle('hidden', isDark);
        });
    }

    // Init icons on load
    updateDarkIcons(document.documentElement.classList.contains('dark'));
    </script>
</body>
</html>
