import { callLink, whatsappLink } from '../utils/whatsapp'
import Icon from './Icon'

/** The phone number with Call (tel:) and WhatsApp (wa.me, prefilled message) buttons. */
export default function PhoneActions({ reminder }) {
  return (
    <div className="phone">
      <span className="phone__number">{reminder.phone}</span>
      <span className="phone__buttons">
        <a className="icon-btn icon-btn--call" href={callLink(reminder.phone)} title="Call" aria-label={`Call ${reminder.phone}`}>
          <Icon name="phone" size={15} />
        </a>
        <a
          className="icon-btn icon-btn--whatsapp"
          href={whatsappLink(reminder)}
          target="_blank"
          rel="noopener noreferrer"
          title="Send WhatsApp message"
          aria-label={`WhatsApp ${reminder.phone}`}
        >
          <Icon name="whatsapp" size={15} />
        </a>
      </span>
    </div>
  )
}
