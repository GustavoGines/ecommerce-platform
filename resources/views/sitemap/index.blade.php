{!! '<'.'?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <!-- Páginas Principales -->
    <url>
        <loc>{{ route('home') }}</loc>
        <lastmod>{{ now()->tz('UTC')->toAtomString() }}</lastmod>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
    </url>
    <url>
        <loc>{{ route('login') }}</loc>
        <changefreq>monthly</changefreq>
        <priority>0.8</priority>
    </url>
    <url>
        <loc>{{ route('register') }}</loc>
        <changefreq>monthly</changefreq>
        <priority>0.8</priority>
    </url>
    <url>
        <loc>{{ route('checkout') }}</loc>
        <changefreq>yearly</changefreq>
        <priority>0.5</priority>
    </url>

    <!-- Categorías -->
    @foreach ($categories as $category)
        <url>
            <loc>{{ url('/?category=' . $category->id) }}</loc>
            <lastmod>{{ $category->updated_at->tz('UTC')->toAtomString() }}</lastmod>
            <changefreq>weekly</changefreq>
            <priority>0.9</priority>
        </url>
    @endforeach

    <!-- Productos -->
    @foreach ($products as $product)
        <url>
            <loc>{{ route('product.detail', ['slug' => $product->slug]) }}</loc>
            <lastmod>{{ $product->updated_at->tz('UTC')->toAtomString() }}</lastmod>
            <changefreq>weekly</changefreq>
            <priority>0.8</priority>
        </url>
    @endforeach
</urlset>
