import { VIEWS } from '../utils/constants'

const CARDS = [
  { view: 'today', tone: 'orange' },
  { view: 'upcoming', tone: 'navy' },
  { view: 'overdue', tone: 'red' },
  { view: 'completed', tone: 'green' },
]

// Short titles for the count tiles (the full view names are used for the table heading).
const TITLES = { today: "Today's Work", upcoming: 'Upcoming', overdue: 'Overdue', completed: 'Completed' }

/** The four counts. Clicking one filters the table below to that view. */
export default function SummaryCards({ summary, activeView, onSelect }) {
  return (
    <div className="summary" role="tablist" aria-label="Reminder views">
      {CARDS.map(({ view, tone }) => (
        <button
          key={view}
          type="button"
          role="tab"
          aria-selected={activeView === view}
          className={`summary__card summary__card--${tone} ${activeView === view ? 'is-active' : ''}`}
          onClick={() => onSelect(view)}
          title={VIEWS[view].description}
        >
          <span className="summary__count">{summary ? summary[view] : '–'}</span>
          <span className="summary__label">{TITLES[view]}</span>
        </button>
      ))}
    </div>
  )
}
