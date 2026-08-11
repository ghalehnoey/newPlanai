<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Str;

class ConversationService
{
    /**
     * یافتن یا ساخت یک مکالمه جدید برای کاربر
     */
    public function findOrCreateForUser(User $user, ?int $conversationId): Conversation
    {
        if ($conversationId) {
            return $user->conversations()->findOrFail($conversationId);
        }

        return Conversation::create([
            'user_id' => $user->id,
            'title'   => 'چت جدید',
            'last_message_at' => now(),
        ]);
    }

    /**
     * ایجاد پیام کاربر
     */
    public function createUserMessage(
        Conversation $conversation,
        User $user,
        string $content,
        string $model,
        array $fileIds = []
    ): Message {
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'user_id'         => $user->id,
            'role'            => 'user',   // **ثابت**
            'content'         => $content,
            'model'           => $model,
        ]);

        if (!empty($fileIds)) {
            $message->files()->attach($fileIds);
        }

        // آپدیت زمان آخرین پیام
        $conversation->update(['last_message_at' => now()]);

        return $message;
    }

    /**
     * ایجاد پیام دستیار (AI)
     */
    public function createAssistantMessage(
        Conversation $conversation,
        User $user,
        string $content,
        string $model
    ): Message {
        $message = Message::create([
            'conversation_id' => $conversation->id,
            'user_id'         => $user->id,  // لازم است متعلق به کاربر باشد، نه GPT
            'role'            => 'assistant',
            'content'         => $content,
            'model'           => $model,
        ]);

        $conversation->update(['last_message_at' => now()]);

        return $message;
    }

    /**
     * آپدیت عنوان گفتگو فقط بعد از اولین سؤال و اولین جواب
     */
    public function updateTitleIfFirstMessages(Conversation $conversation, string $question): void
    {
        // وقتی ۲ پیام شد (مثلا user + assistant)
        if ($conversation->messages()->count() === 2) {
            $title = Str::limit(
                preg_replace('/\s+/', ' ', strip_tags($question)),
                50
            );

            $conversation->update([
                'title' => $title ?: 'چت جدید',
            ]);
        }
    }

    /**
     * بروزرسانی زمان آخرین پیام
     */
    public function touchLastMessageAt(Conversation $conversation): void
    {
        $conversation->update(['last_message_at' => now()]);
    }

    /**
     * کم‌کردن کوتا (اگر اشتراک داشت)
     */
    public function decrementUserQuotaIfAny(User $user): void
    {
        if ($sub = $user->activeSubscription()) {
            $sub->incrementQuestionsUsed();
        }
    }
}
