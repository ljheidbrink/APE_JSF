		APE.Wendi = new Class ({
			Extends: APE.Client,
			Implements: Options,
			options: {
				container: null
			},
			vars: {
				trun: 0,
				nfetch: 0,
				tsnix_last: 0,
				tsnix_current: 0
			},
			loadWendi: function() {
				this.load({
					identifier: 'action',
					channel: 'testchannel'
				});
			},
			initialize: function(options) {
				this.setOptions(options);
				this.container = $(this.options.container) || document.body;
				this.onRaw('postmsg', this.onMsg);
				this.addEvent('load',this.start);
			},
			start: function(core) {
				this.core.start({'name': $time().toString()});
			},
			onMsg: function(raw) {
				ProcessData(raw.data);
			}
		});

