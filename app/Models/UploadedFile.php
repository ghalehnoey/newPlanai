<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UploadedFile extends Model
{
    protected $fillable = [
        'user_id', 'conversation_id', 'message_id',
        'original_name', 'stored_name', 'path', 'mime_type', 'size', 'type', 'extracted_text'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function message()
    {
        return $this->belongsTo(Message::class);
    }

    public function messages()
    {
        return $this->belongsToMany(Message::class, 'message_files', 'file_id', 'message_id');
    }

    public function url()
    {
        return asset('storage/' . $this->path);
    }
}