import app from 'flarum/forum/app';

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
