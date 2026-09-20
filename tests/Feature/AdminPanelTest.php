<?php

namespace Tests\Feature;

use App\Filament\Pages\SiteSettings;
use App\Filament\Resources\Districts\Pages\EditDistrict;
use App\Filament\Resources\Provinces\Pages\EditProvince;
use App\Filament\Resources\Services\Pages\CreateService;
use App\Filament\Resources\Services\Pages\EditService;
use App\Models\District;
use App\Models\GalleryItem;
use App\Models\Page;
use App\Models\Province;
use App\Models\QuoteRequest;
use App\Models\Service;
use App\Models\Setting;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function admin(): User
    {
        return User::where('email', 'admin@example.com')->firstOrFail();
    }

    public function test_guests_are_redirected_to_login(): void
    {
        foreach (['/admin', '/admin/services', '/admin/quote-requests', '/admin/site-settings'] as $url) {
            $this->get($url)->assertRedirect('/admin/login');
        }

        $this->get('/admin/login')->assertOk();
    }

    public function test_all_admin_pages_render_for_admin(): void
    {
        $quote = QuoteRequest::create(['name' => 'Test', 'phone' => '05321112233', 'kvkk_accepted' => true]);

        $urls = [
            '/admin',
            '/admin/site-settings',
            '/admin/services', '/admin/services/create', '/admin/services/'.Service::first()->id.'/edit',
            '/admin/provinces', '/admin/provinces/create', '/admin/provinces/'.Province::first()->id.'/edit',
            '/admin/districts', '/admin/districts/create', '/admin/districts/'.District::first()->id.'/edit',
            '/admin/gallery-items', '/admin/gallery-items/create', '/admin/gallery-items/'.GalleryItem::first()->id.'/edit',
            '/admin/gallery-categories',
            '/admin/testimonials',
            '/admin/faqs',
            '/admin/features',
            '/admin/pages', '/admin/pages/create', '/admin/pages/'.Page::first()->id.'/edit',
            '/admin/quote-requests', '/admin/quote-requests/'.$quote->id, '/admin/quote-requests/'.$quote->id.'/edit',
            '/admin/users',
        ];

        $this->actingAs($this->admin());

        foreach ($urls as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_settings_page_saves_and_site_reflects_change(): void
    {
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(SiteSettings::class)
            ->fillForm([
                'site_name' => 'Deneme Montaj',
                'phone' => '+90 532 999 88 77',
                'whatsapp' => '905329998877',
                'hero_title' => 'Yeni Başlık Mobilya Montaj',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Deneme Montaj', Setting::where('key', 'site_name')->value('value'));

        $this->get('/')
            ->assertSee('Deneme Montaj')
            ->assertSee('tel:+905329998877', false)
            ->assertSee('https://wa.me/905329998877', false);
    }

    public function test_service_can_be_created_and_slug_collisions_are_blocked(): void
    {
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(CreateService::class)
            ->fillForm([
                'title' => 'Koltuk Montajı',
                'slug' => 'koltuk-montaji',
                'short_description' => 'Koltuk ve kanepe kurulumu.',
                'what_we_do' => [['item' => 'Ayak montajı'], ['item' => 'Modül birleştirme']],
                'faqs' => [['question' => 'Ne kadar sürer?', 'answer' => 'Yaklaşık bir saat.']],
                'is_active' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $created = Service::where('slug', 'koltuk-montaji')->firstOrFail();
        $this->assertSame(['Ayak montajı', 'Modül birleştirme'], $created->what_we_do, 'basit liste düz dizi olarak saklanmalı');
        $this->get('/koltuk-montaji')->assertOk()->assertSee('Ayak montajı')->assertSee('Ne kadar sürer?');

        Livewire::test(CreateService::class)
            ->fillForm(['title' => 'Tekirdağ', 'slug' => 'tekirdag'])
            ->call('create')
            ->assertHasFormErrors(['slug']);
    }

    public function test_seeded_records_can_be_saved_unchanged_from_the_panel(): void
    {
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        foreach (Service::all() as $service) {
            Livewire::test(EditService::class, ['record' => $service->getKey()])
                ->call('save')
                ->assertHasNoFormErrors();

            $fresh = $service->fresh();
            $this->assertSame($service->what_we_do, $fresh->what_we_do, $service->slug.': liste bozulmamalı');
            $this->assertSame($service->faqs, $fresh->faqs);
            $this->assertSame($service->process_steps, $fresh->process_steps);
            $this->assertSame($service->image, $fresh->image);
        }

        foreach (Province::all() as $province) {
            Livewire::test(EditProvince::class, ['record' => $province->getKey()])
                ->call('save')
                ->assertHasNoFormErrors();
        }

        foreach (District::all() as $district) {
            Livewire::test(EditDistrict::class, ['record' => $district->getKey()])
                ->call('save')
                ->assertHasNoFormErrors();

            $fresh = $district->fresh();
            $this->assertSame($district->neighborhoods, $fresh->neighborhoods, $district->slug.': mahalleler bozulmamalı');
            $this->assertSame($district->faqs, $fresh->faqs);
        }
    }

    public function test_seeded_settings_can_be_saved_unchanged(): void
    {
        $this->actingAs($this->admin());
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        $before = Setting::pluck('value', 'key')->all();

        Livewire::test(SiteSettings::class)->call('save')->assertHasNoFormErrors();

        foreach (['hero_image', 'about_image', 'hero_title', 'whatsapp_message'] as $key) {
            $this->assertSame($before[$key], Setting::where('key', $key)->value('value'), $key.' değişmemeli');
        }

        // Zengin metin editörü kesme işaretini &#039; olarak kodlar; tarayıcıda aynı görünür.
        // İçeriğin kendisi (varlıklar çözüldükten sonra) birebir aynı kalmalıdır.
        $this->assertSame(
            $before['about_text'],
            html_entity_decode(Setting::where('key', 'about_text')->value('value'), ENT_QUOTES | ENT_HTML5),
            'about_text içeriği değişmemeli'
        );
    }

    public function test_quote_photos_are_private(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('quote-photos/test.jpg', 'fake-image');

        $quote = QuoteRequest::create([
            'name' => 'Test', 'phone' => '05321112233', 'kvkk_accepted' => true,
            'photos' => ['quote-photos/test.jpg'],
        ]);

        $url = route('admin.quote-photo', ['quoteRequest' => $quote, 'index' => 0]);

        $this->get($url)->assertRedirect('/admin/login');

        $this->actingAs($this->admin());
        $this->get($url)->assertOk();
        $this->get(route('admin.quote-photo', ['quoteRequest' => $quote, 'index' => 5]))->assertNotFound();
    }
}
