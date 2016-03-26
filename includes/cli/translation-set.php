<?php
/**
 * Translation Propagation: GP_CLI_Translation_Set_With_Propagation class
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
class GP_CLI_Translation_Set_With_Propagation extends GP_CLI_Translation_Set {

	/**
	 * Import a file into the translation set
	 *
	 * ## OPTIONS
	 *
	 * <project>
	 * : Project path
	 *
	 * <locale>
	 * : Locale to export
	 *
	 * <file>
	 * : File to import
	 *
	 * [--set=<set>]
	 * : Translation set slug; default is "default"
	 *
	 * [--disable-propagating]
	 * : If set, propagation will be disabled.
	 *
	 * @param array $args       Arguments passed to the command.
	 * @param array $assoc_args Parameters passed to the command.
	 */
	public function import( $args, $assoc_args ) {
		$disable_propagating = isset( $assoc_args['disable-propagating'] );
		if ( $disable_propagating ) {
			add_filter( 'gp_enable_propagate_translations_across_projects', '__return_false' );
		}

		parent::import( $args, $assoc_args );

		if ( $disable_propagating ) {
			remove_filter( 'gp_enable_propagate_translations_across_projects', '__return_false' );
		}
	}
}
