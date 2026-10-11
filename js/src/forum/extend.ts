import Extend from 'flarum/common/extenders';
import SupportPage from './components/SupportPage';
import TicketPage from './components/TicketPage';
import Ticket from '../common/models/Ticket';
import TicketCategory from '../common/models/TicketCategory';
import TicketReply from '../common/models/TicketReply';

export default [
  new Extend.Routes()
    .add('support', '/support', SupportPage)
    .add('support.ticket', '/support/:id', TicketPage),

  new Extend.Store()
    .add('tickets', Ticket)
    .add('ticket-categories', TicketCategory)
    .add('ticket-replies', TicketReply),
];
