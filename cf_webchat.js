jQuery(document).ready(function($) {
var soundEmbed = null;
var is_notif_needed = true;
var autohide = true;
var xhr = null;
var last_active_sm_list;
var connection_state = 0;
var cf_password = '';

function play_notif() {
    /*if (soundEmbed) {
        document.body.removeChild(soundEmbed);
        soundEmbed.removed = true;
        soundEmbed = null;
    }
	soundEmbed = document.createElement("embed");
    soundEmbed.setAttribute("src", Drupal.settings.cf_webchat.module_path + "/sounds/new_mess.mp3");
	soundEmbed.setAttribute("hidden", true);
	soundEmbed.setAttribute("autostart", true);
    soundEmbed.removed = false;
    document.body.appendChild(soundEmbed);*/
	/*var sound_path = Drupal.settings.cf_webchat.module_path + "/sounds/new_mess.mp3";

	if (soundEmbed) {
        soundEmbed.remove();
        soundEmbed = null;
    }
	soundEmbed = $('<object></object>').attr({data: sound_path, type: 'audio/x-mpeg'});
    $('body').append(soundEmbed);*/
	var sound_path = cfw_settings.plugin_dir + "/sounds/new_mess.mp3";

	if (soundEmbed) {
        soundEmbed.remove();
        soundEmbed = null;
    }
	soundEmbed = $('<embed></embed>').attr({src: sound_path, autostart: true, hidden: true});
    $('body').append(soundEmbed);
}

function load_messages() {
	xhr = $.ajax({
		type: "POST",
		url : cfw_settings.ajax_url,
		async: true,
		cache: false,
		data: ({action : 'cfw_update', last_msg_time: $("#last_msg_time").attr("value"), channel: $('#channel :selected').text(), connection_state_v: connection_state, password: cf_password}),
		dataType: 'text',
		error: function(data) {
			setTimeout(load_messages(),  5000);
		},
		success: function(data) {
			var result = JSON.parse(data);
			$("#last_msg_time").attr("value", result.last_msg_time);

			if (connection_state != result.connection_state && result.connection_state != undefined) {
				var text = '';
				var css_class = '';
				switch (result.connection_state) {
					case 1:
						text = cfw_settings.conn_unst;
						css_class = 'unstable_conn';
					break;
					case 2:
						text = cfw_settings.conn_mis;
						css_class = 'unactive_conn';
					break;
				}

				if (text != '') {
					$('.connection_state').html(text).addClass(css_class).fadeIn(2000);
				}
				else {
					$('.connection_state').fadeOut(2000).removeClass('unstable_conn unactive_conn');
				}

				connection_state = result.connection_state;
			}

			if (result.messages_array) {
				for (var mess in result.messages_array)
					$(result.messages_array[mess]).appendTo($('#messages_list')).fadeIn(2000);
				if (is_notif_needed)
					play_notif();
				setTimeout(function () { $('#messages_list').scrollTop($('#messages_list').attr('scrollHeight')) }, 10);
			}

			if (result.user_auth != undefined) {
				var alt = cfw_settings.state;
				var answ_array = [
					cfw_settings.st0,
					cfw_settings.st1,
					cfw_settings.st2,
					cfw_settings.st3,
					cfw_settings.st4,
					cfw_settings.st5,
					cfw_settings.st6,
					cfw_settings.st7,
					cfw_settings.st8,
					cfw_settings.st9,
					cfw_settings.st10,
					cfw_settings.st11,
					cfw_settings.st12
				];
				var tip = answ_array[result.user_auth];
				var image;

				if (result.user_auth == 0 || result.user_auth > 3)
					image = cfw_settings.plugin_dir + "/images/stat_error.png";
				else if (result.user_auth == 2)
					image = cfw_settings.plugin_dir + "/images/stat_wait.png";
				else
				{
					image = cfw_settings.plugin_dir + "/images/stat_ok.png";
					alt = cfw_settings.st1;
					tip = cfw_settings.state_tip;
				}

				$(".state img").attr("alt", alt);
				$(".state img").attr("title", alt);
				$(".state img").attr("src", image);

				if ($(".state span").html() != tip)
					$(".state span").html(tip);
			}

			if (result.actions_array)
				do_actions(result.actions_array);

			setTimeout(function() { load_messages() },  1000);
		}
	});
}

function do_actions(actions_array) {
	for (var action in actions_array) {
		var i, nick;
		switch (actions_array[action].type) {
			case "2": case "5":
				i = 0;
				nick = $("#users_list ul li a:eq(" + i + ")").html();
				while (nick) {
					if (nick == actions_array[action].nick) {
						$("#users_list ul li:eq(" + i + ")").remove();
						$("#users_list ul div:eq(" + i + ")").remove();
						nick = null;
					}
					else {
						i++;
						nick = $("#users_list ul li a:eq(" + i + ")").html();
					}
				}
				break;
			case "4":
				i = 0;
				nick = $("#users_list ul li a:eq(" + i + ")").html();
				var is_added = false;

				var form_nick = '<li class="single_user' + ((actions_array[action].male == 1) ? ' woman' : ' man') + '" onmouseout="$(this).children(\'.user_info\').css({display: \'none\'});" onmouseover="$(this).children(\'.user_info\').css({display: \'block\'});">' +
								'<a class="nick_paste" onclick="insert_nick(\'' + actions_array[action].nick + '\')" >' + actions_array[action].nick + '</a>' +
								'<div class="user_info">' +
								'IP: ' + ((cfw_settings.show_ips == 1 && actions_array[action].variable != 'N/A') ? actions_array[action].variable : cfw_settings.ip_hidden) + '<br />' +
								cfw_settings.state + ': ' + cfw_settings.state_undef + ' </div>' +
								'</li>' +
								'<div></div>';

				while (nick && !is_added) {
					if (is_nick_higher(nick, actions_array[action].nick)) {
						$("#users_list ul li:eq(" + i + ")").before(form_nick);
						is_added = true;
					}
					else {
						i++;
						nick = $("#users_list ul li a:eq(" + i + ")").html();
					}
				};
				if (!is_added)
					$("#users_list ul").append(form_nick);
				break;
			case "8":
	    		$(".channel_topic").html(actions_array[action].variable);
				break;
		}
	}
}

function is_nick_higher(current_nick, new_nick) {
	var i = 0;
	var is_higher = false;
	var nn_len = new_nick.length;

	//if current_nick[i] == null (current_nick smaller then new_nick) then new_nick will be above current_nick
	while (i < nn_len && current_nick[i] != null) {
		var cur_nn_sym = new_nick[i].toLowerCase();
		var cur_cn_sym = current_nick[i].toLowerCase();

		//if (current_nick[i] == "Т")
			//alert(cur_cn_sym);
		if (cur_nn_sym != cur_cn_sym) {
			//alert("cur_nn_sym != cur_cn_sym");
			if (cur_nn_sym > cur_cn_sym)
				is_higher = false;
			else if (cur_nn_sym < cur_cn_sym)
				is_higher = true;
			break;
		}
		i++;
	}

	return is_higher;
}

function notif_changed() {
	if ($("#notif_sound").attr("checked") == true)
		  is_notif_needed = true;
    else is_notif_needed = false;
	setcookie("cf_notification", is_notif_needed);
}

function autohide_changed() {
	if ($("#autohide").attr("checked") == true)
		  autohide = true;
    else autohide = false;
	setcookie("cf_autohide", autohide);
}

function ctrlEnter(event) {
  if((event.ctrlKey) && ((event.keyCode == 0xA)||(event.keyCode == 0xD)))
  {
    document.getElementById('send').click();
  }
}

function setcookie(name, value) {
	// Set "Expires" time to one week.
	var expires = new Date();
	expires.setTime(expires.getTime() + (1000 * 21600 * 24 * 7));
	document.cookie = name + "=" + value + ";" +
					  "expires=" + expires.toGMTString() + ";";
}

function reload_channel() {
  if (xhr) {
	xhr.abort();
  }
  setcookie("cf_channel", encodeURIComponent($('#channel :selected').text()));
  window.location.reload();
}

function insert_nick(obj) {
  var nick = obj.innerHTML + '> ';
  $('#message').val($('#message').val() + nick);
  $('#message').focus();
}

function insert_smile(obj) {
  if (autohide) {
	open_sm_tab(last_active_sm_list);
	$("#smilies_block").css("display", "none");
  }
  $('#message').val($('#message').val() + obj.title);
  $('#message').focus();
}

function open_sm_tab(id) {
  if (last_active_sm_list != undefined) {
	$("#smilies_block div:eq(" + last_active_sm_list + ")").css("display", "none");
	$("#smilies_block p span:eq(" + last_active_sm_list + ")").css("border-bottom", "none");
  }
  if (last_active_sm_list != id) {
    $("#smilies_block div:eq(" + id + ")").css("display", "block");
	$("#smilies_block p span:eq(" + id + ")").css("border-bottom", "1px solid #AFAFAF");
	last_active_sm_list = id;
  }
  else last_active_sm_list = null;
}

function send_report(id) {
	$(".send_state_text").css({color: "#F00000"});
	switch (id) {
		case 0:
			$(".send_state_text").html(cfw_settings.send_err_empty);
			break;
		case 1:
			$(".send_state_text").html(cfw_settings.send_suc);
			$(".send_state_text").css({color: "#00D627"});
			break;
		case 2:
			$(".send_state_text").html(cfw_settings.send_err_len);
			break;
	}
	$("#message").val('');
	$(".send_state_text").fadeIn(2000).fadeOut(2000);
}


  	if ($("#notif_sound").attr("checked") == false)
		  is_notif_needed = false;
	if ($("#autohide").attr("checked") == false)
		  autohide = false;

  $("#send").click(function() {
    var id;
	if ($('#message').val().trim() == "")
		id = 0;
	else if ($('#message').val().length > 40000)
		id = 2;
	if (id != undefined)
		send_report(id);
	else {
		$("#send").attr('disabled', 'disabled');
		$("#message").attr('disabled', 'disabled');
		$.post(
			cfw_settings.ajax_url,
			{ action: 'cfw_add', body: $("#message").val(), channel: $('#channel :selected').text(), connection_state_v: connection_state},
			function(data) {
				var result = JSON.parse(data);
				send_report(result.answer);
				$("#send").attr('disabled', '');
				$("#message").attr('disabled', '');
			},
			'text'
		);
	}
  });

  $("#cf_password").blur(function() {
  	cf_password = $(this).attr("value");
  });

  $("#channel").change(function() {
	reload_channel();
  });

  $(".nick_paste").click(function() {
  	insert_nick(this);
  });

  $(".cf_smile").click(function() {
  	insert_smile(this);
  });

  $("#notif_sound").click(function() {
  	notif_changed();
  });

  $("#autohide").click(function() {
  	autohide_changed();
  });

  $(".settings_but").click(function() {
	if ($("#settings_block").css("display") != "none")
		$("#settings_block").css("display", "none");
	else $("#settings_block").css("display", "block");
  });

  $(".smilies_but").click(function() {
	if ($("#smilies_block").css("display") != "none") {
		open_sm_tab(last_active_sm_list);
		$("#smilies_block").css("display", "none");
	}
	else {
		$("#smilies_block").css("display", "block");
		open_sm_tab(0);
	}
  });

  $(".clear_but").click(function() {
	var e_count = $("#messages_list > div").size();
	if (e_count > 10) {
		e_count -= 11;
		for (var i = 0; i <= e_count; i++) {
			$("#messages_list div:first").remove();
		}
	}
  });

  $("#message").keypress(function(event) {
    ctrlEnter(event);
  });

  $("#smilies_block .sm_tab").click(function() {
	open_sm_tab($(this).index());
  });

  load_messages();
});

