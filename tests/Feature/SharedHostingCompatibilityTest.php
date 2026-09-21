<?php

namespace Tests\Feature;

use Symfony\Component\Finder\Finder;
use Tests\TestCase;

/**
 * Paylaşımlı hosting uyumluluğu.
 *
 * Hostingler güvenlik için bazı PHP fonksiyonlarını kapatır (disable_functions).
 * PHP 8'de kapalı bir fonksiyonu çağırmak "Call to undefined function" hatası
 * fırlatır ve başındaki "@" bunu BASTIRMAZ; sayfa çöker. Yerelde her şey açık
 * olduğu için bu hata ancak canlıda görülür — escapeshellarg() bir kez panel
 * sayfasını böyle çökertti. Bu test kodu tarayarak aynı hatanın geri gelmesini önler.
 */
class SharedHostingCompatibilityTest extends TestCase
{
    /** Uygulama kodunda HİÇ kullanılmamalı: canlıda sık kapatılır ve kaçınılabilir. */
    private const BANNED = [
        'escapeshellarg', 'escapeshellcmd', 'exec', 'shell_exec', 'system',
        'passthru', 'popen', 'highlight_file', 'show_source',
    ];

    /** Kullanılabilir ama aynı dosyada function_exists() ile korunmalı. */
    private const GUARDED = [
        'set_time_limit', 'ignore_user_abort', 'proc_open', 'symlink',
        'exif_read_data', 'putenv', 'php_uname', 'disk_free_space',
    ];

    /** @return array<string, string> dosya yolu => içerik */
    private function sources(): array
    {
        $out = [];

        foreach ((new Finder)->files()->in([app_path(), base_path('routes'), resource_path('views')])->name(['*.php']) as $file) {
            $out[$file->getRelativePathname()] = $file->getContents();
        }

        return $out;
    }

    private function calls(string $code, string $function): bool
    {
        // "->exec(" veya "::system(" gibi metot çağrıları ve "$exec(" gibi değişkenler sayılmaz.
        return preg_match('/(?<![\w$>:\\\\])'.preg_quote($function, '/').'\s*\(/', $code) === 1;
    }

    public function test_commonly_disabled_functions_are_not_called(): void
    {
        foreach ($this->sources() as $path => $code) {
            foreach (self::BANNED as $function) {
                $this->assertFalse(
                    $this->calls($code, $function),
                    "{$path} içinde {$function}() çağrılıyor. Paylaşımlı hostingde kapalıysa sayfa çöker."
                );
            }
        }
    }

    public function test_optional_functions_are_guarded(): void
    {
        foreach ($this->sources() as $path => $code) {
            foreach (self::GUARDED as $function) {
                if (! $this->calls($code, $function)) {
                    continue;
                }

                $this->assertStringContainsString(
                    "function_exists('{$function}')",
                    $code,
                    "{$path} içinde {$function}() function_exists() ile korunmadan çağrılıyor."
                );
            }
        }
    }
}
