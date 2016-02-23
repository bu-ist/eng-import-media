<?php
namespace BU\Media;

require_once dirname( __FILE__ ) . "/hm-import-fixers.php";


class MediaFix extends \HM\Import\Fixers {

	/**
	 * test the things
	 *
	 * @alias test-things
	 *
	 */
	public function test_things( $args, $args_assoc ) {

		\WP_CLI::log("subclassed command");
	}
}

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	\WP_CLI::add_command( 'mediafix', __NAMESPACE__ . '\\MediaFix' );
}