@forelse($topUsers as $index => $user)
<tr class="hover:bg-slate-50/50">
    <td class="px-4 py-3">
        @if($index === 0)
            <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-amber-400 text-xs font-bold text-white">۱</span>
        @elseif($index === 1)
            <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-slate-300 text-xs font-bold text-white">۲</span>
        @elseif($index === 2)
            <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-amber-600 text-xs font-bold text-white">۳</span>
        @else
            <span class="px-1 text-xs text-slate-400">{{ $index + 1 }}</span>
        @endif
    </td>
    <td class="px-4 py-3">
        <div class="font-medium text-slate-800">{{ $user->name ?: '—' }}</div>
        <div class="text-xs text-slate-400">#{{ $user->id }}</div>
    </td>
    <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ $user->phone ?: '—' }}</td>
    <td class="px-4 py-3">
        <span class="inline-flex items-center gap-1 rounded-lg bg-indigo-50 px-2.5 py-1 text-sm font-semibold text-indigo-700">
            {{ number_format($user->total_messages) }}
            <span class="text-xs font-normal text-indigo-400">پیام</span>
        </span>
    </td>
    <td class="px-4 py-3 font-medium text-slate-700">{{ number_format($user->questions_used) }}</td>
    <td class="px-4 py-3">
        @if($user->plan_name)<span class="badge-active">{{ $user->plan_name }}</span>@else<span class="text-xs text-slate-300">—</span>@endif
    </td>
</tr>
@empty
<tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">داده‌ای یافت نشد.</td></tr>
@endforelse
