@if ($paginator->hasPages())
    <div class="pagination-wrap">
        <nav aria-label="Pagination">
            @if ($paginator->onFirstPage())
                <span class="muted small">Previous</span>
            @else
                <a class="btn btn-secondary btn-sm" href="{{ $paginator->previousPageUrl() }}">Previous</a>
            @endif

            <span class="small muted">Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</span>

            @if ($paginator->hasMorePages())
                <a class="btn btn-secondary btn-sm" href="{{ $paginator->nextPageUrl() }}">Next</a>
            @else
                <span class="muted small">Next</span>
            @endif
        </nav>
    </div>
@endif
