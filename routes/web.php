<?php

use App\Http\Controllers\AdminFileController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\GalleryController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\RegionController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('/hizmetler', [ServiceController::class, 'index'])->name('services.index');
Route::get('/bolgeler', [RegionController::class, 'index'])->name('regions.index');
Route::get('/galeri', [GalleryController::class, 'index'])->name('gallery.index');
Route::get('/hakkimizda', [PageController::class, 'about'])->name('about');
Route::get('/sss', [PageController::class, 'faq'])->name('faq');
Route::get('/iletisim', [ContactController::class, 'index'])->name('contact');

Route::get('/teklif-al', [QuoteController::class, 'create'])->name('quote.create');
Route::post('/teklif-al', [QuoteController::class, 'store'])->middleware('throttle:quote')->name('quote.store');
Route::get('/teklif-al/tesekkurler', [QuoteController::class, 'thanks'])->name('quote.thanks');

Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

// robots.txt AI ajanlarını AÇIKÇA karşılar ve llms.txt adresini duyurur (spec §3.9).
Route::get('/robots.txt', function () {
    $lines = ['User-agent: *', 'Allow: /'];

    foreach (['/admin', '/admin-files/', '/teklif-al/tesekkurler'] as $disallow) {
        $lines[] = 'Disallow: '.$disallow;
    }

    $lines[] = '';

    foreach (array_keys((array) config('seo.ai_bots', [])) as $bot) {
        $lines[] = 'User-agent: '.$bot;
        $lines[] = 'Allow: /';
        $lines[] = '';
    }

    $lines[] = '# Yapay zeka ajanları için yapılandırılmış özet:';
    $lines[] = '# '.url('/llms.txt');
    $lines[] = '';
    $lines[] = 'Sitemap: '.route('sitemap');
    $lines[] = '';

    return response(implode("\n", $lines), 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
})->name('robots');

// Yönetici paneline giriş yapmış kullanıcılar için özel dosyalar (teklif fotoğrafları)
Route::get('/admin-files/teklif/{quoteRequest}/{index}', [AdminFileController::class, 'quotePhoto'])
    ->whereNumber('index')
    ->middleware('auth')
    ->name('admin.quote-photo');

// Blog rotaları catch-all'dan ÖNCE gelmeli: /{slug} deseni "/blog" adresini de yakalar.
Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
Route::get('/blog/kategori/{category:slug}', [BlogController::class, 'category'])->name('blog.category');
Route::get('/blog/{slug}', [BlogController::class, 'show'])
    ->where('slug', '[a-z0-9\-]+')
    ->name('blog.show');

// Kök dizin slug'ları: /hizmet-slug, /il-slug, /sayfa-slug ve /il-slug/ilce-slug
Route::get('/{slug}', [PageController::class, 'show'])
    ->where('slug', '[a-z0-9\-]+')
    ->name('slug.show');

Route::get('/{province:slug}/{district}', [RegionController::class, 'district'])
    ->where(['province' => '[a-z0-9\-]+', 'district' => '[a-z0-9\-]+'])
    ->name('regions.district');
