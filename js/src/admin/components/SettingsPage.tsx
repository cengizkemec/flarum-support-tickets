import app from 'flarum/admin/app';
import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import Button from 'flarum/common/components/Button';
import Form from 'flarum/common/components/Form';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Switch from 'flarum/common/components/Switch';
import extractText from 'flarum/common/utils/extractText';
import type Mithril from 'mithril';
import TicketCategory from '../../common/models/TicketCategory';

/**
 * Turkish characters that have no ASCII decomposition (or differ from it),
 * so slugs stay valid and readable.
 */
const TURKISH_CHAR_MAP: Record<string, string> = {
  'ç': 'c',
  'Ç': 'c',
  'ğ': 'g',
  'Ğ': 'g',
  'ı': 'i',
  'İ': 'i',
  'ö': 'o',
  'Ö': 'o',
  'ş': 's',
  'Ş': 's',
  'ü': 'u',
  'Ü': 'u',
};

/**
 * Build a URL slug from a human readable name, handling Turkish characters.
 */
export function slugify(value: string): string {
  return value
    .replace(/[çÇğĞıİöÖşŞüÜ]/g, (char) => TURKISH_CHAR_MAP[char] || char)
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-+|-+$/g, '');
}

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

export default class SettingsPage extends ExtensionPage {
  categories: TicketCategory[] = [];
  loadingCategories = true;
  categoriesError = false;

  showForm = false;
  creating = false;
  draft: CategoryDraft = emptyDraft();

  /** Id of the category currently being saved/deleted, used for per-row spinners. */
  busyCategoryId: string | null = null;

  oninit(vnode: any) {
    super.oninit(vnode);
    this.loadCategories();
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
    ];
  }

  content() {
    return (
      <div className="ExtensionPage-settings">
        <div className="container">{this.settingsSection()}</div>
        <div className="container SupportAdmin">
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
        <td className="SupportAdmin-position">{Number(category.attribute('position')) || 0}</td>
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
