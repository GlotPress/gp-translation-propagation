<?php
/**
 * Translation Propagation: GP_CLI_Import_Originals_With_Propagation class
 *
 * @package GlotPress
 * @subpackage Translation_Propagation_CLI
 * @since 1.0.0
 */

/**
 * Core class used to override the default import command.
 *
 * @since 1.0.0
 */
class GP_CLI_Import_Originals_With_Propagation extends GP_CLI_Import_Originals {

	/**
	 * Import originals for a project from a file
	 *
	 * ## OPTIONS
	 *
	 * <project>
	 * : Project name
	 *
	 * <file>
	 * : File to import from
	 *
	 * [--format=<format>]
	 * : Accepted values: po, mo, android, resx, strings. Default: po
	 *
	 * [--disable-propagating]
	 * : If set, propagation will be disabled.
	 *
	 * [--disable-matching]
	 * : If set, matching will be disabled.
	 *
	 * @param array $args       Arguments passed to the command.
	 * @param array $assoc_args Parameters passed to the command.
	 */
	public function __invoke( $args, $assoc_args ) {
		$disable_propagating = isset( $assoc_args['disable-propagating'] );
		$disable_matching = isset( $assoc_args['disable-matching'] );

		if ( $disable_propagating ) {
			add_filter( 'gp_enable_propagate_translations_across_projects', '__return_false' );
		}
		if ( $disable_matching ) {
			add_filter( 'gp_enable_add_translations_from_other_projects', '__return_false' );
		}

		parent::__invoke( $args, $assoc_args );

		if ( $disable_matching ) {
			remove_filter( 'gp_enable_add_translations_from_other_projects', '__return_false' );
		}
		if ( $disable_propagating ) {
			remove_filter( 'gp_enable_propagate_translations_across_projects', '__return_false' );
		}
	}
}
