import Page from 'flarum/common/components/Page';
import PageStructure from 'flarum/forum/components/PageStructure';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import app from 'flarum/forum/app';
import CreateTicketModal from './CreateTicketModal';
import TicketList from './TicketList';

const T = (key: string, params?: any) => app.translator.trans('cengizkemec-support.forum.' + key, params);

const OPEN_STATUSES = ['new', 'answered', 'pending'];

export default class SupportPage extends Page {
  loading = true;
  filter = 'all';
  error: string | null = null;

  oninit(vnode: any) {
    super.oninit(vnode);

    app.store
      .find('tickets', { sort: '-createdAt', include: 'category,assignee,user,replies' })
      .then(() => {
        this.loading = false;
        m.redraw();
      })
      .catch((e: any) => {
        this.loading = false;
        this.error = e?.response?.errors?.[0]?.detail || e?.message || 'Error';
        m.redraw();
      });
  }

  view() {
    const isStaff = !!app.forum.attribute('canSupportViewAll') || !!app.forum.attribute('canSupportManage');

    return (
      <PageStructure
        className="SupportPage"
        hero={() => (
          <div className="SupportHero">
            <div className="SupportHero-main">
              <h2 className="SupportHero-title">
                <i className="icon fas fa-headset" /> {T('title')}
              </h2>
              <p className="SupportHero-subtitle">{T('subtitle')}</p>
            </div>
            <Button className="Button Button--primary" icon="fas fa-plus" onclick={() => app.modal.show(CreateTicketModal)}>
              {T('new_ticket')}
            </Button>
          </div>
        )}
      >
        {this.loading ? (
          <div className="SupportLoading">
            <LoadingIndicator />
          </div>
        ) : (
          this.content(isStaff)
        )}
      </PageStructure>
    );
  }

  content(isStaff: boolean) {
    const tickets = app.store.all('tickets') as any[];

    const total = tickets.length;
    const open = tickets.filter((t) => OPEN_STATUSES.indexOf(t.status()) >= 0).length;
    const closed = total - open;

    const tabs: [string, string, number][] = [
      ['all', T('tab_all'), total],
      ['open', T('tab_open'), open],
      ['closed', T('tab_closed'), closed],
    ];

    return (
      <div>
        <div className="SupportStats">
          <div className="SupportStat">
            <div className="SupportStat-value">{total}</div>
            <div className="SupportStat-label">{T('stat_total')}</div>
          </div>
          <div className="SupportStat">
            <div className="SupportStat-value">{open}</div>
            <div className="SupportStat-label">{T('stat_open')}</div>
          </div>
          <div className="SupportStat">
            <div className="SupportStat-value">{closed}</div>
            <div className="SupportStat-label">{T('stat_closed')}</div>
          </div>
        </div>

        <div className="SupportTabs">
          {tabs.map(([key, label, count]) => (
            <button
              key={key}
              type="button"
              className={'SupportTab' + (this.filter === key ? ' active' : '')}
              onclick={() => {
                this.filter = key;
                m.redraw();
              }}
            >
              {label} <span className="SupportTab-count">{count}</span>
            </button>
          ))}
        </div>

        {this.error ? <div className="Alert Alert--error">{this.error}</div> : <TicketList filter={this.filter} isStaff={isStaff} />}
      </div>
    );
  }
}
