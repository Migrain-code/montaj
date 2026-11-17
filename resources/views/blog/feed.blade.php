{!! '<'.'?xml version="1.0" encoding="UTF-8"?'.'>' !!}
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom">
    <channel>
        <title>{{ site_name() }} — Blog</title>
        <link>{{ route('blog.index') }}</link>
        <description>Mobilya montajı, gardırop kurulumu ve taşınma hakkında pratik bilgiler.</description>
        <language>tr-TR</language>
        <atom:link href="{{ route('blog.feed') }}" rel="self" type="application/rss+xml" />
        @if ($posts->isNotEmpty())
            <lastBuildDate>{{ optional($posts->first()->publish_at ?? $posts->first()->created_at)->toRfc2822String() }}</lastBuildDate>
        @endif
        @foreach ($posts as $post)
            <item>
                <title>{{ $post->title }}</title>
                <link>{{ $post->url }}</link>
                <guid isPermaLink="true">{{ $post->url }}</guid>
                <pubDate>{{ optional($post->publish_at ?? $post->created_at)->toRfc2822String() }}</pubDate>
                @if ($post->category)<category>{{ $post->category->name }}</category>@endif
                <description>{{ $post->summary }}</description>
            </item>
        @endforeach
    </channel>
</rss>
