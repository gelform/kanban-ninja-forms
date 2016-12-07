<?php
/*
Contributors:		gelform
Plugin Name:		Kanban + Ninja Forms
Plugin URI:			https://kanbanwp.com/addons/ninjaforms/
Description:		Use Ninja Forms forms to interact with your Kanban boards.
Requires at least:	4.0
Tested up to:		4.7.0
Version:			0.0.1
Release Date:		November 28, 2016
Author:				Gelform Inc
Author URI:			http://gelwp.com
License:			GPLv2 or later
License URI:		http://www.gnu.org/licenses/gpl-2.0.html
Text Domain:		kanban
Domain Path: 		/languages/
*/



// Kanban + Ninja Forms is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 2 of the License, or
// any later version.
//
// Kanban + Ninja Forms is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Kanban Shortcodes. If not, see {URI to Plugin License}.



// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}



class Kanban_Ninja_Forms {
	static $slug = '';
	static $friendlyname = '';
	static $plugin_basename = '';



	static function init() {
		self::$slug            = basename( __FILE__, '.php' );
		self::$plugin_basename = plugin_basename( __FILE__ );
		self::$friendlyname    = 'Kanban + Ninja Forms';



		register_activation_hook( __FILE__, array( __CLASS__, 'check_for_core' ) );
		add_action( 'admin_init', array( __CLASS__, 'check_for_core' ) );

		// just in case
		if ( ! self::_is_parent_loaded() ) {
			return;
		}



		// Catch and save admin settings.
		add_action( 'init', array( __CLASS__, 'save_settings' ) );

		// Add admin subpage to Kanban admin menu.
		add_action( 'admin_menu', array( __CLASS__, 'admin_menu' ), 10 );

		// Save the submitted form data as a Kanban task.
		add_action( 'ninja_forms_after_submission', array( __CLASS__, 'on_post_submission' ), 10, 2 );

		// Add Kanban data to dropdowns, radio buttons, etc.
		add_filter( 'ninja_forms_render_options', array( __CLASS__, 'populate_field' ), 10, 2 );

		// Add Kanban data to hidden fields.
		add_filter( 'ninja_forms_render_default_value', array( __CLASS__, 'populate_default_value' ), 10, 3 );
	}



	/**
	 * Add admin subpage to Kanban admin menu.
	 */
	static function admin_menu() {
		add_submenu_page(
			Kanban::get_instance()->settings->basename,
			'Kanban Ninja Forms',
			'Ninja Forms',
			'manage_options',
			'kanban_ninjaforms',
			array( __CLASS__, 'add_admin_page' )
		);
	}



	// Render admin subpage.
	static function add_admin_page() {

		// Get forms.
		$forms = array();
		if ( function_exists( 'Ninja_Forms' ) ) {
			$forms = Ninja_Forms()->form()->get_forms();
		}

		// Get all boards.
		$boards = Kanban_Board::get_all();

		// Get all data for all boards.
		foreach ( $boards as $board_id => &$board ) {
			$board->projects  = Kanban_Project::get_all( $board_id );
			$board->statuses  = Kanban_Status::get_all( $board_id );
			$board->estimates = Kanban_Estimate::get_all( $board_id );
			$board->users     = Kanban_User::get_allowed_users( $board_id );
		}



		$table_columns = array(
			'title'            => 'Title',
			'user_id_author'   => 'Task author',
			'user_id_assigned' => 'Assigned to user',
			'status_id'        => 'Status',
			'estimate_id'      => 'Estimate',
			'project_id'       => 'Project'
		);



		// Previously saved data.
		$saved = Kanban_Option::get_option( self::$slug );

		include plugin_dir_path( __FILE__ ) . 'templates/admin-page.php';
	}



	/**
	 * Save admin data.
	 */
	static function save_settings() {

		if ( ! is_admin() || $_SERVER[ 'REQUEST_METHOD' ] != 'POST' || ! isset( $_POST[ self::$slug . '-nonce' ] ) || ! wp_verify_nonce( $_POST[ self::$slug . '-nonce' ], self::$slug ) ) {
			return;
		}

		Kanban_Option::update_option( self::$slug, $_POST[ 'forms' ] );

		wp_redirect(
			add_query_arg(
				array(
					'notice' => __( 'Saved!', 'kanban' )
				),
				sanitize_text_field( wp_unslash( $_POST[ '_wp_http_referer' ] ) )
			)
		);
		exit;
	}



	static function on_post_submission( $form_data ) {

		$saved = Kanban_Option::get_option( self::$slug );

		$form_id = $form_data[ 'form_id' ];

		if ( ! isset( $saved[ $form_id ] ) ) {
			return false;
		}



		$table_columns = Kanban_Task::table_columns();
		$task_data     = array_fill_keys( array_keys( $table_columns ), '' );



		$board_id = $saved[ $form_id ][ 'board' ];

		$task_data[ 'created_dt_gmt' ]   = Kanban_Utils::mysql_now_gmt();
		$task_data[ 'modified_dt_gmt' ]  = Kanban_Utils::mysql_now_gmt();
		$task_data[ 'modified_user_id' ] = 0; // get_current_user_id();
		$task_data[ 'user_id_author' ]   = get_current_user_id();
		$task_data[ 'is_active' ]        = 1;
		$task_data[ 'board_id' ]         = $board_id;



		foreach ( $saved[ $form_id ] as $field_id => $task_field ) {

			// get the board id and move on
			if ( $field_id == 'board' ) {
				continue;
			}

			if ( empty( $task_field[ 'table_column' ] ) ) {
				continue;
			}

			$task_data[ $task_field[ 'table_column' ] ] = $form_data[ 'fields' ][ $field_id ][ 'value' ];
		}



		//Set to the first status if empty.
		if ( empty( $task_data[ 'status_id' ] ) ) {
			$statuses = Kanban_Status::get_all( $board_id );

			$status = reset( $statuses );

			$task_data[ 'status_id' ] = $status->id;
		}



		Kanban_Task::replace( $task_data );
	}



	/**
	 * @link http://developer.ninjaforms.com/codex/pre-populating-fields-on-display/
	 *
	 * @param $default_value
	 * @param $field_type
	 * @param $field_settings
	 *
	 * @return string
	 */
	static function populate_default_value( $default_value, $field_type, $settings ) {

		if ( 'hidden' == $field_type ) {
			$saved = Kanban_Option::get_option( self::$slug );

			$field_id = $settings[ 'id' ];

			$form_id = '';
			foreach ( $saved as $saved_form_id => $form ) {
				foreach ( $form as $saved_field_id => $field ) {
					if ( $saved_field_id == $field_id ) {
						$form_id = $saved_form_id;
						break;
					}
				}
			}



			if ( ! isset( $saved[ $form_id ] ) ) {
				return $default_value;
			}



			foreach ( $saved[ $form_id ] as $field_id => $task_field ) {

				if ( $field_id == 'board' ) {
					continue;
				}

				if ( ! isset( $task_field[ 'defaultValue' ] ) ) {
					$task_field[ 'defaultValue' ] = null;
				}

				$default_value = $task_field[ 'defaultValue' ];

			}

		}

		return $default_value;
	}



	/**
	 *
	 * @link https://github.com/wpninjas/ninja-forms/blob/master/includes/Display/Render.php#L229
	 *
	 * @param $form
	 *
	 * @return object
	 */
	static function populate_field( $options, $settings ) {

		$saved = Kanban_Option::get_option( self::$slug );

		$field_id = $settings[ 'id' ];

		$form_id = '';
		foreach ( $saved as $saved_form_id => $form ) {
			foreach ( $form as $saved_field_id => $field ) {
				if ( $saved_field_id == $field_id ) {
					$form_id = $saved_form_id;
					break;
				}
			}
		}



		if ( ! isset( $saved[ $form_id ] ) ) {
			return $options;
		}

		$board_id = $saved[ $form_id ][ 'board' ];



		$estimates = array();
		$statuses  = array();
		$users     = array();

		$task_field = $saved[ $form_id ][ $field_id ];


		switch ( $task_field[ 'table_column' ] ) {
			case 'estimate_id':

				if ( empty( $estimates ) ) {
					$estimates = Kanban_Estimate::get_all( $board_id );
				}

				$options = array();
				foreach ( $estimates as $estimate ) {

					$options[] = array(
						'label' => $estimate->title,
						'value' => $estimate->id
					);
				}

				break;

			case 'status_id':

				if ( empty( $statuses ) ) {
					$statuses = Kanban_Status::get_all( $board_id );
				}


				$options = array();
				foreach ( $statuses as $status ) {

					$options[] = array(
						'label' => $status->title,
						'value' => $status->id
					);
				}

				break;

			case 'project_id':

				if ( empty( $projects ) ) {
					$projects = Kanban_Project::get_all( $board_id );
				}

				$options = array();
				foreach ( $projects as $project ) {

					$options[] = array(
						'label' => $project->title,
						'value' => $project->id
					);
				}

				break;

			case 'user_id_author':
			case 'user_id_assigned':

				if ( empty( $users ) ) {
					$users = Kanban_User::get_allowed_users( $board_id );
				}

				$options = array();
				foreach ( $users as $user ) {

					$options[] = array(
						'label' => $user->long_name_email,
						'value' => $user->ID
					);
				}

				break;
		}

		return $options;
	}



	static function check_for_core() {
		if ( self::_is_parent_loaded() ) {
			return true;
		}

		deactivate_plugins( plugin_basename( __FILE__ ) );
		add_action( 'admin_notices', array( __CLASS__, 'admin_deactivate_notice' ) );
	}



	static function admin_deactivate_notice() {
		if ( ! is_admin() ) {
			return;
		}
		?>
		<div class="error below-h2">
			<p>
				<?php
				echo sprintf(
					__( 'Whoops! This plugin %s requires the <a href="https://wordpress.org/plugins/kanban/" target="_blank">Kanban for WordPress</a> plugin.
	            		Please make sure it\'s installed and activated.'
					),
					self::$friendlyname
				);
				?>
			</p>
		</div>
		<?php
	}



	static function _is_parent_loaded() {
		return class_exists( 'Kanban' );
	}



	static function _is_parent_activated() {

		if ( is_multisite() ) {
			$active_plugins_basenames = get_site_option( 'active_sitewide_plugins' );

			if ( ! empty( $active_plugins_basenames ) ) {
				$active_plugins_basenames = array_keys( $active_plugins_basenames );
			}
		} else {
			$active_plugins_basenames = get_option( 'active_plugins' );
		}

		foreach ( $active_plugins_basenames as $plugin_basename ) {
			if ( false !== strpos( $plugin_basename, '/kanban.php' ) ) {
				return true;
			}
		}

		return false;
	}


}



function kanban_ninjaforms_addon() {
	Kanban_Ninja_Forms::init();
}



if ( Kanban_Ninja_Forms::_is_parent_loaded() ) {
	// If parent plugin already included, init add-on.
	kanban_ninjaforms_addon();
} else if ( Kanban_Ninja_Forms::_is_parent_activated() ) {
	// Init add-on only after the parent plugins is loaded.
	add_action( 'kanban_loaded', 'kanban_ninjaforms_addon' );
} else {
	// Even though the parent plugin is not activated, execute add-on for activation / uninstall hooks.
	kanban_ninjaforms_addon();
}
