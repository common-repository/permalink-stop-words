<?php

/**
 * Plugin Name: Permalink Stop Words
 * Plugin URI:  https://github.com/wpcomvip/metro/
 * Description: Remove unwanted words from article permalinks.
 * Version:     1.0.0
 * Author:      Metro.co.uk
 * Author URI:  https://github.com/wpcomvip/metro/graphs/contributors
 * Text Domain: permalink-stop-words
 */

namespace MDT;

if ( ! class_exists( 'MDT\Permalink_Stop_Words' ) ) :

	/**
	 * Class Permalink_Stop_Words
	 *
	 * This class adds a Permalink Setting for configuring the words you
	 * don't want in your article permalinks.
	 *
	 * It also adds all the necessary hooks for filtering those words
	 * out of your permalinks.
	 */
	class Permalink_Stop_Words {

		/**
		 * Option name
		 *
		 * @var string
		 */
		CONST OPTION_NAME = 'permalink_unwanted_slug_words';

		/**
		 * Initial load.
		 */
		public static function load() {
			add_action( 'admin_init', [ __CLASS__, 'add_setting' ], 11 );
			add_filter( 'name_save_pre', [ __CLASS__, 'remove_stop_words_on_save' ] );
			add_filter( 'get_sample_permalink', [ __CLASS__, 'remove_stop_words_sample_permalink' ], 10, 4 );
			add_action( 'save_post', [ __CLASS__, 'remove_stop_words_on_quick_edit' ] );
			add_action( 'quick_edit_custom_box', [ __CLASS__, 'quick_edit_nonce' ], 10, 2 );
			add_action( 'manage_posts_custom_column', [ __CLASS__, 'quick_edit_fields' ], 10, 2 );
			add_action( 'admin_enqueue_scripts', [ __CLASS__, 'load_scripts' ] );
		}

		/**
		 * Add the unwanted words field to the Writing Settings page
		 */
		public static function add_setting() {

			/**
			 * Filter to choose anative WordPress page to put the Permalink Stop Words
			 * settings section.
			 *
			 * Other examples include `reading`, `discussion`, or `media`.
			 *
			 * @param string $section The name of the settings sections to add the field to.
			 */
			$settings_page = apply_filters( 'mdt_permalink_stop_words_settings_page', 'writing' );

			// Add a new settings section for the new field
			add_settings_section(
				'permalink_stop_words',
				'Permalink Stop Words',
				[ __CLASS__, 'section_html' ],
				$settings_page
			);

			// Add the settings field to the "optional" section on Permalink Settings
			add_settings_field(
				self::OPTION_NAME,
				sprintf(
					'<label for="%s">%s</label>',
					esc_attr( self::OPTION_NAME ),
					__( 'Words to remove from permalinks', 'permalink_stop_words' )
				),
				[ __CLASS__, 'setting_field_html' ],
				$settings_page,
				'permalink_stop_words'
			);

			// Register a general setting
			register_setting(
				$settings_page,
				self::OPTION_NAME,
				'sanitize_text_field'
			);	
		}

		/**
		 * Permalink Words section HTML.
		 */
		public static function section_html() {
			printf( 
				'<p>%s</p>',
				__( 'WordPress automatically builds a permalink slug based on the article title. Here, you can designate a list of common words to filter out of the permalink generation process to help keep your URLs short.', 'permalink-stop-words' )
			);
		}

		/**
		 * Outputs the settings field HTML.
		 */
		public static function setting_field_html() {
			printf(
				'<textarea id="%s" name="%s" rows="8" cols="50">%s</textarea><br />
				<small class="admin-note">Comma-separate words, e.g (a,about,above,after,again,against)</small>',
				esc_attr( self::OPTION_NAME ),
				esc_attr( self::OPTION_NAME ),
				esc_textarea( get_option( self::OPTION_NAME ) )
			);
		}

		/**
		 * Get the stop words.
		 *
		 * @return array The array of stop words.
		 */
		public static function get_stop_words() {
			$option = get_option( self::OPTION_NAME );

			return explode( ',', $option );
		}

		/**
		 * Remove the unwanted words from a slug.
		 *
		 * @param string $slug    The original article permalink slug.
		 * @param int    $post_id The post ID of the article.
		 * @return string New permalink with stop words removed.
		 */
		public static function remove_words( $slug, $post_id ) {

			/**
			 * Filter to choose anative WordPress page to put the Stop Words
			 * settings section.
			 *
			 * Other examples include `reading`, `discussion`, or `media`.
			 *
			 * @param array $words   The array of stop words to remove from the URL.
			 * @param int   $post_id The post ID of the article.
			 */
			$stop_words = apply_filters( 'mdt_permalink_stop_words', self::get_stop_words(), $post_id );

			// Turn the original slug into an array and strip the unwanted words.
			$new_slug_parts = array_diff( explode( '-', $slug ), $stop_words );

			// Use the original slug if there are less than 3 words left.
			if ( count( $new_slug_parts ) >= 3 ) {
				
				// Turn the array into a string.
				$slug = join( '-', $new_slug_parts );
			}

			return $slug;
		}

		/**
		 * Hook for the name_save_pre action.
		 *
		 * Apply the filter only on publish.
		 *
		 * @param string $slug The permalink slug.
		 * @return string The new permalink slug with stop words removed.
		 */
		public static function remove_stop_words_on_save( $slug ) {

			// If slug is set, remove the stop words.
			if ( isset( $_POST['hidden_post_status'] )
				&& ! empty( $_POST['ID'] )
				&& 'draft' === $_POST['hidden_post_status']
				&& 'publish' === $_POST['post_status'] 
			) {
				$slug = self::remove_words( $slug, $_POST['ID'] );
			}

			return $slug;
		}

		/**
		 * Hook for the admin-ajax call.
		 *
		 * @param array   $permalink {
		 *     Array containing the sample permalink with placeholder for the post name, and the post name.
		 *
		 *     @type string $0 The permalink with placeholder for the post name.
		 *     @type string $1 The post name.
		 * }
		 * @param int     $post_id   Post ID.
		 * @param string  $title     Post title.
		 * @param string  $name      Post name (slug).
		 * @param WP_Post $post      Post object.
		 * @return array The filtered permalink.
		 */
		public static function remove_stop_words_sample_permalink( $permalink, $post_id, $title, $name ) {

			// Remove the unwanted word the moment WordPress generates the sample slug.
			if ( empty( $name[0] ) && ! empty( $title ) ) {
				$permalink[1] = self::remove_words( $permalink[1], $post_id );
			}

			return $permalink;
		}

		/**
		 * Hook for the quick edit save.
		 *
		 * Apply the filter only on publish.
		 *
		 * @param $post_id
		 */
		public static function remove_stop_words_on_quick_edit( $post_id ) {

			// Prevent saving for revisions.
			if ( wp_is_post_revision( $post_id ) ) {
				return;
			}

			// Verify the nonce.
			if ( ! isset( $_POST['permalink-stop-words-nonce'] ) || ! wp_verify_nonce( $_POST['permalink-stop-words-nonce'], plugin_basename( __FILE__ ) ) ) {
				return;
			}

			// Handle the quick-edit request.
			if ( 'inline-save' === $_POST['action']
				&& 'publish' === $_POST['_status']
				&& 'draft' === $_POST['hidden_post_status']
			) {

				// Unhook this function so it doesn't loop infinitely.
				remove_action( 'save_post', [ __CLASS__, 'remove_stop_words_on_quick_edit' ] );

				// Remove stop words
				$new_slug = self::remove_words( wp_unslash( $_POST['post_name'] ), $post_id );

				// Update the post, which calls save_post again.
				wp_update_post(
					[
						'ID'   => $post_id,
						'slug' => $new_slug,
					]
				);

				// Re-hook this function.
				add_action( 'save_post', [ __CLASS__, 'remove_stop_words_on_quick_edit' ] );
			}

		}

		/**
		 * Retrieve the current post status to populate Quick Edit form
		 *
		 * @param string $column  The column name.
		 * @param int    $post_id The post ID.
		 */
		public static function quick_edit_fields( $column, $post_id ) {
			if ( 'status' === $column ) {
				printf(
					'<div class="hidden hidden_post_status">%s</div>',
					esc_html( get_post_status( $post_id ) )
				);
			}
		}

		/**
		 * Add the nonce for validation.
		 *
		 * @param string $column The name of the Quick Edit column.
		 */
		public static function quick_edit_nonce( $column ) {
			if ( 'status' === $column ) {
				wp_nonce_field( plugin_basename( __FILE__ ), 'permalink-stop-words-nonce' );
			}
		}

		/**
		 * Enqueue scripts for Quick Edit screen.
		 */
		public static function load_scripts() {
			$screen = get_current_screen();
			if ( 'edit-post' === $screen->id ) {
				wp_enqueue_script(
					'permalink_stop_words',
					plugin_dir_url( __FILE__ ) . 'assets/js/quick-edit.js',
					[ 'jquery' ]
				);
			}
		}
	}

	Permalink_Stop_Words::load();

endif;
