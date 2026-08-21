@extends('layouts.admin')
@section('title', 'داشبورد مدیریت')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-slate-800">داشبورد مدیریت</h1>
        <p class="text-slate-500 text-sm mt-1">مدیریت کاربران و اشتراک‌ها</p>
    </div>
    <button onclick="document.getElementById('createUserModal').classList.remove('hidden')"
        class="btn-primary flex items-center gap-2">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        کاربر جدید
    </button>
</div>

{{-- Stats Cards --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="stat-card">
        <div class="flex items-center justify-between mb-3">
            <span class="text-slate-500 text-sm">کل کاربران</span>
            <div class="w-9 h-9 bg-indigo-100 rounded-xl flex items-center justify-center">
                <svg class="w-5 h-5 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
            </div>
        </div>
        <div class="text-3xl font-bold text-slate-800">{{ number_format($stats['totalUsers']) }}</div>
        <div class="text-xs text-emerald-600 mt-1">+{{ $stats['newUsersToday'] }} امروز</div>
    </div>
    <div class="stat-card">
        <div class="flex items-center justify-between mb-3">
            <span class="text-slate-500 text-sm">اشتراک فعال</span>
            <div class="w-9 h-9 bg-emerald-100 rounded-xl flex items-center justify-center">
                <svg class="w-5 h-5 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            </div>
        </div>
        <div class="text-3xl font-bold text-slate-800">{{ number_format($stats['activeSubscriptions']) }}</div>
        <div class="text-xs text-slate-400 mt-1">کاربر دارای پلن</div>
    </div>
    <div class="stat-card">
        <div class="flex items-center justify-between mb-3">
            <span class="text-slate-500 text-sm">مکالمات</span>
            <div class="w-9 h-9 bg-violet-100 rounded-xl flex items-center justify-center">
                <svg class="w-5 h-5 text-violet-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
            </div>
        </div>
        <div class="text-3xl font-bold text-slate-800">{{ number_format($stats['totalConversations']) }}</div>
        <div class="text-xs text-slate-400 mt-1">{{ number_format($stats['totalMessages']) }} پیام</div>
    </div>
    <div class="stat-card">
        <div class="flex items-center justify-between mb-3">
            <span class="text-slate-500 text-sm">بدون اشتراک</span>
            <div class="w-9 h-9 bg-amber-100 rounded-xl flex items-center justify-center">
                <svg class="w-5 h-5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
            </div>
        </div>
        <div class="text-3xl font-bold text-slate-800">{{ number_format($stats['noSubscriptionUsers']) }}</div>
        <div class="text-xs text-slate-400 mt-1">نیاز به پیگیری</div>
    </div>
</div>

{{-- Bulk Actions --}}
<div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 mb-4 flex flex-wrap items-center gap-3">
    <span class="text-sm font-medium text-slate-600">عملیات دسته‌جمعی روی اشتراک‌های فعال:</span>
    <form method="POST" action="{{ route('admin.bulk-extend') }}" class="flex gap-2 items-center">
        @csrf
        <input type="number" name="days" placeholder="تعداد روز" class="input-field w-28" required>
        <button class="btn-primary">اعمال</button>
    </form>
    <span class="text-xs text-slate-400">(عدد منفی = کاهش)</span>
</div>

{{-- Filters --}}
<div class="bg-white rounded-2xl border border-slate-100 shadow-sm p-4 mb-4">
    <form method="GET" class="flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-40">
            <label class="text-xs text-slate-500 block mb-1">جستجو</label>
            <input type="text" name="search" value="{{ $search }}" placeholder="نام، ایمیل یا موبایل..." class="input-field">
        </div>
        <div>
            <label class="text-xs text-slate-500 block mb-1">وضعیت اشتراک</label>
            <select name="status" class="input-field w-auto">
                <option value="all" {{ $status == 'all' ? 'selected' : '' }}>همه</option>
                <option value="active" {{ $status == 'active' ? 'selected' : '' }}>فعال</option>
                <option value="expired" {{ $status == 'expired' ? 'selected' : '' }}>منقضی</option>
                <option value="none" {{ $status == 'none' ? 'selected' : '' }}>بدون اشتراک</option>
            </select>
        </div>
        @if(auth()->user()->role === 'manager')
        <div>
            <label class="text-xs text-slate-500 block mb-1">نقش</label>
            <select name="role" class="input-field w-auto">
                <option value="">همه نقش‌ها</option>
                <option value="user" {{ $role == 'user' ? 'selected' : '' }}>کاربر</option>
                <option value="manager" {{ $role == 'manager' ? 'selected' : '' }}>مدیر کل</option>
                <option value="modir2" {{ $role == 'modir2' ? 'selected' : '' }}>مدیر</option>
            </select>
        </div>
        @endif
        <button class="btn-primary">فیلتر</button>
        <a href="{{ route('admin.dashboard') }}" class="px-4 py-2 border border-slate-200 rounded-xl text-sm text-slate-600 hover:bg-slate-50">پاک کردن</a>
    </form>
</div>

{{-- Users Table --}}
<div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
        <h2 class="font-semibold text-slate-800">لیست کاربران ({{ $users->total() }} نفر)</h2>
        <a href="{{ route('admin.users.export') }}?{{ http_build_query(array_filter(['search'=>$search,'status'=>$status,'role'=>$role])) }}"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 border border-emerald-200 rounded-xl text-xs font-medium transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
            </svg>
            دانلود CSV
        </a>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-500 text-xs">
                <tr>
                    <th class="px-4 py-3 text-right font-medium">#</th>
                    <th class="px-4 py-3 text-right font-medium">کاربر</th>
                    <th class="px-4 py-3 text-right font-medium">موبایل</th>
                    <th class="px-4 py-3 text-right font-medium">نقش</th>
                    <th class="px-4 py-3 text-right font-medium">اشتراک</th>
                    <th class="px-4 py-3 text-right font-medium">سؤالات</th>
                    <th class="px-4 py-3 text-right font-medium">تاریخ عضویت</th>
                    <th class="px-4 py-3 text-right font-medium">عملیات</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
            @foreach($users as $user)
            @php $sub = $user->activeSubscription; @endphp
            <tr class="hover:bg-slate-50/50 transition-colors">
                <td class="px-4 py-3 text-slate-400">{{ $user->id }}</td>
                <td class="px-4 py-3">
                    <div class="font-medium text-slate-800">{{ $user->name ?: '—' }}</div>
                    <div class="text-xs text-slate-400">{{ $user->email ?: 'بدون ایمیل' }}</div>
                </td>
                <td class="px-4 py-3 text-slate-600 font-mono text-xs">{{ $user->phone ?: '—' }}</td>
                <td class="px-4 py-3">
                    @if($user->role === 'manager') <span class="badge-manager">مدیر کل</span>
                    @elseif($user->role === 'modir2') <span class="badge-modir2">مدیر</span>
                    @else <span class="badge-user">کاربر</span>
                    @endif
                </td>
                <td class="px-4 py-3">
                    @if($sub)
                        <span class="badge-active">{{ $sub->plan->name ?? 'ندارد' }}</span>
                        <div class="text-xs text-slate-400 mt-0.5">تا {{ \Carbon\Carbon::parse($sub->expires_at)->format('Y/m/d') }}</div>
                    @else
                        <span class="badge-expired">بدون اشتراک</span>
                    @endif
                </td>
                <td class="px-4 py-3">
                    @if($sub)
                        <div class="text-slate-700 font-medium">{{ number_format($sub->questions_used) }} / {{ number_format($sub->plan->questions_limit ?? 0) }}</div>
                        <div class="w-full bg-slate-100 rounded-full h-1.5 mt-1">
                            @php $pct = $sub->plan ? min(100, $sub->questions_used / max(1, $sub->plan->questions_limit) * 100) : 0; @endphp
                            <div class="bg-indigo-400 h-1.5 rounded-full" style="width: {{ $pct }}%"></div>
                        </div>
                    @else
                        <span class="text-slate-300">—</span>
                    @endif
                </td>
                <td class="px-4 py-3 text-xs text-slate-400">{{ $user->created_at?->format('Y/m/d') }}</td>
                <td class="px-4 py-3">
                    <div class="flex gap-1.5">
                        <button onclick="openEditModal({{ $user->id }}, '{{ addslashes($user->name) }}', '{{ $user->phone }}', '{{ $user->email }}', '{{ $user->role }}', {{ $sub ? $sub->plan_id : 'null' }}, '{{ $sub ? $sub->expires_at?->format('Y-m-d') : '' }}', {{ $sub ? $sub->questions_used : 0 }})"
                            class="px-2.5 py-1.5 bg-indigo-50 text-indigo-600 hover:bg-indigo-100 rounded-lg text-xs font-medium transition-colors">
                            ویرایش
                        </button>
                        <a href="{{ route('admin.users.purchases', $user) }}"
                            class="px-2.5 py-1.5 bg-violet-50 text-violet-600 hover:bg-violet-100 rounded-lg text-xs font-medium transition-colors">
                            سابقه خرید
                        </a>
                        @if(auth()->user()->role === 'manager' && $user->id !== auth()->id())
                        <form method="POST" action="{{ route('admin.users.delete', $user) }}" onsubmit="return confirm('آیا مطمئنید؟')">
                            @csrf @method('DELETE')
                            <button class="px-2.5 py-1.5 bg-red-50 text-red-500 hover:bg-red-100 rounded-lg text-xs font-medium transition-colors">حذف</button>
                        </form>
                        @endif
                    </div>
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="px-5 py-4 border-t border-slate-100">
        {{ $users->links() }}
    </div>
</div>

{{-- Top Users by Usage --}}
@if(auth()->user()->role === 'manager')
<div class="bg-white rounded-2xl border border-slate-100 shadow-sm overflow-hidden mb-6">
    <div class="px-5 py-4 border-b border-slate-100 flex items-center gap-2">
        <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/>
        </svg>
        <h2 class="font-semibold text-slate-800">پرمصرف‌ترین کاربران (۱۰ نفر اول)</h2>
        <button type="button" id="loadTopUsersButton" onclick="loadTopUsers()" class="mr-auto rounded-lg bg-amber-50 px-3 py-2 text-xs font-semibold text-amber-700 hover:bg-amber-100">
            نمایش کاربران برتر
        </button>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-500 text-xs">
                <tr>
                    <th class="px-4 py-3 text-right font-medium">#</th>
                    <th class="px-4 py-3 text-right font-medium">کاربر</th>
                    <th class="px-4 py-3 text-right font-medium">موبایل</th>
                    <th class="px-4 py-3 text-right font-medium">تعداد پیام‌ها</th>
                    <th class="px-4 py-3 text-right font-medium">سؤالات دوره جاری</th>
                    <th class="px-4 py-3 text-right font-medium">پلن</th>
                </tr>
            </thead>
            <tbody id="topUsersBody" class="divide-y divide-slate-50">
            <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">برای دریافت این گزارش روی «نمایش کاربران برتر» کلیک کنید.</td></tr>
            </tbody>
        </table>
    </div>
</div>
@endif

@push('modals')
{{-- Edit User Modal --}}
<div id="editModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg" onclick="event.stopPropagation()">
        <div class="p-5 border-b flex justify-between items-center">
            <h3 class="font-bold text-slate-800">ویرایش کاربر: <span id="editUserName"></span></h3>
            <button onclick="closeModal('editModal')" class="text-slate-400 hover:text-slate-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <div class="p-5 space-y-4">
            {{-- Change Password --}}
            <form id="editPasswordForm" method="POST" class="space-y-3">
                @csrf
                <h4 class="text-sm font-semibold text-slate-700">تغییر رمز عبور</h4>
                <div class="flex gap-2">
                    <input type="password" name="password" placeholder="رمز جدید (حداقل ۶ کاراکتر)" class="input-field flex-1">
                    <button class="btn-primary whitespace-nowrap">ذخیره</button>
                </div>
            </form>
            <hr class="border-slate-100">
            {{-- Change Phone --}}
            <form id="editPhoneForm" method="POST" class="space-y-3">
                @csrf
                <h4 class="text-sm font-semibold text-slate-700">ویرایش موبایل</h4>
                <div class="flex gap-2">
                    <input type="text" name="phone" id="editPhoneInput" placeholder="شماره موبایل" class="input-field flex-1">
                    <button class="btn-primary whitespace-nowrap">ذخیره</button>
                </div>
            </form>
            @if(auth()->user()->role === 'manager')
            <hr class="border-slate-100">
            {{-- Change Role --}}
            <form id="editRoleForm" method="POST" class="space-y-3">
                @csrf
                <h4 class="text-sm font-semibold text-slate-700">نقش کاربر</h4>
                <div class="flex gap-2">
                    <select name="role" id="editRoleInput" class="input-field flex-1">
                        <option value="user">کاربر عادی</option>
                        <option value="modir2">مدیر (modir2)</option>
                        <option value="manager">مدیر کل (manager)</option>
                    </select>
                    <button class="btn-primary whitespace-nowrap">ذخیره</button>
                </div>
            </form>
            @endif
            <hr class="border-slate-100">
            {{-- Subscription --}}
            <form id="editSubForm" method="POST" class="space-y-3">
                @csrf
                <h4 class="text-sm font-semibold text-slate-700">مدیریت اشتراک</h4>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="text-xs text-slate-500">پلن</label>
                        <select name="plan_id" id="editPlanInput" class="input-field mt-1">
                            @foreach($plans as $plan)
                            <option value="{{ $plan->id }}">{{ $plan->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">تاریخ انقضا</label>
                        <input type="date" name="expires_at" id="editExpiresInput" class="input-field mt-1">
                    </div>
                </div>
                <div>
                    <label class="text-xs text-slate-500">سؤالات مصرف‌شده</label>
                    <input type="number" name="questions_used" id="editQUsedInput" class="input-field mt-1" min="0">
                </div>
                <button class="btn-primary w-full">ذخیره اشتراک</button>
            </form>
        </div>
    </div>
</div>

{{-- Create User Modal --}}
<div id="createUserModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md">
        <div class="p-5 border-b flex justify-between items-center">
            <h3 class="font-bold text-slate-800">ایجاد کاربر جدید</h3>
            <button onclick="closeModal('createUserModal')" class="text-slate-400 hover:text-slate-600">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        <form method="POST" action="{{ route('admin.users.create') }}" class="p-5 space-y-3">
            @csrf
            <div>
                <label class="text-xs text-slate-500">نام</label>
                <input type="text" name="name" class="input-field mt-1" required>
            </div>
            <div>
                <label class="text-xs text-slate-500">موبایل *</label>
                <input type="text" name="phone" class="input-field mt-1" required>
            </div>
            <div>
                <label class="text-xs text-slate-500">ایمیل (اختیاری)</label>
                <input type="email" name="email" class="input-field mt-1">
            </div>
            <div>
                <label class="text-xs text-slate-500">رمز عبور *</label>
                <input type="password" name="password" class="input-field mt-1" required minlength="6">
            </div>
            @if(auth()->user()->role === 'manager')
            <div>
                <label class="text-xs text-slate-500">نقش</label>
                <select name="role" class="input-field mt-1">
                    <option value="user">کاربر عادی</option>
                    <option value="modir2">مدیر</option>
                    <option value="manager">مدیر کل</option>
                </select>
            </div>
            @endif
            <button class="btn-primary w-full mt-2">ایجاد کاربر</button>
        </form>
    </div>
</div>
@endpush

@push('scripts')
<script>
async function loadTopUsers() {
    const button = document.getElementById('loadTopUsersButton');
    const body = document.getElementById('topUsersBody');
    if (!button || !body) return;

    button.disabled = true;
    button.textContent = 'در حال دریافت...';
    body.innerHTML = '<tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">در حال محاسبه گزارش...</td></tr>';

    try {
        const response = await fetch(@json(route('admin.top-users')), {
            headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}
        });
        const data = await response.json();
        if (!response.ok || !data.ok) throw new Error(data.message || 'دریافت گزارش ناموفق بود.');
        body.innerHTML = data.html;
        button.textContent = 'به‌روزرسانی گزارش';
    } catch (error) {
        body.innerHTML = '<tr><td colspan="6" class="px-4 py-8 text-center text-red-600">دریافت گزارش ناموفق بود؛ دوباره تلاش کنید.</td></tr>';
        button.textContent = 'تلاش دوباره';
    } finally {
        button.disabled = false;
    }
}

function openEditModal(id, name, phone, email, role, planId, expiresAt, questionsUsed) {
    document.getElementById('editUserName').textContent = name;
    document.getElementById('editPasswordForm').action = `/admin/users/${id}/change-password`;
    document.getElementById('editPhoneForm').action = `/admin/users/${id}/update-phone`;
    document.getElementById('editRoleForm') && (document.getElementById('editRoleForm').action = `/admin/users/${id}/update-role`);
    document.getElementById('editSubForm').action = `/admin/users/${id}/subscription`;

    if (document.getElementById('editPhoneInput')) document.getElementById('editPhoneInput').value = phone || '';
    if (document.getElementById('editRoleInput') && role) document.getElementById('editRoleInput').value = role;
    if (document.getElementById('editPlanInput') && planId) document.getElementById('editPlanInput').value = planId;
    if (document.getElementById('editExpiresInput')) document.getElementById('editExpiresInput').value = expiresAt || '';
    if (document.getElementById('editQUsedInput')) document.getElementById('editQUsedInput').value = questionsUsed || 0;

    document.getElementById('editModal').classList.remove('hidden');
}

function closeModal(id) {
    document.getElementById(id).classList.add('hidden');
}

document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal('editModal');
});
document.getElementById('createUserModal').addEventListener('click', function(e) {
    if (e.target === this) closeModal('createUserModal');
});
</script>
@endpush
@endsection
