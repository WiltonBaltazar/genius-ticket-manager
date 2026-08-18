<?php

namespace App\Filament\Resources\Staff\Schemas;

use App\Enums\StaffRole;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Unique;

class StaffForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    // staff.email_active (research.md §3 of 001-core-database-schema) lets an
                    // email be reused once its prior staff account is soft-deleted — mirror
                    // that here rather than the plain "unique on email" Filament default.
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->whereNull('deleted_at')),
                Select::make('role')
                    ->options(collect(StaffRole::cases())->mapWithKeys(
                        fn (StaffRole $role) => [$role->value => Str::headline($role->value)]
                    ))
                    ->required()
                    ->native(false),
                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->maxLength(255)
                    ->same('passwordConfirmation')
                    // Blank on edit means "leave it unchanged" — Staff::password casts 'hashed',
                    // so whatever reaches the model is hashed automatically; nothing else needed.
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->helperText(fn (string $operation): ?string => $operation === 'edit' ? 'Leave blank to keep the current password.' : null),
                TextInput::make('passwordConfirmation')
                    ->label('Confirm password')
                    ->password()
                    ->revealable()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(false),
            ]);
    }
}
