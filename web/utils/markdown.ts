// Markdown rút gọn cho trang chính sách: ## tiêu đề, danh sách -, 1., **đậm**, [chữ](link), đoạn văn.
// Escape HTML trước rồi mới dựng thẻ, nên nội dung từ CMS không chèn được mã lạ.
const esc = (s: string) => s.replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]!)

function inline(s: string) {
  return esc(s)
    .replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>')
    .replace(/\[([^\]]+)\]\((\/[^)\s]*|https?:\/\/[^)\s]+|mailto:[^)\s]+)\)/g, (_m, text, href) => {
      const ext = href.startsWith('http')
      return `<a href="${href}"${ext ? ' target="_blank" rel="noopener"' : ''}>${text}</a>`
    })
    .replace(/\[chưa cập nhật\]/g, '<mark class="todo">[chưa cập nhật]</mark>')
}

export function markdown(src: string) {
  const out: string[] = []
  let list: 'ul' | 'ol' | null = null
  let para: string[] = []
  const flushPara = () => { if (para.length) { out.push(`<p>${inline(para.join(' '))}</p>`); para = [] } }
  const closeList = () => { if (list) { out.push(`</${list}>`); list = null } }

  for (const raw of src.split(/\r?\n/)) {
    const line = raw.trim()
    let m: RegExpMatchArray | null
    if (!line) { flushPara(); closeList(); continue }
    if ((m = line.match(/^(#{2,3})\s+(.*)$/))) {
      flushPara(); closeList()
      out.push(`<h${m[1].length}>${inline(m[2])}</h${m[1].length}>`)
    } else if ((m = line.match(/^[-*]\s+(.*)$/)) || (m = line.match(/^\d+\.\s+(.*)$/))) {
      flushPara()
      const kind = /^\d/.test(line) ? 'ol' : 'ul'
      if (list !== kind) { closeList(); out.push(`<${kind}>`); list = kind }
      out.push(`<li>${inline(m[1])}</li>`)
    } else {
      closeList()
      para.push(line)
    }
  }
  flushPara(); closeList()
  return out.join('\n')
}
