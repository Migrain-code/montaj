<section class="page-banner" style="background-image:url('{{ media_url(setting('banner_image'), asset('images/placeholder.svg')) }}')">
    <div class="container">
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                @foreach ($breadcrumbs as $crumb)
                    @if ($loop->last)
                        <li class="breadcrumb-item active" aria-current="page">{{ $crumb['name'] }}</li>
                    @else
                        <li class="breadcrumb-item"><a href="{{ $crumb['url'] }}">{{ $crumb['name'] }}</a></li>
                    @endif
                @endforeach
            </ol>
        </nav>
        <h1>{{ $title }}</h1>
        @if (! empty($subtitle))
            <p class="lead">{{ $subtitle }}</p>
        @endif
    </div>
</section>
