{{--
    İlçe, marka ve hizmet sayfalarındaki "yaptığımız işler" bölümü. İş yoksa hiç basılmaz:
    boş bir başlık "burada iş yapmıyoruz" demektir.
--}}
@if (isset($projects) && $projects->isNotEmpty())
    <h2 class="h3 mb-3">{{ $projectsTitle }}</h2>
    <div class="row g-3 mb-5">
        @foreach ($projects as $project)
            <div class="col-md-6">
                @include('partials.project-card', ['project' => $project])
            </div>
        @endforeach
    </div>
@endif
