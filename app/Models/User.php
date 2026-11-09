<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class User extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'phone', 'phone_verified',
        'otp_code', 'otp_expires_at', 'username', 'is_admin', 'role',
        'free_question_used_at', 'free_questions_today', 'free_access_started_at',
    ];

    protected $hidden = ['password', 'remember_token', 'otp_code'];

    protected $casts = [
        'email_verified_at'      => 'datetime',
        'otp_expires_at'         => 'datetime',
        'free_question_used_at'  => 'datetime',
        'free_access_started_at' => 'datetime',
        'phone_verified'         => 'boolean',
        'is_admin'               => 'boolean',
    ];

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->active()->latest('id');
    }

    public function remainingQuestions(): int
    {
        $sub = $this->activeSubscription;
        return $sub ? $sub->remainingQuestions() : 0;
    }

    public function canAskQuestion(): bool
    {
        $sub = $this->activeSubscription;
        if ($sub !== null && $sub->remainingQuestions() > 0) {
            return true;
        }

        return $this->canUseFreeQuestion();
    }

    public static function dailyFreeLimit(): int
    {
        $value = DB::table('settings')
            ->where('key', 'daily_free_questions')
            ->value('value');

        return $value === null ? 1 : max(0, (int) $value);
    }

    public static function freeAccessDurationDays(): int
    {
        $value = DB::table('settings')
            ->where('key', 'free_access_duration_days')
            ->value('value');

        // Zero means the daily free allowance has no expiration date.
        return $value === null ? 0 : max(0, (int) $value);
    }

    public function hasFreeAccess(): bool
    {
        $days = self::freeAccessDurationDays();
        if ($days === 0 || is_null($this->free_access_started_at)) {
            return true;
        }

        return $this->free_access_started_at->copy()->addDays($days)->isFuture();
    }

    public function remainingFreeAccessDays(): ?int
    {
        $days = self::freeAccessDurationDays();
        if ($days === 0) {
            return null;
        }
        if (is_null($this->free_access_started_at)) {
            return $days;
        }

        $seconds = $this->free_access_started_at->copy()->addDays($days)->getTimestamp() - now()->getTimestamp();
        return max(0, (int) ceil($seconds / 86400));
    }

    public function canUseFreeQuestion(): bool
    {
        if (!$this->hasFreeAccess()) {
            return false;
        }

        $limit = self::dailyFreeLimit();
        if ($limit <= 0) {
            return false;
        }

        // اگر امروز نبوده → شمارنده ریست می‌شود
        if (is_null($this->free_question_used_at) || !$this->free_question_used_at->isToday()) {
            return true;
        }

        return $this->free_questions_today < $limit;
    }

    public function useFreeQuestion(): void
    {
        if (!is_null($this->free_question_used_at) && $this->free_question_used_at->isToday()) {
            if (is_null($this->free_access_started_at)) {
                $this->update(['free_access_started_at' => now()]);
            }
            $this->increment('free_questions_today');
        } else {
            $this->update([
                'free_access_started_at' => $this->free_access_started_at ?? now(),
                'free_question_used_at' => now(),
                'free_questions_today'  => 1,
            ]);
        }
    }

    public function remainingFreeQuestions(): int
    {
        if (!$this->hasFreeAccess()) return 0;
        $limit = self::dailyFreeLimit();
        if ($limit <= 0) return 0;
        if (is_null($this->free_question_used_at) || !$this->free_question_used_at->isToday()) {
            return $limit;
        }
        return max(0, $limit - $this->free_questions_today);
    }

    public function hasActivePlan(): bool
    {
        return $this->activeSubscription !== null;
    }

    public function isAdmin(): bool
    {
        return $this->role === 'manager' || (bool) $this->is_admin;
    }

    public function isManager(): bool
    {
        return $this->role === 'manager';
    }

    public function isModir2(): bool
    {
        return $this->role === 'modir2';
    }

    public function canManageUsers(): bool
    {
        return in_array($this->role, ['manager', 'modir2']);
    }

    public function setPasswordAttribute($value)
    {
        if (!empty($value)) {
            if (substr($value, 0, 4) === '$2y$') {
                $this->attributes['password'] = $value;
            } else {
                $this->attributes['password'] = Hash::make($value);
            }
        }
    }

    public function conversations()
    {
        return $this->hasMany(Conversation::class)->orderByDesc('last_message_at');
    }

    public function uploadedFiles()
    {
        return $this->hasMany(UploadedFile::class);
    }
}
