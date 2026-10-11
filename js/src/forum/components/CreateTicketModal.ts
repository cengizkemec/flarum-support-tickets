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
    this.loading = false;
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
            <label>{app.translator.trans('acme-support.forum.subject')}</label>
            <input className="FormControl" value={this.subject()} oninput={(e: any) => this.subject(e.target.value)} />
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
            <textarea className="FormControl" rows={6} value={this.message()} oninput={(e: any) => this.message(e.target.value)} />
          </div>

          <div className="Form-group">
            <Button className="Button Button--primary" loading={this.loading} disabled={this.loading} onclick={this.submit.bind(this)}>
              {app.translator.trans('acme-support.forum.create')}
            </Button>
          </div>
        </div>
      </div>
    );
  }

  async submit() {
    this.loading = true;

    try {
      const ticket = await app.store.createRecord('tickets').save({
        subject: this.subject(),
        message: this.message(),
        priority: this.priority(),
        status: 'new',
      });

      app.modal.close();
      m.route.set(app.route('support.ticket', { id: ticket.id() }));
    } catch (e) {
      this.loading = false;
      m.redraw();
    }
  }
}
