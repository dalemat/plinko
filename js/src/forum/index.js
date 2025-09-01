import { extend } from 'flarum/common/extend';
import HeaderPrimary from 'flarum/forum/components/HeaderPrimary';
import PlinkoModal from './PlinkoModal';

app.initializers.add('acmeverse-plinko-fortune', () => {
  extend(HeaderPrimary.prototype, 'items', function (items) {
    items.add('plinko', 
      m('button', {
        className: 'Button Button--link PlinkoButton',
        onclick: () => app.modal.show(PlinkoModal)
      }, [
        m('i', { className: 'fas fa-coins' }),
        ' Plinko'
      ])
    );
  });
});
