import Modal from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';
import Stream from 'flarum/common/utils/Stream';
import app from 'flarum/forum/app';

export default class CreateTicketModal extends Modal {
  oninit(vnode: any) {
    super.oninit(vnode);

    this.subject = Stream('');
    this.message = Stream('');
    this.priority = Stream('medium');
    this.categoryId = Stream('');
    this.attachmentUrl = Stream('');
    this.attachmentName = Stream('');
    this.categories = [];
    this.loading = false;
    this.uploading = false;

    app.store.find('ticket-categories').then((cats) => {
      this.categories = cats;
      m.redraw();
    });
  }

  className() {
    return 'CreateTicketModal Modal--large';
  }

  title() {
    return app.translator.trans('cengizkemec-support.forum.new_ticket');
  }

  content() {
    return (
      <div className="Modal-body">
        <div className="Form">
          <div className="Form-group">
            <label>{app.translator.trans('cengizkemec-support.forum.category')}</label>
            <select className="FormControl" value={this.categoryId()} onchange={(e: any) => this.categoryId(e.target.value)}>
              <option value="">--</option>
              {this.categories.map((cat: any) => (
                <option value={cat.id()}>{cat.name()}</option>
              ))}
            </select>
          </div>

          <div className="Form-group">
            <label>{app.translator.trans('cengizkemec-support.forum.subject')}</label>
            <input className="FormControl" value={this.subject()} oninput={(e: any) => this.subject(e.target.value)} required />
          </div>

          <div className="Form-group">
            <label>{app.translator.trans('cengizkemec-support.forum.priority')}</label>
            <select className="FormControl" value={this.priority()} onchange={(e: any) => this.priority(e.target.value)}>
              <option value="low">Low</option>
              <option value="medium">Medium</option>
              <option value="high">High</option>
              <option value="critical">Critical</option>
            </select>
          </div>

          <div className="Form-group">
            <label>{app.translator.trans('cengizkemec-support.forum.message')}</label>
            <textarea className="FormControl" rows={6} value={this.message()} oninput={(e: any) => this.message(e.target.value)} required />
          </div>

          <div className="Form-group">
            <label>{app.translator.trans('cengizkemec-support.forum.attachment')}</label>
            <input type="file" className="FormControl" onchange={this.uploadFile.bind(this)} disabled={this.uploading} />
            {this.uploading && <span>Uploading...</span>}
            {this.attachmentName() && <div>Uploaded: {this.attachmentName()}</div>}
          </div>

          <div className="Form-group">
            <Button className="Button Button--primary" loading={this.loading} disabled={this.loading || !this.subject() || !this.message()} onclick={this.submit.bind(this)}>
              {app.translator.trans('cengizkemec-support.forum.create')}
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
        // Let browser set Content-Type for multipart
      });

      this.attachmentUrl(response.filename);
      this.attachmentName(response.originalName || file.name);
    } catch (err) {
      app.alerts.show({ type: 'error' }, 'Upload failed');
    } finally {
      this.uploading = false;
      m.redraw();
    }
  }

  async submit() {
    this.loading = true;

    try {
      const data: any = {
        subject: this.subject(),
        message: this.message(),
        priority: this.priority(),
        status: 'new',
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
    } catch (e) {
      this.loading = false;
      m.redraw();
    }
  }
}
