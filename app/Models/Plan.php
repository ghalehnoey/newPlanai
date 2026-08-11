<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Subscription;

class Plan extends Model
{
    protected $fillable = [
        'name',
        'price',
        'questions_limit',
        'is_active',
    ];

    protected $casts = [
        'price'           => 'integer',
        'questions_limit' => 'integer',
        'is_active'       => 'boolean',
    ];

    /* --------------------------------------------------------
     | اسکوپ پلن‌های فعال
     -------------------------------------------------------- */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /* --------------------------------------------------------
     | آیا این پلن نامحدود است؟
     -------------------------------------------------------- */
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

    public function isUnlimited(): bool
    {
        return $this->questions_limit >= 3000;
    }

    /* --------------------------------------------------------
     | توضیح کوتاه پلن (برای نمایش در سایت / داشبورد)
     -------------------------------------------------------- */
    public function getDescriptionAttribute()
    {
        return $this->isUnlimited()
            ? 'نامحدود'
            : "{$this->questions_limit} سؤال";
    }
}
