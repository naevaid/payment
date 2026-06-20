@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination Navigation" class="pagination-bar">
        <div class="pagination-summary">
            Menampilkan {{ $paginator->firstItem() ?? 0 }}-{{ $paginator->lastItem() ?? 0 }}
            dari {{ $paginator->total() }} data
        </div>

        <div class="pagination-list">
            @if ($paginator->onFirstPage())
                <span class="pagination-link is-disabled" aria-disabled="true" aria-label="@lang('pagination.previous')">
                    Sebelumnya
                </span>
            @else
                <a class="pagination-link" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="@lang('pagination.previous')">
                    Sebelumnya
                </a>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <span class="pagination-link is-disabled" aria-disabled="true">{{ $element }}</span>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <span class="pagination-link is-active" aria-current="page">{{ $page }}</span>
                        @else
                            <a class="pagination-link" href="{{ $url }}" aria-label="Ke halaman {{ $page }}">{{ $page }}</a>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <a class="pagination-link" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="@lang('pagination.next')">
                    Berikutnya
                </a>
            @else
                <span class="pagination-link is-disabled" aria-disabled="true" aria-label="@lang('pagination.next')">
                    Berikutnya
                </span>
            @endif
        </div>
    </nav>
@endif
