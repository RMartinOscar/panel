<?php

namespace App\Models;

use App\Contracts\Validatable;
use App\Traits\HasValidation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Ramsey\Uuid\Uuid;
use Illuminate\Http\Request;
use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @deprecated — this class will be dropped in a future version, use the activity log
 */
class AuditLog extends Model implements Validatable
{
    use HasFactory;
    use HasValidation;

    public const UPDATED_AT = null;

    public static array $validationRules = [
        'uuid' => 'required|uuid',
        'action' => 'required|string|max:255',
        'subaction' => 'nullable|string|max:255',
        'device' => 'array',
        'device.ip_address' => 'ip',
        'device.user_agent' => 'string',
        'metadata' => 'array',
    ];

    protected $guarded = [
        'id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'bool',
            'device' => 'array',
            'metadata' => 'array',
            'created_at' => 'immutable_datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    /**
     * Creates a new AuditLog model and returns it, attaching device information and the
     * currently authenticated user if available. This model is not saved at this point, so
     * you can always make modifications to it as needed before saving.
     *
     * @deprecated
     */
    public static function instance(string $action, array $metadata, bool $isSystem = false): self
    {
        /** @var ?Request $request */
        $request = Container::getInstance()->make('request');
        if ($isSystem || !$request instanceof Request) {
            $request = null;
        }

        return (new self())->fill([
            'uuid' => Uuid::uuid4()->toString(),
            'is_system' => $isSystem,
            'user_id' => ($request && $request->user()) ? $request->user()->id : null,
            'server_id' => null,
            'action' => $action,
            'device' => $request ? [
                'ip_address' => $request->getClientIp() ?? '127.0.0.1',
                'user_agent' => $request->userAgent() ?? '',
            ] : [],
            'metadata' => $metadata,
        ]);
    }
}
