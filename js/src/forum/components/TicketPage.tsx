import Page from 'flarum/common/components/Page';
import PageStructure from 'flarum/forum/components/PageStructure';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Button from 'flarum/common/components/Button';
import Stream from 'flarum/common/utils/Stream';
import app from 'flarum/forum/app';

export default class TicketPage extends Page {
  oninit(vnode: any) {
    super.oninit(vnode);
    this.loading = true;
    this.replyMessage = Stream('');
    this.ticket = null;

    const id = m.route.param('id');

    app.store
      .find('tickets', id, { include: 'replies,replies.user,category,assignee,user' })
      .then((ticket) => {
        this.ticket = ticket;
        this.loading = false;
        m.redraw();
      });
  }

  view() {
    if (this.loading || !this.ticket) {
      return (
        <PageStructure>
          <LoadingIndicator />
        </PageStructure>
      );
    }

    const ticket = this.ticket;
    const isStaff = app.session.user?.can?.('support.viewAll');

    return (
      <PageStructure className="TicketPage" hero={() => <h2>{ticket.subject()}</h2>}>
        <div className="TicketPage-meta">
          <span className={`TicketStatus TicketStatus--${ticket.status()}`}>
            {app.translator.trans(`cengizkemec-support.forum.status.${ticket.status()}`)}
          </span>
          <span className="TicketPriority">{ticket.priority()}</span>
          {ticket.category() && <span> | {ticket.category().name()}</span>}
          {ticket.assignee() && <span> | Assigned: {ticket.assignee().displayName()}</span>}
        </div>

        <div className="Post">
          <div className="Post-body">{ticket.message()}</div>
          {ticket.attachment() && <div className="TicketAttachment">Attachment: {ticket.attachment()}</div>}
        </div>

        <div className="TicketReplies">
          {ticket.replies().map((reply: any) => (
            <div className="Post" key={reply.id()}>
              <div className="Post-header">
                <strong>{reply.user()?.displayName()}</strong>
                {reply.isStaff() && <span className="Badge">Staff</span>}
              </div>
              <div className="Post-body">{reply.message()}</div>
            </div>
          ))}
        </div>

        <div className="TicketReplyForm">
          <textarea
            className="FormControl"
            rows={4}
            value={this.replyMessage()}
            oninput={(e: any) => this.replyMessage(e.target.value)}
            placeholder={app.translator.trans('cengizkemec-support.forum.reply')}
          />
          <Button className="Button Button--primary" onclick={this.submitReply.bind(this)}>
            {app.translator.trans('cengizkemec-support.forum.reply')}
          </Button>

          {isStaff && (
            <div className="TicketStaffActions">
              <label>Status</label>
              <select className="FormControl" value={ticket.status()} onchange={(e: any) => this.updateStatus(e.target.value)}>
                <option value="new">New</option>
                <option value="answered">Answered</option>
                <option value="pending">Pending</option>
                <option value="resolved">Resolved</option>
                <option value="closed">Closed</option>
              </select>

              <label>Assign to (user ID)</label>
              <input
                className="FormControl"
                placeholder="User ID"
                onchange={(e: any) => this.assignTo(e.target.value)}
              />
            </div>
          )}
        </div>
      </PageStructure>
    );
  }

  async submitReply() {
    if (!this.replyMessage()) return;

    await app.store.createRecord('ticket-replies').save({
      message: this.replyMessage(),
      relationships: {
        ticket: this.ticket,
      },
    });

    this.replyMessage('');
    // Refresh replies
    await app.store.find('tickets', this.ticket.id(), { include: 'replies,replies.user' });
    m.redraw();
  }

  async updateStatus(status: string) {
    await this.ticket.save({ status });
    m.redraw();
  }

  async assignTo(userId: string) {
    if (!userId) return;
    await this.ticket.save({
      relationships: {
        assignee: app.store.getById('users', userId),
      },
    });
    m.redraw();
  }
}
