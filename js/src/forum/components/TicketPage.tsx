import Page from 'flarum/common/components/Page';
import PageStructure from 'flarum/forum/components/PageStructure';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Button from 'flarum/common/components/Button';
import Stream from 'flarum/common/utils/Stream';
import app from 'flarum/forum/app';
import { formatDateTime } from '../utils/format';

const T = (key: string, params?: any) => app.translator.trans('cengizkemec-support.forum.' + key, params);

const STATUSES = ['new', 'answered', 'pending', 'resolved', 'closed'];

export default class TicketPage extends Page {
  loading = true;
  error: string | null = null;
  ticket: any = null;
  replyMessage = Stream('');
  submitting = false;

  oninit(vnode: any) {
    super.oninit(vnode);

    const id = m.route.param('id');
    this.load(id);
  }

  load(id: any) {
    app.store
      .find('tickets', id, { include: 'replies,replies.user,category,assignee,user' })
      .then((ticket) => {
        this.ticket = ticket;
        this.loading = false;
        m.redraw();
      })
      .catch((e: any) => {
        this.loading = false;
        this.error = e?.response?.errors?.[0]?.detail || e?.message || T('error_load');
        app.alerts.show({ type: 'error' }, this.error);
        m.redraw();
      });
  }

  view() {
    if (this.loading) {
      return (
        <PageStructure className="TicketPage">
          <div className="SupportLoading">
            <LoadingIndicator />
          </div>
        </PageStructure>
      );
    }

    if (this.error || !this.ticket) {
      return (
        <PageStructure className="TicketPage">
          <div className="SupportEmpty">
            <div className="SupportEmpty-icon">
              <i className="icon fas fa-triangle-exclamation" />
            </div>
            <div className="SupportEmpty-title">{this.error || T('error_not_found')}</div>
          </div>
        </PageStructure>
      );
    }

    const ticket = this.ticket;
    const status = ticket.status();
    const isStaff = !!app.forum.attribute('canSupportViewAll') || !!app.forum.attribute('canSupportManage');
    const isClosed = status === 'closed';
    const canReply = !isClosed || isStaff;

    return (
      <PageStructure
        className="TicketPage"
        hero={() => (
          <h2 className="TicketPage-hero">
            <i className="icon fas fa-ticket" /> {ticket.subject()}
          </h2>
        )}
      >
        {this.header(ticket, status)}

        {this.message(ticket)}

        {this.replies(ticket, isStaff)}

        {canReply ? this.replyForm() : <div className="TicketClosedNote">{T('closed_note')}</div>}

        {isStaff && this.staffPanel(ticket, status)}
      </PageStructure>
    );
  }

  header(ticket: any, status: string) {
    const priority = ticket.priority();
    const category = ticket.category();
    const assignee = ticket.assignee();
    const requester = ticket.user();

    return (
      <div className="TicketHeader">
        <div className="TicketHeader-meta">
          <span className={'SupportBadge SupportBadge--' + status}>{T('status.' + status)}</span>
          <span className={'SupportPriority SupportPriority--' + priority}>{T('priority.' + priority)}</span>
          {category && (
            <span className="SupportChip">
              <i className="icon fas fa-folder" /> {category.name()}
            </span>
          )}
          <span className="SupportChip">
            <i className="icon far fa-clock" /> {formatDateTime(ticket.createdAt())}
          </span>
          {requester && (
            <span className="SupportChip">
              <i className="icon far fa-user" /> {requester.displayName()}
            </span>
          )}
          {assignee && (
            <span className="SupportChip">
              <i className="icon fas fa-user-check" /> {T('assigned_to')}: {assignee.displayName()}
            </span>
          )}
        </div>
      </div>
    );
  }

  message(ticket: any) {
    const requester = ticket.user();

    return (
      <div className="TicketMessage">
        <div className="TicketAvatar">{requester && <img className="Avatar" src={requester.avatarUrl()} alt={requester.displayName()} />}</div>
        <div className="TicketMessage-body">
          <div className="TicketMessage-head">
            <span className="TicketMessage-author">{requester ? requester.displayName() : T('unknown_user')}</span>
            <span className="TicketMessage-time">{formatDateTime(ticket.createdAt())}</span>
          </div>
          <div className="TicketMessage-text">{ticket.message()}</div>
          {ticket.attachment() && (
            <a
              className="TicketAttachment"
              href={app.forum.attribute('apiUrl') + '/support/attachments/' + ticket.attachment()}
              target="_blank"
              rel="noopener noreferrer"
            >
              <i className="icon fas fa-paperclip" /> {T('attachment')}
            </a>
          )}
        </div>
      </div>
    );
  }

  replies(ticket: any, isStaff: boolean) {
    const replies = (ticket.replies() || []).slice().sort((a: any, b: any) => {
      const da = a.createdAt() ? a.createdAt().getTime() : 0;
      const db = b.createdAt() ? b.createdAt().getTime() : 0;
      return da - db;
    });

    if (!replies.length) return null;

    return (
      <div className="TicketReplies">
        {replies.map((reply: any) => {
          const author = reply.user();
          const staff = reply.isStaff();

          return (
            <div className={'TicketReply' + (staff ? ' is-staff' : '')} key={reply.id()}>
              <div className="TicketAvatar">
                {author && <img className="Avatar" src={author.avatarUrl()} alt={author.displayName()} />}
              </div>
              <div className="TicketReply-body">
                <div className="TicketReply-head">
                  <span className="TicketReply-author">{author ? author.displayName() : T('unknown_user')}</span>
                  {staff && <span className="TicketStaffTag">{T('staff')}</span>}
                  <span className="TicketReply-time">{formatDateTime(reply.createdAt())}</span>
                </div>
                <p className="TicketReply-text">{reply.message()}</p>
                {reply.attachment() && (
                  <a
                    className="TicketAttachment"
                    href={app.forum.attribute('apiUrl') + '/support/attachments/' + reply.attachment()}
                    target="_blank"
                    rel="noopener noreferrer"
                  >
                    <i className="icon fas fa-paperclip" /> {T('attachment')}
                  </a>
                )}
              </div>
            </div>
          );
        })}
      </div>
    );
  }

  replyForm() {
    return (
      <div className="TicketReplyForm">
        <h3 className="TicketReplyForm-title">{T('reply')}</h3>
        <textarea
          className="FormControl"
          rows={4}
          value={this.replyMessage()}
          oninput={(e: any) => this.replyMessage(e.target.value)}
          placeholder={T('reply_placeholder')}
        />
        <Button
          className="Button Button--primary"
          icon="fas fa-paper-plane"
          loading={this.submitting}
          disabled={this.submitting || !this.replyMessage()}
          onclick={this.submitReply.bind(this)}
        >
          {T('send_reply')}
        </Button>
      </div>
    );
  }

  staffPanel(ticket: any, status: string) {
    const staffList: any[] = (app.forum.attribute('supportStaff') as any[]) || [];
    const assigneeId = ticket.assignee() ? String(ticket.assignee().id()) : '';
    const isClosed = status === 'closed';

    return (
      <div className="TicketStaffPanel">
        <h3 className="TicketStaffPanel-title">
          <i className="icon fas fa-user-shield" /> {T('staff_panel')}
        </h3>

        <div className="TicketStaffGrid">
          <div className="TicketStaffField">
            <label>{T('status_label')}</label>
            <select className="FormControl" value={status} onchange={(e: any) => this.updateStatus(e.target.value)}>
              {STATUSES.map((s) => (
                <option value={s}>{T('status.' + s)}</option>
              ))}
            </select>
          </div>

          <div className="TicketStaffField">
            <label>{T('assigned_to')}</label>
            <select className="FormControl" value={assigneeId} onchange={(e: any) => this.assignTo(e.target.value)}>
              <option value="">{T('unassigned')}</option>
              {staffList.map((u) => (
                <option value={String(u.id)}>
                  {u.displayName}
                </option>
              ))}
            </select>
          </div>
        </div>

        <div className="TicketStaffActions">
          {isClosed ? (
            <Button className="Button" icon="fas fa-rotate-left" onclick={() => this.updateStatus('new')}>
              {T('reopen')}
            </Button>
          ) : (
            <span>
              <Button className="Button Button--primary" icon="fas fa-circle-check" onclick={() => this.updateStatus('resolved')}>
                {T('mark_resolved')}
              </Button>{' '}
              <Button className="Button" icon="fas fa-lock" onclick={() => this.updateStatus('closed')}>
                {T('close_ticket')}
              </Button>
            </span>
          )}
        </div>
      </div>
    );
  }

  async submitReply() {
    if (!this.replyMessage() || this.submitting) return;

    this.submitting = true;
    m.redraw();

    try {
      await app.store.createRecord('ticket-replies').save({
        message: this.replyMessage(),
        relationships: { ticket: this.ticket },
      });

      this.replyMessage('');
      await this.load(this.ticket.id());
    } catch (e: any) {
      app.alerts.show({ type: 'error' }, e?.response?.errors?.[0]?.detail || e?.message || T('error_reply'));
    } finally {
      this.submitting = false;
      m.redraw();
    }
  }

  async updateStatus(status: string) {
    const previous = this.ticket.status();
    try {
      await this.ticket.save({ status });
      m.redraw();
    } catch (e: any) {
      this.ticket.pushData({ attributes: { status: previous } });
      app.alerts.show({ type: 'error' }, e?.response?.errors?.[0]?.detail || e?.message || T('error_status'));
      m.redraw();
    }
  }

  async assignTo(userId: string) {
    try {
      if (!userId) {
        await this.ticket.save({ relationships: { assignee: null } });
      } else {
        const user = await app.store.find('users', userId);
        await this.ticket.save({ relationships: { assignee: user } });
      }
      m.redraw();
    } catch (e: any) {
      app.alerts.show({ type: 'error' }, e?.response?.errors?.[0]?.detail || e?.message || T('error_assign'));
    }
  }
}
