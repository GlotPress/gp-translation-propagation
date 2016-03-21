<?php
/**
 * Translation Propagation: GP_Translation_Propagation class
 *
 * @package GlotPress
 * @subpackage Translation_Propagation
 * @since 1.0.0
 */

/**
 * Core class used to implement the translation propagation.
 *
 * @since 1.0.0
 */
class GP_Translation_Propagation {

	/**
	 * Holds the reference to an instance of this class.
	 *
	 * @var GP_Translation_Propagation
	 */
	private static $instance;

	/**
	 * Returns the GP_Translation_Propagation instance of this class.
	 *
	 * @return GP_Translation_Propagation The GP_Translation_Propagation instance.
	 */
	public static function get_instance() {
		if ( null === static::$instance ) {
			static::$instance = new static();
		}

		return static::$instance;
	}

	/**
	 * Registers callbacks for GlotPress actions.
	 *
	 * @since 1.0.0
	 */
	public function register_events() {
		add_action( 'gp_original_created', array( $this, 'add_translations_from_other_projects' ) );
		add_action( 'gp_translation_created', array( $this, 'propagate_translation_across_projects' ) );
		add_action( 'gp_translation_updated', array( $this, 'propagate_translation_across_projects' ) );
	}

	/**
	 * Populates an original with an existing translation.
	 *
	 * @since 1.0.0
	 *
	 * @param GP_Original $original The original to search translations for.
	 * @return bool False on failure, true on success.
	 */
	public function add_translations_from_other_projects( $original ) {
		/**
		 * Filter whether translations should be added from other projects for newly created originals.
		 *
		 * @since 1.0.0
		 *
		 * @param bool        $add_translations Add translations from other projects. Default true.
		 * @param GP_Original $add_translations The original to search translations for.
		 */
		if ( ! apply_filters( 'gp_enable_add_translations_from_other_projects', true, $original ) ) {
			return false;
		}

		global $wpdb;

		$project_translations_sets = GP::$translation_set->many_no_map( "SELECT * FROM $wpdb->gp_translation_sets WHERE project_id = %d", $original->project_id );

		if ( empty( $project_translations_sets ) ) {
			return false;
		}

		$matched_sets = array();

		$sql_project  = $wpdb->prepare( 'o.project_id != %d', $original->project_id );
		$sql_singular = $wpdb->prepare( 'o.singular = BINARY %s', $original->singular );
		$sql_plural = is_null( $original->plural ) ? 'o.plural IS NULL' : $wpdb->prepare( 'o.plural = BINARY %s', $original->plural );
		$sql_context = is_null( $original->context ) ? 'o.context IS NULL' : $wpdb->prepare( 'o.context = BINARY %s', $original->context );

		$sql = "SELECT t.*, s.locale, s.slug
			FROM {$wpdb->gp_originals} o
				JOIN {$wpdb->gp_translations} t ON o.id = t.original_id
				JOIN {$wpdb->gp_translation_sets} s ON t.translation_set_id = s.id
			WHERE
				$sql_context AND $sql_singular AND $sql_plural
				AND o.status = '+active' AND $sql_project
				AND t.status = 'current'
			GROUP BY t.translation_0, t.translation_1, t.translation_2, t.translation_3, t.translation_4, t.translation_5, s.locale, s.slug
			ORDER BY t.date_modified DESC, t.id DESC";

		$other_project_translations = GP::$translation->many( $sql );

		foreach ( $other_project_translations as $t ) {
			$o_translation_set = array_filter( $project_translations_sets, function( $set ) use ( $t ) {
				return $set->locale === $t->locale && $set->slug === $t->slug;
			} );

			if ( empty( $o_translation_set ) ) {
				continue;
			}

			$o_translation_set = reset( $o_translation_set );
			if ( in_array( $o_translation_set->id, $matched_sets, true ) ) {
				// We already have a translation for this set.
				continue;
			}

			$matched_sets[] = $o_translation_set->id;

			/**
			 * Filter the status of translations copied over from other projects.
			 *
			 * @since 1.0.0
			 *
			 * @param string $status The status of the copied translation. Default: 'fuzzy'.
			 */
			$copy_status = apply_filters( 'gp_translations_from_other_projects_status', 'fuzzy' );
			$this->copy_translation_into_set( $t, $o_translation_set->id, $original->id, $copy_status );
		}

		return true;
	}

	/**
	 * Duplicates a translation to another translation set.
	 *
	 * @since 1.0.0
	 *
	 * @param GP_Translation $translation            The translation which should be duplicated.
	 * @param int            $new_translation_set_id The ID of the new translation set.
	 * @param int            $new_original_id        The ID of the new original.
	 * @param string         $status                 The status of the new translation.
	 * @return bool False on failure, true on success.
	 */
	public function copy_translation_into_set( $translation, $new_translation_set_id, $new_original_id, $status = 'fuzzy' ) {
		if ( ! in_array( $status, GP::$translation->get_static( 'statuses' ), true ) ) {
			return false;
		}

		$new_translation_set = GP::$translation_set->get( $new_translation_set_id );
		$locale = GP_Locales::by_slug( $new_translation_set->locale );
		$new_translation = array();

		for ( $i = 0; $i < $locale->nplurals; $i++ ) {
			$new_translation[] = $translation->{"translation_{$i}"};
		}

		/*
		 * Don't propagate a waiting/fuzzy translation if the same translation
		 * with the same status exists already.
		 */
		if ( in_array( $status, array( 'waiting', 'fuzzy' ), true ) ) {
			$existing_translations = GP::$translation->find_no_map( array(
				'translation_set_id' => $new_translation_set_id,
				'original_id'        => $new_original_id,
				'status'             => $status,
			) );

			foreach ( $existing_translations as $_existing_translation ) {
				$existing_translation = array();
				for ( $i = 0; $i < $locale->nplurals; $i++ ) {
					$existing_translation[] = $_existing_translation->{"translation_{$i}"};
				}

				if ( $existing_translation === $new_translation ) {
					return false;
				}
			}
		}

		/*
		 * Set a waiting translation as current if it's the same translation.
		 */
		if ( 'current' === $status ) {
			$existing_translations = GP::$translation->find( array(
				'translation_set_id' => $new_translation_set_id,
				'original_id'        => $new_original_id,
				'status'             => 'waiting',
			) );

			foreach ( $existing_translations as $_existing_translation ) {
				$existing_translation = array();
				for ( $i = 0; $i < $locale->nplurals; $i++ ) {
					$existing_translation[] = $_existing_translation->{"translation_{$i}"};
				}

				if ( $existing_translation === $new_translation ) {
					// Mark as current and avoid recursion.
					add_filter( 'gp_enable_propagate_translations_across_projects', '__return_false' );
					$_existing_translation->set_as_current();
					remove_filter( 'gp_enable_propagate_translations_across_projects', '__return_false' );
					return true;
				}
			}
		}

		/*
		 * If none of the above cases are matching, copy the same translation
		 * into the new translation set.
		 */
		$copy = new GP_Translation( $translation->fields() );
		$copy->original_id = $new_original_id;
		$copy->translation_set_id = $new_translation_set_id;
		$copy->status = $status;

		GP::$translation->create( $copy );
		// Flush cache, create() doesn't flush caches for copies, see r994.
		gp_clean_translation_set_cache( $new_translation_set_id );

		return true;
	}

	/**
	 * Retrieves matching originals in other projects.
	 *
	 * @since 1.0.0
	 *
	 * @param GP_Original $original The original to search matching originals for.
	 * @return GP_Original[] An array of matching originals.
	 */
	private function get_matching_originals_in_other_projects( $original ) {
		global $wpdb;

		$where = array();
		$where[] = 'singular = BINARY %s';
		$where[] = is_null( $original->plural ) ? '(plural IS NULL OR %s IS NULL)' : 'plural = BINARY %s';
		$where[] = is_null( $original->context ) ? '(context IS NULL OR %s IS NULL)' : 'context = BINARY %s';
		$where[] = 'project_id != %d';
		$where[] = "status = '+active'";
		$where = implode( ' AND ', $where );

		return GP::$original->many( "SELECT * FROM {$wpdb->gp_originals} WHERE $where", $original->singular, $original->plural, $original->context, $original->project_id );
	}

	/**
	 * Propagates a translation to other projects.
	 *
	 * @since 1.0.0
	 *
	 * @param GP_Translation $translation The translation which should be propagated.
	 * @return bool False on failure, true on success.
	 */
	public function propagate_translation_across_projects( $translation ) {
		/**
		 * Filter whether a translation should be propagated across projects.
		 *
		 * @since 1.0.0
		 *
		 * @param bool           $propagate   If a translation should be propagated across projects.
		 * @param GP_Translation $translation The translation which will be propagated.
		 */
		if ( ! apply_filters( 'gp_enable_propagate_translations_across_projects', true, $translation ) ) {
			return false;
		}

		// Only propagte current translations without warnings.
		if ( 'current' !== $translation->status || ! empty( $translation->warnings ) ) {
			return false;
		}

		$original = GP::$original->get( $translation->original_id );
		$originals_in_other_projects = $this->get_matching_originals_in_other_projects( $original );

		if ( ! $originals_in_other_projects ) {
			return false;
		}

		$translation_set = GP::$translation_set->get( $translation->translation_set_id );
		foreach ( $originals_in_other_projects as $o ) {
			$o_translation_set = GP::$translation_set->by_project_id_slug_and_locale( $o->project_id, $translation_set->slug, $translation_set->locale );

			if ( ! $o_translation_set ) {
				continue;
			}

			$current_translation = GP::$translation->find_no_map( array(
				'translation_set_id' => $o_translation_set->id,
				'original_id'        => $o->id,
				'status'             => 'current',
			) );

			if ( ! $current_translation  ) {
				/**
				 * Filter the status that is set for translations propagated across projects.
				 *
				 * @since 1.0.0
				 *
				 * @param string         $copy_status        Status of the translation to be used. Default: 'fuzzy'.
				 * @param GP_Translation $translation        The instance of the translation.
				 * @param int            $translation_set_id The ID of the new translation set.
				 * @param int            $original_id        The ID of the new original.
				 */
				$copy_status = apply_filters( 'gp_translations_to_other_projects_status', 'fuzzy', $this, $o_translation_set->id, $o->id );
				$this->copy_translation_into_set( $translation, $o_translation_set->id, $o->id, $copy_status );
			}
		}

		return true;
	}

	/**
	 * Protected constructor to prevent creating a new instance.
	 */
	protected function __construct() {}

	/**
	 * Private clone method to prevent cloning of the instance.
	 */
	private function __clone() {}

	/**
	 * Private unserialize method to prevent unserializing of the instance.
	 */
	private function __wakeup() {}
}
