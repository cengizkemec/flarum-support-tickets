import app from 'flarum/forum/app';

/**
 * A short, single-paragraph plain-text excerpt of a formatted message.
 *
 * Formatted HTML is converted to text with DOMParser, which never executes
 * scripts or loads resources; raw markdown/plain text is used as-is.
 */
export function plainExcerpt(
  html: string | null | undefined,
  fallback: string | null | undefined,
  length = 180
): string {
  let text = '';

  if (html) {
    const doc = new DOMParser().parseFromString(html, 'text/html');
    text = (doc.body && doc.body.textContent) || '';
  } else {
    text = fallback || '';
  }

  text = text.replace(/\s+/g, ' ').trim();

  return text.length > length ? text.slice(0, length - 1).replace(/\s+$/, '') + '…' : text;
}

/**
 * Format a date value (Date | ISO string | undefined) using the forum locale.
 */
export function formatDateTime(value: any): string {
  if (!value) return '';

  const date = value instanceof Date ? value : new Date(value);
  if (isNaN(date.getTime())) return '';

  try {
    const locale = (app as any).locale || (app as any).data?.locale || 'en';
    return new Intl.DateTimeFormat(locale, { dateStyle: 'medium', timeStyle: 'short' }).format(date);
  } catch (e) {
    return date.toLocaleString();
  }
}
