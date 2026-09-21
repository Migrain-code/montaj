<?php

namespace App\Enums;

/**
 * Panel rolleri.
 *
 * Tek bir "role" alanı kullanılır; dört rol için ayrı bir yetki paketi kurmak
 * gereksiz karmaşıklık olurdu. Yetkiler App\Policies altındaki ilkelerde tanımlıdır.
 */
enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case MusteriTemsilcisi = 'musteri_temsilcisi';
    case Personel = 'personel';
    case Montajci = 'montajci';

    public function label(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Süper Yönetici',
            self::MusteriTemsilcisi => 'Müşteri Temsilcisi',
            self::Personel => 'Personel',
            self::Montajci => 'Montajcı',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Her şeye erişir: içerik, bölgeler, SEO, kullanıcılar ve ayarlar.',
            self::MusteriTemsilcisi => 'Tüm teklif taleplerini görür, montajcılara atar, durum takibi yapar.',
            self::Personel => 'İçerik, blog, galeri ve bölgeleri yönetir. Talepleri görür ama atayamaz.',
            self::Montajci => 'Yalnız kendisine atanan talepleri görür ve durumunu günceller.',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::SuperAdmin => 'danger',
            self::MusteriTemsilcisi => 'info',
            self::Personel => 'warning',
            self::Montajci => 'success',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $r) => [$r->value => $r->label()])->all();
    }
}
