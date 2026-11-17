<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tek kopya adres: www.alanadi → alanadi ve (https zorunluysa) http → https kalıcı (301).
 *
 * Canlıda görüldü: www sürümü 200 dönüyor ve canonical etiketi de www'yi gösteriyordu
 * (url()->current() isteğin geldiği adı kullanır). Google her sayfayı iki ayrı sayfa
 * sayıyor, gösterim ve sıra ikiye bölünüyordu. Aynı sorun http/https ikiliğinde de olur.
 *
 * Yalnız "www." + APP_URL'deki ad yönlendirilir; başka adlar (yerel, test, önizleme)
 * olduğu gibi kalır. https yönlendirmesi config('app.force_https') ile açılır (FORCE_HTTPS,
 * boşsa APP_URL https ise açık); localhost/127.0.0.1 hiçbir zaman yönlenmez. Hosting
 * önündeki vekil sunucu "X-Forwarded-Proto: https" gönderiyorsa istek güvenli sayılır
 * (döngü olmasın). Global middleware'dir: 404 ve panel adresleri de yönlenir.
 */
class CanonicalHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $canonical = parse_url((string) config('app.url'));
        $host = strtolower((string) ($canonical['host'] ?? ''));
        $requestHost = strtolower($request->getHost());

        if ($host !== '' && $requestHost === 'www.'.$host) {
            $scheme = config('app.force_https') ? 'https' : ($canonical['scheme'] ?? 'https');
            $port = isset($canonical['port']) ? ':'.$canonical['port'] : '';

            return redirect()->away($scheme.'://'.$host.$port.$request->getRequestUri(), 301);
        }

        if ($this->shouldForceHttps($request, $requestHost)) {
            return redirect()->away('https://'.$request->getHttpHost().$request->getRequestUri(), 301);
        }

        return $next($request);
    }

    private function shouldForceHttps(Request $request, string $requestHost): bool
    {
        if (! config('app.force_https') || $request->isSecure()
            || strtolower((string) $request->headers->get('X-Forwarded-Proto')) === 'https') {
            return false;
        }

        // Gövdeli istek (form gönderimi) 301 ile kaybolmasın; yalnız sayfa istekleri yönlenir.
        if (! in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
            return false;
        }

        return ! in_array($requestHost, ['localhost', '127.0.0.1', '::1'], true);
    }
}
