import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import Button from 'flarum/common/components/Button';
import app from 'flarum/admin/app';

export default class SettingsPage extends ExtensionPage {
  oninit(vnode: any) {
    super.oninit(vnode);
    this.categories = [];
    this.loadCategories();
  }

  async loadCategories() {
    this.categories = await app.store.find('ticket-categories');
    m.redraw();
  }

  content() {
    return (
      <div className="ExtensionPage-settings">
        <div className="Form">
          <h3>{app.translator.trans('cengizkemec-support.admin.settings')}</h3>

          {this.buildSettingComponent({
            setting: 'cengizkemec-support.auto_close_days',
            type: 'number',
            label: app.translator.trans('cengizkemec-support.admin.auto_close_days'),
            min: 1,
            max: 365,
          })}

          {this.buildSettingComponent({
            setting: 'cengizkemec-support.email_notifications',
            type: 'boolean',
            label: app.translator.trans('cengizkemec-support.admin.email_notifications'),
          })}

          <div className="Form-group">
            {this.submitButton()}
          </div>

          <hr />

          <h3>{app.translator.trans('cengizkemec-support.admin.categories')}</h3>
          <ul>
            {this.categories.map((cat: any) => (
              <li key={cat.id()}>
                {cat.name()} ({cat.slug()})
                <Button className="Button Button--danger Button--small" onclick={() => this.deleteCategory(cat)}>
                  Delete
                </Button>
              </li>
            ))}
          </ul>

          <Button className="Button Button--primary" onclick={() => this.createCategory()}>
            Add Category
          </Button>
        </div>
      </div>
    );
  }

  async createCategory() {
    const name = prompt('Category name');
    if (!name) return;

    const slug = name.toLowerCase().replace(/\s+/g, '-');
    await app.store.createRecord('ticket-categories').save({ name, slug, isActive: true });
    this.loadCategories();
  }

  async deleteCategory(cat: any) {
    if (confirm('Delete this category?')) {
      await cat.delete();
      this.loadCategories();
    }
  }
}
