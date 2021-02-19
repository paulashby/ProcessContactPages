var Contact = (function () {

    'use strict';

    var setup = {
    	success_callbacks : {
	        contact: function (e, data) {
	        	//TODO: Provide success feedback - need this for when return is hit after changing quantity
	        	console.log('contact callback');
	        },
	        registration: function (e, data) {
	        	//TODO: Provide success feedback - need this for when return is hit after changing quantity
	        	console.log('registration callback');
	        }
	    }
	};
	var actions = {
	};

	if 	(document.readyState === "complete" ||
		(document.readyState !== "loading" && !document.documentElement.doScroll)) {
	  	
	  	onDOMloaded();
	} else {
	  document.addEventListener("DOMContentLoaded", onDOMloaded);
	}


	function onDOMloaded () {

		// Use event handlers in actions object

	    document.addEventListener('click', function (e) { 
	    	dataAttrEventHandler(e, actions); 
	    }, false);

	    actions.contact = function (e) {
	    	processForm(e, 'contact');
		}
		actions.registration = function (e) {
	    	processForm(e, 'registration');
		}
		actions.privacy = function (e) {

	    	var token = document.getElementById('contact_token');
	    	var settings = {
				e: e,
				action: 'submit',
				token: {
					name: token.name,
					value: token.value
				},
				action_url: e.target.dataset.actionurl,
				method: 'GET'
			};
			doAction(settings);
		}
	};
	function dataAttrEventHandler (e, actions) {

	    var action = e.target.dataset.action;

	    if(actions[action]) {
	    	actions[action](e);
	    }
	}
	function doAction (settings) {

		var options = {
        	ajaxdata: {
        		action: settings.action,
        		params: settings.params
        	},
        	token: settings.token, 
        	action_url: settings.action_url,
        	role: settings.action, // Set this to run callback
        	method: settings.method,
        	event: settings.e 
        };
        if(settings.params){
        	options.ajaxdata.params = settings.params;
        }
        makeRequest(options);
        settings.e.preventDefault();
	}
	function processForm (e, submission_type) {

		var token = document.getElementById('submission_token');
    	var form = e.target.closest('form');
    	var params = {
    		submission_type: submission_type
    	};
		for (var i = 0, ii = form.length; i < ii; ++i) {
			var input = form[i];
			if (input.name) {
				if(uncheckedBox(input) || input.value.length < 1){
					continue;
				} else {
					params[input.name] = input.value;
				}
			}
		}
    	var settings = {
			e: e,
			action: submission_type,
			token: {
				name: token.name,
				value: token.value
			},
			params: params,
			action_url: e.target.dataset.actionurl,
			method: 'POST'
		};
		doAction(settings);
	}
	function makeRequest (options) {

		var xhttp = new XMLHttpRequest();

		xhttp.onreadystatechange = function() {
		    if (this.readyState == 4 && this.status == 200) {
		    	xhttp.getAllResponseHeaders();
		    	
		    	var response = JSON.parse(this.response);
		    	console.log(response);

		    	if(response.error) {
		    		//TODO: Does this need handling?
		    		console.warn('Ajax call returned an error');
		    	} else {
		    		// Route to appropriate callback
		    		setup.success_callbacks[options.role](options.event, response);
		    	}
		    }
		};
		xhttp.open(options.method, options.action_url, true);
		xhttp.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
		xhttp.setRequestHeader('X-' + options.token.name, options.token.value);
		xhttp.setRequestHeader('Content-type', 'application/json');
		xhttp.send(JSON.stringify(options.ajaxdata));
	}
	function getToken (e) {

		var id = getId (e);
		var token = document.getElementById(id + '_token');
		return {
			name: token.name,
			value: token.value
		};
	}
	function getId (e) {
		return e.target.dataset.context + e.target.dataset.sku;
	}
	function uncheckedBox (input) {
		return input.type.toLowerCase() === 'checkbox' && !input.checked;
	}
}());