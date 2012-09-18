<?php

// Initiate the add-on
class Group_Buying_Loyalty_IDs_Addon extends Group_Buying_Controller {
	
	public static function init() {
		// Hook this plugin into the GBS add-ons controller
		add_filter('gb_addons', array(get_class(),'gb_membership_layalty_addon'), 10, 1);
	}

	public static function gb_membership_layalty_addon( $addons ) {
		$addons['loyalty_ids'] = array(
			'label' => self::__('Loyalty IDs'),
			'description' => self::__('Option on Account page to add a loyalty number for a customer.'),
			'files' => array(
				__FILE__,
			),
			'callbacks' => array(
				array('Group_Buying_Loyalty_IDs', 'init')
			),
		);
		return $addons;
	}

}
Group_Buying_Loyalty_IDs_Addon::init();

class Group_Buying_Loyalty_IDs extends Group_Buying_Controller {

	const TAX = 'gb_loyalty_members';
	const TERM = 'loyal-member';
	const META_KEY = '_gb_loyalty_number';
	const REWRITE_SLUG = 'loyalty_members';

	public static function init() {
		
  		add_action( 'init', array(get_class(), 'init_tax'), 0 );
  
		// Meta Boxes
		add_action( 'add_meta_boxes', array(get_class(), 'add_meta_boxes'));
		add_action( 'save_post', array( get_class(), 'save_meta_boxes' ), 10, 2 );
		
		// Reports
		/// Purchase Report
		add_filter('set_deal_purchase_report_data_column', array(get_class(), 'set_deal_purchase_report_data_column'), 10, 1);
		add_filter('set_deal_purchase_report_data_records', array(get_class(), 'set_deal_purchase_report_data_records'), 10, 1);
		// Merchant Report
		add_filter('set_merchant_purchase_report_column', array(get_class(), 'set_deal_purchase_report_data_column'), 10, 1);
		add_filter('set_merchant_purchase_report_records', array(get_class(), 'set_deal_purchase_report_data_records'), 10, 1);
		/// Vouchers
		add_filter('set_deal_voucher_report_data_column', array(get_class(), 'set_deal_purchase_report_data_column'), 10, 1);
		add_filter('set_deal_voucher_report_data_records', array(get_class(), 'set_deal_purchase_report_data_records'), 10, 1);
		// Merchant Report
		add_filter('set_merchant_voucher_report_data_column', array(get_class(), 'set_deal_purchase_report_data_column'), 10, 1);
		add_filter('set_merchant_voucher_report_data_records', array(get_class(), 'set_deal_purchase_report_data_records'), 10, 1);

		// add_filter( 'gb_get_voucher_code', array( get_class(), 'filter_voucher_code' ), 10, 2 );
		add_filter( 'create_voucher_for_purchase', array( get_class(), 'create_voucher_for_purchase' ), 10, 3 );
	}
	
	public static function init_tax() {
		// register taxonomy
		$taxonomy_args = array(
			'hierarchical' => TRUE,
			'labels' => array('name' => gb__('Loyalty Member')),
			'show_ui' => FALSE,
			'rewrite' => array(
				'slug' => self::REWRITE_SLUG,
				'with_front' => FALSE,
				'hierarchical' => FALSE,
			),
		);
		register_taxonomy( self::TAX, array(Group_Buying_Account::POST_TYPE), $taxonomy_args );
	}

	public static function get_term_slug() {
		$term = get_term_by('slug', self::TERM, self::TAX);
		if ( !empty($term->slug) ) {
			return $term->slug;
		} else {
			$return = wp_insert_term(
				self::TERM, // the term 
				self::TAX, // the taxonomy
					array(
						'description'=> 'This is a loyalty member.',
						'slug' => self::TERM, )
				);
			return $return['slug'];
		}

	}
	
	public static function get_url() {
		return get_term_link( self::TERM, self::TAX);
	}

	public static function is_member_query( WP_Query $query = NULL ) {
		$taxonomy = get_query_var('taxonomy');
		if ( $taxonomy == self::TAX || $taxonomy == self::TAX || $taxonomy == self::TAX ) {
			return TRUE;
		}
		return FALSE;
	}
	
	/**
	 * @return int Alternative Price
	 */
	public function is_member( Group_Buying_Account $account ) {
		$member = array_pop(wp_get_object_terms( $account->get_id(), self::TAX));
		if ( !empty($member) && $member->slug = self::TERM ) {
			return TRUE;
		}
		return FALSE;
	}

	public function account_loyalty_id( $account_id ) {
		return get_post_meta( $account_id, self::META_KEY, TRUE );
	}
	
	public static function add_meta_boxes() {
		add_meta_box('gb_membership_pricing', self::__('Loyalty IDs'), array(get_class(), 'show_meta_boxes'), Group_Buying_Account::POST_TYPE, 'advanced', 'high');
	}

	public static function show_meta_boxes( $post, $metabox ) {
		switch ( $metabox['id'] ) {
			case 'gb_membership_pricing':
				self::show_meta_box($post, $metabox);
				break;
			default:
				self::unknown_meta_box($metabox['id']);
				break;
		}
	}

	private static function show_meta_box( $post, $metabox ) {
		$term = array_pop(wp_get_object_terms( $post->ID, self::TAX));
		$member = FALSE;
		if ( !empty($term) && $term->slug = self::TERM ) {
			$account = TRUE;
		}
		$loyalty_id = self::account_loyalty_id( $post->ID );
		?>
			<table class="form-table">
				<tbody>
					<tr>
						<td>
							<label for="gb_loyalty_id"><?php gb_e('Loyalty Number') ?></label>
							<input type="text" id="gb_loyalty_id" name="gb_loyalty_id" value="<?php echo $loyalty_id ?>"/>
						</td>
					</tr>
				</tbody>
			</table>
		<?php
	}

	public static function save_meta_boxes( $post_id, $post ) {
		// only continue if it's an account post
		if ( $post->post_type != Group_Buying_Account::POST_TYPE ) {
			return;
		}
		// don't do anything on autosave, auto-draft, bulk edit, or quick edit
		if ( wp_is_post_autosave( $post_id ) || $post->post_status == 'auto-draft' || defined('DOING_AJAX') || isset($_GET['bulk_edit']) ) {
			return;
		}
		self::save_meta_box($post_id, $post);
	}

	private static function save_meta_box( $post_id, $post ) {
		$member = ( isset( $_POST['gb_loyalty_id'] ) && $_POST['gb_loyalty_id'] != '' ) ? self::get_term_slug() : null;
		wp_set_object_terms( $post_id, $member, self::TAX );
		update_post_meta( $post_id, self::META_KEY, $_POST['gb_loyalty_id'] );
	}
	
	
	public static function set_deal_purchase_report_data_column( $columns ) {
		$columns['loyalty_id'] = self::__('Loyalty ID');
		return $columns;
	}
	public static function set_deal_purchase_report_data_records( $array ) {
		if ( !is_array($array) ) {
			return; // nothing to do.
		}
		$new_array = array();
		foreach ( $array as $records ) {
			$items = array();
			$purchase = Group_Buying_Purchase::get_instance($records['id']);
			$user_id = $purchase->get_user();
			$account_id = Group_Buying_Account::get_account_id_for_user( $user_id );
			$loyalty_id = self::account_loyalty_id( $account_id );
			if ( !empty($loyalty_id) ) {
				$loyalty = array( 'loyalty_id' => $loyalty_id );
			} else {
				$loyalty = array( 'loyalty_id' => self::__('N/A') );
			}
			$new_array[] = array_merge($records,$loyalty);
		}
		return $new_array;
	}

	public function filter_voucher_code( $serial, $voucher_id ) {
		$voucher = Group_Buying_Voucher::get_instance( $voucher_id );
		$purchase = $voucher->get_purchase();
		$user_id = $purchase->get_user();
		$account_id = Group_Buying_Account::get_account_id_for_user( $user_id );
		return self::account_loyalty_id( $account_id );
	}

	public function create_voucher_for_purchase( $voucher_id, $purchase, $product ) {
		$user_id = $purchase->get_user();
		$account_id = Group_Buying_Account::get_account_id_for_user( $user_id );
		$new_voucher_id = self::account_loyalty_id( $account_id );
		if ( $new_voucher_id ) {
			$voucher = Group_Buying_Voucher::get_instance( $voucher_id );
			$voucher->set_serial_number( $new_voucher_id );
		}
	}
}