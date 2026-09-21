<?php

namespace App\Models;

use App\Enums\UserRole;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser, HasAvatar
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'role',
        'title', 'phone', 'whatsapp', 'photo', 'bio',
        'is_active', 'show_on_site', 'sort_order',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'show_on_site' => 'boolean',
        ];
    }

    /** Pasife alınan personel panele giremez. */
    public function canAccessPanel(Panel $panel): bool
    {
        return (bool) $this->is_active;
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->photo ? media_url($this->photo) : null;
    }

    // ---------- Roller ----------

    public function isSuperAdmin(): bool
    {
        return $this->role === UserRole::SuperAdmin;
    }

    public function isRepresentative(): bool
    {
        return $this->role === UserRole::MusteriTemsilcisi;
    }

    public function isInstaller(): bool
    {
        return $this->role === UserRole::Montajci;
    }

    public function isStaff(): bool
    {
        return $this->role === UserRole::Personel;
    }

    /** İçerik (hizmet, blog, galeri, bölge) yönetebilir mi? */
    public function managesContent(): bool
    {
        return in_array($this->role, [UserRole::SuperAdmin, UserRole::Personel], true);
    }

    /** Teklif taleplerini görebilir mi? Montajcı yalnız kendisininkini görür. */
    public function seesQuotes(): bool
    {
        return $this->role !== null;
    }

    /** Talepleri montajcılara atayabilir mi? */
    public function assignsQuotes(): bool
    {
        return in_array($this->role, [UserRole::SuperAdmin, UserRole::MusteriTemsilcisi], true);
    }

    // ---------- İlişkiler ----------

    public function assignedQuotes(): HasMany
    {
        return $this->hasMany(QuoteRequest::class, 'assigned_to');
    }

    // ---------- Kapsamlar ----------

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeInstallers(Builder $query): Builder
    {
        return $query->where('role', UserRole::Montajci);
    }

    /** Web sitesinde gösterilecek personel. */
    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_active', true)->where('show_on_site', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    // ---------- Görünüm yardımcıları ----------

    public function getRoleLabelAttribute(): string
    {
        return $this->role?->label() ?? '-';
    }

    public function getPhotoUrlAttribute(): ?string
    {
        return $this->photo ? media_url($this->photo) : null;
    }

    /** Baş harfler — fotoğraf yoksa avatar yerine. */
    public function getInitialsAttribute(): string
    {
        return collect(preg_split('/\s+/u', trim((string) $this->name)))
            ->filter()
            ->take(2)
            ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1), 'UTF-8'))
            ->implode('');
    }

    public function getWhatsappNumberAttribute(): ?string
    {
        $value = $this->whatsapp ?: $this->phone;

        return $value ? ltrim(phone_digits($value), '+') : null;
    }
}
