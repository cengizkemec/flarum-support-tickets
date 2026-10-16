import app from 'flarum/admin/app';
import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import Button from 'flarum/common/components/Button';
import Form from 'flarum/common/components/Form';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Switch from 'flarum/common/components/Switch';
import extractText from 'flarum/common/utils/extractText';
import type Mithril from 'mithril';
import TicketCategory from '../../common/models/TicketCategory';
import TicketFormField from '../../common/models/TicketFormField';
import { fieldName, slugify } from '../../common/utils/slug';

// Kept exported for backwards compatibility with the forum bundle.
export { slugify };

interface CategoryDraft {
  name: string;
  slug: string;
  slugTouched: boolean;
  description: string;
  isActive: boolean;
}

const emptyDraft = (): CategoryDraft => ({
  name: '',
  slug: '',
  slugTouched: false,
  description: '',
  isActive: true,
});

/** Field types the administrator can add to the ticket form. */
const FIELD_TYPES = ['text', 'textarea', 'number', 'email', 'url', 'date', 'select', 'radio', 'checkbox'];

/** Types that need a list of choices. */
const CHOICE_TYPES = ['select', 'radio', 'checkbox'];

interface FieldDraft {
  label: string;
  name: string;
  nameTouched: boolean;
  type: string;
  /** One choice per line, as typed by the administrator. */
  optionsText: string;
  isRequired: boolean;
  isActive: boolean;
  categoryId: string;
}

const emptyFieldDraft = (): FieldDraft => ({
  label: '',
  name: '',
  nameTouched: false,
  type: 'text',
  optionsText: '',
  isRequired: false,
  isActive: true,
  categoryId: '',
});

export default class SettingsPage extends ExtensionPage {
  categories: TicketCategory[] = [];
  loadingCategories = true;
  categoriesError = false;

  showForm = false;
  creating = false;
  draft: CategoryDraft = emptyDraft();

  /** Admin-defined extra fields users fill in when opening a ticket. */
  formFields: TicketFormField[] = [];
  loadingFields = true;
  fieldsError = false;
  showFieldForm = false;
  creatingField = false;
  fieldDraft: FieldDraft = emptyFieldDraft();

  /** Id of the category currently being saved/deleted, used for per-row spinners. */
  busyCategoryId: string | null = null;

  /** Id of the form field currently being saved/deleted. */
  busyFieldId: string | null = null;

  oninit(vnode: any) {
    super.oninit(vnode);
    this.loadCategories();
    this.loadFormFields();
  }

  async loadFormFields() {
    this.loadingFields = true;
    this.fieldsError = false;
    m.redraw();

    try {
      const result = await app.store.find('ticket-form-fields', { sort: 'position', include: 'category' });
      const list = Array.isArray(result) ? result : [];

      this.formFields = list.filter(Boolean) as TicketFormField[];
      this.formFields.sort(
        (a, b) => (Number(a.attribute('position')) || 0) - (Number(b.attribute('position')) || 0)
      );
    } catch (e) {
      this.formFields = [];
      this.fieldsError = true;
    } finally {
      this.loadingFields = false;
      m.redraw();
    }
  }

  async loadCategories() {
    this.loadingCategories = true;
    this.categoriesError = false;
    m.redraw();

    try {
      const result = await app.store.find('ticket-categories');
      const list = Array.isArray(result) ? result : [];

      // Guard against unregistered/invalid payloads: every entry must be a model.
      this.categories = list.filter(Boolean) as TicketCategory[];
      this.categories.sort(
        (a, b) => (Number(a.attribute('position')) || 0) - (Number(b.attribute('position')) || 0)
      );
    } catch (e) {
      this.categories = [];
      this.categoriesError = true;
    } finally {
      this.loadingCategories = false;
      m.redraw();
    }
  }

  /**
   * The settings we manage ourselves (they are not registered in the admin
   * registry), so submit/reset buttons know about them.
   */
  protected settingsEntries() {
    return [
      {
        key: 'cengizkemec-support.auto_close_days',
        label: app.translator.trans('cengizkemec-support.admin.auto_close_days'),
      },
      {
        key: 'cengizkemec-support.email_notifications',
        label: app.translator.trans('cengizkemec-support.admin.email_notifications'),
      },
      {
        key: 'cengizkemec-support.staff_group_id',
        label: app.translator.trans('cengizkemec-support.admin.staff_group'),
      },
      {
        key: 'cengizkemec-support.auto_assign',
        label: app.translator.trans('cengizkemec-support.admin.auto_assign'),
      },
    ];
  }

  /**
   * The support team is a real Flarum group, so the administrator can simply
   * point at the group they already created (e.g. "Destek Ekibi") instead of
   * granting permissions member by member.
   */
  protected groupOptions(): Record<string, string> {
    const options: Record<string, string> = {
      '': app.translator.trans('cengizkemec-support.admin.staff_group_none') as string,
    };

    const groups = (app.store.all('groups') as any[]) || [];

    groups
      .slice()
      .sort((a: any, b: any) => String(a.nameSingular()).localeCompare(String(b.nameSingular())))
      .forEach((group: any) => {
        const id = String(group.id());

        // Guests can never own a ticket; showing the group would only invite a
        // misconfiguration.
        if (id === '2') return;

        options[id] = group.nameSingular();
      });

    return options;
  }

  content() {
    return (
      <div className="ExtensionPage-settings">
        <div className="container">{this.settingsSection()}</div>
        <div className="container SupportAdmin">
          <hr className="SupportAdmin-divider" />
          {this.formsSection()}
          <hr className="SupportAdmin-divider" />
          {this.categoriesSection()}
        </div>
      </div>
    );
  }

  protected settingsSection() {
    return (
      <Form>
        <h3 className="ExtensionPage-subHeader">
          {app.translator.trans('cengizkemec-support.admin.settings')}
        </h3>

        {this.buildSettingComponent({
          setting: 'cengizkemec-support.auto_close_days',
          type: 'number',
          label: app.translator.trans('cengizkemec-support.admin.auto_close_days'),
          help: app.translator.trans('cengizkemec-support.admin.auto_close_days_help'),
          min: 1,
          max: 365,
        })}

        {this.buildSettingComponent({
          setting: 'cengizkemec-support.email_notifications',
          type: 'boolean',
          label: app.translator.trans('cengizkemec-support.admin.email_notifications'),
          help: app.translator.trans('cengizkemec-support.admin.email_notifications_help'),
        })}

        {this.buildSettingComponent({
          setting: 'cengizkemec-support.staff_group_id',
          type: 'select',
          label: app.translator.trans('cengizkemec-support.admin.staff_group'),
          help: app.translator.trans('cengizkemec-support.admin.staff_group_help'),
          options: this.groupOptions(),
        })}

        {this.buildSettingComponent({
          setting: 'cengizkemec-support.auto_assign',
          type: 'boolean',
          label: app.translator.trans('cengizkemec-support.admin.auto_assign'),
          help: app.translator.trans('cengizkemec-support.admin.auto_assign_help'),
        })}

        <div className="Form-group Form-controls">
          {this.submitButton()}
          {this.resetButton(
            this.settingsEntries(),
            app.translator.trans(
              'core.admin.extension.reset_settings.title_extension',
              { extensionTitle: this.extension.extra['flarum-extension'].title },
              true
            ),
            this.extension.id
          )}
        </div>
      </Form>
    );
  }

  /**
   * HESK-style ticket form builder: the administrator decides which extra
   * questions a user answers when they open a ticket.
   */
  protected formsSection() {
    return (
      <div className="SupportAdmin-section">
        <div className="SupportAdmin-header">
          <div className="SupportAdmin-headerText">
            <h3 className="ExtensionPage-subHeader">
              {app.translator.trans('cengizkemec-support.admin.forms.title')}
            </h3>
            <p className="helpText">{app.translator.trans('cengizkemec-support.admin.forms.help')}</p>
          </div>

          <Button
            className="Button Button--primary"
            icon={this.showFieldForm ? 'fas fa-times' : 'fas fa-plus'}
            disabled={this.loadingFields}
            onclick={() => {
              this.showFieldForm = !this.showFieldForm;
              if (this.showFieldForm) this.fieldDraft = emptyFieldDraft();
              m.redraw();
            }}
          >
            {this.showFieldForm
              ? app.translator.trans('cengizkemec-support.admin.forms.cancel')
              : app.translator.trans('cengizkemec-support.admin.forms.add')}
          </Button>
        </div>

        {this.showFieldForm && this.fieldForm()}

        {this.fieldsBody()}
      </div>
    );
  }

  protected fieldForm() {
    const draft = this.fieldDraft;
    const needsOptions = CHOICE_TYPES.indexOf(draft.type) >= 0;
    const options = this.parseOptions(draft.optionsText);

    const valid =
      !!draft.label.trim() &&
      !!fieldName(draft.name || draft.label) &&
      (!needsOptions || options.length > 0);

    return (
      <div className="Form SupportAdmin-form">
        <div className="Form-group">
          <label>{app.translator.trans('cengizkemec-support.admin.forms.field_label')}</label>
          <input
            className="FormControl"
            value={draft.label}
            oninput={(event: Event) => {
              draft.label = (event.target as HTMLInputElement).value;
              if (!draft.nameTouched) draft.name = fieldName(draft.label);
            }}
          />
          <p className="helpText">
            {app.translator.trans('cengizkemec-support.admin.forms.field_label_help')}
          </p>
        </div>

        <div className="Form-group">
          <label>{app.translator.trans('cengizkemec-support.admin.forms.field_name')}</label>
          <input
            className="FormControl"
            value={draft.name}
            oninput={(event: Event) => {
              draft.name = (event.target as HTMLInputElement).value;
              draft.nameTouched = true;
            }}
          />
          <p className="helpText">
            {app.translator.trans('cengizkemec-support.admin.forms.field_name_help', {
              name: fieldName(draft.name || draft.label) || '-',
            })}
          </p>
        </div>

        <div className="Form-group">
          <label>{app.translator.trans('cengizkemec-support.admin.forms.field_type')}</label>
          <select
            className="FormControl"
            value={draft.type}
            onchange={(event: Event) => {
              draft.type = (event.target as HTMLSelectElement).value;
            }}
          >
            {FIELD_TYPES.map((type) => (
              <option value={type}>
                {app.translator.trans('cengizkemec-support.admin.forms.type.' + type)}
              </option>
            ))}
          </select>
        </div>

        {needsOptions && (
          <div className="Form-group">
            <label>{app.translator.trans('cengizkemec-support.admin.forms.field_options')}</label>
            <textarea
              className="FormControl"
              rows={4}
              value={draft.optionsText}
              oninput={(event: Event) => {
                draft.optionsText = (event.target as HTMLTextAreaElement).value;
              }}
            />
            <p className="helpText">
              {app.translator.trans('cengizkemec-support.admin.forms.field_options_help')}
            </p>
          </div>
        )}

        <div className="Form-group">
          <label>{app.translator.trans('cengizkemec-support.admin.forms.field_category')}</label>
          <select
            className="FormControl"
            value={draft.categoryId}
            onchange={(event: Event) => {
              draft.categoryId = (event.target as HTMLSelectElement).value;
            }}
          >
            <option value="">{app.translator.trans('cengizkemec-support.admin.forms.category_all')}</option>
            {this.categories.map((category) => (
              <option value={String(category.id())}>{category.name()}</option>
            ))}
          </select>
          <p className="helpText">
            {app.translator.trans('cengizkemec-support.admin.forms.field_category_help')}
          </p>
        </div>

        <div className="Form-group">
          <Switch state={draft.isRequired} onchange={(value: boolean) => (draft.isRequired = value)}>
            {app.translator.trans('cengizkemec-support.admin.forms.field_required')}
          </Switch>
        </div>

        <div className="Form-group">
          <Switch state={draft.isActive} onchange={(value: boolean) => (draft.isActive = value)}>
            {app.translator.trans('cengizkemec-support.admin.forms.field_active')}
          </Switch>
        </div>

        <div className="Form-group Form-controls">
          <Button
            className="Button Button--primary"
            icon="fas fa-plus"
            loading={this.creatingField}
            disabled={this.creatingField || !valid}
            onclick={() => this.createField()}
          >
            {app.translator.trans('cengizkemec-support.admin.forms.create')}
          </Button>

          <Button
            className="Button"
            disabled={this.creatingField}
            onclick={() => {
              this.showFieldForm = false;
              this.fieldDraft = emptyFieldDraft();
              m.redraw();
            }}
          >
            {app.translator.trans('cengizkemec-support.admin.forms.cancel')}
          </Button>
        </div>
      </div>
    );
  }

  protected fieldsBody() {
    if (this.loadingFields) {
      return (
        <div className="SupportAdmin-state">
          <LoadingIndicator />
        </div>
      );
    }

    if (this.fieldsError) {
      return (
        <div className="SupportAdmin-state">
          <div className="Alert Alert--warning">
            <span className="Alert-body">
              {app.translator.trans('cengizkemec-support.admin.forms.load_error')}
            </span>
          </div>
        </div>
      );
    }

    if (!this.formFields.length) {
      return (
        <div className="SupportAdmin-state">
          <p className="helpText">{app.translator.trans('cengizkemec-support.admin.forms.empty')}</p>
        </div>
      );
    }

    return (
      <table className="SupportAdmin-table">
        <thead>
          <tr>
            <th>{app.translator.trans('cengizkemec-support.admin.forms.field_label')}</th>
            <th>{app.translator.trans('cengizkemec-support.admin.forms.field_name')}</th>
            <th>{app.translator.trans('cengizkemec-support.admin.forms.field_type')}</th>
            <th>{app.translator.trans('cengizkemec-support.admin.forms.field_category')}</th>
            <th>{app.translator.trans('cengizkemec-support.admin.forms.field_required')}</th>
            <th>{app.translator.trans('cengizkemec-support.admin.forms.field_active')}</th>
            <th className="SupportAdmin-actionsColumn" />
          </tr>
        </thead>
        <tbody>{this.formFields.map((field) => this.renderFieldRow(field))}</tbody>
      </table>
    );
  }

  protected renderFieldRow(field: TicketFormField) {
    const id = String(field.id());
    const busy = this.busyFieldId === id;
    const category = field.category();

    return (
      <tr key={id}>
        <td>
          <strong>{field.label()}</strong>
        </td>
        <td>
          <code>{field.name()}</code>
        </td>
        <td>{app.translator.trans('cengizkemec-support.admin.forms.type.' + field.type())}</td>
        <td className="SupportAdmin-description">{category ? category.name() : app.translator.trans('cengizkemec-support.admin.forms.category_all')}</td>
        <td className="SupportAdmin-position">{field.isRequired() ? '✓' : '—'}</td>
        <td>
          <Switch state={!!field.isActive()} loading={busy} onchange={() => this.toggleFieldActive(field)} />
        </td>
        <td className="SupportAdmin-actionsColumn">
          <Button
            className="Button Button--danger Button--small"
            icon="fas fa-trash-alt"
            loading={busy}
            disabled={busy}
            onclick={() => this.deleteField(field)}
          >
            {app.translator.trans('cengizkemec-support.admin.forms.delete')}
          </Button>
        </td>
      </tr>
    );
  }

  /** Turn the textarea content into a clean list of choices. */
  protected parseOptions(text: string): string[] {
    return (text || '')
      .split('\n')
      .map((line) => line.trim())
      .filter((line) => line !== '');
  }

  protected nextFieldPosition(): number {
    return (
      this.formFields.reduce(
        (max, field) => Math.max(max, Number(field.attribute('position')) || 0),
        0
      ) + 1
    );
  }

  async createField() {
    const label = this.fieldDraft.label.trim();
    const name = fieldName(this.fieldDraft.name || label);
    const needsOptions = CHOICE_TYPES.indexOf(this.fieldDraft.type) >= 0;
    const options = needsOptions ? this.parseOptions(this.fieldDraft.optionsText) : [];

    if (!label || !name) return;
    if (needsOptions && !options.length) return;

    this.creatingField = true;
    m.redraw();

    try {
      const data: any = {
        label,
        name,
        type: this.fieldDraft.type,
        options,
        isRequired: this.fieldDraft.isRequired,
        isActive: this.fieldDraft.isActive,
        position: this.nextFieldPosition(),
      };

      if (this.fieldDraft.categoryId) {
        data.relationships = {
          category: app.store.getById('ticket-categories', this.fieldDraft.categoryId),
        };
      }

      await app.store.createRecord('ticket-form-fields').save(data);

      this.fieldDraft = emptyFieldDraft();
      this.showFieldForm = false;

      app.alerts.show(
        { type: 'success' },
        app.translator.trans('cengizkemec-support.admin.forms.created')
      );

      await this.loadFormFields();
    } catch (e) {
      this.showError(e, 'cengizkemec-support.admin.forms.save_error');
    } finally {
      this.creatingField = false;
      m.redraw();
    }
  }

  async toggleFieldActive(field: TicketFormField) {
    const id = String(field.id());

    this.busyFieldId = id;
    m.redraw();

    try {
      await field.save({ isActive: !field.isActive() });
    } catch (e) {
      this.showError(e, 'cengizkemec-support.admin.forms.save_error');
      await this.loadFormFields();
    } finally {
      this.busyFieldId = null;
      m.redraw();
    }
  }

  async deleteField(field: TicketFormField) {
    const id = String(field.id());
    const label = field.label();

    const confirmed = confirm(
      extractText(
        app.translator.trans('cengizkemec-support.admin.forms.confirm_delete', { label })
      )
    );

    if (!confirmed) return;

    this.busyFieldId = id;
    m.redraw();

    try {
      await field.delete();

      this.formFields = this.formFields.filter((item) => String(item.id()) !== id);

      app.alerts.show(
        { type: 'success' },
        app.translator.trans('cengizkemec-support.admin.forms.deleted')
      );
    } catch (e) {
      this.showError(e, 'cengizkemec-support.admin.forms.delete_error');
      await this.loadFormFields();
    } finally {
      this.busyFieldId = null;
      m.redraw();
    }
  }

  protected categoriesSection() {
    return (
      <div className="SupportAdmin-section">
        <div className="SupportAdmin-header">
          <div className="SupportAdmin-headerText">
            <h3 className="ExtensionPage-subHeader">
              {app.translator.trans('cengizkemec-support.admin.categories.title')}
            </h3>
            <p className="helpText">{app.translator.trans('cengizkemec-support.admin.categories.help')}</p>
          </div>

          <Button
            className="Button Button--primary"
            icon={this.showForm ? 'fas fa-times' : 'fas fa-plus'}
            disabled={this.loadingCategories}
            onclick={() => {
              this.showForm = !this.showForm;
              if (this.showForm) this.draft = emptyDraft();
              m.redraw();
            }}
          >
            {this.showForm
              ? app.translator.trans('cengizkemec-support.admin.categories.cancel')
              : app.translator.trans('cengizkemec-support.admin.categories.add')}
          </Button>
        </div>

        {this.showForm && this.categoryForm()}

        {this.categoriesBody()}
      </div>
    );
  }

  protected categoryForm() {
    const draft = this.draft;
    const valid = !!draft.name.trim() && !!slugify(draft.slug || draft.name).length;

    return (
      <div className="Form SupportAdmin-form">
        <div className="Form-group">
          <label>{app.translator.trans('cengizkemec-support.admin.categories.field_name')}</label>
          <input
            className="FormControl"
            value={draft.name}
            oninput={(event: Event) => {
              draft.name = (event.target as HTMLInputElement).value;
              if (!draft.slugTouched) draft.slug = slugify(draft.name);
            }}
          />
        </div>

        <div className="Form-group">
          <label>{app.translator.trans('cengizkemec-support.admin.categories.field_slug')}</label>
          <input
            className="FormControl"
            value={draft.slug}
            oninput={(event: Event) => {
              draft.slug = (event.target as HTMLInputElement).value;
              draft.slugTouched = true;
            }}
          />
          <p className="helpText">
            {app.translator.trans('cengizkemec-support.admin.categories.field_slug_help', {
              slug: slugify(draft.slug || draft.name) || '-',
            })}
          </p>
        </div>

        <div className="Form-group">
          <label>{app.translator.trans('cengizkemec-support.admin.categories.field_description')}</label>
          <textarea
            className="FormControl"
            rows={2}
            value={draft.description}
            oninput={(event: Event) => {
              draft.description = (event.target as HTMLTextAreaElement).value;
            }}
          />
        </div>

        <div className="Form-group">
          <Switch state={draft.isActive} onchange={(value: boolean) => (draft.isActive = value)}>
            {app.translator.trans('cengizkemec-support.admin.categories.field_active')}
          </Switch>
          <p className="helpText">
            {app.translator.trans('cengizkemec-support.admin.categories.field_active_help')}
          </p>
        </div>

        <div className="Form-group Form-controls">
          <Button
            className="Button Button--primary"
            icon="fas fa-plus"
            loading={this.creating}
            disabled={this.creating || !valid}
            onclick={() => this.createCategory()}
          >
            {app.translator.trans('cengizkemec-support.admin.categories.create')}
          </Button>

          <Button
            className="Button"
            disabled={this.creating}
            onclick={() => {
              this.showForm = false;
              this.draft = emptyDraft();
              m.redraw();
            }}
          >
            {app.translator.trans('cengizkemec-support.admin.categories.cancel')}
          </Button>
        </div>
      </div>
    );
  }

  protected categoriesBody() {
    if (this.loadingCategories) {
      return (
        <div className="SupportAdmin-state">
          <LoadingIndicator />
        </div>
      );
    }

    if (this.categoriesError) {
      return (
        <div className="SupportAdmin-state">
          <div className="Alert Alert--warning">
            <span className="Alert-body">
              {app.translator.trans('cengizkemec-support.admin.categories.load_error')}
            </span>
          </div>
        </div>
      );
    }

    if (!this.categories.length) {
      return (
        <div className="SupportAdmin-state">
          <p className="helpText">
            {app.translator.trans('cengizkemec-support.admin.categories.empty')}
          </p>
        </div>
      );
    }

    return (
      <table className="SupportAdmin-table">
        <thead>
          <tr>
            <th>{app.translator.trans('cengizkemec-support.admin.categories.field_name')}</th>
            <th>{app.translator.trans('cengizkemec-support.admin.categories.field_slug')}</th>
            <th>{app.translator.trans('cengizkemec-support.admin.categories.field_description')}</th>
            <th>{app.translator.trans('cengizkemec-support.admin.categories.position')}</th>
            <th>{app.translator.trans('cengizkemec-support.admin.categories.field_active')}</th>
            <th className="SupportAdmin-actionsColumn" />
          </tr>
        </thead>
        <tbody>{this.categories.map((category) => this.renderCategoryRow(category))}</tbody>
      </table>
    );
  }

  protected renderCategoryRow(category: TicketCategory) {
    const id = String(category.id());
    const busy = this.busyCategoryId === id;

    return (
      <tr key={id}>
        <td>
          <strong>{category.name()}</strong>
        </td>
        <td>
          <code>{category.slug()}</code>
        </td>
        <td className="SupportAdmin-description">{category.description() || '—'}</td>
        <td className="SupportAdmin-position">
          <input className="FormControl" style={{width:'62px'}} type="number" value={Number(category.attribute('position')) || 0} onChange={(e) => {
            const pos = Number((e.target as HTMLInputElement).value) || 0;
            this.busyCategoryId = String(category.id());
            m.redraw();
            category.save({position: pos}).finally(() => { this.busyCategoryId = null; this.loadCategories(); });
          }} />
        </td>
        <td>
          <Switch state={!!category.isActive()} loading={busy} onchange={() => this.toggleActive(category)} />
        </td>
        <td className="SupportAdmin-actionsColumn">
          <Button
            className="Button Button--danger Button--small"
            icon="fas fa-trash-alt"
            loading={busy}
            disabled={busy}
            onclick={() => this.deleteCategory(category)}
          >
            {app.translator.trans('cengizkemec-support.admin.categories.delete')}
          </Button>
        </td>
      </tr>
    );
  }

  async createCategory() {
    const name = this.draft.name.trim();
    const slug = slugify(this.draft.slug || name);

    if (!name || !slug) return;

    this.creating = true;
    m.redraw();

    try {
      await app.store.createRecord('ticket-categories').save({
        name,
        slug,
        description: this.draft.description.trim() || null,
        position: this.nextPosition(),
        isActive: this.draft.isActive,
      });

      this.draft = emptyDraft();
      this.showForm = false;

      app.alerts.show(
        { type: 'success' },
        app.translator.trans('cengizkemec-support.admin.categories.created')
      );

      await this.loadCategories();
    } catch (e) {
      this.showError(e, 'cengizkemec-support.admin.categories.save_error');
    } finally {
      this.creating = false;
      m.redraw();
    }
  }

  async toggleActive(category: TicketCategory) {
    const id = String(category.id());

    this.busyCategoryId = id;
    m.redraw();

    try {
      await category.save({ isActive: !category.isActive() });
    } catch (e) {
      // Roll back to the server state so the switch never lies about the value.
      this.showError(e, 'cengizkemec-support.admin.categories.save_error');
      await this.loadCategories();
    } finally {
      this.busyCategoryId = null;
      m.redraw();
    }
  }

  async deleteCategory(category: TicketCategory) {
    const id = String(category.id());
    const name = category.name();

    const confirmed = confirm(
      extractText(
        app.translator.trans('cengizkemec-support.admin.categories.confirm_delete', { name })
      )
    );

    if (!confirmed) return;

    this.busyCategoryId = id;
    m.redraw();

    try {
      await category.delete();

      this.categories = this.categories.filter((item) => String(item.id()) !== id);

      app.alerts.show(
        { type: 'success' },
        app.translator.trans('cengizkemec-support.admin.categories.deleted')
      );
    } catch (e) {
      this.showError(e, 'cengizkemec-support.admin.categories.delete_error');
      await this.loadCategories();
    } finally {
      this.busyCategoryId = null;
      m.redraw();
    }
  }

  /** Next free position, so newly created categories are appended in order. */
  protected nextPosition(): number {
    return this.categories.reduce(
      (max, category) => Math.max(max, Number(category.attribute('position')) || 0),
      0
    ) + 1;
  }

  protected showError(error: any, fallbackKey: string) {
    const detail =
      error &&
      error.response &&
      Array.isArray(error.response.errors) &&
      error.response.errors[0] &&
      error.response.errors[0].detail;

    app.alerts.show(
      { type: 'error' },
      detail || app.translator.trans(fallbackKey)
    );
  }
}
