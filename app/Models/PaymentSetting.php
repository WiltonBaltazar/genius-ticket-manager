<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentSetting extends Model
{
    protected $fillable = [
        'whatsapp_number',
        'bank_account_name',
        'bank_account_number',
        'bank_nib',
        'bank_name',
        'bank_branch',
        'bank_transfer_instructions',
    ];

    /**
     * Singleton settings row — the migration seeds exactly one, so this is
     * always the row to read or update.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate([]);
    }
}
