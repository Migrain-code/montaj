{{-- Tamamlanan iş kartı: liste, ilçe, marka ve hizmet sayfalarında aynı görünüm. --}}
<article class="service-card compact h-100">
    <div class="media">
        <a class="thumb" href="{{ $project->url }}" tabindex="-1" aria-hidden="true">
            <img src="{{ $project->cover_url }}" alt="{{ $project->cover()['alt'] ?? $project->title }}" loading="lazy" width="600" height="450" style="aspect-ratio:4/3;object-fit:cover">
        </a>
    </div>
    <div class="body">
        <span class="subtitle mb-2 d-inline-block">
            {{ collect([$project->district?->name, $project->brand?->name, $project->completed_label])->filter()->implode(' · ') ?: 'Tamamlanan iş' }}
        </span>
        <h3><a href="{{ $project->url }}">{{ $project->title }}</a></h3>
        @if ($project->summary)
            <p>{{ $project->summary }}</p>
        @endif
        <a class="btn-link-arrow mt-auto" href="{{ $project->url }}">İşi İncele <i class="fa-solid fa-arrow-right"></i></a>
    </div>
</article>
