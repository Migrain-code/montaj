@extends('layouts.app')

@section('content')
    @include('partials.page-banner', [
        'title' => 'İşlerimiz',
        'subtitle' => 'Tamamladığımız mobilya montajları: öncesi, sonrası ve kurulan ürünler.',
        'breadcrumbs' => [['name' => 'Ana Sayfa', 'url' => url('/')], ['name' => 'İşlerimiz', 'url' => route('projects.index')]],
    ])

    <section class="section">
        <div class="container">
            @if ($projects->isEmpty())
                <p class="text-center text-muted py-5">Tamamlanan işler yakında eklenecek.</p>
            @else
                <div class="row g-4">
                    @foreach ($projects as $project)
                        <div class="col-lg-4 col-md-6 reveal">
                            @include('partials.project-card', ['project' => $project])
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    @include('partials.cta-band')
@endsection
