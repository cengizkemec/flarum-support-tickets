import app from 'flarum/forum/app';
import { slugify } from '../../common/utils/slug';

/**
 * Canonical, readable URL for a ticket: /support/18/ses-ve-mikrofon-sorunu
 *
 * Falls back to the bare /support/18 form when a ticket has no usable subject
 * so a link is always produced.
 */
export function ticketUrl(ticket: any): string {
  if (!ticket) {
    return app.route('support');
  }

  const id = ticket.id();

  if (!id) {
    return app.route('support');
  }

  const slug = (ticket.slug && ticket.slug()) || slugify(ticket.subject ? ticket.subject() : '');

  return slug ? (app.route as any)('support.ticket.slug', { id, slug }) : (app.route as any)('support.ticket', { id });
}

/**
 * URL for a ticket id + optional slug, used by notifications where the full
 * model may not be in the store yet.
 */
export function ticketUrlById(id: string | number, slug?: string | null): string {
  const cleanSlug = slug ? slugify(slug) : '';

  return cleanSlug ? (app.route as any)('support.ticket.slug', { id, slug: cleanSlug }) : (app.route as any)('support.ticket', { id });
}
