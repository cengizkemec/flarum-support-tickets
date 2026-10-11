import ExtensionPage from 'flarum/admin/components/ExtensionPage';
import app from 'flarum/admin/app';

export default class SettingsPage extends ExtensionPage {
  content() {
    return (
      <div className="ExtensionPage-settings">
        <div className="Form">
          {this.buildSettingComponent({
            setting: 'acme-support.auto_close_days',
            type: 'number',
            label: app.translator.trans('acme-support.admin.auto_close_days'),
            min: 1,
            max: 365,
          })}

          {this.buildSettingComponent({
            setting: 'acme-support.email_notifications',
            type: 'boolean',
            label: app.translator.trans('acme-support.admin.email_notifications'),
          })}

          <div className="Form-group">
            {this.submitButton()}
          </div>
        </div>
      </div>
    );
  }
}
