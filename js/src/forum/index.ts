import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import IndexSidebar from 'flarum/forum/components/IndexSidebar';
import LinkButton from 'flarum/common/components/LinkButton';

export { default as extend } from './extend';

app.initializers.add('acme-support', () => {
  extend(IndexSidebar.prototype, 'navItems', function (items) {
    if (app.session.user) {
      items.add(
        'support',
        <LinkButton href={app.route('support')} icon="fas fa-headset">
          {app.translator.trans('acme-support.forum.nav_item')}
        </LinkButton>,
        75
      );
    }
  });
});
