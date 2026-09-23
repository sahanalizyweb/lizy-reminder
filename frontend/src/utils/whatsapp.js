import { formatDate } from './date'

const NO_CUSTOMER = 'Not Provided'

/** tel: link. Formatting characters are removed but a leading + is kept. */
export function callLink(phone) {
  return `tel:${String(phone).replace(/[^\d+]/g, '')}`
}

/**
 * Number in the form wa.me expects: digits only, with country code.
 * A bare 10-digit number is treated as Indian (+91); a leading 0 is dropped.
 * The stored phone number itself is never changed.
 */
export function whatsappNumber(phone) {
  let digits = String(phone).replace(/\D/g, '').replace(/^0+/, '')
  if (digits.length === 10) digits = `91${digits}`
  return digits
}

function greeting(reminder) {
  const customer = reminder.customer_name && reminder.customer_name !== NO_CUSTOMER ? reminder.customer_name : ''
  return customer ? `Hello ${customer},` : 'Hello,'
}

function productMessage(reminder) {
  const date = formatDate(reminder.scheduled_date)
  return [
    greeting(reminder),
    '',
    `This is a reminder from Lizyweb: your ${reminder.product_name} order (Qty: ${reminder.quantity}) is scheduled for ${date}.`,
    'Please let us know if you have any questions.',
    '',
    'Thank you!',
    '',
    'Lizyweb',
  ].join('\n')
}

function itServiceMessage(reminder) {
  const date = formatDate(reminder.scheduled_date)
  return [
    greeting(reminder),
    '',
    'This is a reminder from Lizyweb regarding the website:',
    '',
    reminder.website_link,
    '',
    `The scheduled follow-up date is ${date}.`,
    'Please check and follow up accordingly.',
    '',
    'Thank you!',
    '',
    'Lizyweb',
  ].join('\n')
}

function travelMessage(reminder) {
  const date = formatDate(reminder.scheduled_date)
  return [
    greeting(reminder),
    '',
    `This is a reminder from Lizyweb regarding your ${reminder.booking_name} booking, scheduled for ${date}.`,
    'Please let us know if you have any questions.',
    '',
    'Thank you!',
    '',
    'Lizyweb',
  ].join('\n')
}

function realEstateMessage(reminder) {
  const date = formatDate(reminder.scheduled_date)
  const property = reminder.location ? `${reminder.property_name} in ${reminder.location}` : reminder.property_name
  return [
    greeting(reminder),
    '',
    `This is a reminder from Lizyweb regarding ${property}.`,
    `The follow-up date is ${date}.`,
    'Please let us know if you have any questions.',
    '',
    'Thank you!',
    '',
    'Lizyweb',
  ].join('\n')
}

/** A reminder saved under a type from before Product / IT Service existed. */
function legacyMessage(reminder) {
  const date = formatDate(reminder.scheduled_date)
  const what = reminder.product_name ? ` (${reminder.product_name})` : ''
  return [
    greeting(reminder),
    '',
    `This is a reminder from Lizyweb: your ${reminder.reminder_type}${what} is scheduled for ${date}.`,
    'Please let us know if you have any questions. Thank you!',
    '',
    'Lizyweb',
  ].join('\n')
}

/** The WhatsApp message, built from the reminder's customer and details. */
export function whatsappMessage(reminder) {
  if (reminder.reminder_type === 'Product') return productMessage(reminder)
  if (reminder.reminder_type === 'IT Service') return itServiceMessage(reminder)
  if (reminder.reminder_type === 'Travel / Family Tour Booking') return travelMessage(reminder)
  if (reminder.reminder_type === 'Real Estate Marketing') return realEstateMessage(reminder)
  return legacyMessage(reminder)
}

export function whatsappLink(reminder) {
  return `https://wa.me/${whatsappNumber(reminder.phone)}?text=${encodeURIComponent(whatsappMessage(reminder))}`
}
