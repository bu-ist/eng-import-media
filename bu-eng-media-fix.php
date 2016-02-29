<?php
namespace BU\Media;
//only depending on the hm-importer-fixers for get_attachment_from_src()
require_once dirname( __FILE__ ) . "/hm-import-fixers.php";


class MediaFix extends \HM\Import\Fixers {

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
	 * @alias process-one
	 *
	 * @param array $args Positional args.
	 * @param array $args Assocative args.
	 */
	public function process_one( $args, $args_assoc ) {
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
	 * Iterate over every post, processing the attached media and re-writing the links in the post
	 *
	 *
	 * [--import-host]
	 * : If importing media from a host other than the current one, set it here (with protocol)
	 *
	 * @alias fix-all-attached
	 *
	 */
	public function fix_all_attached($args_assoc) {
		//disable thumbnail generation
		add_filter( 'intermediate_image_sizes_advanced', '__return_false' );

		//set the import host from flag, or default to the current host
		$import_host = \WP_CLI\Utils\get_flag_value( $args_assoc, 'import-host' );
		if(!$import_host) {$import_host = self::default_host();}

		$limit     = 50;
		$post_args = array(
			'offset'           => 0,
			'posts_per_page'   => $limit,
			'suppress_filters' => false,
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

		// clean up xpath processing and return re-written post text
		// $text was wrapped in a <div> tag to avoid DOMDocument changing things, so remove it.
		$text = trim( $dom->saveHTML( $dom->getElementsByTagName('div')->item( 0 ) ) );
		$text = substr( $text, strlen( '<div>' ) );
		$text = substr( $text, 0, -strlen( '</div>' ) );

		return trim( $text );
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
		\WP_CLI::success( sprintf("Imported from post_id %d file %s with attachment id %s", $post->ID, $media_path, $importedID) );

		//keep the original path in a post meta for future reference
		add_post_meta($importedID, "_original_imported_src", $media_path);
		return $importedID;
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