import Extend from 'flarum/common/extenders';
import app from 'flarum/admin/app';
import SettingsPage from './components/SettingsPage.tsx';

export default [
  new Extend.Admin()
    .page(SettingsPage)
    .permission(
      () => ({
        icon: 'fas fa-eye',
        label: app.translator.trans('cengizkemec-support.admin.permissions.view_all'),
        permission: 'support.viewAll',
      }),
      'moderate',
      90
    )
    .permission(
      () => ({
        icon: 'fas fa-reply',
        label: app.translator.trans('cengizkemec-support.admin.permissions.reply'),
        permission: 'support.reply',
      }),
      'reply',
      90
    )
    .permission(
      () => ({
        icon: 'fas fa-cog',
        label: app.translator.trans('cengizkemec-support.admin.permissions.manage'),
        permission: 'support.manage',
      }),
      'moderate',
      85
    ),
];
