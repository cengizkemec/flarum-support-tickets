import Modal from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';
import Stream from 'flarum/common/utils/Stream';
import app from 'flarum/forum/app';

const T = (key: string, params?: any) => app.translator.trans('cengizkemec-support.forum.' + key, params);

const PRIORITIES = ['low', 'medium', 'high', 'critical'];

export default class CreateTicketModal extends Modal {
  subject = Stream('');
  message = Stream('');
  priority = Stream('medium');
  categoryId = Stream('');
  attachmentUrl = Stream('');
  attachmentName = Stream('');
  categories: any[] = [];
  loading = false;
  uploading = false;

  oninit(vnode: any) {
    super.oninit(vnode);

    app.store.find('ticket-categories').then((cats) => {
      this.categories = (cats as any[]).filter((c: any) => c.isActive());
      m.redraw();
    });
  }

  className() {
    return 'CreateTicketModal Modal--large';
  }

  title() {
    return T('new_ticket');
  }

  selectedCategory(): any {
    if (!this.categoryId()) return null;
    return app.store.getById('ticket-categories', this.categoryId());
  }

  content() {
    const selected = this.selectedCategory();

    return (
      <div className="Modal-body">
        <div className="Form">
          <div className="Form-group">
            <label>{T('subject')}</label>
            <input
              className="FormControl"
              value={this.subject()}
              oninput={(e: any) => this.subject(e.target.value)}
              placeholder={T('subject_placeholder')}
              required
            />
          </div>

          <div className="Form-group">
            <label>{T('category')}</label>
            <select className="FormControl" value={this.categoryId()} onchange={(e: any) => this.categoryId(e.target.value)}>
              <option value="">{T('category_none')}</option>
              {this.categories.map((cat: any) => (
                <option value={String(cat.id())}>{cat.name()}</option>
              ))}
            </select>
            {selected && selected.description() && <div className="TicketCategoryHint">{selected.description()}</div>}
          </div>

          <div className="Form-group">
            <label>{T('priority_label')}</label>
            <select className="FormControl" value={this.priority()} onchange={(e: any) => this.priority(e.target.value)}>
              {PRIORITIES.map((p) => (
                <option value={p}>{T('priority.' + p)}</option>
              ))}
            </select>
          </div>

          <div className="Form-group">
            <label>{T('message')}</label>
            <textarea
              className="FormControl"
              rows={6}
              value={this.message()}
              oninput={(e: any) => this.message(e.target.value)}
              placeholder={T('message_placeholder')}
              required
            />
          </div>

          <div className="Form-group">
            <label>{T('attachment')}</label>
            <div className="TicketUploadRow">
              <input type="file" className="FormControl" onchange={this.uploadFile.bind(this)} disabled={this.uploading} />
              {this.uploading && <span className="TicketUploadStatus">{T('uploading')}</span>}
              {!this.uploading && this.attachmentName() && (
                <span className="TicketUploadDone">
                  <i className="icon fas fa-check" /> {this.attachmentName()}
                </span>
              )}
            </div>
          </div>

          <div className="Form-group">
            <Button
              className="Button Button--primary"
              icon="fas fa-paper-plane"
              loading={this.loading}
              disabled={this.loading || !this.subject() || !this.message()}
              onclick={this.submit.bind(this)}
            >
              {T('create')}
            </Button>
          </div>
        </div>
      </div>
    );
  }

  async uploadFile(e: any) {
    const file = e.target.files[0];
    if (!file) return;

    this.uploading = true;
    m.redraw();

    const formData = new FormData();
    formData.append('file', file);

    try {
      const response = await app.request({
        method: 'POST',
        url: app.forum.attribute('apiUrl') + '/support/upload',
        body: formData,
      });

      this.attachmentUrl(response.filename);
      this.attachmentName(response.originalName || file.name);
    } catch (err) {
      app.alerts.show({ type: 'error' }, T('upload_failed'));
    } finally {
      this.uploading = false;
      m.redraw();
    }
  }

  async submit() {
    this.loading = true;
    m.redraw();

    try {
      const data: any = {
        subject: this.subject(),
        message: this.message(),
        priority: this.priority(),
      };

      if (this.attachmentUrl()) {
        data.attachment = this.attachmentUrl();
      }

      if (this.categoryId()) {
        data.relationships = {
          category: app.store.getById('ticket-categories', this.categoryId()),
        };
      }

      const ticket = await app.store.createRecord('tickets').save(data);

      app.modal.close();
      m.route.set(app.route('support.ticket', { id: ticket.id() }));
    } catch (e: any) {
      this.loading = false;
      app.alerts.show({ type: 'error' }, e?.response?.errors?.[0]?.detail || e?.message || T('error_create'));
      m.redraw();
    }
  }
}
