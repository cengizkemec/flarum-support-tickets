import Extend from 'flarum/common/extenders';
import app from 'flarum/admin/app';
import SettingsPage from './components/SettingsPage.tsx';
import Ticket from '../common/models/Ticket';
import TicketCategory from '../common/models/TicketCategory';
import TicketReply from '../common/models/TicketReply';

export default [
  // The admin bundle must register the same models as the forum bundle.
  // Without this, app.store.find('ticket-categories') returns null entries and
  // app.store.createRecord('ticket-categories') throws
  // "type not yet registered in the store", which broke the settings page and
  // made it impossible to save categories from the admin panel.
  new Extend.Store()
    .add('tickets', Ticket)
    .add('ticket-categories', TicketCategory)
    .add('ticket-replies', TicketReply),

  new Extend.Admin()
    .page(SettingsPage)
    .permission(
      () => ({
        icon: 'fas fa-eye',
        label: app.translator.trans('cengizkemec-support.admin.permissions.view_all'),
        permission: 'support.viewAll',
      }),
      'moderate',
      90
    )
    .permission(
      () => ({
        icon: 'fas fa-reply',
        label: app.translator.trans('cengizkemec-support.admin.permissions.reply'),
        permission: 'support.reply',
      }),
      'reply',
      90
    )
    .permission(
      () => ({
        icon: 'fas fa-cog',
        label: app.translator.trans('cengizkemec-support.admin.permissions.manage'),
        permission: 'support.manage',
      }),
      'moderate',
      85
    ),
];
