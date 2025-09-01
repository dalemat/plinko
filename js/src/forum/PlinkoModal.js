import Modal from 'flarum/common/components/Modal';
import Button from 'flarum/common/components/Button';

export default class PlinkoModal extends Modal {
  oninit(vnode) {
    super.oninit(vnode);
    this.betAmount = 10;
    this.dropPosition = 4;
    this.balance = app.session.user.commentCount() || 0;
    this.playing = false;
    this.result = null;
    this.animating = false;
    this.ballPath = [];
    this.animationStep = 0;
  }

  className() {
    return 'PlinkoModal Modal--large';
  }

  title() {
    return 'Plinko Fortune';
  }

  content() {
    return [
      m('.Modal-body', [
        // Game Header
        m('.plinko-header', [
          m('.balance', `Balance: ${this.balance} points`),
          m('.multipliers', ['0x', '0.5x', '1x', '2x', '5x', '2x', '1x', '0.5x', '0x'].map(mult => 
            m('.mult', mult)
          ))
        ]),

        // Game Board
        m('.plinko-board', [
          // Drop positions
          m('.drop-positions', 
            Array.from({length: 9}, (_, i) => 
              m('.drop-pos', {
                className: this.dropPosition === i ? 'active' : '',
                onclick: () => this.dropPosition = i
              })
            )
          ),
          
          // Pegs (simple dots)
          m('.pegs', 
            Array.from({length: 25}, (_, i) => m('.peg'))
          ),

          // Animated ball
          this.animating && m('.ball', {
            style: this.getBallStyle()
          }),

          // Slots
          m('.slots',
            [0, 0.5, 1, 2, 5, 2, 1, 0.5, 0].map((mult, i) => 
              m('.slot', {
                className: this.result?.slot_hit === i ? 'winning' : ''
              }, `${mult}x`)
            )
          )
        ]),

        // Controls
        m('.plinko-controls', [
          m('input[type=number]', {
            value: this.betAmount,
            min: 1,
            max: 100,
            onchange: (e) => this.betAmount = parseInt(e.target.value) || 1
          }),
          
          Button.component({
            className: 'Button Button--primary',
            loading: this.playing,
            disabled: this.playing || this.betAmount > this.balance,
            onclick: () => this.playGame()
          }, 'Drop Ball')
        ]),

        // Result
        this.result && m('.result', [
          m('h3', this.result.profit > 0 ? '🎉 You Won!' : '😔 You Lost'),
          m('p', `Ball landed in slot ${this.result.slot_hit + 1} (${this.result.multiplier}x)`),
          m('p', `Payout: ${this.result.payout} points`),
          m('p', `Profit: ${this.result.profit > 0 ? '+' : ''}${this.result.profit} points`)
        ])
      ])
    ];
  }

  playGame() {
    this.playing = true;
    this.result = null;

    app.request({
      method: 'POST',
      url: app.forum.attribute('apiUrl') + '/plinko/play',
      body: {
        bet_amount: this.betAmount,
        drop_position: this.dropPosition
      }
    }).then(result => {
      this.result = result;
      this.balance = result.new_balance;
      this.ballPath = result.ball_path;
      this.animateBall();
    }).catch(error => {
      this.playing = false;
      alert(error.message || 'Error playing game');
      m.redraw();
    });
  }

  animateBall() {
    this.animating = true;
    this.animationStep = 0;
    
    const animate = () => {
      if (this.animationStep < this.ballPath.length) {
        this.animationStep++;
        m.redraw();
        setTimeout(animate, 300);
      } else {
        setTimeout(() => {
          this.animating = false;
          this.playing = false;
          m.redraw();
        }, 1000);
      }
    };
    
    animate();
  }

  getBallStyle() {
    const step = Math.min(this.animationStep, this.ballPath.length - 1);
    const position = this.ballPath[step] || 4;
    
    return {
      left: `${(position * 11) + 5}%`,
      top: `${(step * 15) + 10}%`
    };
  }
}
