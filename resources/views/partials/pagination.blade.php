@if ($paginator->hasPages())
    @if ($paginator->onFirstPage())
        <span style="color:var(--muted)">ก่อนหน้า</span>
    @else
        <a href="{{ $paginator->previousPageUrl() }}">ก่อนหน้า</a>
    @endif

    @foreach ($elements as $element)
        @if (is_string($element))
            <span>{{ $element }}</span>
        @endif

        @if (is_array($element))
            @foreach ($element as $page => $url)
                @if ($page == $paginator->currentPage())
                    <span class="on">{{ $page }}</span>
                @else
                    <a href="{{ $url }}">{{ $page }}</a>
                @endif
            @endforeach
        @endif
    @endforeach

    @if ($paginator->hasMorePages())
        <a href="{{ $paginator->nextPageUrl() }}">ถัดไป</a>
    @else
        <span style="color:var(--muted)">ถัดไป</span>
    @endif
@endif
