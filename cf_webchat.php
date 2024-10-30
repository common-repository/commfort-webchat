<?php
/*
Plugin Name: CommFort WebChat
Plugin URI: http://webcf.ru/
Description: Provides a web-based chat CommFort
Version: 0.2
Author: SteelRat
Author URI: http://steelrat.info
*/

/*
Copyright 2010-2011  SteelRat  (email: global AT steelrat DOT info)

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

$cfw_css_h = 'cfw_css';
$cfw_js_h = 'cfw_js';
$tables = array(
	'users_online' => $wpdb->prefix . 'cf_users_online',
	'channels'     => $wpdb->prefix . 'cf_channels',
	'web_users'    => $wpdb->prefix . 'cf_web_users',
	'actions'      => $wpdb->prefix . 'cf_actions',
	'settings'     => $wpdb->prefix . 'cf_settings',
	'mess_to_send' => $wpdb->prefix . 'cf_messages_to_send',
);

// Registring css style.
wp_register_style( $cfw_css_h, plugins_url( '/cf_webchat.css', __FILE__ ) );

// Registring javascript.
wp_register_script( $cfw_js_h, plugins_url( '/cf_webchat.js', __FILE__ ), array( 'jquery', 'json2' ) );

// Adding action to load text domain.
add_action( 'init', 'cfw_on_init' );

// Adding action when we are in dashboard.
add_action( 'admin_menu', 'cfw_options_menu' );

// Adding action to add widget when all plugins loaded.
add_action('plugins_loaded', 'cfw_reg_widget');

// Adding actions to add css and js.
add_action('wp_print_scripts', 'cfw_add_js');
add_action('wp_print_styles', 'cfw_add_css');

//Adding filter to modify content if keyword found.
add_filter( "the_content", "cfw_mod_content" );

// If both logged in and not logged in users can send this AJAX request,
// add both of these actions, otherwise add only the appropriate one.
add_action( 'wp_ajax_nopriv_cfw_update', 'cfw_update' );
add_action( 'wp_ajax_cfw_update', 'cfw_update' );
add_action( 'wp_ajax_nopriv_cfw_add', 'cfw_send_message' );
add_action( 'wp_ajax_cfw_add', 'cfw_send_message' );

// Loads plugin text domain.
function cfw_on_init() {
	$roles = new WP_Roles();

	$roles_name = array(
		'administrator',
		'editor',
		'author',
		'contributor',
	);

	foreach ( $roles_name as $key => $role_name ) {
		// Adding capability to all roles above subscriber.
		$roles->add_cap( $role_name, 'send CommFort chat messages' );
	}

	// Set translation domain and path to folder with files.
	load_plugin_textdomain( 'cf_webchat', false, dirname( plugin_basename( __FILE__ ) ) . '/translations' );
}

function cfw_reg_widget() {
	wp_register_sidebar_widget( 'cf_webchat', __( 'CommFort WebChat', 'cf_webchat' ), 'cfw_widget' );
}

// Registring options.
function cfw_reg_set() {
	//register_setting( 'cf_webchat', 'cf_webchat_page_url', 'check_chat_url' );
	register_setting( 'cf_webchat', 'cf_webchat_show_ips' );
	register_setting( 'cf_webchat', 'cf_webchat_smilies_enabled', 'cfw_optimize_smilies' );
	register_setting( 'cf_webchat', 'cf_webchat_def_sound_state' );
	register_setting( 'cf_webchat', 'cf_webchat_ping_check' );
}

function cfw_options_menu() {
	// Include file with settings page functions.
	require_once( 'cf_webchat.admin.inc' );

	// Add options page referenced to function from settings file.
	add_options_page( __( 'CommFort WebChat', 'cf_webchat' ), __( 'CommFort WebChat', 'cf_webchat' ), 'manage_options', 'cf_webchat', 'cfw_set' );

	// Call function to register settings when we are in admin menu.
	add_action( 'admin_init', 'cfw_reg_set' );
}

// Add js.
function cfw_add_js() {
	global $cfw_js_h;
	wp_enqueue_script( $cfw_js_h );
	$settings = array(
		'ajax_url'   => admin_url( 'admin-ajax.php' ),
		'plugin_dir' => plugins_url( '', __FILE__ ),
		'show_ips'   => get_option( 'cf_webchat_show_ips', 1 ),
	);
	wp_localize_script( $cfw_js_h, 'cfw_settings', array_merge( $settings, cfw_t_js_lines(), cfw_auth_states() ) );
}

// Add css.
function cfw_add_css() {
	global $cfw_css_h;
	wp_enqueue_style( $cfw_css_h );
}

// Outputs widget content.
function cfw_widget() {
	global $wpdb, $tables, $user_login, $l10n;

	// Fill $user_login variable with data.
	get_currentuserinfo();

	$accounts = $wpdb->get_results($wpdb->prepare('SELECT n.id, n.nick, n.male, n.ip, n.state FROM ' . $tables['users_online'] . ' n ORDER BY n.nick ASC;'), OBJECT);
	$online_users = $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . $tables['users_online'] . ';'));

	$channels = $wpdb->get_col($wpdb->prepare('SELECT name FROM ' . $tables['channels'] . ' ORDER BY id ASC;'));

	( empty( $_COOKIE['cf_channel'] ) ) ? $a_channel = 'main' : $a_channel = rawurldecode( $_COOKIE['cf_channel'] );
	$auth_state = cfw_get_auth_state();
	$auth_loc_array = cfw_auth_states();
	$alt = __( 'State', 'cf_webchat' );
	$tip = $auth_loc_array['st' . $auth_state];
	if ($auth_state == 0 || $auth_state > 3) {
		$img = "error";
	}
	elseif ($auth_state == 2) {
		$img = "wait";
	}
	else {
		$img = "ok";
		$alt = $auth_loc_array['st1'];
		$tip = __( 'Click to change state', 'cf_webchat' );
	}

	$show_ips = get_option( 'cf_webchat_show_ips', 1 );
	$img = plugins_url( '/images/stat_' . $img . '.png', __FILE__ );

	// Include files with widget template.
	if (is_user_logged_in()) {
		require_once( 'templates/user_block.php' );
	}
	require_once( 'templates/users_online.php' );
}

// Replace variables in text with it definitions.
function cfw_rep_var( $text, $vars ) {
	foreach ( $vars as $key => $value ) {
		$text = str_replace ( $key, $value, $text );
	}

	return $text;
}

/**
 * Implementation of hook_nodeapi().
 */
function cf_webchat_nodeapi(&$node, $op, $a3 = NULL, $a4 = NULL) {
  $chat_url = variable_get('cf_webchat_page_url', 'chat');
  if (($node->path == $chat_url || "node/" . $node->nid == $chat_url) && $op == 'alter') {
    if (user_access('view chat')) {
      $node->body = cf_webchat_block_messages_content();
    }
    else {
      $node->body = t('You are not authorized to view and post messages in chat.');
    }
  }
}

function cfw_get_themed_nick($name, $webicon, $title) {
	if ( $title ) {
		$title = ' title="' . ( ( $webicon ) ? __( 'User from web', 'cf_webchat' ) . ". " : "") . __( 'Insert nick in the posting input', 'cf_webchat' ) . '"';
	} else {
		$title = '';
	}

	return '<a class="nick_paste' . ( ( $webicon ) ? " from_web" : "" ) . '"' . $title . '>' . $name . '</a>';
}

function cfw_load_smilies_list() {
	$upload_dir = wp_upload_dir();
	$optim_folder = $upload_dir['basedir'] . "/cf_webchat/";

	$smilies_list = "";
	$tab_list = "";
	if ( file_exists( $optim_folder . 'sm_paths.txt' ) && file_exists( $optim_folder . 'sm_symbs.txt' ) ) {
		$symbs_file = file( $optim_folder . 'sm_symbs.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
		$paths_file = file( $optim_folder . 'sm_paths.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
		$smiles_dir = plugins_url( '/smilies/', __FILE__ );
		$tab_list = "<p>";
		$c_count = 0;
		$skip_group = false;
		$symb_count = count( $symbs_file );
		for ( $i = 0; $i < $symb_count; $i++ ) {
			$cur_line = $symbs_file[$i];
			if ( $cur_line[0] == "#" && $cur_line[1] != "^" ) {
			// Smilies are limited with two tabs. Too big page load.
				if ( $c_count == 2 ) {
					break;
				}
				$tab_list .= '<span class="sm_tab" title="' . __( 'Show / Hide smilies in this tab', 'cf_webchat' ) . '">' . substr($cur_line, 1) . "</span>&nbsp;";
				if ( $c_count != 0 ) {
					$smilies_list .= '</div>';
				}
				$smilies_list .= '<div class="cf_sm_list">';
				$c_count++;
				$skip_group = false;
			} elseif ( $cur_line[1] != "^" && !$skip_group ) {
				$path = $smiles_dir . $paths_file[$i];
				// We are escaping "\" symbols only in pop-up message.
				$alt = str_replace( "\"", "&quot;", $cur_line );
				$smilies_list .= '<img src="' . $path . '" alt="' . $alt . '" title="' . $alt .'" class="cf_smile" />';
			} else {
				$skip_group = true;
			}
		}
		$tab_list .= "</p>";
		$smilies_list .= '</div>';
	}

	return $tab_list . $smilies_list;
}

function cfw_smilies_rep( $body ) {
  $upload_dir = wp_upload_dir();
  $optim_folder = $upload_dir['basedir'] . "/cf_webchat/";

  if ( file_exists( $optim_folder . 'sm_paths.txt' ) && file_exists( $optim_folder . 'sm_symbs.txt' ) && trim( $body ) != '' ) {
    $symbs_file = file( $optim_folder . 'sm_symbs.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
    $paths_file = file( $optim_folder . 'sm_paths.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
    $smiles_dir = plugins_url( '/smilies/', __FILE__ );

	for ( $i = 0; $i < count($symbs_file); $i++ ) {
      $cur_smile = trim( $symbs_file[$i] );
      if ( $cur_smile[0] != "#" && substr($cur_smile, 0, 2) != "#^" ) {
        $pos = strpos( $body, $cur_smile );
        while ( false !== $pos ) {
          $path = $smiles_dir . $paths_file[$i];
		  // We can't add text representation of smile in alt ot title, because it may be replaced
		  // by next smile search.
		  $image = '<img src="' . $path . '" alt="' . __( 'Smile', 'cf_webchat' ) . '" />';
          $body = substr_replace( $body, $image, $pos, strlen( $cur_smile ) );
          $offset = $pos + strlen( $image );
          $pos = strpos( $body, $cur_smile, $offset );
        }
      }
    }
  }

  return $body;
}

function cfw_spec_markup_rep( $body ) {
  $patterns = array(
    '/\[url\=((http|https|ftp):\/\/[^\]]+)\]([^[]+)\[\/url\]/i',
    '/\[url\]((http|https|ftp):\/\/[^[]+)\[\/url\]/i',
    '/\[url\=([^[]+)\]([^[]+)\[\/url\]/i',
    '/\[url\]([^[]+)\[\/url\]/i',
    '/\[image\]/i',
    '/\\n/i',
  );
  $replaces = array(
    '<a href="\1" target="_blank" title="' . __( 'Open link in new window', 'cf_webchat' ) . '">\3</a>',
    '<a href="\1" target="_blank" title="' . __( 'Open link in new window', 'cf_webchat' ) . '">\1</a>',
    '\2',
    '\1',
    '[' . __( 'User has inserted an image, images are not supported', 'cf_webchat' ) . ']',
    '<br />',
  );

  $body = preg_replace( $patterns, $replaces, $body );

  return $body;
}

function cfw_constr_mess( $actions, $is_ajax, $bot_nick ) {
	/*
		Messages type:
			0 - Simple Message
			1 - Message posted with status flag
			2 - User leaved the chat
			3 - User joined the chat
			4 - User joined the channel
			5 - User leaved the channel
			6 - User changed his/her state
			7 - User changed his/her icon of sex
			8 - User changed channel talk topic
	*/

	$is_from_web = false;
	$m_text = '';
	$m_type = $actions -> type;

	// If message shows that user leaved chat ot channel then we disallow replies.
	// Message types 2 or 5.
	if ( $m_type == 2 || $m_type == 5 ) {
		$nick = '<strong>' . $actions -> nick . '</strong>';
	}
	else {
		$nick = cfw_get_themed_nick( $actions -> nick, $is_from_web, true );
	}

	$body = '';

	// If message type implies availability of HTML tags, smiles or CommFort BBcode.
	// Body of these messages is edited by users(id's: 0, 1, 6, 8).
	if ( in_array( $m_type, array( 0, 1, 6, 8 ) ) ) {
		$body = strip_tags( $actions -> variable );
		if ( get_option( 'cf_webchat_smilies_enabled', 0 ) ) {
			$body = cfw_smilies_rep( $body );
		}
	}

	// If it is users messages then we must replace CommFort BBCode and check if it is
	// unlogged web user. Message types 0 or 1. Else we format this message as system message.
	if ( $m_type == 0 || $m_type == 1 ) {
		$body = cfw_spec_markup_rep( $body );
		$is_from_web = ( $actions -> nick == $bot_nick ) ? true : false;

		if ( $is_from_web ) {
			$pos = strpos( $body, ':' );
			if ( strpos( $body, ':', $pos ) ) {
				$actions -> nick = substr( $body, 0, $pos );
				$body = substr( $body, $pos + 2, strlen( $body ) - $pos + 2 );
			}
		}
		$msg_class = "single_message";
		$m_text .= $nick . ': ' . $body;

	} else {
		$m_text .= cfw_rep_var( __( 'User !user_name', 'cf_webchat' ), array( "!user_name" => $nick ) ) . ' ' . cfw_t_messages( $m_type, $body, $actions -> variable );
		$msg_class = "single_system_message";
	}

	$message =
    '<div class="' . $msg_class . '"' . ( ( $is_ajax ) ? ' style="display: none;"' : '' ) . '>
      ' . $m_text . '
      <span class="msg_time">' . date_i18n( "H:i:s", $actions -> datetime ) . '</span>
    </div>';

	return $message;
}

function cfw_get_connection_state() {
	global $wpdb, $tables;

	$ping_time = $wpdb->get_var( $wpdb->prepare( "SELECT n.value FROM " . $tables['settings'] . " n WHERE n.name = 'ping' LIMIT 0, 1" ) );
	$interrupt_duration = time() - $ping_time;

	if ( $interrupt_duration < 30 ) {
		$connection_state = 0;
	} elseif ( $interrupt_duration > 30 && $interrupt_duration < 120 ) {
		$connection_state = 1;
	} elseif ( $interrupt_duration >= 120 ) {
		$connection_state = 2;
	}

	return $connection_state;
}

function cfw_send_message() {
    $body             = $_POST['body'];
    $channel          = $_POST['channel'];
    $connection_state = $_POST['connection_state_v'];

    if ( isset( $connection_state ) && isset( $body ) && isset( $channel ) ) {
		global $wpdb, $tables, $user_login;
		$answer = 1;

		if ( trim( $body ) ) {
			if ( strlen( $body ) <= 40000 ) {
				// Fill $user_login variable with data.
				get_currentuserinfo();

				// If connection state is active - put message to cf_messages_to_send table. If unactive - put just to cf_actions table.
				if ( ! get_option( 'cf_webchat_ping_check', 1 ) || $connection_state != 2 ) {
					$wpdb->insert( $tables['mess_to_send'], array( 'user' => $user_login, 'ip' => $_SERVER['REMOTE_ADDR'], 'channel' => $channel, 'body' => $body, 'datetime' => time(), 'type' => 0 ), array( '%s', '%s', '%s', '%s', '%s', '%d' ) );
				} else {
					$wpdb->insert( $tables['actions'], array( 'variable' => $body, 'nick' => $user_login, 'male' => 0, 'channel' => $channel, 'datetime' => time(), 'type' => 0 ), array( '%s', '%s', '%d', '%s', '%d', '%d' ) );
				}
			} else {
				$answer = 2;
			}
		} else {
			$answer = 0;
		}

		// Generating the response.
	    $response = json_encode( array( 'answer' => $answer ) );

	    // Response output.
	    header( "Content-Type: application/json" );
	    echo $response;

	    // IMPORTANT: don't forget to "exit"
	    exit;
    }
}

function cfw_update() {
    $last_msg_time    =  $_POST['last_msg_time'];
    $channel          =  $_POST['channel'];
    $password         =  $_POST['password'];
    $connection_state =  $_POST['connection_state_v'];

    if ( isset( $connection_state ) && isset( $last_msg_time ) && isset( $channel ) ) {
		global $wpdb, $tables;
		$new_last_msg_time = $last_msg_time;
		$max_execution_time = ini_get( 'max_execution_time' );
		if ( isset($max_execution_time) ) {
			$end_time = time() + $max_execution_time - 10;
		} else {
			$end_time = time() + 20;
		}

		$is_new = false;
		$new_connection_state = $connection_state;
		$ping_check = get_option( 'cf_webchat_ping_check', true );

		// Check: if connection state changed (or this check disabled), new actions recieved or connection is ended.
		do {
			if ( $last_msg_time == "0" ) {
				$number_of_messages = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . $tables['actions'] . " WHERE channel = %s OR channel = ''", $channel ) );
				if ( $number_of_messages < 10 ) {
					$number_of_messages = 10;
				}
				$actions = $wpdb->get_results( $wpdb->prepare( "SELECT n.variable, n.nick, n.male, n.datetime, n.type FROM " . $tables['actions'] . " n WHERE n.channel = %s OR n.channel = '' ORDER BY n.datetime, n.id ASC LIMIT %d, 10", $channel, $number_of_messages-10 ), OBJECT );
			} else {
				$actions = $wpdb->get_results( $wpdb->prepare( "SELECT n.variable, n.nick, n.male, n.datetime, n.type FROM " . $tables['actions'] . " n WHERE n.datetime > %d AND (n.channel = %s OR n.channel = '') ORDER BY n.datetime, n.id ASC", $last_msg_time, $channel ), OBJECT );
			}

			$is_new = ! empty( $actions );

			if ( $ping_check ) {
				$new_connection_state = cfw_get_connection_state();
			}

			// Free the CPU.
			sleep(1);
			clearstatcache();
		} while ( ( ! $ping_check || $new_connection_state == $connection_state ) && ! $is_new && $end_time >= time() );

		$ready_mess = array();
		$f_actions = array();
		// If new actions appeared - add them to array.
		if ( $is_new ) {
			$bot_nick = $wpdb->get_var( "SELECT value FROM " . $tables['settings'] . " WHERE name = 'bot_nick' LIMIT 0, 1" );
			foreach ( $actions as $action ) {
				$ready_mess[] = cfw_constr_mess( $action, true, $bot_nick );
				if ( ! in_array( $action -> type, array( 0, 1, 3 ) ) && $last_msg_time != "0" ) {
					$f_actions[] = array(
						'type'     => $action -> type,
						'variable' => $action -> variable,
						'nick'     => $action -> nick,
						'male'     => $action -> male,
					);
				}
				$new_last_msg_time = $action -> datetime;
			};
		}

		$a_response = array(
			'last_msg_time' => $new_last_msg_time,
			'connection_state' => $new_connection_state,
			'messages_array' => $ready_mess,
			'actions_array' => $f_actions,
			'user_auth' => cfw_get_auth_state( $password ),
		);
		// Generating the response.
	    $js_response = json_encode( $a_response );

	    // Response output.
	    header( "Content-Type: application/json" );
	    echo $js_response;

	    // IMPORTANT: don't forget to "exit"
	    exit;
    }
}

function cfw_t_js_lines() {
	// Text translated for plugin JavaScript file.
	$t_array = array(
		'conn_unst'      => __('Connection to the server is not stable. There may be delays.', 'cf_webchat' ),
		'conn_mis'       => __('Connection to the server is missing. You can communicate only among users from web. Apologies for technical problems.', 'cf_webchat' ),
		'state'          => __('State', 'cf_webchat' ),
		'state_tip'      => __('Click to change state', 'cf_webchat' ),
		'state_undef'    => __('Undefined', 'cf_webchat' ),
		'ip_hidden'      => __('Hidden', 'cf_webchat' ),
		'send_err_empty' => __('You write nothing! Please fill text line', 'cf_webchat' ),
		'send_suc'       => __('Your message succesfully sended', 'cf_webchat' ),
		'send_err_len'   => __('The size of the text line must be less then 40000 symbols', 'cf_webchat' ),
	);

	return $t_array;
}

function cfw_auth_states() {
	// User authirization states.
	// Array keys must begin with letters, because function wp_localize_script() transform this array to
	// JavaScript Object and each key of array converts to object key. Object keys must begin with letters.
	$auth_array = array(
		'st0' => __('You are not registred in chat', 'cf_webchat' ),
		'st1' => __('Already in chat', 'cf_webchat' ),
		'st2' => __('Waiting for authorization..', 'cf_webchat' ),
		'st3' => __('You are not logged in on sait', 'cf_webchat' ),
		'st4' => __('Too much users online', 'cf_webchat' ),
		'st5' => __('Nick is out of rules', 'cf_webchat' ),
		'st6' => __('You are banned', 'cf_webchat' ),
		'st7' => __('Nick includes bad words', 'cf_webchat' ),
		'st8' => __('Nick is already registred', 'cf_webchat' ),
		'st9' => __('Too much users from IP', 'cf_webchat' ),
		'st10' => __('Activation request sended', 'cf_webchat' ),
		'st11' => __('Wrong password', 'cf_webchat' ),
		'st12' => __('Activation request denied or in progress', 'cf_webchat' ),
	);

	return $auth_array;
}

function cfw_t_messages( $id, $body, $var ) {
	$messages = array(
		__( 'leaved the chat', 'cf_webchat' ),
		__( 'joined the chat', 'cf_webchat' ),
		__( 'joined the channel', 'cf_webchat' ),
		__( 'leaved the channel', 'cf_webchat' ),
	);
	if ( in_array( $id, array( 2, 3, 4, 5 ) ) ) {
		$t_message = $messages[$id-2];
	} else {
		switch($id) {
			case 6:
				$t_message = ( $body != '' ) ? __( 'switched to state', 'cf_webchat' ) . ' "' . $body . '"' : __( 'is now online', 'cf_webchat' );
				break;
			case 7:
				$t_message = __( 'changed sex to', 'cf_webchat' ) . ' "' . ( ( $var == 0 ) ? __( 'male', 'cf_webchat' ) : __( 'female', 'cf_webchat' ) ) . '"';
				break;
			case 8:
				$t_message = __( 'changed channel theme to', 'cf_webchat' ) . ' "' . $var . '"';
		}
	}

	return $t_message;
}

function cfw_get_auth_state( $password = '' ) {
	// Getting authorization states.
	$result = 0;

	if ( is_user_logged_in() ) {
		global $wpdb, $tables, $user_login, $user_pass;

		// Fill $user_login and $user_pass variables with data.
		get_currentuserinfo();

		$query_res = $wpdb->get_row( $wpdb->prepare( 'SELECT auth, error FROM ' . $tables['web_users'] . ' WHERE nick = %s;', $user_login ), OBJECT );

		if ( empty( $query_res ) && ! empty( $password ) ) {
			$wpdb->insert( $tables['web_users'], array( 'nick' => $user_login, 'ip' => $_SERVER[ 'REMOTE_ADDR' ], 'pass' => md5( $password ), 'male' => 0, 'auth' => 0, 'ping' => time() ), array( '%s', '%s', '%s', '%d', '%d', '%d', '%d' ) );
			$result = 2;
		} elseif ( ! empty( $query_res ) ) {
			if ( $query_res->error == 0 ) {
				$wpdb->update( $tables['web_users'], array( 'ping' => time() ), array( 'nick' => $user_login ) );
				if ( $query_res->auth == 0 ) {
					$result = 2;
				} else {
					$result = 1;
				}
			} else {
				$result = $query_res->error;
			}
		}
    }
    else {
      $result = 3;
    }

    return $result;
}

function cfw_str_to_bool( $string ) {
  return ( $string == 'false' ) ? false : true;
}

Function cfw_mod_content( $content = '' ) {
	$pos = strpos( $content, '[cf_webchat]' );
	if ( false !== $pos ) {
		$content = cfw_messages_content();
	}

	return $content;
}

function cfw_messages_content() {
	global $wpdb, $tables;

	$channel = ( empty( $_COOKIE['cf_channel'] ) ) ? '' : rawurldecode( $_COOKIE['cf_channel'] );
	$channel_exists = false;

	if ( $channel != '' ) {
		$channel_exists = $wpdb->get_var( $wpdb->prepare( "SELECT name FROM " . $tables['channels'] . " WHERE name = %s LIMIT 0, 1", $channel ) );
	}
	if ( ! $channel_exists || $channel == '' ) {
		$channel = $wpdb->get_var( $wpdb->prepare( "SELECT name FROM " . $tables['channels'] . " ORDER BY id ASC LIMIT 0, 1" ) );
	}

	$number_of_messages = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM " . $tables['actions'] . " WHERE channel = %s OR channel = ''", $channel ) );

	// If there is less then 10 results - set results number to 10 and query will load entries from 0 to 10.
	if ( $number_of_messages < 10 ) {
		$number_of_messages = 10;
	}
	$messages = $wpdb->get_results( $wpdb->prepare( "SELECT n.nick, n.male, n.variable, n.datetime, n.type FROM " . $tables['actions'] . " n WHERE n.channel = %s OR n.channel = '' ORDER BY n.datetime, n.id ASC LIMIT %d, 10", $channel, $number_of_messages-10, 10 ), OBJECT );

	$channel_topic = $wpdb->get_var( $wpdb->prepare( "SELECT topic FROM " . $tables['channels'] . " WHERE name='%s' LIMIT 0, 1", $channel ) );
	$bot_nick = $wpdb->get_var( $wpdb->prepare( "SELECT value FROM " . $tables['settings'] . " WHERE name = 'bot_nick' LIMIT 0, 1" ) );

	// If cookie is not set, then use variable from settings. Else - use cookie state.
	$is_notif_sound = empty( $_COOKIE['cf_notification'] ) ? get_option( 'cf_webchat_def_sound_state', 1 ) : cfw_str_to_bool( $_COOKIE['cf_notification'] );
	$is_autohide_sm = empty( $_COOKIE['cf_autohide'] ) ? true : cfw_str_to_bool( $_COOKIE['cf_autohide'] );

	// Get connection state.
	$connection_state = 0;
	$text = "";
	if ( get_option( 'cfw_ping_check', 1 ) ) {
		$connection_state = cfw_get_connection_state();
		if ( $connection_state == 1 ) {
			$css_class = "unstable_conn";
			$text = __( 'Connection to the server is not stable. There may be delays.', 'cf_webchat' );
		}
		elseif ( $connection_state == 2 ) {
			$css_class = "unactive_conn";
			$text = __( 'Connection to the server is missing. You can communicate only among users from web. Apologies for technical problems.', 'cf_webchat' );
		}
	}

	$plugin_dir = plugins_url( '', __FILE__ );

	require_once( 'templates/messages_area.php' );

	return $content;
}