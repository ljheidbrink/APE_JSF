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

		var outtxt = 'Received on ' + decodeURIComponent(raw.data.ts)
				+ ', ' + decodeURIComponent(raw.data.ch1) 
				+ ', ' + decodeURIComponent(raw.data.ch2)
				+ ', ' + decodeURIComponent(raw.data.ch3);

		this.vars.tsnix_last = this.vars.tsnix_current;
		this.vars.tsnix_current = decodeURIComponent(raw.data.tsnix)*1000;
		if (this.vars.tsnix_last > 0)
			this.vars.telapsed = this.vars.tsnix_current - this.vars.tsnix_last;
		else
			this.vars.telapsed = 0;

		this.vars.nfetch++;		
		this.vars.trun = this.vars.trun + this.vars.telapsed;

		$('nfetch').set('text', this.vars.nfetch);
		$('tfetch_avg').set('text', this.vars.trun/(this.vars.nfetch-1));

		$('element2').set('text', $('element1').get('text'));
		$('element1').set('text', outtxt);

//		new Element('div', {
//			'class': 'message',
//			html: outtxt
//		}).inject(this.container,'top');

	}
	
});
