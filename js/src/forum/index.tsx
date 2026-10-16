import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import IndexSidebar from 'flarum/forum/components/IndexSidebar';
import LinkButton from 'flarum/common/components/LinkButton';

export { default as extend } from './extend';

const T = (key: string) => app.translator.trans('cengizkemec-support.forum.' + key);

app.initializers.add('cengizkemec-support', () => {
  const supportLink = () => (
    <LinkButton href={app.route('support')} icon="fas fa-headset">
      {T('nav_item')}
    </LinkButton>
  );

  // The support entry belongs in the forum's left menu for everybody, guests
  // included: they can read the landing page and are invited to sign in.
  extend(IndexSidebar.prototype, 'navItems', function (items) {
    items.add('support', supportLink(), 75);
  });

  // Let users decide whether ticket activity reaches them by alert and/or email.
  // `NotificationGrid` lives in a lazy chunk, so extend it by path.
  extend('flarum/forum/components/NotificationGrid', 'notificationTypes', function (items) {
    items.add('newTicket', {
      name: 'newTicket',
      icon: 'fas fa-headset',
      label: T('notification.new_ticket_label'),
    });
    items.add('ticketReply', {
      name: 'ticketReply',
      icon: 'fas fa-reply',
      label: T('notification.ticket_reply_label'),
    });
  });
});
