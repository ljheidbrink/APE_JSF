/***
 * APE JSF Setup
 */

APE.Config.baseUrl = 'http://ape-test.local:8080/APE_JSF'; //APE JSF 
APE.Config.domain = 'ape-test.local'; 
APE.Config.server = 'ape.ape-test.local:80'; //APE server URL

(function(){
	for (var i = 0; i < arguments.length; i++)
		APE.Config.scripts.push(APE.Config.baseUrl + '/Source/' + arguments[i] + '.js');
})('mootools-core', 'Core/APE', 'Core/Events', 'Core/Core', 'Pipe/Pipe', 'Pipe/PipeProxy', 'Pipe/PipeMulti', 'Pipe/PipeSingle', 'Request/Request','Request/Request.Stack', 'Request/Request.CycledStack', 'Transport/Transport.longPolling','Transport/Transport.SSE', 'Transport/Transport.XHRStreaming', 'Transport/Transport.JSONP', 'Transport/Transport.WebSocket', 'Core/Utility', 'Core/JSON');
