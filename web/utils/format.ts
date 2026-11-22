export const fmt = (n: number) => Math.round(n).toLocaleString('vi-VN')

// 30000000 -> "30 triệu"
export function short(n: number) {
  if (n >= 1e9) return (n / 1e9).toLocaleString('vi-VN', { maximumFractionDigits: 1 }) + ' tỷ'
  if (n >= 1e6) return (n / 1e6).toLocaleString('vi-VN', { maximumFractionDigits: 1 }) + ' triệu'
  return fmt(n) + 'đ'
}

export function initials(s: string) {
  return s.trim().split(/\s+/).slice(0, 2).map((w) => w[0]?.toUpperCase() ?? '').join('') || '?'
}

const esc = (s: string) => s.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]!)

// Tiêu đề: cụm đặt trong *dấu sao* được tô nổi bật
export const headlineHtml = (s: string) => esc(s).replace(/\*([^*]+)\*/g, '<mark>$1</mark>')
export const plainHeadline = (s: string) => s.replace(/\*/g, '')

export const lines = (s: string) => s.split(/\r?\n/).map((l) => l.trim()).filter(Boolean)

// Trả góp đều hằng tháng với lãi suất năm r%
export function monthly(p: number, yearRate: number, n: number) {
  const i = yearRate / 100 / 12
  return i ? (p * i) / (1 - Math.pow(1 + i, -n)) : p / n
}
