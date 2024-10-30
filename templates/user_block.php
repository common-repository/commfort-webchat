<?php
// $Id$
?>
<div id="curr_user">
  <span class="name man"><a href="/wp-admin/profile.php" title="<?php _e( 'Your Profile' ); ?>" ><?php echo $user_login; ?></a></span>
  <div class="channel">
    <select name="channel" id="channel">
      <?php foreach ( $channels as $channel ) { ?>
        <option<?php echo ($a_channel == $channel) ?  " selected" : ""; ?>><?php echo $channel; ?></option>
      <?php }; ?>
    </select>
  </div>
  <div class="state"><img src="<?php echo $img; ?>" alt="<?php echo $alt; ?>" title="<?php echo $alt; ?>" /><span><?php echo $tip; ?></span></div>
</div>