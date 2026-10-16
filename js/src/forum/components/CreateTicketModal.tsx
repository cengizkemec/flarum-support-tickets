import Modal from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';
import Stream from 'flarum/common/utils/Stream';
import app from 'flarum/forum/app';
import { ticketUrl } from '../utils/ticketUrl';
import { attachmentError, attachmentUrl, isImageAttachment, pastedImage, uploadAttachment } from '../utils/attachments';

const T = (key: string, params?: any) => app.translator.trans('cengizkemec-support.forum.' + key, params);

const PRIORITIES = ['low', 'medium', 'high', 'critical'];

/** Field types rendered as <input type="..."> rather than a control widget. */
const INPUT_TYPES = ['text', 'number', 'email', 'url', 'date'];

export default class CreateTicketModal extends Modal {
  subject = Stream('');
  message = Stream('');
  priority = Stream('medium');
  categoryId = Stream('');
  attachment = Stream('');
  attachmentName = Stream('');
  /** Local object URL used to preview the file before it is submitted. */
  preview = Stream('');
  categories: any[] = [];
  /** Extra fields the administrator added to the ticket form (HESK style). */
  formFields: any[] = [];
  /** One stream per field, keyed by the field's machine name. */
  fieldStreams: Record<string, Stream<any>> = {};
  fieldsError = false;
  loading = false;
  uploading = false;

  oninit(vnode: any) {
    super.oninit(vnode);

    app.store.find('ticket-categories').then((cats) => {
      this.categories = (cats as any[]).filter((c: any) => c.isActive());
      m.redraw();
    });

    app.store
      .find('ticket-form-fields', { sort: 'position', include: 'category' })
      .then((fields) => {
        (fields as any[]).forEach((field: any) => {
          const name = field.name();
          if (name) this.fieldStreams[name] = Stream(field.type() === 'checkbox' ? [] : '');
        });
        this.formFields = fields as any[];
        m.redraw();
      })
      .catch(() => {
        // A missing definition must never block opening a ticket: the form
        // simply renders with the standard fields.
        this.fieldsError = true;
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

  /**
   * The custom fields that apply to the category the user has picked: global
   * fields (no category) are always shown, category specific ones only once
   * that category is chosen.
   */
  visibleFields(): any[] {
    const categoryId = this.categoryId();

    return this.formFields
      .filter((field: any) => {
        if (!field.isActive()) return false;

        const category = field.category();
        if (!category) return true;

        return !!categoryId && String(category.id()) === String(categoryId);
      })
      .sort((a: any, b: any) => (a.position() || 0) - (b.position() || 0));
  }

  fieldValue(field: any): any {
    const stream = this.fieldStreams[field.name()];
    return stream ? stream() : '';
  }

  toggleCheckbox(field: any, option: string) {
    const name = field.name();
    if (!this.fieldStreams[name]) this.fieldStreams[name] = Stream([]);

    const current: string[] = this.fieldStreams[name]() || [];
    const index = current.indexOf(option);

    this.fieldStreams[name](index >= 0 ? current.filter((v) => v !== option) : current.concat([option]));
    m.redraw();
  }

  renderField(field: any) {
    const name = field.name();
    const type = field.type();
    const required = !!field.isRequired();
    const options: string[] = (field.options() || []).filter((o: any) => typeof o === 'string' && o !== '');
    const stream = this.fieldStreams[name] || Stream('');

    if (!name) return null;

    let input: any;

    if (type === 'textarea') {
      input = <textarea className="FormControl" rows={4} value={stream()} oninput={(e: any) => stream(e.target.value)} />;
    } else if (type === 'select') {
      input = (
        <select className="FormControl" value={stream()} onchange={(e: any) => stream(e.target.value)}>
          <option value="">—</option>
          {options.map((option) => (
            <option value={option}>{option}</option>
          ))}
        </select>
      );
    } else if (type === 'radio') {
      input = (
        <div className="TicketChoices" role="radiogroup">
          {options.map((option) => (
            <label className="TicketChoice" key={option}>
              <input
                type="radio"
                name={'ticket-field-' + name}
                value={option}
                checked={stream() === option}
                onchange={() => stream(option)}
              />
              <span>{option}</span>
            </label>
          ))}
        </div>
      );
    } else if (type === 'checkbox') {
      const selected: string[] = stream() || [];
      input = (
        <div className="TicketChoices">
          {options.map((option) => (
            <label className="TicketChoice" key={option}>
              <input type="checkbox" checked={selected.indexOf(option) >= 0} onchange={() => this.toggleCheckbox(field, option)} />
              <span>{option}</span>
            </label>
          ))}
        </div>
      );
    } else if (INPUT_TYPES.indexOf(type) >= 0) {
      input = <input className="FormControl" type={type} value={stream()} oninput={(e: any) => stream(e.target.value)} />;
    } else {
      input = <input className="FormControl" type="text" value={stream()} oninput={(e: any) => stream(e.target.value)} />;
    }

    return (
      <div className={'Form-group TicketFormField' + (required ? ' is-required' : '')} key={name}>
        <label>
          {field.label()}
          {required && <span className="TicketFormField-required"> *</span>}
        </label>
        {input}
      </div>
    );
  }

  /**
   * Answers to the custom fields, ready to be sent with the ticket.
   */
  collectCustomFields(): Record<string, any> {
    const values: Record<string, any> = {};

    this.visibleFields().forEach((field: any) => {
      const name = field.name();
      const value = this.fieldValue(field);

      const empty = Array.isArray(value) ? value.length === 0 : value === '' || value === null || value === undefined;
      if (empty) return;

      values[name] = value;
    });

    return values;
  }

  /** @returns the translation key of the first missing field, or null. */
  firstUnfilledField(): any {
    const missing = this.visibleFields().filter((field: any) => {
      if (!field.isRequired()) return false;

      const value = this.fieldValue(field);
      return Array.isArray(value) ? value.length === 0 : value === '' || value === null || value === undefined;
    });

    return missing.length ? missing[0] : null;
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

          {this.visibleFields().map((field: any) => this.renderField(field))}

          <div className="Form-group">
            <label>{T('message')}</label>
            <textarea
              className="FormControl"
              rows={6}
              value={this.message()}
              oninput={(e: any) => this.message(e.target.value)}
              onpaste={this.onPaste.bind(this)}
              placeholder={T('message_placeholder')}
              required
            />
            <div className="AttachmentHint">{T('attachment_hint')}</div>
          </div>

          <div className="Form-group">
            <label>{T('attachment')}</label>
            <div className="TicketUploadRow">
              <input
                type="file"
                className="FormControl"
                accept="image/png,image/jpeg,image/gif,image/webp,application/pdf,text/plain,application/zip"
                onchange={this.onFileChange.bind(this)}
                disabled={this.uploading}
              />
              {this.uploading && <span className="AttachmentStatus">{T('uploading')}</span>}
            </div>
            {this.attachmentArea()}
          </div>

          <div className="Form-group">
            <Button
              className="Button Button--primary"
              icon="fas fa-paper-plane"
              loading={this.loading}
              disabled={
                this.loading ||
                this.uploading ||
                !this.subject() ||
                !this.message() ||
                !!this.firstUnfilledField()
              }
              onclick={this.submit.bind(this)}
            >
              {T('create')}
            </Button>
          </div>
        </div>
      </div>
    );
  }

  attachmentArea() {
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

  onPaste(e: any) {
    const file = pastedImage(e);
    if (!file) return;

    e.preventDefault();
    this.attach(file);
  }

  onFileChange(e: any) {
    const file = e.target.files && e.target.files[0];
    if (!file) return;

    this.attach(file);
  }

  async attach(file: File) {
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
      // Preview the local file: the server URL only works once saved.
      if (file.type.indexOf('image/') === 0) {
        this.revokePreview();
        this.preview(URL.createObjectURL(file));
      }
    } catch (err) {
      app.alerts.show({ type: 'error' }, T('upload_failed'));
    } finally {
      this.uploading = false;
      m.redraw();
    }
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

  async submit() {
    const missing = this.firstUnfilledField();
    if (missing) {
      app.alerts.show({ type: 'error' }, missing.label() + ': ' + T('field_required'));
      return;
    }

    this.loading = true;
    m.redraw();

    try {
      const data: any = {
        subject: this.subject(),
        message: this.message(),
        priority: this.priority(),
      };

      const customFields = this.collectCustomFields();
      if (Object.keys(customFields).length) {
        data.customFields = customFields;
      }

      if (this.attachment()) {
        data.attachment = this.attachment();
      }

      if (this.categoryId()) {
        data.relationships = {
          category: app.store.getById('ticket-categories', this.categoryId()),
        };
      }

      const ticket = await app.store.createRecord('tickets').save(data);

      this.revokePreview();
      app.modal.close();
      m.route.set(ticketUrl(ticket));
    } catch (e: any) {
      this.loading = false;
      app.alerts.show({ type: 'error' }, e?.response?.errors?.[0]?.detail || e?.message || T('error_create'));
      m.redraw();
    }
  }
}
