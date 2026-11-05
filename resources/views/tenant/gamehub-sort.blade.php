<nav class="hub-sort" id="hub-sort" aria-label="Urutkan game">
    <a href="{{ $urls[\App\Services\GameHubCatalog::SORT_NEW] }}"{!! $sort === \App\Services\GameHubCatalog::SORT_NEW ? ' aria-current="page"' : '' !!}>Terbaru</a>
    <a href="{{ $urls[\App\Services\GameHubCatalog::SORT_VIEWS] }}"{!! $sort === \App\Services\GameHubCatalog::SORT_VIEWS ? ' aria-current="page"' : '' !!}>Paling dilihat</a>
</nav>
