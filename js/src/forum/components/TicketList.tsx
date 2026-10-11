import Component from 'flarum/common/components/Component';
import Link from 'flarum/common/components/Link';
import app from 'flarum/forum/app';

export default class TicketList extends Component {
  view() {
    const tickets = app.store.all('tickets');

    if (!tickets.length) {
      return <div className="SupportPage-empty">{app.translator.trans('cengizkemec-support.forum.no_tickets')}</div>;
    }

    return (
      <ul className="TicketList">
        {tickets.map((ticket) => (
          <li key={ticket.id()} className="TicketList-item">
            <Link href={app.route('support.ticket', { id: ticket.id() })}>
              <strong>{ticket.subject()}</strong>
              <span className={`TicketStatus TicketStatus--${ticket.status()}`}>
                {app.translator.trans(`cengizkemec-support.forum.status.${ticket.status()}`)}
              </span>
              <span className="TicketPriority">{ticket.priority()}</span>
            </Link>
          </li>
        ))}
      </ul>
    );
  }
}
