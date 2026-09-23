export default function Pagination({ meta, onPage }) {
  if (!meta || meta.total === 0) return null

  return (
    <div className="pagination">
      <span>
        Showing {meta.from}–{meta.to} of {meta.total}
      </span>
      <div className="pagination__buttons">
        <button type="button" className="btn btn--ghost btn--sm" disabled={meta.current_page <= 1} onClick={() => onPage(meta.current_page - 1)}>
          Previous
        </button>
        <span className="pagination__page">
          Page {meta.current_page} of {meta.last_page}
        </span>
        <button
          type="button"
          className="btn btn--ghost btn--sm"
          disabled={meta.current_page >= meta.last_page}
          onClick={() => onPage(meta.current_page + 1)}
        >
          Next
        </button>
      </div>
    </div>
  )
}
