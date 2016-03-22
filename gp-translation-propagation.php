<?php
/**
 * Plugin Name: GlotPress – Translation Propagation
 * Plugin URI: https://wordpress.org/plugins/gp-translation-propagation/
 * Description: Brings Translation Propagation to GlotPress.
 * Version: 1.0.0
 * Author: the GlotPress team
 * Author URI: http://glotpress.org
 * License: GPLv2 or later
 * Text Domain: gp-translation-propagation
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
 *
 * @package GlotPress
 * @subpackage Translation_Propagation
 */

require_once __DIR__ . '/inc/class-gp-translation-propagation.php';

add_action( 'gp_init', 'gptp_init' );

/**
 * Initializes the plugin.
 *
 * @since 1.0.0
 */
function gptp_init() {
	$translation_propagation = GP_Translation_Propagation::get_instance();
	$translation_propagation->register_events();

	if ( defined( 'WP_CLI' ) && WP_CLI ) {
		gptp_register_cli_commands();
	}
}

/**
 * Registers CLI commands.
 *
 * @since 1.0.0
 */
function gptp_register_cli_commands() {
	require_once __DIR__ . '/inc/cli/import-originals.php';
	require_once __DIR__ . '/inc/cli/translation-set.php';

	WP_CLI::add_command( 'glotpress import-originals', 'GP_CLI_Import_Originals_With_Propagation' );
	WP_CLI::add_command( 'glotpress translation-set', 'GP_CLI_Translation_Set_With_Propagation' );
}
