<?php
	/*
		Plugin Name: Gravity Forms Search
		Author: Gennady Kovshenin
		Description: Adds search functionality to Gravity Forms forms lists.
		Author URI: http://codeseekah.com
		Version: 0.1
	*/

	if ( !class_exists( 'GFForms' ) || !defined( 'WPINC' ) )
		return;

	class GFFormsSearch {
		public static $_translation_domain = 'gfformssearch';

		public static function init() {
			if ( is_admin() ) {
				add_action( 'admin_init', array( __CLASS__, 'admin_init' ) );
			}
		}

		public static function admin_init() {
			add_action( 'current_screen', array( __CLASS__, 'filter_form_list' ) );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'render_select2' ) );
			add_filter( 'gform_form_apply_button', array( __CLASS__, 'form_list_searchform' ) );
		}

		/**
		 * Renders select2 functionality when appropriate
		 */
		public static function render_select2() {
			$current_screen = get_current_screen();
			if ( 'forms_page_gf_export' != $current_screen->id && 
				 'forms_page_gf_entries' != $current_screen->id && 
				 'post' != $current_screen->base && 'edit' != $current_screen->base )
				return;

			wp_enqueue_script( 'select2', plugins_url( 'select2/select2.js', __FILE__ ) );
			wp_enqueue_script( 'render_select2', plugins_url( 'select2/render_select2.js', __FILE__ ) );
			wp_enqueue_style( 'select2', plugins_url( 'select2/select2.css', __FILE__ ) );
		}

		/**
		 * Render search form through script at gform_form_apply_button hook
		 */
		public static function form_list_searchform( $return ) {
			/* The filter is applied twice, we only need to inject once... */
			remove_filter( 'gform_form_apply_button', array( __CLASS__, 'form_list_searchform' ) );
			?>
				<script type="text/javascript">
					jQuery( document ).ready( function() {
						var searchform = jQuery( '<form class="search-form" method="GET">' );
						searchform.append( '<input type="hidden" name="page" value="gf_edit_forms" /><p class="search-box"><label class="screen-reader-text" for="searchtags"><?php esc_html_e( 'Search Forms', self::$_translation_domain ); ?>:</label><input type="search" id="searchforms" name="s" value="<?php _admin_search_query(); ?>" /><?php submit_button( __( 'Search Forms', self::$_translation_domain ), 'button', false, false, array( 'id' => 'search-submit' ) ); ?></p>' );
						jQuery('.wrap h2').after( searchform );
					} );
				</script>

			<?php

			// Add the search results label if search
			if ( isset( $_GET['s'] ) ) {
				?>
					<script type="text/javascript">	
						jQuery( document ).ready( function() {
							var searchspan = '<?php printf( '<span class="subtitle">' . __( 'Search results for &#8220;%s&#8221;' ) . '</span>', esc_html( wp_unslash( $_REQUEST['s'] ) ) ); ?>';
							jQuery( '.wrap h2' ).append( searchspan );

						} );
					</script>
				<?php
			}

			return $return;
		}

		/**
		 * Rewrites the Gravity Forms get_forms query to
		 * filter based on our query.
		 * Hopefully some day Gravity Forms decides to provide
		 * more hooks to us developers and make our life easier...
		 *
		 * Also hijacks the translation string to display a better
		 * message when no forms found for a specific query. Thanks
		 * Gravity Forms, you're a pleasure to work with...
		 */
		public static function filter_form_list( $current_screen ) {
			if ( 'toplevel_page_gf_edit_forms' != $current_screen->id )
				return;

	?>
			<?php
			if ( empty( $_GET['s'] ) )
				return;

			add_filter( 'query', function( $query ) {
				global $wpdb;
				
				if ( strpos( $query, 'SELECT f.id, f.title, f.date_created, f.is_active, 0 as lead_count, 0 view_count' ) !== 0 )
					return $query;

				if ( strpos( $query, 'ORDER BY' ) !== false ) $query = str_replace( 'ORDER BY', $wpdb->prepare( 'AND title LIKE %s ORDER BY', '%' . like_escape( $_GET['s'] ) . '%' ), $query );
				else $query .= $wpdb->prepare( 'AND title LIKE %s ORDER BY', '%' . like_escape( $_GET['s'] ) . '%' );

				return $query;
			} );

			add_filter( 'gform_form_apply_button', function( $return ) {
				add_filter( 'gettext', array( __CLASS__, 'filter_form_list_strings' ), null, 3 );
				return $return;
			} );
		}


		/**
		 * Rewrites some strings.
		 */
		public static function filter_form_list_strings( $translations, $text, $domain ) {
			if ( $domain != 'gravityforms' || $text != "You don't have any forms. Let's go %screate one%s!" )
				return $translations;

			remove_filter( 'gettext', array( __CLASS__, 'filter_form_list_strings' ), null, 3 );

			/* There's a sprintf that we need to hide */
			return sprintf( __( 'No forms match your current search. <a href="%s">View all forms</a>.', self::$_translation_domain ),
				remove_query_arg( 's' ) ) . '<span class="hidden">%s%s</span>';
		}
	}

	add_action( 'init', array( 'GFFormsSearch', 'init' ) );
