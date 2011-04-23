$(document).ready(function(){
	// TODO: send an xhr, to know the status
	_ajax = $.ajax({
		'url': '/welcome/get_login_status',
		'type': 'GET',
		'dataType': 'json',
		'success': function(data, textStatus, jqXHR) {
			if(data.hasOwnProperty('login_url')) {
				// Not logged in!
				
				login(data);
			} else {
				// Logged in!
				
				start_listening();
			}
		}
	});
});

var _time_gap_updation = 10000; // 10 seconds
var _timer;
var _ajax;
var _last_id = 0;

function start_listening() {
	update_data();
	_timer = setInterval(update_data, _time_gap_updation);
}

function update_data() {
	if(_ajax && _ajax.readyState != 0 && _ajax.readyState != 4) {
		console.log("A request is already in progress! Let it continue");
		return;
	}
	_ajax = $.ajax({
		'url': '/welcome/get_tweets',
		'type': 'GET',
		'dataType': 'json',
		'data': {'last_id': _last_id},
		'success': function(data, textStatus, jqXHR) {
			if(data) {
				if(data.hasOwnProperty('login_url')) {
					// Not logged in!
					login(data);
				} else {
					// Logged in!
					// Update the tweets
					if(data.hasOwnProperty('tweets')) {
						update_tweets(data['tweets']);
					}
					// update the stats
					if(data.hasOwnProperty('stats')) {
						update_stats(data['stats']);
					}
					if(data.hasOwnProperty('last_id')) { 
						_last_id = data['last_id'];
					}
				}
			}
		},
		'error': function() {
			// Error in request!
		}
	});
}


function login($data) {
	stop_listening();
	$("#loginLink").attr('href', decodeURIComponent($data['login_url']));
	$("#login").show();
}

function stop_listening() {
	clearInterval(_timer);
}

function update_stats($stats) {
	var table_html = "<table>";
	for(var i = 0, len = $stats.length; i < len; i++) {
		
		table_html += "<tr>";
		table_html += "<td>" + $stats[i]['name'] + "</td>";
		table_html += "<td>" + $stats[i]['counter'] + "</td>";
		table_html += "</tr>";
	}
	table_html += "</table>";
	$("#stats").empty();
	$(table_html).appendTo("#stats");
}

function update_tweets($tweets) {
	var div = '';
	for(var i = 0, len = $tweets.length; i < len; i++) {
		div += '<div id="tweet_' + i + '" class="tweet">';
		div += $tweets[i].content;
		div += '</div>';
	}
	$(div).prependTo("#tweets");
}