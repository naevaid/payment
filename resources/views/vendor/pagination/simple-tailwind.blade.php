@if ($paginator->hasPages())
    <nav role="navigation" aria-label="Pagination Navigation" class="pagination-bar">
        <div class="pagination-summary">
            Halaman {{ $paginator->currentPage() }}
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
