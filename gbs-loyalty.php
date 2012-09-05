<?php
/*
Plugin Name: Group Buying Addon - Loyalty IDs
Version: 1
Plugin URI: http://groupbuyingsite.com/marketplace
Description: Option on Account page to add a loyalty number for a customer.
Author: Sprout Venture
Author URI: http://sproutventure.com/wordpress
Plugin Author: Dan Cameron
Contributors: Dan Cameron 
Text Domain: group-buying
Domain Path: /lang

*/

add_action('plugins_loaded', 'gb_load_loyalty_ids');
function gb_load_loyalty_ids() {
	if (class_exists('Group_Buying_Controller')) {
		require_once('groupBuyingLoyalty.class.php');
		require_once('library/template_tags.php');
	}
}