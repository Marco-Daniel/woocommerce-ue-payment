<?php

// if uninstall.php is not called by WordPress, die
if (!defined('WP_UNINSTALL_PLUGIN')) {
  die;
}

// delete plugin options
delete_option('title');
delete_option('description');
delete_option('enabled');
delete_option('testmode');
delete_option('use_accessclient');
delete_option('username');
delete_option('password');
delete_option('accessclient');

?>