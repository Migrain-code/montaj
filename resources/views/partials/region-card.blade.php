<div class="region-card">
    <div class="head">
        <span class="icon"><i class="fa-solid fa-map-location-dot"></i></span>
        <div>
            <h3><a href="{{ $province->url }}">{{ $province->name }}</a></h3>
            <small>{{ $province->activeDistricts->count() }} ilçede mobilya montajı</small>
        </div>
    </div>
    <div class="chips">
        @foreach ($province->activeDistricts as $district)
            <a href="{{ url('/'.$province->slug.'/'.$district->slug) }}">{{ $district->name }}</a>
        @endforeach
    </div>
    <a class="btn-link-arrow" href="{{ $province->url }}">{{ $province->name }} montaj hizmeti <i class="fa-solid fa-arrow-right"></i></a>
</div>
