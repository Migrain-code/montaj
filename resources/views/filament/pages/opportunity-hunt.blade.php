<x-filament-panels::page>
    @if (! $gscReady)
        <x-filament::section>
            <div class="text-sm text-gray-600 dark:text-gray-400">
                <strong>Search Console bağlı değil.</strong>
                Sıralar çekilemez; yalnız odak bölge kelimeleri takibe alınır.
                <a class="font-medium text-warning-500" href="{{ \App\Filament\Pages\SeoAiSettings::getUrl() }}">SEO &amp; AI Ayarları → Search Console</a>
                bölümünden servis hesabı anahtarını yükleyin.
            </div>
        </x-filament::section>
    @endif

    <x-filament::section>
        <div class="text-sm text-gray-600 dark:text-gray-400 space-y-1">
            <p>
                Search Console'da görünen <strong>bölge + montaj</strong> aramaları burada takip edilir ve her biri
                o bölgenin sayfasına bağlanır. Amaç her kelimeyi <strong>ilk {{ $target }} sıraya</strong> taşımak.
                İlk {{ $target }}'e giren kelimeye <strong>hiçbir işlem yapılmaz</strong>; sıra düşerse kendiliğinden yeniden ele alınır.
            </p>
            <p>
                Otomatik iyileştirme
                <x-filament::badge :color="$autopilot ? 'success' : 'gray'" class="inline-block">{{ $autopilot ? 'açık' : 'kapalı' }}</x-filament::badge>
                · her sabah 05:15 · günde en fazla <strong>{{ $dailyLimit }}</strong> kelime ·
                aynı kelimeye en erken <strong>{{ $cooldown }} günde</strong> bir.
                @unless ($aiReady)
                    <span class="text-warning-500">AI anahtarı tanımlı değil: başlık, bölüm ve soru-cevap adımları çalışmaz.</span>
                @endunless
            </p>
        </div>
    </x-filament::section>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        @foreach ($focus as $region)
            <x-filament::section>
                <div class="text-xs font-medium text-gray-500">★ {{ $region['name'] }}</div>
                <div class="mt-1 text-2xl font-bold tabular-nums text-gray-900 dark:text-white">
                    {{ $region['best'] !== null ? '#'.round($region['best'], 1) : '—' }}
                </div>
                <div class="mt-1 text-xs text-gray-500 truncate">
                    @if ($region['best_keyword'])
                        en iyi: {{ $region['best_keyword'] }}
                    @else
                        henüz sıra verisi yok
                    @endif
                </div>
                <div class="mt-2 text-xs text-gray-500">{{ $region['won'] }}/{{ $region['count'] }} kelime hedefte</div>
            </x-filament::section>
        @endforeach

        <x-filament::section>
            <div class="text-xs font-medium text-gray-500">Tüm takip</div>
            <div class="mt-1 text-2xl font-bold tabular-nums text-gray-900 dark:text-white">{{ $stats['won'] }}/{{ $stats['total'] }}</div>
            <div class="mt-1 text-xs text-gray-500">hedefte</div>
            <div class="mt-2 text-xs text-gray-500">
                4–10: {{ $stats['near'] }} · 11+: {{ $stats['far'] }} · ölçülmedi: {{ $stats['unmeasured'] }}
            </div>
        </x-filament::section>
    </div>

    {{ $this->table }}

    <x-filament::section>
        <x-slot name="heading">İşlem kaydı</x-slot>
        <x-slot name="description">Sayfalara yapılan her değişiklik burada; yazma işlemleri geri alınabilir.</x-slot>

        @if ($actions->isEmpty())
            <p class="text-sm text-gray-500">Henüz işlem yapılmadı.</p>
        @else
            <div class="divide-y divide-gray-100 dark:divide-gray-800">
                @foreach ($actions as $action)
                    <div class="flex items-start justify-between gap-4 py-2">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-3">
                                <x-filament::badge :color="match ($action->status) { 'done' => 'success', 'failed' => 'danger', default => 'gray' }">
                                    {{ match ($action->status) { 'done' => 'yapıldı', 'failed' => 'hata', 'reverted' => 'geri alındı', default => $action->status } }}
                                </x-filament::badge>
                                <span class="text-sm font-medium">{{ $action->label }}</span>
                                <span class="text-xs text-gray-500">{{ $action->keyword?->keyword }}</span>
                            </div>
                            <div class="mt-1 text-xs text-gray-500 truncate">
                                {{ $action->created_at?->format('d.m.Y H:i') }}
                                @if ($action->rank_before !== null) · o an #{{ round($action->rank_before, 1) }} @endif
                                · {{ $action->message }}
                            </div>
                        </div>

                        @if ($canEdit && $action->isRevertible())
                            <div class="shrink-0">
                                {{ ($this->revertAction)(['id' => $action->id]) }}
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>

    <x-filament-actions::modals />
</x-filament-panels::page>
