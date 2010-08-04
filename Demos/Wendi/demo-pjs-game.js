var cmouseY = 255/2;
var cpmouseY = 255/2;

APE.Controller = new Class({
	
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

	initialize: function(options){
		this.setOptions(options);
		this.container = $(this.options.container) || document.body;

		this.onRaw('postmsg', this.onMsg);
		this.addEvent('load',this.start);
	},
	
	start: function(core){
		this.core.start({'name': $time().toString()});
	},
	
	onMsg: function(raw){

		cpmouseY = cmouseY;
		cmouseY = decodeURIComponent(raw.data.ch1);
		draw();

		var outtxt = 'Received on ' + decodeURIComponent(raw.data.ts)
				+ ', ' + decodeURIComponent(raw.data.ch1) 
				+ ', ' + decodeURIComponent(raw.data.ch2)
				+ ', ' + decodeURIComponent(raw.data.ch3);

		$('element2').set('text', $('element1').get('text'));
		$('element1').set('text', outtxt);

	}
	
});
