<?php

namespace {
	// Fake for unit tests.
	if ( ! class_exists( 'WP_CLI_Command' ) ) {
		class WP_CLI_Command {}
	}
}


namespace BU\Migrate {
/**
 * Plugin Name: ENG Mediafix
 * Description: Collection of WP-CLI commands to work with linked media library files
 * Author: jaydub
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Version: 0.1
 */

class MediaFix extends \WP_CLI_Command {

	/**
	 * Import and relink media attachments for a single post
	 *
	 * ## OPTIONS
	 *
	 * <command>
 	 * : The command to run.
 	 *
 	 * [<subcommand>]
  	 * : The subcommand to run.
  	 *
	 * [--import-host]
	 * : If importing media from a host other than the current one, set it here (with protocol)
	 *
	 * @alias fix-one-attachments
	 *
	 * @param array $args Positional args.
	 * @param array $args Assocative args.
	 */
	public function fix_one_attachments( $args, $args_assoc ) {
		//disable thumbnail generation
		add_filter( 'intermediate_image_sizes_advanced', '__return_false' );

		$post = get_post($args[0]);
		if(!$post) {\WP_CLI::error("Post not found");}

		//set the import host from flag, or default to the current host
		$import_host = \WP_CLI\Utils\get_flag_value( $args_assoc, 'import-host' );
		if(!$import_host) {$import_host = self::default_host();}


		$text = $post->post_content;

		$new_text = self::process_attached_media($text,$post,$import_host);
		
		if ( $new_text === $text ) {
			\WP_CLI::log( sprintf("\tPost %d unchanged", $post->ID) );
			return;
		}

		// Update post.
		$result = wp_update_post( array(
			'ID'           => $post->ID,
			'post_content' => $new_text,
		), true );

		if ( is_wp_error( $result ) ) {
			\WP_CLI::log( sprintf(
				"\t[#%d] Failed rewriting post links: %s",
				$post->ID,
				$result->get_error_message()
			) );
		} else {
			\WP_CLI::log( sprintf("\tPost %d updated.", $post->ID) );
		}
	}

	/**
	 * Import and relink imgs for a single post
	 *
	 * ## OPTIONS
	 *
	 * <command>
 	 * : The command to run.
 	 *
 	 * [<subcommand>]
  	 * : The subcommand to run.
  	 *
	 * [--import-host]
	 * : If importing media from a host other than the current one, set it here (with protocol)
	 *
	 * @alias fix-one-img
	 *
	 * @param array $args Positional args.
	 * @param array $args Assocative args.
	 */
	public function fix_one_img( $args, $args_assoc ) {
		//disable thumbnail generation
		add_filter( 'intermediate_image_sizes_advanced', '__return_false' );

		$post = get_post($args[0]);
		if(!$post) {\WP_CLI::error("Post not found");}

		//set the import host from flag, or default to the current host
		$import_host = \WP_CLI\Utils\get_flag_value( $args_assoc, 'import-host' );
		if(!$import_host) {$import_host = self::default_host();}


		$text = $post->post_content;

		$new_text = self::process_imgs($text,$post,$import_host);
		
		if ( $new_text === $text ) {
			\WP_CLI::log( sprintf("\tPost %d unchanged", $post->ID) );
			return;
		}

		// Update post.
		$result = wp_update_post( array(
			'ID'           => $post->ID,
			'post_content' => $new_text,
		), true );

		if ( is_wp_error( $result ) ) {
			\WP_CLI::log( sprintf(
				"\t[#%d] Failed rewriting post links: %s",
				$post->ID,
				$result->get_error_message()
			) );
		} else {
			\WP_CLI::log( sprintf("\tPost %d updated.", $post->ID) );
		}
	}


	/**
	 * Import and relink a href media for a single post
	 *
	 * ## OPTIONS
	 *
	 * <command>
 	 * : The command to run.
 	 *
 	 * [<subcommand>]
  	 * : The subcommand to run.
  	 *
	 * [--import-host]
	 * : If importing media from a host other than the current one, set it here (with protocol)
	 *
	 * @alias fix-one-a-href
	 *
	 * @param array $args Positional args.
	 * @param array $args Assocative args.
	 */
	public function fix_one_a_href( $args, $args_assoc ) {
		//disable thumbnail generation
		add_filter( 'intermediate_image_sizes_advanced', '__return_false' );

		$post = get_post($args[0]);
		if(!$post) {\WP_CLI::error("Post not found");}

		//set the import host from flag, or default to the current host
		$import_host = \WP_CLI\Utils\get_flag_value( $args_assoc, 'import-host' );
		if(!$import_host) {$import_host = self::default_host();}


		$text = $post->post_content;

		$new_text = self::process_a_hrefs($text,$post,$import_host);
		
		if ( $new_text === $text ) {
			\WP_CLI::log( sprintf("\tPost %d unchanged", $post->ID) );
			return;
		}

		// Update post.
		$result = wp_update_post( array(
			'ID'           => $post->ID,
			'post_content' => $new_text,
		), true );

		if ( is_wp_error( $result ) ) {
			\WP_CLI::log( sprintf(
				"\t[#%d] Failed rewriting post links: %s",
				$post->ID,
				$result->get_error_message()
			) );
		} else {
			\WP_CLI::log( sprintf("\tPost %d updated.", $post->ID) );
		}
	}

	/**
	 * Scans post text for scaled imgs and, if missing, generates the correct size for existing markup
	 *
	 * @alias fix-one-thumbnails
	 *
	 * @param array $args Positional args.
	 * @param array $args Assocative args.
	 */
	public function fix_one_thumbnails( $args, $args_assoc ) {
		$post = get_post($args[0]);
		if(!$post) {\WP_CLI::error("Post not found");}

		$text = $post->post->content;

		$result = self::process_img_thumbs($post);

		if ($result) {
			\WP_CLI::log( sprintf( "Post %d processed", $post->ID ) );
		} else {
			\WP_CLI::warning( sprintf( "Error on post %d", $post->ID ) );
		}
	}

	/**
	 * Scans post text Media Library items with an alt tag and checks for that value in the post-meta
	 *
	 * @alias fix-one-alt
	 *
	 * @param array $args Positional args.
	 * @param array $args Assocative args.
	 */
	public function fix_one_alt( $args, $args_assoc ) {
		$post = get_post($args[0]);
		if(!$post) {\WP_CLI::error("Post not found");}

		$text = $post->post->content;

		$result = self::process_img_alt($post);

		if ($result) {
			\WP_CLI::log( sprintf( "Post %d processed", $post->ID ) );
		} else {
			\WP_CLI::warning( sprintf( "Error on post %d", $post->ID ) );
		}
	}



	/**
	 * Iterate over every post, processing the attached media and re-writing the links in the post
	 *
	 *
	 * [--import-host]
	 * : If importing media from a host other than the current one, set it here (with protocol)
	 *
	 * [--post-type]
	 * : Specify a post type to operate on.  Defaults to any
	 *
	 * @alias fix-all-attachments
	 *
	 */
	public function fix_all_attachments($args, $args_assoc) {
		//disable thumbnail generation
		add_filter( 'intermediate_image_sizes_advanced', '__return_false' );

		//set the import host from flag, or default to the current host
		$import_host = \WP_CLI\Utils\get_flag_value( $args_assoc, 'import-host' );
		if(!$import_host) {$import_host = self::default_host();}

		//set the post type from flag, or default to any post type
		$post_type = \WP_CLI\Utils\get_flag_value( $args_assoc, 'post-type' );
		if (!$post_type) {$post_type = 'any';}

		$limit     = 50;
		$post_args = array(
			'offset'           => 0,
			'posts_per_page'   => $limit,
			'suppress_filters' => false,
			'post_type'        => $post_type
		);

		if ( ! current_user_can( 'import' ) ) {
			\WP_CLI::error( "You must run this command with a --user specified (site admin or network admin)." );
			exit;
		}

		\WP_CLI::log( PHP_EOL . 'WARNING: Not extensively tested with non-UTF8.' );
		\WP_CLI::log( 'WARNING: DOMDocument is likely to make small changes to any HTML as part of its processing.' . PHP_EOL );
		libxml_use_internal_errors( true );

		while ( ( $posts = get_posts( $post_args ) ) !== array() ) {
			\WP_CLI::log( "\nSearching posts..." );

			foreach ( $posts as $post ) {
				$text = $post->post_content;
				//scan the post text, import media, and get back the re-written post text
				$new_text = self::process_attached_media($text, $post, $import_host);

				if ( $new_text === $text ) {
					\WP_CLI::log( sprintf("\tPost %d unchanged", $post->ID) );
					continue;
				}

				// Update post.
				$result = wp_update_post( array(
					'ID'           => $post->ID,
					'post_content' => $new_text,
				), true );

				if ( is_wp_error( $result ) ) {
					\WP_CLI::log( sprintf(
						"\t[#%d] Failed rewriting post links: %s",
						$post->ID,
						$result->get_error_message()
					) );
				} else {
					\WP_CLI::log( sprintf("\tPost %d updated.", $post->ID) );
				}

			} //end foreach

			$post_args['offset'] += $limit;  // Keep the loop loopin'.
		} //endwhile

		libxml_clear_errors();
		libxml_use_internal_errors( false );
	}


	/**
	 * Iterate over every post, processing the img tags and re-writing the links in the post
	 *
	 *
	 * [--import-host]
	 * : If importing media from a host other than the current one, set it here (with protocol)
	 *
	 * [--post-type]
	 * : Specify a post type to operate on.  Defaults to any
	 *
	 * @alias fix-all-img
	 *
	 */
	public function fix_all_img($args, $args_assoc) {
		//disable thumbnail generation
		add_filter( 'intermediate_image_sizes_advanced', '__return_false' );

		//set the import host from flag, or default to the current host
		$import_host = \WP_CLI\Utils\get_flag_value( $args_assoc, 'import-host' );
		if(!$import_host) {$import_host = self::default_host();}

		//set the post type from flag, or default to any post type
		$post_type = \WP_CLI\Utils\get_flag_value( $args_assoc, 'post-type' );
		if (!$post_type) {$post_type = 'any';}

		$limit     = 50;
		$post_args = array(
			'offset'           => 0,
			'posts_per_page'   => $limit,
			'suppress_filters' => false,
			'post_type'        => $post_type
		);

		if ( ! current_user_can( 'import' ) ) {
			\WP_CLI::error( "You must run this command with a --user specified (site admin or network admin)." );
			exit;
		}

		\WP_CLI::log( PHP_EOL . 'WARNING: Not extensively tested with non-UTF8.' );
		\WP_CLI::log( 'WARNING: DOMDocument is likely to make small changes to any HTML as part of its processing.' . PHP_EOL );
		libxml_use_internal_errors( true );

		while ( ( $posts = get_posts( $post_args ) ) !== array() ) {
			\WP_CLI::log( "\nSearching posts..." );

			foreach ( $posts as $post ) {
				$text = $post->post_content;
				//scan the post text, import media, and get back the re-written post text
				$new_text = self::process_imgs($text, $post, $import_host);

				if ( $new_text === $text ) {
					\WP_CLI::log( sprintf("\tPost %d unchanged", $post->ID) );
					continue;
				}

				// Update post.
				$result = wp_update_post( array(
					'ID'           => $post->ID,
					'post_content' => $new_text,
				), true );

				if ( is_wp_error( $result ) ) {
					\WP_CLI::log( sprintf(
						"\t[#%d] Failed rewriting post links: %s",
						$post->ID,
						$result->get_error_message()
					) );
				} else {
					\WP_CLI::log( sprintf("\tPost %d updated.", $post->ID) );
				}

			} //end foreach

			$post_args['offset'] += $limit;  // Keep the loop loopin'.
		} //endwhile

		libxml_clear_errors();
		libxml_use_internal_errors( false );
	}

	/**
	 * Iterate over every post, processing the img tags and re-writing the links in the post
	 *
	 *
	 * [--import-host]
	 * : If importing media from a host other than the current one, set it here (with protocol)
	 *
	 * [--post-type]
	 * : Specify a post type to operate on.  Defaults to any
	 *
	 * @alias fix-all-a-href
	 *
	 */
	public function fix_all_a_href($args, $args_assoc) {
		//disable thumbnail generation
		add_filter( 'intermediate_image_sizes_advanced', '__return_false' );

		//set the import host from flag, or default to the current host
		$import_host = \WP_CLI\Utils\get_flag_value( $args_assoc, 'import-host' );
		if(!$import_host) {$import_host = self::default_host();}

		//set the post type from flag, or default to any post type
		$post_type = \WP_CLI\Utils\get_flag_value( $args_assoc, 'post-type' );
		if (!$post_type) {$post_type = 'any';}

		$limit     = 50;
		$post_args = array(
			'offset'           => 0,
			'posts_per_page'   => $limit,
			'suppress_filters' => false,
			'post_type'        => $post_type
		);

		if ( ! current_user_can( 'import' ) ) {
			\WP_CLI::error( "You must run this command with a --user specified (site admin or network admin)." );
			exit;
		}

		\WP_CLI::log( PHP_EOL . 'WARNING: Not extensively tested with non-UTF8.' );
		\WP_CLI::log( 'WARNING: DOMDocument is likely to make small changes to any HTML as part of its processing.' . PHP_EOL );
		libxml_use_internal_errors( true );

		while ( ( $posts = get_posts( $post_args ) ) !== array() ) {
			\WP_CLI::log( "\nSearching posts..." );

			foreach ( $posts as $post ) {
				$text = $post->post_content;
				//scan the post text, import media, and get back the re-written post text
				$new_text = self::process_a_hrefs($text, $post, $import_host);

				if ( $new_text === $text ) {
					\WP_CLI::log( sprintf("\tPost %d unchanged", $post->ID) );
					continue;
				}

				// Update post.
				$result = wp_update_post( array(
					'ID'           => $post->ID,
					'post_content' => $new_text,
				), true );

				if ( is_wp_error( $result ) ) {
					\WP_CLI::log( sprintf(
						"\t[#%d] Failed rewriting post links: %s",
						$post->ID,
						$result->get_error_message()
					) );
				} else {
					\WP_CLI::log( sprintf("\tPost %d updated.", $post->ID) );
				}

			} //end foreach

			$post_args['offset'] += $limit;  // Keep the loop loopin'.
		} //endwhile

		libxml_clear_errors();
		libxml_use_internal_errors( false );
	}

	/**
	 * Iterate over every post, scanning post text for scaled imgs and, if missing, generating the correct size for existing markup
	 *
	 * [--post-type]
	 * : Specify a post type to operate on.  Defaults to any
	 *
	 * @alias fix-all-thumbnails
	 *
	 */
	public function fix_all_thumbnails($args, $args_assoc) {

		//set the post type from flag, or default to any post type
		$post_type = \WP_CLI\Utils\get_flag_value( $args_assoc, 'post-type' );
		if (!$post_type) {$post_type = 'any';}

		$limit     = 50;
		$post_args = array(
			'offset'           => 0,
			'posts_per_page'   => $limit,
			'suppress_filters' => false,
			'post_type'        => $post_type
		);
	
		libxml_use_internal_errors( true );

		while ( ( $posts = get_posts( $post_args ) ) !== array() ) {
			\WP_CLI::log( "\nSearching posts..." );

			foreach ( $posts as $post ) {
				$text = $post->post_content;
				//scan post content
				$result = self::process_img_thumbs($post);
			} //end foreach

			$post_args['offset'] += $limit;  // Keep the loop loopin'.
		} //endwhile

		libxml_clear_errors();
		libxml_use_internal_errors( false );
	}

	/**
	 * Iterate over every post, scanning post text for scaled imgs and, if missing, generating the correct size for existing markup
	 *
	 * [--post-type]
	 * : Specify a post type to operate on.  Defaults to any
	 *
	 * @alias fix-all-alt
	 *
	 */
	public function fix_all_alt($args, $args_assoc) {

		//set the post type from flag, or default to any post type
		$post_type = \WP_CLI\Utils\get_flag_value( $args_assoc, 'post-type' );
		if (!$post_type) {$post_type = 'any';}

		$limit     = 50;
		$post_args = array(
			'offset'           => 0,
			'posts_per_page'   => $limit,
			'suppress_filters' => false,
			'post_type'        => $post_type
		);
	
		libxml_use_internal_errors( true );

		while ( ( $posts = get_posts( $post_args ) ) !== array() ) {
			\WP_CLI::log( "\nSearching posts..." );

			foreach ( $posts as $post ) {
				\WP_CLI::log(sprintf( '- Processing post id %d', $post->ID ) );
				
				//process post content
				$result = self::process_img_alt($post);
			} //end foreach

			$post_args['offset'] += $limit;  // Keep the loop loopin'.
		} //endwhile

		libxml_clear_errors();
		libxml_use_internal_errors( false );
	}



	/**
	 * Scan all img tags and report back the scr attribute of all internal img, with a status of whether or not they are valid urls for existing library media
	 *
	 * [--post-type]
	 * : Specify a post type to scan (defaults to any)
	 *
	 * @alias report-img
	 *
	 */
	public function report_img($args, $args_assoc) {
		
		//check option flags
		$post_type = \WP_CLI\Utils\get_flag_value( $args_assoc, 'post-type' );
		if (!$post_type) {$post_type = 'any';}

		//setup post paging loop
		$limit     = 50;
		$post_args = array(
			'offset'           => 0,
			'posts_per_page'   => $limit,
			'suppress_filters' => false,
			'post_type'        => $post_type
		);

		//setup a table to return the data
		$output = new \cli\Table();
		$output->setHeaders(array('post_id','src_url','scr_status','fullrez_status','fullrez_id'));

		while ( ( $posts = get_posts( $post_args ) ) !== array() ) {

			foreach ($posts as $post) {
				//run scanner
				$these_rows = self::get_relative_imgs($post);
				if ($these_rows) {
					foreach ($these_rows as $row) {$output->addRow($row);}
				}
			}

			$post_args['offset'] += $limit;  // Keep the loop loopin'.
		} //endwhile

		//return data as a text table to the console, or a tab delimited text file if sent to a file
		$output->display();
	}

	/**
	 * Scan all a tags linking to media library files and report back the href attribute, with a status of whether or not they are valid urls for existing library media
	 *
	 * [--post-type]
	 * : Specify a post type to scan (defaults to any)
	 *
	 * @alias report-library-a
	 *
	 */
	public function report_library_a($args, $args_assoc) {

		//set the post type from flag, or default to any post type
		$post_type = \WP_CLI\Utils\get_flag_value( $args_assoc, 'post-type' );
		if (!$post_type) {$post_type = 'any';}

		//setup post paging loop
		$limit     = 50;
		$post_args = array(
			'offset'           => 0,
			'posts_per_page'   => $limit,
			'suppress_filters' => false,
			'post_type'        => $post_type
		);

		//setup a table to return the data
		$output = new \cli\Table();
		$output->setHeaders(array('post_id','href','status'));

		while ( ( $posts = get_posts( $post_args ) ) !== array() ) {

			foreach ($posts as $post) {
				//run scanner
				$these_rows = self::get_library_hrefs($post);
				if ($these_rows) {
					foreach ($these_rows as $row) {$output->addRow($row);}
				}
			}

			$post_args['offset'] += $limit;  // Keep the loop loopin'.
		} //endwhile

		//return data as a text table to the console, or a tab delimited text file if sent to a file
		$output->display();
	}

	/**
	 * Scan for url sources from imports
	 *
	 *
	 * @alias report-import-dept
	 *
	 */

	public function report_import_dept($args, $args_assoc) {
		$update = false;
		if ( $args[0] === 'update' ) { $update = true; }

		//set the post type from flag, or default to any post type
		$post_type = \WP_CLI\Utils\get_flag_value( $args_assoc, 'post-type' );
		if (!$post_type) {$post_type = 'attachment';}

		//setup post paging loop
		$limit     = 50;
		$post_args = array(
			'offset'           => 0,
			'posts_per_page'   => $limit,
			'suppress_filters' => false,
			'post_type'        => $post_type
		);

		//setup a table to return the data
		$output = new \cli\Table();
		$output->setHeaders( array( 'post_id','dept','status' ) );

		while ( ( $posts = get_posts( $post_args ) ) !== array() ) {

			foreach ($posts as $post) {
				// Get post_meta for attachment, looking for '_original_imported_src'
				$orig_path = get_post_meta( $post->ID, '_original_imported_src', true );

				if ( $orig_path ) {
					$parts = explode( '/', $orig_path );
					$dept_slug = $parts[1];

					// Fix ooops
					if ($dept_slug === 'mse-colo') { $dept_slug = 'mse'; }
					if ($dept_slug === 'eng-staging6') { $dept_slug = 'mse'; }

					// Check for existing department term
					$exists = has_term( $dept_slug, 'department', $post->ID );

					// If the update flag was set, go ahead and add the department term
					if ( $update && ! $exists ) {
						$update_result = wp_set_object_terms( $post->ID, $dept_slug, 'department', true );
						if ( $update_result ) {
							\WP_CLI::success( sprintf( 'Updated %d with dept %s', $post->ID, $dept_slug ) );
							$exists = true;
						} else {
							\WP_CLI::error( sprintf( 'Update Failed on %d with dept %s', $post->ID, $dept_slug ) );
						}
					}

					$status = ( $exists ? 'set' : 'unset' );

					$output->addRow( array( $post->ID, $dept_slug, $status ) ); 
				}
			}

			$post_args['offset'] += $limit;  // Keep the loop loopin'.
		} //endwhile

		//return data as a text table to the console, or a tab delimited text file if sent to a file
		$output->display();
	}


	/**
	 * Helper/internal functions.
	 */

	/**
	 * Scans the post text for media attachments to import.  For each attachment, it imports the media to the local library and rewrites the links
	 * Also records the original media path in a postmeta record.
	 *
	 * @param string $text
	 * @param post $post
	 * @param string import_host
	 * @return string Returns the rewritten post text
	 */
	protected static function process_attached_media( $text, $post, $import_host ) {
		$dom = new \DOMDocument();
		$dom->loadHTML(
			mb_convert_encoding( '<div>' . $text . '</div>', 'HTML-ENTITIES', 'UTF-8' )
		);

		$xpath = new \DOMXPath( $dom );
		
		//track whether a text re-write is necessary; in other words, don't let DOMDocument change the markup unless you have to
		$flag_text_needs_rewrite = false;

		//scan the text for all img tags wrapped in an anchor tag where the src of the img doesn't start with http
		foreach ( $xpath->query( '//a[@href]/img[not(starts-with(@src,"http"))]/..' ) as $anchor_element ) {
			$href_url = $anchor_element->getAttribute( 'href' );

			$img_urls = array();
			$img_elements = array();
			// Find images wrapped by that anchor
			foreach ( $xpath->query( './img', $anchor_element ) as $img_element ) {
				$img_elements[] = $img_element;
				$img_urls[] = $img_element->getAttribute('src');
			}

			//assume only one img in the href, as this is the pattern for attachments
			$img_url = $img_urls[0];
			$img_elem = $img_elements[0];

			//test to see if the a href matches the /files/ pattern, if not then skip it
			if ( strpos($href_url, '/files/') === false ) {
				\WP_CLI::log(sprintf( 'Link %s from post id %d not a library link', $href_url, $post->ID ) );
				continue;
			}

			//check to see if the file is already in the library
			$attachmentExists = self::get_attachment_from_src($href_url);

			if ($attachmentExists) {
				\WP_CLI::log( sprintf("Scanned existing attachment, skipping %s", $href_url) );
				continue;
			}

			//check to see if the file was imported on a previous run
			global $wpdb;
			$alreadyThereID = $wpdb->get_var($wpdb->prepare("SELECT post_id from $wpdb->postmeta WHERE meta_key = '_original_imported_src' AND meta_value = %s", $href_url));

			//get the attachment post ID one way or the other
			$attchmentID = 0;
			if (!$alreadyThereID) {
				//if it isn't already there, import it
				$attchmentID = self::import_media($href_url, $post, $import_host);
			} else {
				\WP_CLI::log( sprintf("Duplicate detected for %s - using attachment ID %d" , $href_url ,$alreadyThereID));
				$attchmentID = $alreadyThereID;
			}

			//check to see if the file was really imported, skip rewrites if not
			if (!$attchmentID) {
				\WP_CLI::warning( sprintf( "Unable to import from href %s in post %d, skipping rewrite", $href_url, $post->ID ) );
				continue;
			}

			//since it is a library link and isn't already there, the link should be re-written
			$flag_text_needs_rewrite = true;

			//rewrite href and img links
			//first look up the correct new path from the attachment id
			$newURI = wp_get_attachment_url($attchmentID);

			//set the a href link first.  Could also just unwrap the img and eliminate the (realistically extraneous) enclosing <a>
			$anchor_element->setAttribute('href', $newURI);
			\WP_CLI::log(sprintf("rewrote link to %s", $newURI));

			//set the wrapped img src, preserving thumbnail size designation, if any
			if ($img_url === $href_url) { 
				$newThumbURI = $newURI;
				\WP_CLI::log( sprintf("Thumbnail and href identical, rewriting thumbnail to match: %s",$newThumbURI) );
			} else {
				preg_match("/-\d+[Xx]\d+\./", $img_url, $thumb_matches);

				if ($thumb_matches[0]) {
					\WP_CLI::log( sprintf("Found thumbnail, size %s", $thumb_matches[0]) );

					$thumb_basename = basename($img_url);
					$newDir = dirname($newURI);
					$newThumbURI = $newDir . "/" . $thumb_basename;
					\WP_CLI::log( sprintf("rewrote img link to %s", $newThumbURI) );
				} else {
					\WP_CLI::warning( sprintf( "a href %s and img src %s do not match on post %d", $href_url, $img_url, $post->ID)); 
					//preserve existing img src
					$newThumbURI = $img_url;
				}
			}
			$img_elem->setAttribute('src', $newThumbURI);
			//may need to re-generate thumbnail to specific size?
		}

		//check here if any links were actually re-written.  if not, just return the original text, don't overwrite if you don't need to
		if (!$flag_text_needs_rewrite) {
			\WP_CLI::log( sprintf( "Links okay, no rewrite for post id %d", $post->ID ) );
			return $text;
		}

		// clean up xpath processing and return re-written post text
		// $text was wrapped in a <div> tag to avoid DOMDocument changing things, so remove it.
		$text = trim( $dom->saveHTML( $dom->getElementsByTagName('div')->item( 0 ) ) );
		$text = substr( $text, strlen( '<div>' ) );
		$text = substr( $text, 0, -strlen( '</div>' ) );

		return trim( $text );
	}

	/**
	 * Scans the post text for img to import.  For each img, it imports the media to the local library and rewrites the links
	 * Also records the original media path in a postmeta record.
	 *
	 * @param string $text
	 * @param post $post
	 * @param string import_host
	 * @return string Returns the rewritten post text
	 */
	protected static function process_imgs( $text, $post, $import_host ) {
		$dom = new \DOMDocument();
		$dom->loadHTML(
			mb_convert_encoding( '<div>' . $text . '</div>', 'HTML-ENTITIES', 'UTF-8' )
		);

		$xpath = new \DOMXPath( $dom );

		//track whether a text re-write is necessary; in other words, don't let DOMDocument change the markup unless you have to
		$flag_text_needs_rewrite = false;

		//scan the text for all img tags where the src of the img doesn't start with http
		foreach ( $xpath->query( '//img[not(starts-with(@src,"http"))]' ) as $img_element ) {

			$img_url = $img_element->getAttribute('src');
			
			//test to see if the src matches the /files/ pattern, if not then skip it
			if ( strpos($img_url, '/files/') === false ) {
				\WP_CLI::log(sprintf( 'src %s from post id %d not a library link', $img_url, $post->ID ) );
				continue;
			}

			//check if the img src is scaled
			//if it isn't, work with the urls as they are
			//if it is scaled, download the full rez file without the scaling tag
			//but preserve it to use in the re-written link
			preg_match("/-\d+[Xx]\d+\./", $img_url, $scaled_matches);

			$capture_url = '';

			if ($scaled_matches[0]) {
				//rewrite the capture url to the full rez source to try downloading that
				$capture_url = preg_replace("/-\d+[Xx]\d+\./", '.', $img_url);
			} else {
				//capture from the given url
				$capture_url = $img_url;
			}

			//check to see if the file is already in the library
			$srcExists = self::get_attachment_from_src($capture_url);

			if ($srcExists) {
				\WP_CLI::log( sprintf("Scanned existing src, skipping %s", $capture_url) );
				continue;
			}

			//check to see if the file was imported on a previous run
			global $wpdb;
			$alreadyThereID = $wpdb->get_var($wpdb->prepare("SELECT post_id from $wpdb->postmeta WHERE meta_key = '_original_imported_src' AND meta_value = %s", $capture_url));

			//get the src post ID one way or the other
			$srcID = 0;
			if (!$alreadyThereID) {
				//if it isn't already there, import it
				$srcID = self::import_media($capture_url, $post, $import_host);
			} else {
				\WP_CLI::log( sprintf("Duplicate detected for %s - using src ID %d" , $capture_url ,$alreadyThereID));
				$srcID = $alreadyThereID;
			}

			//check to see if the file was really imported, skip rewrites if not
			if (!$srcID) {
				//try to import the full img_url with dimensions?
				$srcID = self::import_media($img_url, $post, $import_host);
				if ($srcID) {
					\WP_CLI::success( sprintf( "Import scaled src from img src %s in post %d - no unscaled src available", $img_url, $post->ID ) );
				} else {
					\WP_CLI::warning( sprintf( "Unable to import from img src %s in post %d, skipping rewrite", $img_url, $post->ID ) );
					continue;
				}
				
			}

			//since it is a library link and isn't already there, the link should be re-written
			$flag_text_needs_rewrite = true;

			//lookup the correct new path from the uploaded post id
			$newURI = wp_get_attachment_url($srcID);

			//rewrite img src
			if ($scaled_matches[0]) {
				\WP_CLI::log( sprintf("Preserving scaled src, size %s", $scaled_matches[0]) );

				$scaled_basename = basename($img_url);
				$newDir = dirname($newURI);
				$newURI = $newDir . "/" . $scaled_basename;
				\WP_CLI::log( sprintf("rewrote img link to %s", $newURI) );
			} else {
				\WP_CLI::log( sprintf( "using unscaled img src %s in post %d", $img_url, $post->ID)); 
			}
			
			$img_element->setAttribute('src', $newURI);
			//may need to re-generate thumbnail to specific size?
		}

		//check here if any links were actually re-written.  if not, just return the original text, don't overwrite if you don't need to
		if (!$flag_text_needs_rewrite) {
			\WP_CLI::log( sprintf( "Links okay, no rewrite for post id %d", $post->ID ) );
			return $text;
		}

		// clean up xpath processing and return re-written post text
		// $text was wrapped in a <div> tag to avoid DOMDocument changing things, so remove it.
		$text = trim( $dom->saveHTML( $dom->getElementsByTagName('div')->item( 0 ) ) );
		$text = substr( $text, strlen( '<div>' ) );
		$text = substr( $text, 0, -strlen( '</div>' ) );

		return trim( $text );
	}

	/**
	 * Scans the post text for a href linked media to import.  For each media file, it imports the media to the local library and rewrites the links in the post
	 * Also records the original media path in a postmeta record.
	 *
	 * @param string $text
	 * @param post $post
	 * @param string import_host
	 * @return string Returns the rewritten post text
	 */
	protected static function process_a_hrefs( $text, $post, $import_host ) {
		$dom = new \DOMDocument();
		$dom->loadHTML(
			mb_convert_encoding( '<div>' . $text . '</div>', 'HTML-ENTITIES', 'UTF-8' )
		);

		$xpath = new \DOMXPath( $dom );

		//track whether a text re-write is necessary; in other words, don't let DOMDocument change the markup unless you have to
		$flag_text_needs_rewrite = false;

		//scan the text for all anchor tags containing library links
		foreach ( $xpath->query( '//a[contains(@href,"/files/")][not(starts-with(@href,"http"))]' ) as $anchor_element ) {

			$file_url = $anchor_element->getAttribute('href');

			//check to see if the file is already in the library
			$hrefExists = self::get_attachment_from_src($file_url);

			if ($hrefExists) {
				\WP_CLI::log( sprintf("Scanned existing href, skipping %s", $file_url) );
				continue;
			}

			//check to see if the file was imported on a previous run
			global $wpdb;
			$alreadyThereID = $wpdb->get_var($wpdb->prepare("SELECT post_id from $wpdb->postmeta WHERE meta_key = '_original_imported_src' AND meta_value = %s", $file_url));

			//get the src post ID one way or the other
			$fileID = 0;
			if (!$alreadyThereID) {
				//if it isn't already there, import it
				$fileID = self::import_media($file_url, $post, $import_host);
			} else {
				\WP_CLI::log( sprintf("Duplicate detected for %s - using href ID %d" , $file_url ,$alreadyThereID));
				$fileID = $alreadyThereID;
			}

			//check to see if the file was really imported, skip rewrites if not
			if (!$fileID) {
				\WP_CLI::warning( sprintf( "Unable to import from href %s in post %d, skipping rewrite", $file_url, $post->ID ) );
				continue;
			}

			//since it is a library link and isn't already there, the link should be re-written
			$flag_text_needs_rewrite = true;

			//lookup the correct new path from the uploaded post id
			$newURI = wp_get_attachment_url($fileID);

			//rewrite a href
			$anchor_element->setAttribute('href', $newURI);
			//may need to re-generate thumbnail to specific size - use fix-thumbnails command in a separate pass
		}

		//check here if any links were actually re-written.  if not, just return the original text, don't overwrite if you don't need to
		if (!$flag_text_needs_rewrite) {
			\WP_CLI::log( sprintf( "Links okay, no rewrite for post id %d", $post->ID ) );
			return $text;
		}

		// clean up xpath processing and return re-written post text
		// $text was wrapped in a <div> tag to avoid DOMDocument changing things, so remove it.
		$text = trim( $dom->saveHTML( $dom->getElementsByTagName('div')->item( 0 ) ) );
		$text = substr( $text, strlen( '<div>' ) );
		$text = substr( $text, 0, -strlen( '</div>' ) );

		return trim( $text );
	}

	/**
	 * Scans the post text for scaled images and checks for an image at the correct size.
	 * If the scaled down version is missing, a new one is created
	 *
	 * @param post $post
	 * @return string If successful, return the attachment post ID.  Otherwise, return false
	 */
	protected static function process_img_thumbs($post) {
		$dom = new \DOMDocument();
		$dom->loadHTML(
			mb_convert_encoding( '<div>' . $post->post_content . '</div>', 'HTML-ENTITIES', 'UTF-8' )
		);

		$xpath = new \DOMXPath( $dom );

		foreach ( $xpath->query( '//img[not(starts-with(@src,"http"))]' ) as $img_element ) {
			$img_url = $img_element->getAttribute('src');
			
			//test to see if the src matches the /files/ pattern, if not then skip it
			if ( strpos($img_url, '/files/') === false ) {
				\WP_CLI::log(sprintf( 'src %s from post id %d not a library link', $img_url, $post->ID ) );
				continue;
			}

			//get src parts
			preg_match("/-(\d+)[Xx](\d+)\./", $img_url, $scaled_matches);

			$width  = $scaled_matches[1];
			$height = $scaled_matches[2];

			$fullrez_url = preg_replace("/-\d+[Xx]\d+\./", '.', $img_url);
			//skip if src is unscaled
			if ($fullrez_url === $img_url) {continue;}

			$fullrez = self::get_attachment_from_src($fullrez_url);

			if (!$fullrez) {
				\WP_CLI::warning( sprintf("On post id %d - Full resolution source not found for url %s",$post->ID,$fullrez_url) );
				continue;
			}

			//check for existing scaled image in the post meta
			$src_exists = self::exists_sized_attachment($fullrez->ID, $img_url);
			if ($src_exists) {
				\WP_CLI::log( sprintf("Post ID %d - Scaled image exists for src %s attachment id %d",$post->ID,$img_url,$fullrez->ID) );
				continue;
			}

			//check if thumbnail already exists in the filesystem
			if (self::img_file_exists($img_url)) {
				\WP_CLI::log( sprintf("Post ID %d - file exists for src %s attachment id %d",$post->ID,$img_url,$fullrez->ID));
				//should add post-meta for file?
				continue;
			}

			//thumbnail size needs to be created
			\WP_CLI::log( sprintf("Post ID %d - Will make new scaled version for attachment id %d",$post->ID,$fullrez->ID) );
			$fullrez_path = get_attached_file($fullrez->ID);

			$newSize = wp_get_image_editor($fullrez_path);
			$newSize->resize($width,$height,false);
			$newFile = $newSize->save();
			
			if ($newFile) {
				if ($newFile['width'] == $width && $newFile['height'] == $height) {
					\WP_CLI::success( sprintf("Post ID %d - new resized file for attachment id %d created named %s",$post->ID,$fullrez->ID,$newFile['file']) );
				} else {
					\WP_CLI::warning( sprintf("Post ID %d - size mismatch for attachment id %d created named %s",$post->ID,$fullrez->ID,$newFile['file']) );
					\WP_CLI::warning( sprintf("width = %d height= %d for file %s",$width,$height,$newFile['file']) );
				}
				
			} else {
				\WP_CLI::warning( sprintf("Post ID %d - resize failed for attachment id %d",$post->ID,$fullrez->ID) );
				//set a flag to report back to the command?
				continue;
			}

			//add the new size to the attachment metadata
			$meta_arr = get_post_meta($fullrez->ID, '_wp_attachment_metadata',true);

			//newFile metadata is the same as the attachement metadata, except for the 'path' attribute, so remove it
			unset($newFile['path']);
			
			$meta_arr['sizes']['custom-import'] = $newFile;

			$update = wp_update_attachment_metadata($fullrez->ID,$meta_arr);

			if (!$update) { \WP_CLI::warning( sprintf("Metadata update failed for attachment id %d on post id %d",$fullrez->ID,$post->ID) ); } 
		}

		return true;
	}

	/**
	 * Scans the post text for scaled images and checks for an image at the correct size.
	 * If the scaled down version is missing, a new one is created
	 *
	 * @param post $post
	 * @return bool 
	 */
	protected static function process_img_alt($post) {
		$dom = new \DOMDocument();
		$dom->loadHTML(
			mb_convert_encoding( '<div>' . $post->post_content . '</div>', 'HTML-ENTITIES', 'UTF-8' )
		);

		$xpath = new \DOMXPath( $dom );

		foreach ( $xpath->query( '//img[not(starts-with(@src,"http"))]' ) as $img_element ) {
			$img_url = $img_element->getAttribute('src');
			$alt = $img_element->getAttribute('alt');
			
			//test to see if the src matches the /files/ pattern, if not then skip it
			if ( strpos($img_url, '/files/') === false ) {
				\WP_CLI::log(sprintf( 'src %s from post id %d not a library link', $img_url, $post->ID ) );
				continue;
			}

			$fullrez_url = preg_replace("/-\d+[Xx]\d+\./", '.', $img_url);

			$fullrez = self::get_attachment_from_src($fullrez_url);
			if (!$fullrez) {
				\WP_CLI::log(sprintf( 'Image not found in library: %s in post %d ', $img_url, $post->ID ) );
				continue;
			}

			$meta_alt = get_post_meta($fullrez->ID, '_wp_attachment_image_alt',true);

			if (!$alt) {
				\WP_CLI::warning(sprintf( 'alt empty for attach id %d on post id %s', $fullrez->ID, $post->ID ) );
				continue;
			}

			if ($meta_alt == $alt) {
				\WP_CLI::log(sprintf( 'alt in markup and postmeta match for attach id %d in post id %d', $fullrez->ID, $post->ID ) );
				continue;
			}

			//write alt from markup into the correct postmeta
			$result = update_post_meta($fullrez->ID,'_wp_attachment_image_alt',$alt);

			if ($result) {
				\WP_CLI::success(sprintf( 'postmeta alt added for attach id %d from post id %s', $fullrez->ID, $post->ID ) );
			} else {
				\WP_CLI::warning(sprintf( 'problem writing postmeta alt for attach id %d from post id %s', $fullrez->ID, $meta_alt ) );
			}
		}
		return true;
	}



	/**
	 * Imports a media file to as an attachment into the library from the given media path and host (defaults to current site host)
	 * Also records the original media path in a postmeta record.
	 *
	 * @param string $media_path
	 * @param post $post
	 * @return string If successful, return the attachment post ID.  Otherwise, return false
	 */
	protected function import_media($media_path, $post, $import_host) {
		//launch a new wp-cli session to run the import command in order to capture the output
		//porcelain option causes the STDOUT of the import command to return just the post_id of the newly imported file
		//using WP_CLI::run_command is much faster, but only returns the attachment id to STDOUT, which might as well be dev/null 
		$import_result = \WP_CLI::launch_self('media import', array(($import_host . $media_path)), array(porcelain=>true),false,true);
			
		if ($import_result->return_code !== 0) {
			\WP_CLI::warning( sprintf("Failed to import %s from post id %d", $media_path, $post->ID) );
			return false;
		}

		$importedID = trim($import_result->stdout);

		if (!$importedID) {
			\WP_CLI::warning( sprintf("Failed to import %s from post id %d", $media_path, $post->ID) );
			return false;
		}

		\WP_CLI::success( sprintf("Imported from post_id %d file %s with attachment id %s", $post->ID, $media_path, $importedID) );

		//keep the original path in a post meta for future reference
		add_post_meta($importedID, "_original_imported_src", $media_path);
		return $importedID;
	}


	/**
	 * gets all of the img tags with relative src attributes from a post
	 *
	 * @param post $post
	 * @return array Returns an array of rows representing each img found in the post text
	 */
	static function get_relative_imgs($post) {
		//setup dom document
		$dom = new \DOMDocument();
		$dom->loadHTML(
			mb_convert_encoding( '<div>' . $post->post_content . '</div>', 'HTML-ENTITIES', 'UTF-8' )
		);

		$xpath = new \DOMXPath( $dom );

		$rows = array();

		//scan the text for all img tags wrapped in an anchor tag where the src of the img doesn't start with http
		foreach ( $xpath->query( '//img[not(starts-with(@src,"http"))]' ) as $img_element ) {
			$img_url = $img_element->getAttribute( 'src' );	
			$fullrez_id = "";

			//check if linked media is a sized down image attachment
			$fullrez_url = preg_replace("/-\d+[Xx]\d+\./", '.', $img_url);
			if ($fullrez_url === $img_url) {
				//link is to the fullrez source, so check for it in the library
				$url_exists = self::get_attachment_from_src($img_url);
				if ($url_exists) {$src_status = $fullrez_status = "exists";} else {$src_status = $fullrez_status = "missing";}
			} else {
				//link is to a sized down version: check for the fullrez url in the library, and the sized down in the corresponding postmeta
				$fullrez = self::get_attachment_from_src($fullrez_url);
				if ($fullrez) {
					$fullrez_status = "exists";
					$fullrez_id = $fullrez->ID;
					$src_exists = self::exists_sized_attachment($fullrez->ID, $img_url);
					if ($src_exists) {$src_status = "exists";} else {$src_status = "missing";}
				} else {
					//might be just a scaled version, check if sized file exists
					if (self::img_file_exists($img_url)) {
						$fullrez_status = $src_status = "exists";
					} else {
						//looks like it's just not there
						$fullrez_status = $src_status = "missing";
					}
					
				}

			}

			$row = array($post->ID, $img_url, $src_status, $fullrez_status, $fullrez_id);
			$rows[] = $row;
		}
		return $rows;
	}

	/**
	 * gets all of the a tags with the string /files/ in the href attribute, that aren't absolute links
	 *
	 * @param post $post
	 * @return array Returns an array of rows representing each a found in the post text
	 */
	public static function get_library_hrefs($post) {
		//setup dom document
		$dom = new \DOMDocument();
		$dom->loadHTML(
			mb_convert_encoding( '<div>' . $post->post_content . '</div>', 'HTML-ENTITIES', 'UTF-8' )
		);

		$xpath = new \DOMXPath( $dom );

		$rows = array();

		//scan the text for all img tags wrapped in an anchor tag where the src of the img doesn't start with http
		foreach ( $xpath->query( '//a[contains(@href,"/files/")][not(starts-with(@href,"http"))]' ) as $anchor_element ) {
			$href_url = $anchor_element->getAttribute( 'href' );
			
			$url_exists = self::get_attachment_from_src($href_url);
			if($url_exists) {$status = "exists";} else {$status = "missing";}

			$row = array($post->ID, $href_url, $status);
			$rows[] = $row;
		}
		return $rows;
	}

	/**
	 * Checks file attachment metadata to see if the specified downsampled version exists for the given attachment id
	 *
	 * @param $attach_id
	 * @param $filepath
	 * @return bool
	 */
	public static function exists_sized_attachment( $attach_id,$filepath ) {
		$filename = basename($filepath);

		$meta_arr = get_post_meta($attach_id, '_wp_attachment_metadata',true);
		if (!$meta_arr['sizes']) {return false;}

		$exists = false;
		foreach ($meta_arr['sizes'] as $size) {
			if ($size['file'] === $filename) {$exists = true;}
		}

		return $exists;
	}

	/**
	 * Checks to see if a file already exists in the uploads directory for an img src
	 *
	 * @param $src
	 * @return bool
	 */
	public static function img_file_exists($src) {
		$uploads= wp_upload_dir(false);
		$path_parts = explode('/',$src);
		$partial_path = implode('/', array_slice($path_parts,3));
		return file_exists($uploads['basedir'] . '/' . $partial_path);
	}

	/**
	 * Attempts to get an attachment from it's source url
	 *
	 * @param $src
	 * @return array|bool|null|\WP_Post
	 */
	protected static function get_attachment_from_src( $src ) {

		global $wpdb;

		$split = explode( '/', $src );
		$path  = implode( '/', array_slice( $split, -3 ) );

		$post_id = $wpdb->get_var( $wpdb->prepare( "SELECT post_id from $wpdb->postmeta WHERE meta_key = '_wp_attached_file' AND meta_value = %s", $path ) );

		if ( $post_id ) {
			return get_post( $post_id );
		}

		return false;
	}

	/**
	 * Returns the current host with scheme as a default host for media imports
	 *
	 * @return string
	 */
	protected function default_host() {
		$home_url = parse_url( get_home_url());
		return $home_url['scheme'] . '://' . $home_url['host'];
	}
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	\WP_CLI::add_command( 'mediafix', __NAMESPACE__ . '\\MediaFix' );
}
} // Namespace BU\Migrate
