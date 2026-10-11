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

    return (
      <PageStructure className="TicketPage" hero={() => <h2>{ticket.subject()}</h2>}>
        <div className="TicketPage-meta">
          <span className={`TicketStatus TicketStatus--${ticket.status()}`}>
            {app.translator.trans(`acme-support.forum.status.${ticket.status()}`)}
          </span>
          <span>{ticket.priority()}</span>
        </div>

        <div className="Post">
          <div className="Post-body">{ticket.message()}</div>
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
            placeholder={app.translator.trans('acme-support.forum.reply')}
          />
          <Button className="Button Button--primary" onclick={this.submitReply.bind(this)}>
            {app.translator.trans('acme-support.forum.reply')}
          </Button>
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
    m.redraw();
  }
}
