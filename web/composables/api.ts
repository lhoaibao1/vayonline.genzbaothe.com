// Gọi API Go: phía server dùng địa chỉ nội bộ, phía trình duyệt dùng đường dẫn tương đối /api
export function apiBase() {
  return import.meta.server ? useRuntimeConfig().apiInternal : ''
}

export function useApi<T>(key: string, path: string | (() => string)) {
  return useAsyncData<T>(key, () => $fetch<T>(apiBase() + (typeof path === 'function' ? path() : path)))
}

export interface Offer {
  id: number
  type: 'loan' | 'card'
  slug: string
  name: string
  partner: string
  logo: string
  badge: string
  amount_min: number
  amount_max: number
  rate_text: string
  rate_min: number
  term_min: number
  term_max: number
  age_text: string
  approval_text: string
  annual_fee: string
  highlights: string
  requirements: string
  description: string
}

export interface Landing {
  id: number
  slug: string
  name: string
  headline: string
  subheadline: string
  offer_type: 'all' | 'loan' | 'card'
  show_calculator: boolean
  seo_title: string
  seo_description: string
  is_default: boolean
}

export interface LandingData {
  landing: Landing
  loans: Offer[]
  cards: Offer[]
}

export interface Site {
  pages: { slug: string; title: string }[]
  company_name: string
  tax_code: string
  company_address: string
  site_name: string
  site_tagline: string
  hotline: string
  zalo: string
  contact_email: string
  company_info: string
  disclaimer: string
  faq: { q: string; a: string }[]
}

export interface Opt { value: string; label: string }
export interface Options {
  provinces: string[]
  income: Opt[]
  occupation: Opt[]
  purpose: Opt[]
  salary_method: Opt[]
  has_card: Opt[]
  card_need: Opt[]
  terms: number[]
  data_request_type: Opt[]
}

export const useSite = () => useApi<Site>('site', '/api/public/site')
export const useOptions = () => useApi<Options>('options', '/api/public/options')

// Gói đang mở form đăng ký + số tiền/kỳ hạn khách đã chọn ở công cụ tính
export const useRegister = () => useState<{ offer: Offer | null; amount: number; term: number; landingId: number }>('register', () => ({
  offer: null, amount: 0, term: 12, landingId: 0,
}))
