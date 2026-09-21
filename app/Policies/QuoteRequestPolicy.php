<?php

namespace App\Policies;

use App\Models\QuoteRequest;
use App\Models\User;

/**
 * Teklif talebi ilkeleri.
 *
 * MONTAJCI YALNIZ KENDİSİNE ATANANI GÖRÜR. Bu kural iki yerde birden uygulanır:
 * burada (tek kayıt erişimi) ve listeleme sorgusunda (scopeVisibleTo). Yalnız
 * sorguyu filtrelemek yetmez — kayıt kimliğini bilen biri doğrudan adrese gidebilir.
 */
class QuoteRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return true; // montajcı da listeyi görür, ama yalnız kendi kayıtlarını
    }

    public function view(User $user, QuoteRequest $quote): bool
    {
        if ($user->isInstaller()) {
            return $quote->assigned_to === $user->getKey();
        }

        return true;
    }

    /** Talepler siteden gelir; panelden elle oluşturulmaz. */
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, QuoteRequest $quote): bool
    {
        // Montajcı kendi işinin durumunu ve notunu güncelleyebilir.
        if ($user->isInstaller()) {
            return $quote->assigned_to === $user->getKey();
        }

        return $user->isSuperAdmin() || $user->isRepresentative() || $user->isStaff();
    }

    public function delete(User $user, QuoteRequest $quote): bool
    {
        return $user->isSuperAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isSuperAdmin();
    }

    /** Montajcıya atama yetkisi. */
    public function assign(User $user, QuoteRequest $quote): bool
    {
        return $user->assignsQuotes();
    }
}
