var Contact = (function () {

    'use strict';

    var setup = {
    	success_callbacks : {
    		// Using separate callbacks in case we ever want to customise individual responses
    		// Confimration messages provided by utilities-contact-interactions.php as already tailored to the request type

	        contact: function (e, data) {
	        	showConfirmation(e, data);
	        },
	        catalogue: function (e, data) {
	        	showConfirmation(e, data);
	        },
	        registration: function (e, data) {
	        	showConfirmation(e, data);
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
		actions.catalogue = function (e) {
	    	processForm(e, 'catalogue');
		}
		actions.registration = function (e) {
	    	processForm(e, 'registration');
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

			if (!input.name || input.value.length < 1) {
				continue;
			}

			if(uncheckedBox(input)){
				if(input.name === 'consent'){
					// No go without consent
					console.log('Consent is required to process the form');
					return;
				}
				continue;
			}
			params[input.name] = input.value;
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
		var submitting_form = $(options.event.target).closest('form');
		var error_report = submitting_form.parent().find('.form__error--submission');

		xhttp.onreadystatechange = function() {
		    if (this.readyState == 4 && this.status == 200) {
		    	xhttp.getAllResponseHeaders();
		    	
		    	var response = JSON.parse(this.response);
		    	console.log(response);

		    	if(response.error) {
		    		//TODO: Does this need handling?
		    		console.warn('Ajax call returned an error');
		    		error_report.html(response.error).addClass('form__error--show');
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
	function showConfirmation (e, data) {
		var title = $('.form__title').html();
		$('.pcp_forms').html("<h2 class='form__title'>" + title + "</h2><p class='form_message'>" + data.message + "</p>");
	}
	function uncheckedBox (input) {
		return input.type.toLowerCase() === 'checkbox' && !input.checked;
	}
}());