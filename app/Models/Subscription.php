<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class Subscription extends Model
{
    protected $fillable = [
        'user_id',
        'plan_id',
        'questions_used',
        'expires_at',
        'zibal_track_id',
        'paid',
        'paid_amount_toman'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'paid'       => 'boolean',
        'paid_amount_toman' => 'integer',
    ];

    /* --------------------------------------------------------
     | روابط مدل‌ها
     -------------------------------------------------------- */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    /* --------------------------------------------------------
     | اسکوپ اشتراک فعال
     -------------------------------------------------------- */
    public function scopeActive(Builder $query)
    {
        return $query->where('paid', true)
                     ->where('expires_at', '>', now());
    }

    /* --------------------------------------------------------
     | آیا اشتراک فعال است؟
     -------------------------------------------------------- */
    public function isActive(): bool
    {
        return $this->paid
            && !empty($this->expires_at)
            && ($this->expires_at instanceof Carbon)
            && $this->expires_at->isFuture();
            
    }

    /* --------------------------------------------------------
     | آیا پلن نامحدود است؟ (ظاهری)
     -------------------------------------------------------- */
    public function isUnlimited(): bool
    {
        return $this->plan
            && is_numeric($this->plan->questions_limit)
            && $this->plan->questions_limit >= 3000;
    }

    /* --------------------------------------------------------
     | تعداد سؤال باقی‌مانده واقعی
     | — حتی اگر پلن نامحدود باشد، سقف واقعی ۳۰۰۰ است
     -------------------------------------------------------- */
    public function remainingQuestions(): int
    {
        if (!$this->isActive() || !$this->plan) {
            return 0;
        }

        // برای پلن‌های نامحدود → سقف واقعی = 3000
        if ($this->isUnlimited()) {
            $used = intval($this->questions_used);
            return max(0, 3000 - $used);
        }

        // پلن محدود عادی
        $limit = intval($this->plan->questions_limit);
        $used  = intval($this->questions_used);

        return max(0, $limit - $used);
    }

    /* --------------------------------------------------------
     | افزایش سؤال مصرف‌شده
     | — اگر کاربر سقف ۳۰۰۰ را پر کرده باشد، دیگر افزایش نمی‌دهد
     -------------------------------------------------------- */
    public function incrementQuestionsUsed(): void
    {
        if (!$this->isActive()) {
            return;
        }

        // اگر پلن نامحدود است → سقف واقعی 3000
        if ($this->isUnlimited()) {
            if ($this->questions_used >= 3000) {
                return; // دیگر اجازه نمی‌دهیم از 3000 بالاتر برود
            }
            $this->increment('questions_used');
            return;
        }

        // پلن محدود عادی
        if ($this->questions_used < intval($this->plan->questions_limit)) {
            $this->increment('questions_used');
        }
    }
}
