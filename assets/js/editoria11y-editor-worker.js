let allPorts = [];
let options = false;
let results = false;
let action = false;

onconnect = function (event) {
	const port = event.ports[0];
	allPorts.push(port);
	port.onmessage = function (e) {
		if (!!e.data[0]) {
			console.log('writing');
			console.log(e.data[0]);
			options = e.data[0];
		}
		if (!!e.data[1]) {
			results = e.data[1];
		}
		if (!!e.data[2]) {
			action = e.data[2];
		}
		allPorts.forEach(port => {
			port.postMessage([options, results, action]);
		  })
		action = false;
		console.log('returning');
		console.log([options, results, action]);

		/*switch (e.data[0]) {
			case 'sendOptions':
				options = e.data[1];
				port.postMessage(['options', options]);
				break;
			case 'sendCount':
				count = e.data[1];
				port.postMessage(['count', results]);
				break;
			case 'getOptions':
				port.postMessage(['options', options]);
				break;
			case 'getcount':
				port.postMessage(['count', results]);
				break;
		}*/
	};
};