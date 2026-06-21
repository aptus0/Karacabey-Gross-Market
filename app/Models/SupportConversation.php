<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SupportConversation extends Model
{
    use HasFactory;

    public const STATUS_OPEN = 'open';
    public const STATUS_PENDING = 'pending';
    public const STATUS_CLOSED = 'closed';

    protected $fillable = [
        'tenant_id',
        'user_id',
        'assigned_admin_id',
        'public_token',
        'guest_token',
        'status',
        'source',
        'customer_name',
        'customer_email',
        'customer_phone',
        'subject',
        'last_message_preview',
        'last_message_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'last_message_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_admin_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class)->orderBy('id');
    }

    public function touchLastMessage(SupportMessage $message): void
    {
        $this->forceFill([
            'last_message_preview' => mb_substr($message->body, 0, 500),
            'last_message_at' => $message->created_at ?? now(),
        ])->save();
    }
}
