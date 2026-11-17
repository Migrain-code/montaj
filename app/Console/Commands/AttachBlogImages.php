<?php

namespace App\Console\Commands;

use App\Models\Blog;
use App\Services\Media\WebpConverter;
use Illuminate\Console\Command;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Toplu blog kapak görseli bağlama.
 *
 * Sunucuda storage/app/public/blog-yukle/ klasörüne {slug}.jpg|jpeg|png|webp adlı dosyalar
 * yüklenir (hosting dosya yöneticisiyle). Komut her dosyayı aynı slug'lı yazıya kapak olarak
 * bağlar: WebP'ye çevirip blog/ altına yazar, görsel ALT metni boşsa yazı başlığını koyar,
 * kaynak dosyayı siler. Görseli olan yazıya --force olmadan dokunmaz.
 *
 *   php artisan blog:attach-images
 */
class AttachBlogImages extends Command
{
    public const INBOX = 'blog-yukle';

    private const EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    protected $signature = 'blog:attach-images
                            {--force : Görseli olan yazının kapağını da değiştir}
                            {--no-score : Sonra SEO skorlarını yeniden hesaplama}';

    protected $description = 'blog-yukle/ klasöründeki {slug} adlı görselleri blog yazılarına kapak olarak bağlar';

    public function handle(WebpConverter $converter): int
    {
        $disk = Storage::disk('public');
        $files = collect($disk->files(self::INBOX))
            ->filter(fn (string $path) => in_array(Str::lower(pathinfo($path, PATHINFO_EXTENSION)), self::EXTENSIONS, true))
            ->values();

        if ($files->isEmpty()) {
            $this->warn('storage/app/public/'.self::INBOX.'/ klasöründe görsel yok.');

            return self::SUCCESS;
        }

        $attached = 0;

        foreach ($files as $path) {
            $slug = pathinfo($path, PATHINFO_FILENAME);
            $blog = Blog::query()->where('slug', $slug)->first();

            if (! $blog) {
                $this->line("  ? {$slug}: bu adreste yazı yok, atlandı");

                continue;
            }

            if (filled($blog->image) && ! $this->option('force')) {
                $this->line("  - {$slug}: zaten görseli var, atlandı (--force ile değiştirilir)");

                continue;
            }

            $upload = new UploadedFile($disk->path($path), basename($path), null, null, true);
            $stored = $converter->store($upload, 'public', 'blog', 'public');

            $blog->image = $stored;

            if (blank($blog->image_alt)) {
                $blog->image_alt = Str::limit($blog->title, 200, '');
            }

            $blog->save();
            $disk->delete($path);
            $attached++;
            $this->line("  ✓ {$slug} → {$stored}");
        }

        $this->info("{$attached} yazıya görsel bağlandı.");

        if ($attached > 0 && ! $this->option('no-score')) {
            $this->call('seo:score');
        }

        return self::SUCCESS;
    }
}
