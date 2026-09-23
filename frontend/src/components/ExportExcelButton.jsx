import { useState } from 'react'
import { useToast } from '../hooks/useToast'
import { reminderService } from '../services/reminderService'
import { toQuery } from '../utils/filters'

/** Downloads the reminders matching `filters` as an .xlsx file (all of them, not just the current page). */
export default function ExportExcelButton({ filters }) {
  const toast = useToast()
  const [exporting, setExporting] = useState(false)

  const handleExport = async () => {
    setExporting(true)
    try {
      await reminderService.export(toQuery(filters))
    } catch (err) {
      toast.error(err.message || 'Could not export reminders.')
    } finally {
      setExporting(false)
    }
  }

  return (
    <button type="button" className="btn btn--ghost" onClick={handleExport} disabled={exporting}>
      {exporting ? 'Exporting…' : 'Export Excel'}
    </button>
  )
}
