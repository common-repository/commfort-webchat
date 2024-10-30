<?php
// $Id$

/**
 * @file
 * The administrative part of module.
 *
 * It containes settings form and help to config the module.
 */
 
function cfw_set() {
	$smilies_desc = __( 'Smilies have to be located at path - "!foldername". File "!filename" must have encoding UTF8', 'cf_webchat' );
	$smilies_desc = cfw_rep_var( $smilies_desc, array("!foldername" => "cf_webchat/smilies", "!filename" => "smilies_serv.txt") );
	?>
	<div class="wrap">
		<h2><?php _e( 'CommFort WebChat', 'cf_webchat' ); ?></h2>
		<form method="post" action="options.php">
			<?php settings_fields( 'cf_webchat' ); ?>
			<table class="form-table">	
				<?php /*<tr valign="top">
					<th scope="row"><?php _e( 'Chat URL', 'cf_webchat' ); ?></th>
					<td>
						<input type="text" name="cf_webchat_page_url" value="<?php echo get_option( 'cf_webchat_page_url', 'chat' ); ?>" size="40" maxlength="128" />
						<span class="description"><?php _e( "Enter the URL, where the chat would be located. Do not include base root (http://sait_name/)! Examples: chat, node/1...", 'cf_webchat' ); ?></span>
					</td>
				</tr>*/ ?>
				<tr valign="top">
					<th scope="row">
						<label>
							<input type="checkbox" name="cf_webchat_show_ips" value="1"<?php checked( '1', get_option( 'cf_webchat_show_ips', '1' ) ); ?>" />
							<?php _e( "Show users IP's", 'cf_webchat' ); ?>
						</label>
					</th>
					<td>			
						<span class="description"><?php _e( "Check, if IPs must be shown in users block", 'cf_webchat' ); ?></span>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<label>
							<input type="checkbox" name="cf_webchat_smilies_enabled" value="1"<?php checked('1', get_option('cf_webchat_smilies_enabled')); ?>" />
							<?php _e( "Enable smilies", 'cf_webchat' ); ?>
						</label>
					</th>
					<td>			
						<span class="description"><?php echo $smilies_desc; ?></span>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<label>
							<input type="checkbox" name="cf_webchat_def_sound_state" value="1"<?php checked('1', get_option('cf_webchat_def_sound_state', '1')); ?>" />
							<?php _e( 'Enable sound notification', 'cf_webchat' ); ?>
						</label>
					</th>
					<td>			
						<span class="description"><?php _e( 'Check, to enable sound notification by default. The sound will be played on each chat event', 'cf_webchat' ); ?></span>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<label>
							<input type="checkbox" name="cf_webchat_ping_check" value="1"<?php checked('1', get_option('cf_webchat_ping_check', '1')); ?>" />
							<?php _e( 'Enable checking ping from plugin', 'cf_webchat' ); ?>
						</label>
					</th>
					<td>			
						<span class="description"><?php _e( 'Check, to enable checking ping from plugin, to determine connection state between plugin and webchat (webchat users will see tips, if connection problems appeared). When connection state would set to unactive - all webchat talking will be only among webchat users. Time between machine, where plugin placed and machine, where webchat works, must be strongly SYNCHRONIZED', 'cf_webchat' ); ?></span>
					</td>
				</tr>
			</table>
			<p class="submit"><input type="submit" class="button-primary" value="<?php _e( 'Save Changes' ); ?>" /></p>
		</form>
	</div>
	<?php
}

function check_chat_url( $input )  {
	$output = trim($input, "/ \ \t\n\r\0\x0B");
  
	return $output;
 /* module_load_include('inc', 'cf_webchat', 'cf_webchat.common');
  cf_webchat_node_changed($form_state['values']['cf_webchat_page_url']);
  
  return system_settings_form_submit($form, $form_state);*/
}

function cfw_optimize_smilies( $input )  {
	// If user unchecked using smilies, then do nothing. 
	if ( ! $input ) {
		return;
	}
	
	$sm_server = WP_PLUGIN_DIR . '/' . str_replace( basename( __FILE__ ), '', plugin_basename( __FILE__ ) ) . 'smilies/smilies_serv.txt';

	if ( file_exists( $sm_server ) ) {
		$file = file( $sm_server, FILE_SKIP_EMPTY_LINES );
		$line_offset = 0;
		while ( $file[$line_offset][0] != '[' ) {
			$line_offset++; 
		}  
		$upload_dir = wp_upload_dir();
		$optim_folder = $upload_dir['basedir'] . "/cf_webchat";
		if ( ! is_dir( $optim_folder ) ) {
			mkdir( $optim_folder );
		}
		$sm_paths = fopen( $optim_folder . '/sm_paths.txt', "wb" );
		$sm_symbs = fopen( $optim_folder . '/sm_symbs.txt', "wb" );

		for ( $i = $line_offset; $i < count($file); $i++ ) {
			$line = trim( $file[$i] );
			if ( $line != '' ) {
				if ( $line[0] != '[' ) {
					$pos = strpos( $line, '|' );
					$path = str_replace( "\\", "/", substr( $line, 0, $pos ) ) . "\r\n";
					$pos2 = strpos( $line, '"', $pos + 1 );
					$smile = substr( $line, $pos2 + 1, strlen( $line ) - $pos2 - 2 ) . "\r\n";       
					fwrite( $sm_paths, $path );
					fwrite( $sm_symbs, $smile );
				} else {
					$pos = strpos( $file[$i], ']' );
					$group = substr( $file[$i], 1, $pos - 1 );
					( $group == "hidden" ) ? $group = '#^hidden' : $group = '#' . $group; 
					$group .= "\r\n";
					fwrite( $sm_paths, $group );
					fwrite( $sm_symbs, $group );
				}
			}  
		}
		fclose( $sm_paths );
		fclose( $sm_symbs );
		add_settings_error('general', 'settings_updated', __( 'Settings saved.' ) . ' ' . __( 'Smilies successfully optimized', 'cf_webchat' ), 'updated');
	} else {
		$input = false;
		add_settings_error('general', 'settings_error', cfw_rep_var( __( 'Smilies file does not exist. Please, locate file "smilies_serv.txt" in folder "@foldername"', 'cf_webchat' ), array( "@foldername" => "cf_webchat/smilies/" ) ), 'error');
	}
	
	return $input;
}