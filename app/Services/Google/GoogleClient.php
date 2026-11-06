<?php

namespace App\Services\Google;

use App\Support\AutomationLog;
use App\Support\SeoConfig;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Google servis hesabı sarmalayıcı (spec §8.1).
 *
 * Kimlik yoksa istisna fırlatmaz — isConfigured() false döner ve GSC aksiyonları
 * panelde gizlenir, sistem çökmez (spec §10.5).
 *
 * Kimlik JSON'u ASLA log'lanmaz (spec §10.2).
 */
class GoogleClient
{
    public const SCOPE_WEBMASTERS = 'https://www.googleapis.com/auth/webmasters.readonly';

    public const SCOPE_INDEXING = 'https://www.googleapis.com/auth/indexing';

    /** Yalnız kapalı bir API'yi açmak için (Service Usage); başka yerde kullanılmaz. */
    public const SCOPE_CLOUD = 'https://www.googleapis.com/auth/cloud-platform';

    public function isConfigured(): bool
    {
        return filled($this->credentialsPath()) && is_readable((string) $this->credentialsPath()) && filled($this->property());
    }

    public function property(): ?string
    {
        $value = SeoConfig::string('google_property', config('seo.google.property'));

        return filled($value) ? trim((string) $value) : null;
    }

    public function credentialsPath(): ?string
    {
        $path = SeoConfig::string('google_credentials', config('seo.google.credentials'));

        if (blank($path)) {
            return null;
        }

        // Göreli yol "local" diskine göre çözülür. Yolu elle kurmak, disk kökü
        // değiştiğinde (veya testte sahte disk kullanıldığında) sessizce kırılır.
        return str_starts_with($path, '/')
            ? $path
            : Storage::disk('local')->path(ltrim($path, '/'));
    }

    /** Servis hesabının e-postası — GSC'ye kullanıcı olarak eklenecek adres (spec §7.9). */
    public function serviceAccountEmail(): ?string
    {
        try {
            $json = json_decode((string) file_get_contents((string) $this->credentialsPath()), true);

            return $json['client_email'] ?? null;
        } catch (Throwable) {
            return null;
        }
    }

    /** Servis hesabının bağlı olduğu Google Cloud projesi. */
    public function projectId(): ?string
    {
        try {
            $json = json_decode((string) file_get_contents((string) $this->credentialsPath()), true);

            return $json['project_id'] ?? null;
        } catch (Throwable) {
            return null;
        }
    }

    public function token(string $scope = self::SCOPE_WEBMASTERS): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Google kimliği yapılandırılmamış (servis hesabı JSON veya GSC mülkü eksik).');
        }

        $cacheKey = 'google.token.'.md5($scope.'|'.$this->credentialsPath());

        return Cache::remember($cacheKey, now()->addMinutes(45), function () use ($scope) {
            $credentials = new ServiceAccountCredentials($scope, $this->credentialsPath());
            $token = $credentials->fetchAuthToken();

            if (empty($token['access_token'])) {
                throw new RuntimeException('Google erişim anahtarı alınamadı.');
            }

            return (string) $token['access_token'];
        });
    }

    public function request(string $scope = self::SCOPE_WEBMASTERS): PendingRequest
    {
        return Http::withToken($this->token($scope))->timeout(60)->acceptJson();
    }

    /**
     * 403 hatasını okunabilir hale getirir (spec §7.9): üç farklı sebebi olabilir.
     */
    public function explainPermissionError(int $status, ?string $message = null): string
    {
        if ($status !== 403) {
            return 'Google API hatası (HTTP '.$status.')'.($message ? ': '.$message : '');
        }

        /*
         * 403'ün her zaman yetki sorunu olmadığı canlıda görüldü: Google Cloud projesinde
         * Indexing API etkin değildi, mesaj ise "sahip değilsiniz" diyordu ve Sahip yetkisi
         * verilmesine rağmen hata sürdü. API kapalıysa bunu açıkça söyle.
         */
        $message = (string) $message;

        if (str_contains($message, 'has not been used') || str_contains($message, 'is disabled')) {
            preg_match('#https://console\.developers\.google\.com/\S+#', $message, $link);

            return 'Google Cloud projesinde bu API etkin değil (403). Google Cloud Console\'da API\'yi etkinleştirin'
                .(isset($link[0]) ? ': '.rtrim($link[0], '.') : '').' — etkinleştirdikten sonra birkaç dakika bekleyip yeniden deneyin.';
        }

        $email = $this->serviceAccountEmail() ?: 'servis hesabı e-postası okunamadı';

        return implode(' ', array_filter([
            // Google'ın kendi açıklaması EN BAŞTA: hata 300 karakterlik sütuna kesilerek yazılır
            // ve aşağıdaki tahminler yanlışsa asıl sebep budur.
            $message !== '' ? 'Google erişimi reddetti (403): "'.mb_substr($message, 0, 160).'".' : 'Google erişimi reddetti (403).',
            'Olası sebepler:',
            "1) Servis hesabı ({$email}) Search Console mülküne kullanıcı olarak eklenmemiş.",
            '2) Mülk tipi uyuşmuyor: ayarlardaki "'.$this->property().'" değeri GSC\'deki mülkle BİREBİR aynı olmalı',
            '(Domain mülkü "sc-domain:ornek.com", URL öneki mülkü "https://ornek.com/" biçimindedir).',
            '3) İncelenen adres mülkün dışında (www / http farkı).',
            'URL Inspection için "Tam" yetki yeterlidir; Indexing API için "Sahip" gerekir ve sahiplik ayrı ekrandan verilir.',
        ]));
    }

    public function logUnavailable(string $task): void
    {
        AutomationLog::summary($task, ['configured' => false], reasons: ['not_configured']);
    }
}
