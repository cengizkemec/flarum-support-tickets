import Page from 'flarum/common/components/Page';
import PageStructure from 'flarum/forum/components/PageStructure';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Button from 'flarum/common/components/Button';
import Link from 'flarum/common/components/Link';
import LinkButton from 'flarum/common/components/LinkButton';
import extractText from 'flarum/common/utils/extractText';
import Stream from 'flarum/common/utils/Stream';
import app from 'flarum/forum/app';
import { formatDateTime } from '../utils/format';
import { ticketUrl } from '../utils/ticketUrl';
import { attachmentError, attachmentUrl, isImageAttachment, pastedImage, uploadAttachment } from '../utils/attachments';

const T = (key: string, params?: any) => app.translator.trans('cengizkemec-support.forum.' + key, params);

const STATUSES = ['new', 'answered', 'pending', 'resolved', 'closed'];

/**
 * Link to a user's profile so nicknames inside a ticket are clickable.
 */
function userHref(user: any): string {
  return app.route('user', { username: user.slug ? user.slug() : user.username() });
}

export default class TicketPage extends Page {
  loading = true;
  error: string | null = null;
  ticket: any = null;
  replyMessage = Stream('');
  attachment = Stream('');
  attachmentName = Stream('');
  /** Local object URL used to preview the file before it is submitted. */
  preview = Stream('');
  submitting = false;
  uploading = false;

  oninit(vnode: any) {
    super.oninit(vnode);

    // Never leave the previous page's title in the tab while loading.
    app.setTitle(extractText(T('meta_title_text')));

    const id = m.route.param('id');
    this.load(id);
  }

  load(id: any) {
    app.store
      .find('tickets', id, { include: 'replies,replies.user,category,assignee,user' })
      .then((ticket) => {
        this.ticket = ticket;
        this.loading = false;
        app.setTitle(extractText(ticket.subject() || T('meta_title_text')));

        // Upgrade /support/18 to the readable /support/18/ticket-subject form.
        // Guarded on a missing slug so it can only ever happen once and can
        // never bounce back and forth.
        if (!m.route.param('slug') && ticket.slug && ticket.slug()) {
          m.route.set(ticketUrl(ticket), {}, { replace: true });
        }

        m.redraw();
      })
      .catch((e: any) => {
        this.loading = false;
        this.error = e?.response?.errors?.[0]?.detail || e?.message || T('error_load');
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
    const canReply = (!isClosed || isStaff) && !!app.session.user;

    return (
      <PageStructure
        className="TicketPage"
        hero={() => (
          <div className="container">
            <LinkButton className="TicketPage-back" href={app.route('support')} icon="fas fa-arrow-left">
              {T('back_to_support')}
            </LinkButton>
            <h2 className="TicketPage-hero">
              <i className="icon fas fa-ticket" /> {ticket.subject()}
            </h2>
          </div>
        )}
      >
        {this.header(ticket, status)}

        {this.customFields(ticket)}

        {this.message(ticket)}

        {this.replies(ticket)}

        {canReply ? this.replyForm() : this.closedNote()}

        {isStaff && this.staffPanel(ticket, status)}
      </PageStructure>
    );
  }

  /**
   * A closed ticket cannot be replied to, so give people a way out: back to the
   * ticket list they came from.
   */
  closedNote() {
    return (
      <div className="TicketClosedNote">
        <p className="TicketClosedNote-text">{T('closed_note')}</p>
        <LinkButton className="Button" href={app.route('support')} icon="fas fa-arrow-left">
          {T('back_to_support')}
        </LinkButton>
      </div>
    );
  }

  /**
   * Render a stored attachment: images are shown inline so pasted screenshots
   * are visible straight away, everything else stays a download link.
   */
  attachmentBlock(filename: string | null) {
    if (!filename) return null;

    const url = attachmentUrl(filename);

    if (isImageAttachment(filename)) {
      return (
        <a className="TicketAttachment-image" href={url} target="_blank" rel="noopener noreferrer">
          <img src={url} alt={T('attachment')} loading="lazy" />
        </a>
      );
    }

    return (
      <a className="TicketAttachment" href={url} target="_blank" rel="noopener noreferrer">
        <i className="icon fas fa-paperclip" /> {T('attachment')}
      </a>
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
              <i className="icon far fa-user" />{' '}
              <Link className="TicketUserLink" href={userHref(requester)}>
                {requester.displayName()}
              </Link>
            </span>
          )}
          {assignee && (
            <span className="SupportChip">
              <i className="icon fas fa-user-check" /> {T('assigned_to')}:{' '}
              <Link className="TicketUserLink" href={userHref(assignee)}>
                {assignee.displayName()}
              </Link>
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
        <div className="TicketAvatar">
          {requester && (
            <Link href={userHref(requester)}>
              <img className="Avatar" src={requester.avatarUrl()} alt={requester.displayName()} />
            </Link>
          )}
        </div>
        <div className="TicketMessage-body">
          <div className="TicketMessage-head">
            <span className="TicketMessage-author">
              {requester ? (
                <Link className="TicketUserLink" href={userHref(requester)}>
                  {requester.displayName()}
                </Link>
              ) : (
                T('unknown_user')
              )}
            </span>
            <span className="TicketMessage-time">{formatDateTime(ticket.createdAt())}</span>
          </div>
          <div className="TicketMessage-text">{ticket.message()}</div>
          {this.attachmentBlock(ticket.attachment())}
        </div>
      </div>
    );
  }

  /**
   * Show the answers the user gave to the admin-defined ticket form fields.
   */
  customFields(ticket: any) {
    const values: any[] = (ticket.customFieldValues && ticket.customFieldValues()) || [];

    if (!values.length) return null;

    return (
      <div className="TicketFields">
        <h4 className="TicketFields-title">
          <i className="icon fas fa-list-check" /> {T('details')}
        </h4>
        <dl className="TicketFields-list">
          {values.map((field: any, index: number) => (
            <div className="TicketField" key={(field.label || 'field') + index}>
              <dt className="TicketField-label">{field.label}</dt>
              <dd className="TicketField-value">{field.value}</dd>
            </div>
          ))}
        </dl>
      </div>
    );
  }

  replies(ticket: any) {
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
                {author && (
                  <Link href={userHref(author)}>
                    <img className="Avatar" src={author.avatarUrl()} alt={author.displayName()} />
                  </Link>
                )}
              </div>
              <div className="TicketReply-body">
                <div className="TicketReply-head">
                  <span className="TicketReply-author">
                    {author ? (
                      <Link className="TicketUserLink" href={userHref(author)}>
                        {author.displayName()}
                      </Link>
                    ) : (
                      T('unknown_user')
                    )}
                  </span>
                  {staff && <span className="TicketStaffTag">{T('staff')}</span>}
                  <span className="TicketReply-time">{formatDateTime(reply.createdAt())}</span>
                </div>
                <p className="TicketReply-text">{reply.message()}</p>
                {this.attachmentBlock(reply.attachment())}
              </div>
            </div>
          );
        })}
      </div>
    );
  }

  attachmentArea() {
    if (this.uploading) {
      return (
        <div className="AttachmentRow">
          <span className="AttachmentStatus">
            <i className="icon fas fa-circle-notch fa-spin" /> {T('uploading')}
          </span>
        </div>
      );
    }

    const filename = this.attachment();
    if (!filename) return null;

    const label = this.attachmentName() || T('attachment');

    return (
      <div className="AttachmentRow">
        {isImageAttachment(filename) ? (
          <span className="AttachmentPreview">
            <img className="AttachmentPreview-image" src={this.preview() || attachmentUrl(filename)} alt={label} />
            <button type="button" className="AttachmentRemove" title={T('remove_attachment')} onclick={() => this.clearAttachment()}>
              <i className="icon fas fa-times" />
            </button>
          </span>
        ) : (
          <span className="AttachmentChip">
            <i className="icon fas fa-paperclip" /> {label}
            <button type="button" className="AttachmentRemove" title={T('remove_attachment')} onclick={() => this.clearAttachment()}>
              <i className="icon fas fa-times" />
            </button>
          </span>
        )}
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
          onpaste={this.onPaste.bind(this)}
          placeholder={T('reply_placeholder')}
        />
        <div className="AttachmentHint">{T('attachment_hint')}</div>
        {this.attachmentArea()}
        <Button
          className="Button Button--primary"
          icon="fas fa-paper-plane"
          loading={this.submitting}
          disabled={this.submitting || this.uploading || !this.replyMessage()}
          onclick={this.submitReply.bind(this)}
        >
          {T('send_reply')}
        </Button>
      </div>
    );
  }

  onPaste(e: any) {
    const file = pastedImage(e);
    if (!file) return;

    // Stop the image from being pasted as a data URL into the textarea.
    e.preventDefault();
    this.attachFromFile(file);
  }

  async attachFromFile(file: File) {
    if (this.uploading) return;

    const problem = attachmentError(file);
    if (problem) {
      app.alerts.show({ type: 'error' }, T('upload_' + problem));
      return;
    }

    this.uploading = true;
    m.redraw();

    try {
      const response = await uploadAttachment(file);
      this.attachment(response.filename);
      this.attachmentName(response.originalName || file.name || '');
      // Show the picked image straight away; the server-side URL only becomes
      // readable once the reply is saved.
      this.setPreview(file);
    } catch (e: any) {
      app.alerts.show({ type: 'error' }, T('upload_failed'));
    } finally {
      this.uploading = false;
      m.redraw();
    }
  }

  setPreview(file: File) {
    if (file.type.indexOf('image/') !== 0) return;

    this.revokePreview();
    this.preview(URL.createObjectURL(file));
  }

  revokePreview() {
    if (this.preview()) {
      URL.revokeObjectURL(this.preview());
      this.preview('');
    }
  }

  clearAttachment() {
    this.revokePreview();
    this.attachment('');
    this.attachmentName('');
    m.redraw();
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
            <div className="TicketStaffActions-buttons">
              <Button className="Button Button--primary" icon="fas fa-circle-check" onclick={() => this.updateStatus('resolved')}>
                {T('mark_resolved')}
              </Button>
              <Button className="Button" icon="fas fa-lock" onclick={() => this.updateStatus('closed')}>
                {T('close_ticket')}
              </Button>
            </div>
          )}
        </div>
      </div>
    );
  }

  async submitReply() {
    if (!this.replyMessage() || this.submitting || this.uploading) return;

    this.submitting = true;
    m.redraw();

    try {
      const data: any = {
        message: this.replyMessage(),
        relationships: { ticket: this.ticket },
      };

      if (this.attachment()) {
        data.attachment = this.attachment();
      }

      await app.store.createRecord('ticket-replies').save(data);

      this.replyMessage('');
      this.revokePreview();
      this.attachment('');
      this.attachmentName('');
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
