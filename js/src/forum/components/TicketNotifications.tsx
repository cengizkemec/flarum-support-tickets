import app from 'flarum/forum/app';
import Notification from 'flarum/forum/components/Notification';
import { ticketUrlById } from '../utils/ticketUrl';

const T = (key: string, params?: any) => app.translator.trans('cengizkemec-support.forum.notification.' + key, params) as any;

/** The custom payload carried by the notification blueprint. */
function notificationData(notification: any): any {
  return (notification.content && notification.content()) || {};
}

/**
 * Find the ticket a notification points at.
 *
 * The `subject` relationship is the primary source. The ticket id carried in
 * the notification payload is the fallback for the rare case where the subject
 * model has not made it into the store yet, so the link never dead-ends.
 */
function ticketId(notification: any): string | null {
  const subject = notification.subject();

  if (subject && subject.id()) {
    return subject.id();
  }

  const data = notificationData(notification);

  return data.ticketId ? String(data.ticketId) : null;
}

/**
 * The slug is what turns the link into the readable /support/18/title form.
 */
function ticketSlug(notification: any): string | null {
  const subject = notification.subject();

  if (subject && typeof subject.slug === 'function' && subject.slug()) {
    return subject.slug();
  }

  const data = notificationData(notification);

  return data.slug ? String(data.slug) : null;
}

function ticketHref(notification: any): string {
  const id = ticketId(notification);

  return id ? ticketUrlById(id, ticketSlug(notification)) : app.route('support');
}

function ticketSubject(notification: any): string | null {
  const subject = notification.subject();

  return subject && subject.subject ? subject.subject() : null;
}

/**
 * "Somebody opened a new support ticket."
 */
export class NewTicketNotification extends Notification {
  icon() {
    return 'fas fa-headset';
  }

  href() {
    return ticketHref(this.attrs.notification);
  }

  content() {
    return T('new_ticket', { user: this.attrs.notification.fromUser() });
  }

  excerpt() {
    return ticketSubject(this.attrs.notification);
  }
}

/**
 * "Somebody replied to a support ticket."
 */
export class TicketReplyNotification extends Notification {
  icon() {
    return 'fas fa-reply';
  }

  href() {
    return ticketHref(this.attrs.notification);
  }

  content() {
    return T('ticket_reply', { user: this.attrs.notification.fromUser() });
  }

  excerpt() {
    return ticketSubject(this.attrs.notification);
  }
}
