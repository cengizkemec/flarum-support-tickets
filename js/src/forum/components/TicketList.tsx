import Component from 'flarum/common/Component';
import Link from 'flarum/common/components/Link';
import app from 'flarum/forum/app';
import { formatDateTime } from '../utils/format';

const T = (key: string, params?: any) => app.translator.trans('cengizkemec-support.forum.' + key, params);

const OPEN_STATUSES = ['new', 'answered', 'pending'];

export default class TicketList extends Component {
  view() {
    const filter = this.attrs.filter || 'all';

    let tickets = app.store.all('tickets') as any[];
    if (filter === 'open') {
      tickets = tickets.filter((t) => OPEN_STATUSES.indexOf(t.status()) >= 0);
    } else if (filter === 'closed') {
      tickets = tickets.filter((t) => OPEN_STATUSES.indexOf(t.status()) < 0);
    }

    if (!tickets.length) {
      return (
        <div className="SupportEmpty">
          <div className="SupportEmpty-icon">
            <i className="icon far fa-life-ring" />
          </div>
          <div className="SupportEmpty-title">{T('empty_title')}</div>
          <div className="SupportEmpty-text">{T('empty_text')}</div>
        </div>
      );
    }

    return <ul className="SupportList">{tickets.map((ticket) => this.card(ticket))}</ul>;
  }

  card(ticket: any) {
    const status = ticket.status();
    const priority = ticket.priority();
    const category = ticket.category();
    const replies = (ticket.replies && ticket.replies()) || [];

    return (
      <li key={ticket.id()}>
        <Link className={'SupportCard SupportCard--' + status} href={app.route('support.ticket', { id: ticket.id() })}>
          <div className="SupportCard-top">
            <h3 className="SupportCard-title">{ticket.subject()}</h3>
            <div className="SupportCard-badges">
              <span className={'SupportPriority SupportPriority--' + priority}>{T('priority.' + priority)}</span>
              <span className={'SupportBadge SupportBadge--' + status}>{T('status.' + status)}</span>
            </div>
          </div>

          {ticket.message() && <p className="SupportCard-excerpt">{ticket.message()}</p>}

          <div className="SupportCard-meta">
            {category && (
              <span className="SupportChip">
                <i className="icon fas fa-folder" /> {category.name()}
              </span>
            )}
            <span className="SupportChip">
              <i className="icon far fa-clock" /> {formatDateTime(ticket.createdAt())}
            </span>
            {replies.length > 0 && (
              <span className="SupportChip">
                <i className="icon far fa-comment" /> {replies.length}
              </span>
            )}
            {ticket.assignee() && (
              <span className="SupportChip">
                <i className="icon fas fa-user-check" /> {ticket.assignee().displayName()}
              </span>
            )}
          </div>
        </Link>
      </li>
    );
  }
}
