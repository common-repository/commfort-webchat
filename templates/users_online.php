<?php
// $Id$
?>
<div id="users_list">
  <div class="all_users_count"><strong><?php _e( 'Users online', 'cf_webchat' ); ?>:</strong> <?php echo $online_users; ?>.</div>
  <ul>
    <?php foreach ( $accounts as $account ) { ?>
      <li class="single_user <?php echo ($account -> male == 1) ? 'woman' : 'man'; ?>" onmouseout="jQuery('div', this).css('display', 'none');" onmouseover="jQuery('div', this).css('display', 'block');">
    <?php echo cfw_get_themed_nick($account -> nick, false, false); ?>
          <div class="user_info">
          IP: <?php echo ($show_ips && $account -> ip != 'N/A') ? $account -> ip : __( 'Hidden', 'cf_webchat' ); ?><br />
          <?php _e( 'State', 'cf_webchat' ); ?>: <?php echo ($account -> state != '') ? $account -> state : __( 'Undefined', 'cf_webchat' ); ?>
          </div>
      </li>
    <?php }; ?>
  </ul>
</div>