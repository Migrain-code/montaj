<?php

namespace App\Http\Controllers;

use App\Models\QuoteRequest;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Teklif formundan yüklenen fotoğraflar özel diskte tutulur; yalnızca giriş yapmış yöneticiler görebilir.
 */
class AdminFileController extends Controller
{
    public function quotePhoto(QuoteRequest $quoteRequest, int $index): StreamedResponse
    {
        $path = $quoteRequest->photos[$index] ?? null;

        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path);
    }
}
