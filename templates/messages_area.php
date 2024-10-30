<?php 
	$content = 
		'<div class="connection_state' . ( ( $connection_state != 0 ) ? ' ' . $css_class . '" style="display: block"' : '"' ) . ' title="' . __( 'Connection state', 'cf_webchat' ) . '">' . $text . '</div>
		<span class="channel_topic" title="' . __( 'Channel theme', 'cf_webchat' ) . '">' . $channel_topic . '</span>
		<div id="messages_list">';
	foreach( $messages as $message ) {
		$content .= cfw_constr_mess( $message, FALSE, $bot_nick );
		$time = $message -> datetime;
	};
	$content .= 
		'</div>
		<input type="hidden" value="' . $time . '" id="last_msg_time" name="last_msg_time" />
		<div id = "settings_block">
			<div class = "sound_opt" title="' . __( 'Enable or disable sound notification, when new message in chat recieved', 'cf_webchat' ) . '">
			<input type = "checkbox" name="notif_sound" id="notif_sound"' . ( ( $is_notif_sound ) ? ' checked="checked"' : '' ) . ' />
			<label for = "notif_sound" >' . __( 'Sound notification', 'cf_webchat' ) . '</label>
		</div>
		<div class = "autohide_opt" title="' . __( 'Enable or disable smilies list autohide, when smile added to message text area', 'cf_webchat' ) . '">
			<input type = "checkbox" name="autohide" id="autohide"' . ( ( $is_autohide_sm ) ? ' checked="checked"' : '' ) .' />
			<label for = "autohide" >' . __( 'Autohide smilies', 'cf_webchat' ) . '</label>
		</div>
		</div>
		<div id = "smilies_block">' . cfw_load_smilies_list() . '</div>';
	if ( current_user_can( 'send CommFort chat messages' ) ) {
		$content .=
		'<table id="send_block">
			<tbody>
				<tr class="send_row">
					<td class="input_field"><textarea id="message" rows="1" cols="1"></textarea></td>
					<td class="addit_btns">
						<img src="' . $plugin_dir . "/images/smilies.png" . '" alt="' . __( 'Show / Hide smilies block', 'cf_webchat' ) . '" title="' . __( 'Show / Hide smilies list', 'cf_webchat' ) . '" class="smilies_but" />
						<img src="' . $plugin_dir . "/images/settings.png" . '" alt="' . __( 'Show / Hide settings block', 'cf_webchat' ) . '" title="' . __( 'Show / Hide settings block', 'cf_webchat' ) . '" class="settings_but" />
						<img src="' . $plugin_dir . "/images/clear_chat.png" . '" alt="' .  __( 'Clear chat', 'cf_webchat' ) . '" title="' . __( 'Clear chat', 'cf_webchat' ) . '" class="clear_but" />
					</td>
					<td class="send_but">
						<input id="send" type="button" value="' . __( 'Send', 'cf_webchat' ) . '" />		
						<p>' . __( 'or', 'cf_webchat' ) . ' Ctrl+Enter</p>
					</td>
				</tr>
				<tr>
					<td><p class="send_state_text"></p></td>
					<td></td>
					<td></td>
				</tr>
			</tbody>
		</table>';
}