<?php

namespace App\Policies;

use App\Models\User;

/**
 * İçerik ilkeleri: hizmetler, bölgeler, blog, galeri, yorumlar, SSS, sayfalar.
 *
 * Süper yönetici ve personel yönetir. Müşteri temsilcisi görür ama değiştiremez.
 * Montajcı içeriğe hiç girmez — işi montajdır, paneli sade kalmalıdır.
 */
class ContentPolicy
{
    public function viewAny(User $user): bool
    {
        return ! $user->isInstaller();
    }

    public function view(User $user): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->managesContent();
    }

    public function update(User $user): bool
    {
        return $user->managesContent();
    }

    public function delete(User $user): bool
    {
        return $user->managesContent();
    }

    public function deleteAny(User $user): bool
    {
        return $user->managesContent();
    }

    public function reorder(User $user): bool
    {
        return $user->managesContent();
    }
}
