import { isToday } from './helpers'

export const avatarText = value => {
  if (!value)
    return ''
  const nameArray = value.split(' ')
  
  return nameArray.map(word => word.charAt(0).toUpperCase()).join('')
}

// TODO: Try to implement this: https://twitter.com/fireship_dev/status/1565424801216311297
export const kFormatter = num => {
  const regex = /\B(?=(\d{3})+(?!\d))/g
  
  return Math.abs(num) > 9999 ? `${Math.sign(num) * +((Math.abs(num) / 1000).toFixed(1))}k` : Math.abs(num).toFixed(0).replace(regex, ',')
}

/**
 * Format and return date in Humanize format
 * Intl docs: https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Intl/DateTimeFormat/format
 * Intl Constructor: https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Intl/DateTimeFormat/DateTimeFormat
 * @param {string} value date to format
 * @param {Intl.DateTimeFormatOptions} formatting Intl object to format with
 */
export const formatDate = (value, formatting = { month: 'short', day: 'numeric', year: 'numeric' }) => {
  if (!value)
    return value
  
  return new Intl.DateTimeFormat('en-US', formatting).format(new Date(value))
}

/**
 * Return short human friendly month representation of date
 * Can also convert date to only time if date is of today (Better UX)
 * @param {string} value date to format
 * @param {boolean} toTimeForCurrentDay Shall convert to time if day is today/current
 */
export const formatDateToMonthShort = (value, toTimeForCurrentDay = true) => {
  const date = new Date(value)
  let formatting = { month: 'short', day: 'numeric' }
  if (toTimeForCurrentDay && isToday(date))
    formatting = { hour: 'numeric', minute: 'numeric' }
  
  return new Intl.DateTimeFormat('en-US', formatting).format(new Date(value))
}
export const prefixWithPlus = value => value > 0 ? `+${value}` : value

/**
 * Capitaliza primer nombre y primer apellido (primeras dos palabras).
 * Ej: "juan perez" -> "Juan Perez", "MARÍA GARCÍA LÓPEZ" -> "María García"
 */
export const capitalizeFirstAndLastName = value => {
  if (!value || typeof value !== 'string') return ''
  const parts = value.trim().split(/\s+/).filter(Boolean)
  const first = parts[0] ? parts[0].charAt(0).toUpperCase() + parts[0].slice(1).toLowerCase() : ''
  const second = parts[1] ? parts[1].charAt(0).toUpperCase() + parts[1].slice(1).toLowerCase() : ''
  return [first, second].filter(Boolean).join(' ')
}
