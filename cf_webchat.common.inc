<?php
// $Id$

/**
 * @file
 * Common functions part of module.
 *
 * It containes common functions of the module to use in different files.
 */


function cf_webchat_node_changed($new_node) {
  $chat_url = variable_get('cf_webchat_page_url', 'chat');
  
  // Delete old node and set node, to be created, to $new_node,
  // If node URI changed ($new_node != FALSE) and old node URI is not equal to new node URI ($chat_url != $new_node). 
  if ($new_node && $chat_url != $new_node) {
    if (substr($chat_url, 0, 4) != "node/") {
      $chat_node = drupal_lookup_path('source', $chat_url);     
    }
    $pos = strpos($chat_node, '/');
    $node_id = substr($chat_node, $pos + 1);
    node_delete($node_id);
      
    $chat_url = $new_node; 
  }  
  
  // Set chat alias to FALSE (if it isn't given) and $chat_url to FALSE if alias needed, but page for it doesn't exists. 
  if (substr($chat_url, 0, 4) != "node/") {
    if (!module_exists("path")) {
      module_enable(array("path"));
      drupal_set_message(t('Path module was enabled'));
    }
    $chat_alias = $chat_url;
    $chat_url = drupal_lookup_path('source', $chat_url);
  }
  else $chat_alias = FALSE;
  
  // Check, if new node URI exists. 
  if ($chat_url) {
    $pos = strpos($chat_node, '/');
    $node_id = substr($chat_node, $pos + 1);
    $node = node_load($node_id);
  }
  
  // If new node hasn't existed yet, create new node and alias (if needed). 
  if (!isset($node)) {
    $node->title = 'CommFort Chat';
    $node->created = time();
    // Set status to "published".
    $node->status = 1; 
    $node->promote = 0;
    $node->sticky = 0;
    $node->body = t('Chat will be based here');
    $node->type = 'page'; 
    node_save($node);
    if ($chat_alias) {
      path_set_alias("node/" . $node->nid, $chat_alias);
    }
    $message = t('Page for chat was created');
  }
  else {
    $message = t('Page for chat is already exists');
  }

  $page = (($chat_alias) ? $chat_alias : $chat_url);
  $message .= '. ' . l(t('Go to chat page'), $page, array('absolute' => TRUE));
  drupal_set_message($message);
}