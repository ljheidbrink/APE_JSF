var Ape_move = new Class({
	Implements: [APEClient, Options],
	options: {
		container: document.body
	},

	initialize: function(core,options){
		this._core = core;
		this.setOptions(options);
		this.addEvent('initialized', this.init_playground);
		this.addEvent('new_user', this.create_user);
		this.addEvent('new_pipe_multi', this.set_pipe);
		this.addEvent('raw_positions',this.raw_positions);
		this.addEvent('raw_data',this.raw_data);
		this.addEvent('cmd_send', this.cmd_send);
		this.addEvent('user_left', this.delete_user);
		this.addEvent('err_004',this.reset);
		if (this.options.name) this._core.start(this.options.name);
	},
	delete_user: function(user, pipe){
		user.element.dispose();
	},

	cmd_send: function(pipe,sessid,pubid,message){
		this.write_message(pipe,message,this._core.user);
	},
	raw_data: function(raw,pipe){
		this.write_message(pipe,raw.datas.msg,raw.datas.sender);
	},
	set_pipe: function(pipe, options){
		this.pipe = pipe;
	},
	raw_positions: function(raw, pipe){
		this.move_point(raw.datas.sender,raw.datas.sender.properties.x,raw.datas.sender.properties.y);
	},
	parse_message: function(message){
		return unescape(message);
	},
	write_message: function(pipe,message,sender){
		//Define sender
		sender = this.pipe.getUser(sender.pubid);

		//destory old message
		sender.element.getElements('.ape_message_container').destroy();

		//Create message container
		var msg = new Element('div',{'opacity':'0','class':'ape_message_container'});
		var cnt = new Element('div',{'class':'msg_top'}).inject(msg);

		//Add message
		new Element('div',{
					'html':this.parse_message(message),
					'class':'ape_message'
				}).inject(cnt);
		new Element('div',{'class':'msg_bot'}).inject(cnt);

		//Inject message
		msg.inject(sender.element);

		//Show message
		var fx = msg.morph({'opacity':'1'});

		//Delete old message
		(function(el){
			$(el).morph({'opacity':'0'});
			(function(){
				$(this).dispose();
			}).delay(300,el);
		 }).delay(3000,this,msg);
	},
	create_user: function(user, pipe){
		if(user.properties.x){
			var x = user.properties.x;
			var y = user.properties.y;
		}else{
			var x = 8;
			var y = 8;
		}
		var pos = this.element.getCoordinates();
		x = x.toInt()+pos.left;
		y = y.toInt()+pos.top;
		user.element = new Element('div',{
				'class':'demo_box_container',
				'styles':{
					'left':x+'px',
					'top':y+'px'
				}
			}).inject(this.element,'inside');
		new Element('div',{
				'class':'user',
				'styles':{
					'background-color':'rgb('+this.user_color(user.properties.name)+')'
				}
				}).inject(user.element,'inside');
		new Element('span',{
				'text':user.properties.name
			}).inject(user.element,'inside');
	},
	user_color: function(nickname){
		var color = new Array(0,0,0);
		var i=0;
		while(i<3 && i<nickname.length){
			//Transformation du code ascii du caractère en code couleur
			color[i] = Math.abs(Math.round(((nickname.charCodeAt(i)-97)/26)*200+10));			
			i++;
		}
		return color.join(',');
	},
	sendpos: function(x,y){
		var pos=this.pos_to_relative(x,y);
		this._core.request('SETPOS',[this.pipe.getPubid(),pos.x,pos.y]);
		this.move_point(this._core.user,pos.x,pos.y);	
	},
	pos_to_relative:function(x,y){
		var pos = this.element.getCoordinates();
		x = x-pos.left-36;
		y = y-pos.top-46;
		if(x<0) x = 10;
		if(x>pos.width)  x=pos.width-10;
		if(y<0) y = 10;
		if(y>pos.height) y = pos.height-10;
		return {'x':x,'y':y};
	},
	move_point:function(user,x,y){
		var user = this.pipe.getUser(user.pubid); 
		var el = user.element;
		var fx = el.retrieve('fx'); 

		if(!fx){
			fx = new Fx.Morph(el,{'duration':300,'fps':100});
			el.store('fx',fx);
		}
		el.retrieve('fx').cancel();

		pos = this.element.getCoordinates();

		x = x.toInt();
		y = y.toInt()
		//Save position in user properties
		user.properties.x = x;
		user.properties.y = y;

		fx.start({'left':pos.left+x,'top':pos.top+y});
	},
	init_playground: function(){
		this.element = this.options.container;
		this.els = {};
		this.els.move_box = new Element('div',{'class':'move_box'}).inject(this.element);
		this.els.move_box.addEvent('click',function(ev){ 
			this.sendpos(ev.page.x,ev.page.y);
		}.bindWithEvent(this));
		this.els.more = new Element('div',{'id':'more'}).inject(this.element,'inside');

		this.els.sendbox_container = new Element('div',{'id':'ape_sendbox_container'}).inject(this.els.more);

		this.els.send_box = new Element('div',{'text':'Dire : ','id':'ape_sendbox'}).inject(this.els.sendbox_container,'bottom');
		this.els.sendbox = new Element('input',{
							'type':'text',
							'id':'sendbox_input',
							'autocomplete':'off',
							'events':{
								'keypress':function(ev){
										if(ev.code == 13){
											var val = this.els.sendbox.get('value');
											if(val!=''){
												this.pipe.send(val);
												$(ev.target).set('value','');
											}
										}
									}.bind(this)
							}
						}).inject(this.els.send_box);
		this.els.send_button = new Element('input',{
							'type':'button',
							'id':'sendbox_button',
							'value':'Envoyer',
							'events': {

								'click': function(){
									var val = this.els.sendbox.get('value');
									if(val!=''){
										this.pipe.send(val);
										$(ev.target).set('value','');
									}
								}.bind(this)
							}
						}).inject(this.els.send_box);
	},
	reset: function(){
		this._core.clearSession();
		if (this.element) {
			this.element.empty();
		}
		this._core.initialize(this._core.options);
	}
});