<?php

namespace App\Services\Seo;

use App\Support\AutomationLog;
use App\Support\SeoConfig;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * IndexNow: yeni/güncellenen adresi Bing, Yandex, Seznam, Naver… arama motorlarına
 * ANINDA bildirir (tek uç nokta hepsine dağıtır). Google IndexNow'u kullanmaz; Google
 * için site haritası + Search Console (ve varsa Indexing API) geçerlidir.
 *
 * Anahtar panel ayarında (SEO Merkezi → Ayarlar → IndexNow) ya da .env'de (INDEXNOW_KEY,
 * 8–128 hex karakter). Doğrulama dosyası ayrıca
 * yüklenmez: /{anahtar}.txt rotası anahtarı döndürür.
 * Anahtar yoksa servis sessizce devre dışıdır; site çalışmaya devam eder.
 */
class IndexNow
{
    public const ENDPOINT = 'https://api.indexnow.org/indexnow';

    /**
     * Ana uç nokta reddederse denenen yedek. Katılımcı motorlar bildirimleri birbiriyle
     * paylaşır; Bing bir siteyi geçmişteki erişim hatası yüzünden bir süre "yetkisiz"
     * (403) sayabiliyor, bu sırada Yandex aynı anahtarı kabul edip dağıtıyor.
     */
    public const FALLBACK_ENDPOINT = 'https://yandex.com/indexnow';

    /** IndexNow tek istekte en fazla 10.000 adres kabul eder. */
    public const BATCH = 10000;

    /** Anahtar: panel ayarı `seo_indexnow_key` → .env INDEXNOW_KEY. */
    public function key(): ?string
    {
        $key = trim((string) SeoConfig::string('indexnow_key', (string) config('seo.indexnow.key')));

        return preg_match('/^[a-f0-9]{8,128}$/i', $key) ? $key : null;
    }

    /** Son gönderimde neden hiçbir adres kabul edilmediği (komut çıktısında gösterilir). */
    public ?string $lastError = null;

    public function enabled(): bool
    {
        return $this->key() !== null;
    }

    /**
     * @param  array<int, string>  $urls
     * @return int bildirilen adres sayısı (devre dışıysa 0)
     */
    public function submit(array $urls): int
    {
        $key = $this->key();
        // Host ve anahtar adresi APP_URL'den: url() şemayı istekten alır ve proxy
        // arkasında "http://" üretebilir; IndexNow keyLocation'ı birebir doğrular.
        $root = rtrim((string) config('app.url'), '/') ?: rtrim(url('/'), '/');
        $host = parse_url($root, PHP_URL_HOST);

        // Yalnız bu sitenin adresleri: IndexNow farklı host içeren listeyi tümden reddeder.
        $this->lastError = null;
        $total = count($urls);
        $urls = array_values(array_unique(array_filter($urls, fn ($u) => parse_url($u, PHP_URL_HOST) === $host)));

        if ($urls === [] && $total > 0) {
            $this->lastError = 'Adreslerin hiçbiri APP_URL alan adıyla ('.$host.') eşleşmiyor. APP_URL değerini ve yapılandırma önbelleğini kontrol edin.';
        }

        if (! $key || $urls === [] || in_array($host, ['localhost', '127.0.0.1'], true)) {
            return 0;
        }

        $sent = 0;

        foreach (array_chunk($urls, self::BATCH) as $chunk) {
            $payload = [
                'host' => $host,
                'key' => $key,
                'keyLocation' => $root.'/'.$key.'.txt',
                'urlList' => $chunk,
            ];

            foreach ([self::ENDPOINT, self::FALLBACK_ENDPOINT] as $endpoint) {
                try {
                    $response = Http::timeout(15)->acceptJson()->post($endpoint, $payload);

                    // 200 = kabul, 202 = alındı (anahtar doğrulaması bekliyor).
                    if (in_array($response->status(), [200, 202], true)) {
                        $sent += count($chunk);
                        $this->lastError = null;

                        break;
                    }

                    $error = 'IndexNow '.$response->status().' döndürdü ('.self::explain($response->status()).') '.mb_substr(trim($response->body()), 0, 200)
                        .' · anahtar dosyası: '.$root.'/'.$key.'.txt';
                    $this->lastError = $this->lastError ? $this->lastError.' | yedek ('.parse_url($endpoint, PHP_URL_HOST).'): '.$error : $error;
                    AutomationLog::error('seo.indexnow', 'IndexNow reddetti', ['endpoint' => $endpoint, 'status' => $response->status(), 'body' => mb_substr($response->body(), 0, 300)]);
                } catch (Throwable $e) {
                    $this->lastError = 'Bağlantı hatası: '.$e->getMessage();
                    AutomationLog::error('seo.indexnow', $e->getMessage(), ['endpoint' => $endpoint]);
                }
            }
        }

        if ($sent > 0) {
            AutomationLog::info('seo.indexnow', ['submitted' => $sent]);
        }

        return $sent;
    }

    private static function explain(int $status): string
    {
        return match ($status) {
            400 => 'istek biçimi hatalı',
            403 => 'anahtar doğrulanamadı: anahtar dosyası bulunamadı ya da içeriği anahtarla aynı değil',
            422 => 'adresler bu alan adına ait değil ya da anahtar dosyasıyla eşleşmiyor',
            429 => 'çok sık gönderim, daha sonra tekrar deneyin',
            default => 'beklenmeyen yanıt',
        };
    }
}
