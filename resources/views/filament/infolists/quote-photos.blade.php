@php
    $record = $getRecord();
    $photos = $record->photos ?? [];
@endphp

@if (empty($photos))
    <span class="text-sm text-gray-500 dark:text-gray-400">Fotoğraf eklenmemiş.</span>
@else
    <div class="flex flex-wrap gap-3">
        @foreach ($photos as $index => $photo)
            <a href="{{ route('admin.quote-photo', ['quoteRequest' => $record, 'index' => $index]) }}" target="_blank" rel="noopener" class="block">
                <img
                    src="{{ route('admin.quote-photo', ['quoteRequest' => $record, 'index' => $index]) }}"
                    alt="Talep fotoğrafı {{ $index + 1 }}"
                    class="h-32 w-32 rounded-lg object-cover ring-1 ring-gray-950/10 dark:ring-white/10"
                    loading="lazy"
                >
            </a>
        @endforeach
    </div>
@endif
