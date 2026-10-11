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
    this.attachment = Stream('');
    this.categories = [];
    this.loading = false;

    app.store.find('ticket-categories').then((cats) => {
      this.categories = cats;
      m.redraw();
    });
  }

  className() {
    return 'CreateTicketModal Modal--large';
  }

  title() {
    return app.translator.trans('acme-support.forum.new_ticket');
  }

  content() {
    return (
      <div className="Modal-body">
        <div className="Form">
          <div className="Form-group">
            <label>{app.translator.trans('acme-support.forum.category')}</label>
            <select className="FormControl" value={this.categoryId()} onchange={(e: any) => this.categoryId(e.target.value)}>
              <option value="">--</option>
              {this.categories.map((cat: any) => (
                <option value={cat.id()}>{cat.name()}</option>
              ))}
            </select>
          </div>

          <div className="Form-group">
            <label>{app.translator.trans('acme-support.forum.subject')}</label>
            <input className="FormControl" value={this.subject()} oninput={(e: any) => this.subject(e.target.value)} required />
          </div>

          <div className="Form-group">
            <label>{app.translator.trans('acme-support.forum.priority')}</label>
            <select className="FormControl" value={this.priority()} onchange={(e: any) => this.priority(e.target.value)}>
              <option value="low">Low</option>
              <option value="medium">Medium</option>
              <option value="high">High</option>
              <option value="critical">Critical</option>
            </select>
          </div>

          <div className="Form-group">
            <label>{app.translator.trans('acme-support.forum.message')}</label>
            <textarea className="FormControl" rows={6} value={this.message()} oninput={(e: any) => this.message(e.target.value)} required />
          </div>

          <div className="Form-group">
            <label>{app.translator.trans('acme-support.forum.attachment')}</label>
            <input type="file" className="FormControl" onchange={(e: any) => this.handleFile(e)} />
            <small>File upload is prepared — integrate with Flarum filesystem or fof/upload for full support.</small>
          </div>

          <div className="Form-group">
            <Button className="Button Button--primary" loading={this.loading} disabled={this.loading || !this.subject() || !this.message()} onclick={this.submit.bind(this)}>
              {app.translator.trans('acme-support.forum.create')}
            </Button>
          </div>
        </div>
      </div>
    );
  }

  handleFile(e: any) {
    const file = e.target.files[0];
    if (file) {
      // Placeholder: in production convert to base64 or upload via API
      this.attachment(file.name);
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

      if (this.categoryId()) {
        data.relationships = {
          category: app.store.getById('ticket-categories', this.categoryId()),
        };
      }

      if (this.attachment()) {
        data.attachment = this.attachment();
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
