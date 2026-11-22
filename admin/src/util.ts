export const fmt = (n: number | null | undefined) => (n == null ? '' : Math.round(n).toLocaleString('vi-VN'))

export function short(n: number | null | undefined) {
  if (!n) return '—'
  if (n >= 1e9) return (n / 1e9).toLocaleString('vi-VN', { maximumFractionDigits: 1 }) + ' tỷ'
  if (n >= 1e6) return (n / 1e6).toLocaleString('vi-VN', { maximumFractionDigits: 1 }) + ' triệu'
  return fmt(n) + 'đ'
}

export function dt(s: string | null | undefined) {
  if (!s) return ''
  const d = new Date(s)
  const p = (x: number) => String(x).padStart(2, '0')
  return `${p(d.getDate())}/${p(d.getMonth() + 1)}/${d.getFullYear()} ${p(d.getHours())}:${p(d.getMinutes())}`
}

export const age = (by: number | null | undefined) => (by ? new Date().getFullYear() - by : null)

export const initials = (s: string) => s.trim().split(/\s+/).slice(0, 2).map((w) => w[0]?.toUpperCase() ?? '').join('') || '?'

// Module trong đường dẫn CMS: /loans/... hoặc /cards/...
export type Mod = 'loans' | 'cards'
export const modType = (m: Mod) => (m === 'cards' ? 'card' : 'loan')
export const typeMod = (t: string): Mod => (t === 'card' ? 'cards' : 'loans')
export const modText = {
  loans: { name: 'Khoản vay', leads: 'Đăng ký vay', offers: 'Gói vay', one: 'gói vay', lead: 'đăng ký vay' },
  cards: { name: 'Thẻ tín dụng', leads: 'Đăng ký mở thẻ', offers: 'Gói thẻ', one: 'gói thẻ', lead: 'đăng ký mở thẻ' },
}

export const moneyInput = (v: string) => {
  const d = v.replace(/\D/g, '')
  return d ? Number(d) : 0
}
