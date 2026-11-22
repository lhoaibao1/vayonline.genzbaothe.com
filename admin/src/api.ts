import { reactive } from 'vue'

export class ApiError extends Error {
  constructor(public status: number, message: string, public errors: Record<string, string> = {}) {
    super(message)
  }
}

// Gọi API Go. Mọi yêu cầu thay đổi dữ liệu gửi kèm X-Requested-With để server chấp nhận.
export async function api<T = any>(path: string, opts: { method?: string; body?: any; query?: Record<string, any> } = {}): Promise<T> {
  let url = '/api/admin' + path
  if (opts.query) {
    const q = new URLSearchParams()
    for (const [k, v] of Object.entries(opts.query)) if (v !== '' && v !== null && v !== undefined && v !== 0) q.set(k, String(v))
    if ([...q].length) url += '?' + q
  }
  const isForm = opts.body instanceof FormData
  const res = await fetch(url, {
    method: opts.method || 'GET',
    credentials: 'same-origin',
    headers: { 'X-Requested-With': 'XMLHttpRequest', ...(opts.body && !isForm ? { 'Content-Type': 'application/json' } : {}) },
    body: opts.body ? (isForm ? opts.body : JSON.stringify(opts.body)) : undefined,
  })
  const data = await res.json().catch(() => ({}))
  if (!res.ok) {
    if (res.status === 401 && !path.startsWith('/login') && !path.startsWith('/setup')) {
      session.me = null
      if (!location.pathname.endsWith('/login')) location.href = '/admin/login?next=' + encodeURIComponent(location.pathname + location.search)
    }
    throw new ApiError(res.status, data.message || 'Không kết nối được máy chủ. Thử lại sau ít phút.', data.errors || {})
  }
  return data as T
}

export interface Me {
  admin: { id: number; name: string; email: string; role: string }
  role_label: string
  perms: string[]
}

export interface Opt { value: string; label: string }
export interface Options {
  provinces: string[]; income: Opt[]; occupation: Opt[]; purpose: Opt[]; salary_method: Opt[]
  has_card: Opt[]; card_need: Opt[]; terms: number[]; status: Opt[]
}

export const session = reactive<{ me: Me | null; options: Options | null }>({ me: null, options: null })

export const can = (perm: string) => !!session.me?.perms.includes(perm)

export async function loadSession() {
  if (session.me) return session.me
  try {
    session.me = await api<Me>('/me')
    session.options ??= await api<Options>('/options')
  } catch {
    session.me = null
  }
  return session.me
}

export function label(field: keyof Options, value: string | null | undefined) {
  if (!value) return ''
  const list = session.options?.[field] as Opt[] | undefined
  return list?.find((o) => o.value === value)?.label || value
}

// Thông báo ngắn góc trên
export const toast = reactive<{ text: string; kind: 'ok' | 'err'; t?: number }>({ text: '', kind: 'ok' })
export function notify(text: string, kind: 'ok' | 'err' = 'ok') {
  toast.text = text
  toast.kind = kind
  clearTimeout(toast.t)
  toast.t = window.setTimeout(() => (toast.text = ''), 3500)
}

// Trang chi tiết đặt navKey để menu bên trái sáng đúng module
export const ui = reactive<{ navKey: string }>({ navKey: '' })
