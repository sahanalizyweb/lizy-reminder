/** Coloured badge for a reminder's calculated status. */
export default function StatusBadge({ status }) {
  return <span className={`badge badge--${String(status).toLowerCase()}`}>{status}</span>
}
