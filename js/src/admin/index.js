import app from 'flarum/admin/app';

app.initializers.add('acmeverse/flarum-plinko-fortune', () => {
  app.extensionData
    .for('acmeverse-plinko-fortune')
    .registerSetting({
      setting: 'acmeverse-plinko-fortune.max_attempts',
      type: 'number',
      label: app.translator.trans('acmeverse-plinko-fortune.admin.max_attempts_label'),
      help: app.translator.trans('acmeverse-plinko-fortune.admin.max_attempts_help'),
    })
    .registerSetting({
      setting: 'acmeverse-plinko-fortune.enable_leaderboard',
      type: 'boolean',
      label: app.translator.trans('acmeverse-plinko-fortune.admin.enable_leaderboard_label'),
      help: app.translator.trans('acmeverse-plinko-fortune.admin.enable_leaderboard_help'),
    });
});
