		function ProcessData(data) {
  	  SetPaddleXY(data.ch1/255*550, 400-data.ch2/255*400);
			var outtxt = 'Received on ' + decodeURIComponent(data.ts)
						+ ', ' + decodeURIComponent(data.ch1) 
						+ ', ' + decodeURIComponent(data.ch2)
						+ ', ' + decodeURIComponent(data.ch3);
			$('element2').set('text', $('element1').get('text'));
			$('element1').set('text', outtxt);
		}

    function getFlashObject(movieName) {
      if (window.document[movieName]) {
        return window.document[movieName];
      }
      if (navigator.appName.indexOf("Microsoft Internet")==-1) {
        if (document.embeds && document.embeds[movieName])
          return document.embeds[movieName]; 
      }
      else { // if (navigator.appName.indexOf("Microsoft Internet")!=-1)
        return document.getElementById(movieName);
      }
    }
    function SetSpeed() {
      var flashObj = getFlashObject("BounceGame");
      flashObj.SetBallSpeed(document.getElementById("speed").value);
    }
    function SetPaddleXY(x, y) {
      var flashObj = getFlashObject("BounceGame");
      flashObj.SetPaddleX(x);
      flashObj.SetPaddleY(y);    
    }
		function DisplayLives(lives) {
      document.getElementById("lives").innerHTML = "# of lives = " + lives;
		}
		function DisplaySpeed(speed) {
      document.getElementById("speed").value = speed;
		}
