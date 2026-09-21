<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\QuoteRequest;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function user(UserRole $role, array $attributes = []): User
    {
        return User::create(array_merge([
            'name' => $role->label(),
            'email' => $role->value.'@ornek.test',
            'password' => 'parola1234',
            'role' => $role,
            'is_active' => true,
        ], $attributes));
    }

    private function quote(array $attributes = []): QuoteRequest
    {
        // refresh(): status gibi alanların varsayılanı veritabanından gelir.
        return QuoteRequest::create(array_merge([
            'name' => 'Müşteri', 'phone' => '05321112233', 'kvkk_accepted' => true,
        ], $attributes))->refresh();
    }

    // ---------- Panel erişimi ----------

    public function test_inactive_account_cannot_enter_the_panel(): void
    {
        $user = $this->user(UserRole::Personel, ['is_active' => false]);

        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    public static function allRoles(): array
    {
        return collect(UserRole::cases())->mapWithKeys(fn (UserRole $r) => [$r->label() => [$r]])->all();
    }

    #[DataProvider('allRoles')]
    public function test_every_active_role_can_open_the_panel(UserRole $role): void
    {
        $this->actingAs($this->user($role))->get('/admin')->assertOk();
    }

    // ---------- Sayfa erişimi ----------

    /**
     * Sayfa / rol erişim matrisi.
     *
     * Her satır TEK bir rolü dener. Aynı test içinde kullanıcı değiştirmek,
     * Filament'in oturum durumunu taşıdığı için yanıltıcı sonuç verir.
     *
     * @return array<string, array{string, UserRole, bool}>
     */
    public static function pageMatrix(): array
    {
        $pages = [
            '/admin/site-settings' => ['super_admin'],
            '/admin/seo-ai-settings' => ['super_admin'],
            '/admin/users' => ['super_admin'],
            '/admin/duplicate-cleaner' => ['super_admin'],
            '/admin/seo-dashboard' => ['super_admin', 'personel'],
            '/admin/seo-keywords' => ['super_admin', 'personel'],
            '/admin/analytics' => ['super_admin', 'personel', 'musteri_temsilcisi'],
            '/admin/services' => ['super_admin', 'personel', 'musteri_temsilcisi'],
            '/admin/quote-requests' => ['super_admin', 'personel', 'musteri_temsilcisi', 'montajci'],
        ];

        $rows = [];

        foreach ($pages as $url => $allowedRoles) {
            foreach (UserRole::cases() as $role) {
                $allowed = in_array($role->value, $allowedRoles, true);
                $rows[$url.' · '.$role->label()] = [$url, $role, $allowed];
            }
        }

        return $rows;
    }

    #[DataProvider('pageMatrix')]
    public function test_page_access_matches_the_role_matrix(string $url, UserRole $role, bool $allowed): void
    {
        $response = $this->actingAs($this->user($role))->get($url);

        $allowed
            ? $response->assertOk()
            : $response->assertForbidden();
    }

    // ---------- İçerik yetkileri ----------

    public function test_only_admin_and_staff_can_change_content(): void
    {
        $service = Service::first();

        $this->assertTrue($this->user(UserRole::SuperAdmin)->can('update', $service));
        $this->assertTrue($this->user(UserRole::Personel)->can('update', $service));
        $this->assertFalse($this->user(UserRole::MusteriTemsilcisi)->can('update', $service));
        $this->assertFalse($this->user(UserRole::Montajci)->can('view', $service));
    }

    // ---------- Teklif talebi görünürlüğü ----------

    public function test_installer_sees_only_their_own_quotes(): void
    {
        $installer = $this->user(UserRole::Montajci);
        $other = $this->user(UserRole::Montajci, ['email' => 'montajci2@ornek.test']);

        $mine = $this->quote(['assigned_to' => $installer->id]);
        $theirs = $this->quote(['assigned_to' => $other->id, 'phone' => '05321112299']);
        $unassigned = $this->quote(['phone' => '05321112288']);

        $visible = QuoteRequest::query()->visibleTo($installer)->pluck('id');

        $this->assertTrue($visible->contains($mine->id));
        $this->assertFalse($visible->contains($theirs->id), 'başka montajcının işi görünmemeli');
        $this->assertFalse($visible->contains($unassigned->id), 'atanmamış iş montajcıya görünmemeli');

        // Temsilci hepsini görür.
        $this->assertCount(3, QuoteRequest::query()->visibleTo($this->user(UserRole::MusteriTemsilcisi))->get());
    }

    public function test_installer_cannot_open_another_installers_quote_by_url(): void
    {
        $installer = $this->user(UserRole::Montajci);
        $other = $this->user(UserRole::Montajci, ['email' => 'm2@ornek.test']);

        $mine = $this->quote(['assigned_to' => $installer->id]);
        $theirs = $this->quote(['assigned_to' => $other->id, 'phone' => '05321112299']);

        $this->actingAs($installer);

        // Sorgu filtresi listeyi korur; ilke doğrudan adresi korur.
        $this->assertTrue($installer->can('view', $mine));
        $this->assertFalse($installer->can('view', $theirs));

        // Kendi işini açabilir.
        $this->get('/admin/quote-requests/'.$mine->id)->assertOk();

        /*
         * Başkasının işi AÇILAMAZ. Durum kodu 403 değil 404'tür: kayıt zaten
         * sorgudan elenir, yani kaydın VARLIĞI da sızmaz. Asıl güvence, müşteri
         * verisinin yanıtta bulunmamasıdır.
         */
        $response = $this->get('/admin/quote-requests/'.$theirs->id);

        $this->assertContains($response->getStatusCode(), [403, 404]);
        $this->assertStringNotContainsString('05321112299', $response->getContent());
    }

    // ---------- Atama ----------

    public function test_only_admin_and_representative_can_assign(): void
    {
        $quote = $this->quote();

        $this->assertTrue($this->user(UserRole::SuperAdmin)->can('assign', $quote));
        $this->assertTrue($this->user(UserRole::MusteriTemsilcisi)->can('assign', $quote));
        $this->assertFalse($this->user(UserRole::Personel)->can('assign', $quote));
        $this->assertFalse($this->user(UserRole::Montajci)->can('assign', $quote));
    }

    public function test_assignment_records_who_when_and_moves_status_forward(): void
    {
        $rep = $this->user(UserRole::MusteriTemsilcisi);
        $installer = $this->user(UserRole::Montajci);
        $quote = $this->quote();

        $this->assertSame(QuoteRequest::STATUS_NEW, $quote->status);

        $quote->assignTo($installer, $rep, 'Asansör yok, 4. kat.');
        $quote->refresh();

        $this->assertSame($installer->id, $quote->assigned_to);
        $this->assertSame($rep->id, $quote->assigned_by);
        $this->assertNotNull($quote->assigned_at);
        $this->assertSame('Asansör yok, 4. kat.', $quote->assignment_note);
        // Atanan bir talep artık "yeni" değildir.
        $this->assertSame(QuoteRequest::STATUS_CONTACTED, $quote->status);
    }

    public function test_reassignment_keeps_a_finished_status(): void
    {
        $rep = $this->user(UserRole::MusteriTemsilcisi);
        $installer = $this->user(UserRole::Montajci);
        $quote = $this->quote(['status' => QuoteRequest::STATUS_COMPLETED]);

        $quote->assignTo($installer, $rep);

        $this->assertSame(QuoteRequest::STATUS_COMPLETED, $quote->refresh()->status, 'tamamlanmış iş geri alınmamalı');
    }

    // ---------- Kullanıcı yönetimi ----------

    public function test_only_super_admin_manages_users(): void
    {
        $admin = $this->user(UserRole::SuperAdmin);
        $rep = $this->user(UserRole::MusteriTemsilcisi);

        $this->assertTrue($admin->can('viewAny', User::class));
        $this->assertFalse($rep->can('viewAny', User::class));

        // Herkes kendi profilini düzenleyebilir.
        $this->assertTrue($rep->can('update', $rep));
        $this->assertFalse($rep->can('update', $admin));
    }

    public function test_nobody_can_delete_themselves(): void
    {
        $admin = $this->user(UserRole::SuperAdmin);
        $other = $this->user(UserRole::Personel);

        $this->assertFalse($admin->can('delete', $admin), 'kendini silmek panelde yönetici bırakmayabilir');
        $this->assertTrue($admin->can('delete', $other));
    }
}
