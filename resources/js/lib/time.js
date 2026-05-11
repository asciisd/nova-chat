const RTF = new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' })

const UNITS = [
    ['year', 60 * 60 * 24 * 365],
    ['month', 60 * 60 * 24 * 30],
    ['day', 60 * 60 * 24],
    ['hour', 60 * 60],
    ['minute', 60],
    ['second', 1],
]

export function formatRelative(iso) {
    if (!iso) return ''
    const date = new Date(iso)
    const diffSec = Math.round((date.getTime() - Date.now()) / 1000)
    const absSec = Math.abs(diffSec)

    if (absSec < 30) return 'just now'

    for (const [unit, secs] of UNITS) {
        if (absSec >= secs || unit === 'second') {
            return RTF.format(Math.round(diffSec / secs), unit)
        }
    }
    return RTF.format(diffSec, 'second')
}
