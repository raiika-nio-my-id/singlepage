@forelse ($games as $game)
    <article class="card{{ in_array('multiplayer', $game['tags'], true) ? ' is-mp' : '' }}" data-game="{{ $game['subdomain'] }}" data-views="{{ $game['views'] }}" aria-labelledby="g-{{ $game['number'] }}">
        <div class="preview">
            <div class="ph" aria-hidden="true">
                <div class="mono">{{ $game['initials'] }}</div>
                <div class="st">{{ $game['hostLabel'] }}</div>
            </div>
            @if ($game['image'] !== '')
                <img loading="lazy" decoding="async" width="{{ $game['snapshotSize'][0] }}" height="{{ $game['snapshotSize'][1] }}" alt="Cuplikan tampilan {{ $game['title'] }}" src="{{ $game['image'] }}">
            @endif
            <a class="hit" href="{{ $game['url'] }}" aria-label="Main {{ $game['title'] }}"></a>
            @if ($game['badge'] !== '' || in_array('multiplayer', $game['tags'], true))
                <div class="badges">
                    @if (in_array('multiplayer', $game['tags'], true))
                        <span class="sticker mp">Multiplayer</span>
                    @endif
                    @if ($game['badge'] !== '')
                        <span class="sticker new">{{ $game['badge'] }}</span>
                    @endif
                </div>
            @endif
            <span class="num" aria-hidden="true">{{ $game['number'] }}</span>
        </div>
        <div class="info">
            <h3 class="name" id="g-{{ $game['number'] }}" title="{{ $game['title'] }}">{{ $game['title'] }}</h3>
            <div class="host">{{ $game['hostLabel'] }}</div>
            @if ($game['description'] !== '')
                <p class="desc" title="{{ $game['description'] }}">{{ $game['description'] }}</p>
            @endif
            <div class="meta">
                @if ($game['tags'] !== [])
                    <ul class="tags" aria-label="Tag">
                        @foreach ($game['tags'] as $tag)
                            <li class="tag{{ $tag === 'multiplayer' ? ' mp' : '' }}">{{ $tag }}</li>
                        @endforeach
                    </ul>
                @endif
                <a class="play" href="{{ $game['url'] }}">Main <span class="sr-only">{{ $game['title'] }}</span><span class="arr" aria-hidden="true">&#x2197;</span></a>
            </div>
        </div>
    </article>
@empty
    <p class="hub-empty">Belum ada game.</p>
@endforelse
