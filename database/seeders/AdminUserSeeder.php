<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->firstOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@example.com')],
            [
                'name' => 'Yönetici',
                'password' => env('ADMIN_PASSWORD', 'password'),
                'role' => UserRole::SuperAdmin,
                'is_active' => true,
            ]
        );

        // Var olan kurulumlarda ilk hesap süper yönetici olmalı; aksi hâlde
        // rol sistemi devreye girdiğinde kimse ayarlara ve kullanıcılara erişemez.
        if ($admin->role !== UserRole::SuperAdmin && User::query()->where('role', UserRole::SuperAdmin)->doesntExist()) {
            $admin->forceFill(['role' => UserRole::SuperAdmin, 'is_active' => true])->save();
        }
    }
}
