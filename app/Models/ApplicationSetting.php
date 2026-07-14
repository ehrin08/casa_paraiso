<?php

namespace App\Models;

use Database\Factories\ApplicationSettingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class ApplicationSetting extends Model
{
    /** @use HasFactory<ApplicationSettingFactory> */
    use HasFactory;

    protected $fillable = [
        'business_name',
        'contact_email',
        'contact_phone',
        'business_address',
        'default_payment_method',
        'updated_by',
    ];

    public static function current(): self
    {
        if (! self::tableAvailable()) {
            return new self(self::defaults());
        }

        return self::query()->first() ?? new self(self::defaults());
    }

    public static function tableAvailable(): bool
    {
        return Schema::hasTable((new self)->getTable());
    }

    /** @return array<string, mixed> */
    public static function defaults(): array
    {
        return [
            'business_name' => config('casa.business_name'),
            'contact_email' => null,
            'contact_phone' => null,
            'business_address' => null,
            'default_payment_method' => Transaction::METHOD_CASH,
            'updated_by' => null,
        ];
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
