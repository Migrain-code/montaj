<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * www.alanadi → alanadi kalıcı (301) yönlendirmesi.
 *
 * Canlıda görüldü: www sürümü 200 dönüyor ve canonical etiketi de www'yi gösteriyordu
 * (url()->current() isteğin geldiği adı kullanır). Google her sayfayı iki ayrı sayfa
 * sayıyor, gösterim ve sıra ikiye bölünüyordu.
 *
 * Yalnız "www." + APP_URL'deki ad yönlendirilir; başka adlar (yerel, test, önizleme)
 * olduğu gibi kalır. Global middleware'dir: 404 ve panel adresleri de yönlenir.
 */
class CanonicalHost
{
    public function handle(Request $request, Closure $next): Response
    {
        $canonical = parse_url((string) config('app.url'));
        $host = strtolower((string) ($canonical['host'] ?? ''));

        if ($host !== '' && strtolower($request->getHost()) === 'www.'.$host) {
            $scheme = $canonical['scheme'] ?? 'https';
            $port = isset($canonical['port']) ? ':'.$canonical['port'] : '';

            return redirect()->away($scheme.'://'.$host.$port.$request->getRequestUri(), 301);
        }

        return $next($request);
    }
}
