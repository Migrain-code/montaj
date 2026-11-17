<x-filament-panels::page>
    {{-- tavsiyemiz "Kelime Sırası" düzeni: durum satırı, otomasyon kartı, beş sayaç kutusu. --}}
    <div class="flex flex-wrap items-center gap-3 text-sm text-gray-500 dark:text-gray-400">
        <x-filament::badge :color="$gscReady ? 'success' : 'gray'" icon="heroicon-m-signal">
            {{ $gscReady ? 'Google bağlı' : 'Google bağlı değil' }}
        </x-filament::badge>
        <span>Son güncelleme: {{ $lastRanked ? \Illuminate\Support\Carbon::parse($lastRanked)->format('d.m.Y H:i') : '—' }}</span>
        @unless ($gscReady)
            <span>· Search Console servis hesabı <a class="font-medium text-primary-600 dark:text-primary-400" href="{{ \App\Filament\Pages\SeoAiSettings::getUrl() }}">Ayarlar</a> sekmesinden yüklenir; o zamana kadar yalnız odak bölge kelimeleri takip edilir.</span>
        @endunless
    </div>

    <x-filament::section>
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="min-w-0 space-y-1.5 text-sm text-gray-600 dark:text-gray-400">
                <div class="flex items-center gap-2">
                    <span class="text-base font-semibold text-gray-950 dark:text-white">Otomasyon</span>
                    <x-filament::badge :color="$autopilot ? 'success' : 'gray'">{{ $autopilot ? 'Açık' : 'Kapalı' }}</x-filament::badge>
                </div>
                <p>
                    Her sabah 05:15'te sıralar Search Console'dan çekilir; ilk <strong>{{ $target }}</strong> sırada olmayan
                    en fazla <strong>{{ $dailyLimit }}</strong> kelimenin sayfası iyileştirilir. Aynı kelimeye en erken <strong>{{ $cooldown }} günde</strong> bir dokunulur;
                    ilk {{ $target }}'e giren kelimeye hiçbir işlem yapılmaz.
                </p>
                <div class="flex items-center gap-3">
                    <span>Bugün işlenen: <strong>{{ $processedToday }} / {{ $dailyLimit }}</strong></span>
                    <div class="h-1.5 w-40 overflow-hidden rounded-full bg-gray-200 dark:bg-white/10">
                        <div class="h-full rounded-full bg-primary-500" style="width: {{ min(100, $dailyLimit ? round($processedToday / $dailyLimit * 100) : 0) }}%"></div>
                    </div>
                </div>
                @unless ($aiReady)
                    <p class="text-warning-600 dark:text-warning-400">AI anahtarı tanımlı değil: başlık, bölüm ve soru-cevap adımları çalışmaz.</p>
                @endunless
            </div>
            @if ($canEdit)
                <div class="flex shrink-0 flex-wrap items-center gap-2">
                    {{ $this->autopilotAction }}
                    {{ $this->runNowAction }}
                    <x-filament::icon-button icon="heroicon-o-cog-6-tooth" color="gray" tag="a"
                        :href="\App\Filament\Pages\SeoAiSettings::getUrl()" label="Ayarlar" tooltip="Otomasyon ayarları" />
                </div>
            @endif
        </div>
    </x-filament::section>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        @php
            $tiles = [
                ['label' => 'Toplam kelime', 'value' => number_format($stats['total'], 0, ',', '.'), 'sub' => $stats['unmeasured'] ? $stats['unmeasured'].' kelime henüz ölçülmedi' : null],
                ['label' => 'Ortalama sıra', 'value' => $stats['avg'] !== null ? number_format($stats['avg'], 1, ',', '.') : '—', 'sub' => null],
                ['label' => "İlk 10'da", 'value' => number_format($stats['top10'], 0, ',', '.'), 'sub' => "İlk 3'te: ".$stats['top3'], 'class' => 'text-success-600 dark:text-success-400'],
            ];
        @endphp
        @foreach ($tiles as $tile)
            <x-filament::section>
                <div class="text-center">
                    <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $tile['label'] }}</div>
                    <div class="mt-1 text-3xl font-bold tabular-nums {{ $tile['class'] ?? 'text-gray-950 dark:text-white' }}">{{ $tile['value'] }}</div>
                    @if ($tile['sub'])<div class="mt-1 text-xs text-gray-500">{{ $tile['sub'] }}</div>@endif
                </div>
            </x-filament::section>
        @endforeach
        <x-filament::section>
            <div class="text-center">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Hareket</div>
                <div class="mt-1 text-3xl font-bold tabular-nums">
                    <span class="text-success-600 dark:text-success-400">↑{{ $stats['up'] }}</span>
                    <span class="text-gray-400">/</span>
                    <span class="text-danger-600 dark:text-danger-400">↓{{ $stats['down'] }}</span>
                </div>
            </div>
        </x-filament::section>
        <x-filament::section>
            <div class="text-center">
                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Google tıklama</div>
                <div class="mt-1 text-3xl font-bold tabular-nums text-gray-950 dark:text-white">{{ number_format($stats['clicks'], 0, ',', '.') }}</div>
                <div class="mt-1 text-xs text-gray-500">{{ number_format($stats['impressions'], 0, ',', '.') }} gösterim</div>
            </div>
        </x-filament::section>
    </div>

    @if (count($focus))
        <div class="flex flex-wrap gap-2">
            @foreach ($focus as $region)
                <x-filament::badge color="gray">
                    ★ {{ $region['name'] }}: {{ $region['best'] !== null ? '#'.round($region['best'], 1) : '—' }} · {{ $region['won'] }}/{{ $region['count'] }} hedefte
                </x-filament::badge>
            @endforeach
        </div>
    @endif

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
