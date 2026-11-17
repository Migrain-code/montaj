<?php

namespace Tests\Feature;

use App\Console\Commands\AttachBlogImages;
use App\Models\Blog;
use App\Services\Seo\ContentScorer;
use App\Services\Seo\ScorableContent;
use App\Support\SeoIssue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BlogImageAttachTest extends TestCase
{
    use RefreshDatabase;

    public function test_uploaded_images_are_attached_to_blogs_by_slug(): void
    {
        Storage::fake('public');
        $disk = Storage::disk('public');
        $inbox = AttachBlogImages::INBOX;

        $empty = Blog::create(['title' => "Çorlu'da Baza Montajı", 'slug' => 'corlu-baza-montaji', 'body_html' => '<p>x</p>', 'status' => Blog::STATUS_PUBLISHED]);
        $hasImage = Blog::create(['title' => 'Var', 'slug' => 'gorseli-var', 'body_html' => '<p>x</p>', 'status' => Blog::STATUS_PUBLISHED, 'image' => 'blog/eski.webp']);

        $disk->put("$inbox/corlu-baza-montaji.jpg", UploadedFile::fake()->image('a.jpg', 1200, 800)->getContent());
        $disk->put("$inbox/gorseli-var.png", UploadedFile::fake()->image('b.png', 800, 600)->getContent());
        $disk->put("$inbox/olmayan-yazi.jpg", UploadedFile::fake()->image('c.jpg', 800, 600)->getContent());

        $this->artisan('blog:attach-images', ['--no-score' => true])->assertSuccessful();

        $empty->refresh();
        $this->assertNotNull($empty->image);
        $this->assertStringStartsWith('blog/corlu-baza-montaji', $empty->image);
        $disk->assertExists($empty->image);
        $this->assertSame("Çorlu'da Baza Montajı", $empty->image_alt);
        $disk->assertMissing("$inbox/corlu-baza-montaji.jpg");

        // Görseli olan yazıya --force olmadan dokunulmaz; bilinmeyen slug yerinde kalır.
        $this->assertSame('blog/eski.webp', $hasImage->refresh()->image);
        $disk->assertExists("$inbox/gorseli-var.png");
        $disk->assertExists("$inbox/olmayan-yazi.jpg");
    }

    public function test_decorative_empty_alt_and_apostrophes_are_not_missing_alt(): void
    {
        $scorer = app(ContentScorer::class);
        $make = fn (string $html) => new ScorableContent(contentType: 'blog', contentId: 1, title: 'T', bodyHtml: $html, slug: 't');

        $ok = $scorer->score($make('<p>metin</p><img src="a.webp" alt=""><img src="b.webp" alt="Çorlu\'da montaj">'));
        $this->assertNotContains(SeoIssue::IMAGE_ALT_MISSING, $ok['issues']);

        $bad = $scorer->score($make('<p>metin</p><img src="a.webp">'));
        $this->assertContains(SeoIssue::IMAGE_ALT_MISSING, $bad['issues']);
    }
}
