import Page from 'flarum/common/components/Page';
import PageStructure from 'flarum/forum/components/PageStructure';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import app from 'flarum/forum/app';
import CreateTicketModal from './CreateTicketModal';
import TicketList from './TicketList';

export default class SupportPage extends Page {
  oninit(vnode: any) {
    super.oninit(vnode);
    this.loading = true;

    app.store
      .find('tickets', { sort: '-createdAt', include: 'category,assignee,user' })
      .then(() => {
        this.loading = false;
        m.redraw();
      })
      .catch(() => {
        this.loading = false;
        m.redraw();
      });
  }

  view() {
    return (
      <PageStructure className="SupportPage" hero={() => <h2>{app.translator.trans('cengizkemec-support.forum.title')}</h2>}>
        <div className="SupportPage-toolbar">
          <Button className="Button Button--primary" onclick={() => app.modal.show(CreateTicketModal)}>
            {app.translator.trans('cengizkemec-support.forum.new_ticket')}
          </Button>
        </div>

        {this.loading ? <LoadingIndicator /> : <TicketList />}
      </PageStructure>
    );
  }
}
