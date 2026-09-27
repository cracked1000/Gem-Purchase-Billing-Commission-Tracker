{{-- Compact pagination used across GemDesk tables. --}}
@if ($paginator->hasPages())
    <div class="pagination">
        <div class="pagination-info">
            Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }} · {{ $paginator->total() }} total
        </div>
        <div class="pagination-controls">
            @if ($paginator->onFirstPage())
                <span class="page-item disabled" aria-disabled="true">‹</span>
            @else
                <a class="page-item" href="{{ $paginator->previousPageUrl() }}" rel="prev">‹</a>
            @endif

            @foreach ($elements as $element)
                {{-- "Three Dots" separator --}}
                @if (is_string($element))
                    <span class="page-dots">{{ $element }}</span>
                @endif

                {{-- Array of page links --}}
                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="page-item active">{{ $page }}</span>
                        @else
                            <a class="page-item" href="{{ $url }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a class="page-item" href="{{ $paginator->nextPageUrl() }}" rel="next">›</a>
            @else
                <span class="page-item disabled" aria-disabled="true">›</span>
            @endif
        </div>
    </div>
@endif
