@if ($blocker)
    <div class="rounded-lg border border-warning-400/60 bg-warning-50/50 p-3 text-sm dark:bg-warning-950/20">
        {{ $blocker }}
    </div>
@endif

<div class="divide-y divide-gray-100 dark:divide-gray-800">
    @foreach ($steps as $step => $status)
        <div class="flex items-center justify-between gap-4 py-2">
            <div class="min-w-0">
                <div class="text-sm font-medium">{{ $labels[$step] ?? $step }}</div>
                <div class="text-xs text-gray-500">{{ $status['reason'] }}</div>
            </div>
            <x-filament::badge :color="match ($status['state']) { 'todo' => 'warning', 'done' => 'success', default => 'gray' }">
                {{ match ($status['state']) { 'todo' => 'yapılacak', 'done' => 'tamam', default => 'yapılamaz' } }}
            </x-filament::badge>
        </div>
    @endforeach
</div>
