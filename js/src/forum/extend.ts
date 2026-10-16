import Extend from 'flarum/common/extenders';
import SupportPage from './components/SupportPage';
import TicketPage from './components/TicketPage';
import { NewTicketNotification, TicketReplyNotification } from './components/TicketNotifications';
import SupportDepartment from '../common/models/SupportDepartment';
import Ticket from '../common/models/Ticket';
import TicketCategory from '../common/models/TicketCategory';
import TicketFormField from '../common/models/TicketFormField';
import TicketReply from '../common/models/TicketReply';

export default [
  new Extend.Routes()
    .add('support', '/support', SupportPage)
    .add('support.ticket', '/support/:id', TicketPage)
    // The readable form of a ticket URL, /support/18/ses-ve-mikrofon-sorunu.
    // Flarum builds named routes by replacing each :segment, so an optional
    // segment needs its own route (this is exactly how core handles
    // /d/:id vs /d/:id/:near).
    .add('support.ticket.slug', '/support/:id/:slug', TicketPage),

  new Extend.Store()
    .add('tickets', Ticket)
    .add('ticket-categories', TicketCategory)
    .add('support-departments', SupportDepartment)
    .add('ticket-form-fields', TicketFormField)
    .add('ticket-replies', TicketReply),

  // Without a component for each type Flarum renders an empty row, which is
  // why ticket notifications used to show up blank in the dropdown.
  new Extend.Notification()
    .add('newTicket', NewTicketNotification)
    .add('ticketReply', TicketReplyNotification),
];
