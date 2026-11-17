<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreQuoteRequest;
use App\Mail\QuoteRequestReceived;
use App\Models\QuoteRequest;
use App\Services\Media\WebpConverter;
use App\Support\SchemaOrg;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class QuoteController extends Controller
{
    public function create(): View
    {
        return view('quote.create', [
            'metaTitle' => 'Teklif Al | Fotoğraf Gönderin, Fiyat Alın',
            'metaDescription' => 'Mobilya montajı için teklif formunu doldurun; mobilyanızın fotoğrafını ekleyin, size en kısa sürede net fiyat verelim.',
            'canonical' => route('quote.create'),
            'jsonLd' => [SchemaOrg::breadcrumbs([
                ['name' => 'Ana Sayfa', 'url' => url('/')],
                ['name' => 'Teklif Al', 'url' => route('quote.create')],
            ])],
        ]);
    }

    public function store(StoreQuoteRequest $request): RedirectResponse
    {
        $data = $request->safe()->except(['photos', 'kvkk', 'website']);

        // Müşteri fotoğrafları da WebP'ye çevrilir: telefon fotoğrafları büyüktür ve
        // panelde hızlı açılması gerekir. Çevrilemezse orijinal korunur.
        $photos = [];
        $converter = app(WebpConverter::class);

        foreach ($request->file('photos', []) as $file) {
            $photos[] = $converter->store($file, 'local', 'quote-photos/'.now()->format('Y/m'));
        }

        $quote = QuoteRequest::query()->create($data + [
            'photos' => $photos,
            'kvkk_accepted' => true,
            'status' => QuoteRequest::STATUS_NEW,
            'source' => $request->input('source', 'form') === 'contact' ? 'contact' : 'form',
            'page_url' => $this->safePageUrl($request->input('page_url')) ?? $this->safePageUrl($request->headers->get('referer')),
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
        ]);

        $this->notify($quote);

        return redirect()->route('quote.thanks')
            ->with('quote_sent', true)
            ->with('lead', $this->leadEvent($quote, $request->input('page_type')));
    }

    /**
     * Teşekkür sayfasında Google Analytics'e gidecek "generate_lead" olayının parametreleri.
     * Flash oturumda taşınır: olay talep başına YALNIZ BİR KEZ gönderilir, sayfa yenilenirse
     * tekrar gönderilmez. Kişisel veri (ad, telefon, e-posta, serbest metin) KESİNLİKLE
     * eklenmez; yalnız hizmet ve formun doldurulduğu sayfa.
     *
     * @return array<string, string>
     */
    protected function leadEvent(QuoteRequest $quote, mixed $pageType): array
    {
        $path = $quote->page_url ? parse_url($quote->page_url, PHP_URL_PATH) : null;
        $pageType = is_string($pageType) && preg_match('/^[a-z_]{1,30}$/', $pageType) ? $pageType : null;

        return array_filter([
            'form' => $quote->source === 'contact' ? 'contact' : 'quote',
            'service' => $quote->service?->slug,
            'page_path' => is_string($path) && preg_match('#^/[A-Za-z0-9/_.%-]{0,200}$#', $path) ? $path : null,
            'page_type' => $pageType,
        ], fn ($value) => filled($value));
    }

    public function thanks(): View
    {
        return view('quote.thanks', [
            'metaTitle' => 'Talebiniz Alındı',
            'metaDescription' => 'Teklif talebiniz bize ulaştı. En kısa sürede sizinle iletişime geçeceğiz.',
            'canonical' => route('quote.thanks'),
            'robots' => 'noindex, follow',
        ]);
    }

    /**
     * page_url yönetim panelinde tıklanabilir bağlantı olarak gösterilir; bu yüzden yalnızca
     * bu siteye ait http(s) adresleri kabul edilir (javascript: vb. şemalar reddedilir).
     */
    protected function safePageUrl(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '' || strlen($url) > 255) {
            return null;
        }

        $parts = parse_url($url);

        if (! is_array($parts) || ! in_array($parts['scheme'] ?? '', ['http', 'https'], true)) {
            return null;
        }

        return ($parts['host'] ?? null) === request()->getHost() ? $url : null;
    }

    protected function notify(QuoteRequest $quote): void
    {
        $email = setting('notification_email');

        if (blank($email)) {
            return;
        }

        try {
            Mail::to($email)->send(new QuoteRequestReceived($quote));
        } catch (\Throwable $e) {
            Log::warning('Teklif talebi e-postası gönderilemedi: '.$e->getMessage(), ['quote_id' => $quote->getKey()]);
        }
    }
}
