<?php

class Test_GP_Translation_Propagation extends GP_UnitTestCase {

	/**
	 * Tests whether an existing current translation gets populated to a new original.
	 *
	 * @see https://glotpress.trac.wordpress.org/ticket/327
	 *
	 * @group matching
	 */
	function test_add_translations_from_other_projects_copies_current_translation_as_fuzzy() {
		$set1 = $this->factory->translation_set->create_with_project_and_locale( array(), array( 'name' => 'project_one' ) );
		$set2 = $this->factory->translation_set->create_with_project( array( 'locale' => $set1->locale ), array( 'name' => 'project_two' ) );

		// Originals for project 1.
		$original1 = $this->factory->original->create( array( 'project_id' => $set1->project_id, 'status' => '+active', 'singular' => 'baba' ) );
		$original2 = $this->factory->original->create( array( 'project_id' => $set1->project_id, 'status' => '+active', 'singular' => 'bubu' ) );

		$this->factory->translation->create( array( 'translation_set_id' => $set1->id, 'original_id' => $original1->id, 'status' => 'current' ) );
		$this->factory->translation->create( array( 'translation_set_id' => $set1->id, 'original_id' => $original2->id, 'status' => 'waiting' ) );

		// Originals for project 2.
		$this->factory->original->create( array( 'project_id' => $set2->project_id, 'status' => '+active', 'singular' => 'baba' ) );
		$this->factory->original->create( array( 'project_id' => $set2->project_id, 'status' => '+active', 'singular' => 'bubu' ) );

		$set2_fuzzy_translations = GP::$translation->for_export( $set2->project, $set2, array( 'status' => 'fuzzy' ) );
		$this->assertEquals( 1, count( $set2_fuzzy_translations ) );
	}

	/**
	 * Tests whether a translation with placeholders gets correctly propagated.
	 *
	 * @see https://glotpress.trac.wordpress.org/ticket/327
	 *
	 * @group matching
	 */
	function test_add_translations_from_other_projects_with_placeholders_in_original() {
		$set1 = $this->factory->translation_set->create_with_project_and_locale( array(), array( 'name' => 'project_one' ) );
		$set2 = $this->factory->translation_set->create_with_project( array( 'locale' => $set1->locale ), array( 'name' => 'project_two' ) );

		// Original for project 1.
		$original1 = $this->factory->original->create( array( 'project_id' => $set1->project_id, 'status' => '+active', 'singular' => '%s baba', 'plural' => '%s babas' ) );
		$translation1 = $this->factory->translation->create( array( 'translation_set_id' => $set1->id, 'original_id' => $original1->id, 'status' => 'current' ) );

		// Original for project 2.
		$this->factory->original->create( array( 'project_id' => $set2->project_id, 'status' => '+active', 'singular' => '%s baba', 'plural' => '%s babas' ) );

		$set2_current_translations = GP::$translation->for_export( $set2->project, $set2, array( 'status' => 'fuzzy' ) );
		$this->assertEquals( 1, count( $set2_current_translations ) );
		$this->assertEquals( $translation1->translation_0, $set2_current_translations[0]->translations[0] );
	}

	/**
	 * Tests that propagation doesn't create any duplicates.
	 *
	 * @see https://glotpress.trac.wordpress.org/ticket/327
	 *
	 * @group matching
	 */
	function test_add_translations_from_other_projects_not_creating_duplicate_translations() {
		$set1 = $this->factory->translation_set->create_with_project_and_locale( array(), array( 'name' => 'project_one' ) );
		$set2 = $this->factory->translation_set->create_with_project( array( 'locale' => $set1->locale ), array( 'name' => 'project_two' ) );
		$set3 = $this->factory->translation_set->create_with_project( array( 'locale' => $set1->locale ), array( 'name' => 'project_three' ) );
		$set4 = $this->factory->translation_set->create_with_project( array( 'locale' => $set1->locale ), array( 'name' => 'project_four' ) );

		// Insert first original with a waiting translation in project 1.
		$original1 = $this->factory->original->create( array( 'project_id' => $set1->project_id, 'status' => '+active', 'singular' => 'baba' ) );
		$this->factory->translation->create( array( 'translation_set_id' => $set1->id, 'original_id' => $original1->id, 'status' => 'waiting' ) );

		// Insert the same original with a current translation in project 2.
		$original2 = $this->factory->original->create( array( 'project_id' => $set2->project_id, 'status' => '+active', 'singular' => 'baba' ) );
		$this->factory->translation->create( array( 'translation_set_id' => $set2->id, 'original_id' => $original2->id, 'status' => 'current' ) );

		// Insert the same original with a current translation in project 3.
		$original3 = $this->factory->original->create( array( 'project_id' => $set3->project_id, 'status' => '+active', 'singular' => 'baba' ) );
		$translation3 = $this->factory->translation->create( array( 'translation_set_id' => $set3->id, 'original_id' => $original3->id, 'status' => 'current' ) );

		// Insert the same original with no translation in project 4.
		$this->factory->original->create( array( 'project_id' => $set4->project_id, 'status' => '+active', 'singular' => 'baba' ) );

		// The translation of the fourth original should be equal with the translation in project 3, because it's the newest.
		$set4_current_translations = GP::$translation->for_export( $set4->project, $set4, array( 'status' => 'fuzzy' ) );
		$this->assertEquals( 1, count( $set4_current_translations ) );
		$this->assertEquals( $translation3->translation_0, $set4_current_translations[0]->translations[0] );
	}

	/**
	 * Helper to create dummy translations.
	 *
	 * @param array $entries Data for a translation entry.
	 *
	 * @return Translations
	 */
	function create_translations_with( $entries ) {
		$translations = new Translations;
		foreach ( $entries as $entry ) {
			$translations->add_entry( $entry );
		}
		return $translations;
	}

	/**
	 * Tests that propagation clears count caches of translation sets.
	 *
	 * @see https://github.com/GlotPress/GlotPress-WP/issues/332
	 *
	 * @group matching
	 */
	function test_import_for_project_cleans_cache_for_translation_sets() {
		$set1 = $this->factory->translation_set->create_with_project_and_locale( array(), array( 'name' => 'project_one' ) );
		$set2 = $this->factory->translation_set->create_with_project( array( 'locale' => $set1->locale ), array( 'name' => 'project_two' ) );

		$this->assertEquals( 0, $set2->current_count() );

		$original = $this->factory->original->create( array( 'project_id' => $set1->project->id, 'status' => '+active', 'singular' => 'baba baba' ) );
		$this->factory->translation->create( array( 'translation_set_id' => $set1->id, 'original_id' => $original->id, 'status' => 'current' ) );

		$translations_for_import = $this->create_translations_with( array( array( 'singular' => $original->singular ) ) );

		list( $originals_added, $originals_existing, $originals_fuzzied, $originals_obsoleted ) = GP::$original->import_for_project( $set2->project, $translations_for_import );

		$this->assertEquals( 1, $originals_added );
		$this->assertEquals( 0, $originals_existing );
		$this->assertEquals( 0, $originals_fuzzied );
		$this->assertEquals( 0, $originals_obsoleted );

		// `GP_Translation_Set` stores the counts as a property too, set it null to force recalculation.
		$set2->fuzzy_count = null;

		$this->assertEquals( 1, $set2->fuzzy_count() );
	}

	/**
	 * Tests that translation propagation works.
	 *
	 * @group propagating
	 */
	function test_propagate_across_projects_propagates() {
		$set1 = $this->factory->translation_set->create_with_project_and_locale( array(), array( 'name' => 'project_one' ) );
		$set2 = $this->factory->translation_set->create_with_project( array( 'locale' => $set1->locale ), array( 'name' => 'project_two' ) );

		$original = $this->factory->original->create( array( 'project_id' => $set1->project->id, 'status' => '+active', 'singular' => 'baba' ) );
		$this->factory->original->create( array( 'project_id' => $set2->project->id, 'status' => '+active', 'singular' => 'baba' ) );

		$this->factory->translation->create( array( 'translation_set_id' => $set1->id, 'original_id' => $original->id, 'status' => 'current' ) );

		$set2_current_translations = GP::$translation->for_export( $set2->project, $set2, array( 'status' => 'fuzzy' ) );

		$this->assertEquals( 1, count( $set2_current_translations ) );
	}

	/**
	 * Tests that translation propagation doesn't propagate if it's disabled
	 * via the `gp_enable_propagate_translations_across_projects` filter.
	 *
	 * @group propagating
	 */
	function test_propagate_across_projects_does_not_propagate_if_disabled() {
		$set1 = $this->factory->translation_set->create_with_project_and_locale( array(), array( 'name' => 'project_one' ) );
		$set2 = $this->factory->translation_set->create_with_project( array( 'locale' => $set1->locale ), array( 'name' => 'project_two' ) );

		$original = $this->factory->original->create( array( 'project_id' => $set1->project->id, 'status' => '+active', 'singular' => 'baba' ) );
		$this->factory->original->create( array( 'project_id' => $set2->project->id, 'status' => '+active', 'singular' => 'baba' ) );

		add_filter( 'gp_enable_propagate_translations_across_projects', '__return_false' );
		$this->factory->translation->create( array( 'translation_set_id' => $set1->id, 'original_id' => $original->id, 'status' => 'current' ) );
		remove_filter( 'gp_enable_propagate_translations_across_projects', '__return_false' );

		$set2_current_translations = GP::$translation->for_export( $set2->project, $set2, array( 'status' => 'fuzzy' ) );

		$this->assertEquals( 0, count( $set2_current_translations ) );
	}

	/**
	 * Tests that translation propagation is case-sensitiv.
	 *
	 * @group propagating
	 */
	function test_propagate_across_projects_propagates_case_sensitiv() {
		$set1 = $this->factory->translation_set->create_with_project_and_locale( array(), array( 'name' => 'project_one' ) );
		$set2 = $this->factory->translation_set->create_with_project( array( 'locale' => $set1->locale ), array( 'name' => 'project_two' ) );

		$original = $this->factory->original->create( array( 'project_id' => $set1->project->id, 'status' => '+active', 'singular' => 'baba' ) );
		$this->factory->original->create( array( 'project_id' => $set2->project->id, 'status' => '+active', 'singular' => 'baba' ) );
		$this->factory->original->create( array( 'project_id' => $set2->project->id, 'status' => '+active', 'singular' => 'Baba' ) );

		$this->factory->translation->create( array( 'translation_set_id' => $set1->id, 'original_id' => $original->id, 'status' => 'current' ) );

		$set2_current_translations = GP::$translation->for_export( $set2->project, $set2, array( 'status' => 'fuzzy' ) );
		$this->assertEquals( 1, count( $set2_current_translations ) );
	}

	/**
	 * Tests that translations with warnings are not propagated.
	 *
	 * @group propagating
	 */
	function test_propagate_across_projects_propagates_ignores_translations_with_warnings() {
		$set1 = $this->factory->translation_set->create_with_project_and_locale( array(), array( 'name' => 'project_one' ) );
		$set2 = $this->factory->translation_set->create_with_project( array( 'locale' => $set1->locale ), array( 'name' => 'project_two' ) );

		$original = $this->factory->original->create( array( 'project_id' => $set1->project->id, 'status' => '+active', 'singular' => 'baba' ) );
		$this->factory->original->create( array( 'project_id' => $set2->project->id, 'status' => '+active', 'singular' => 'baba' ) );

		$warnings = array( 0 => array( 'placeholder' => 'Missing %2$s placeholder in translation.' ) );
		$this->factory->translation->create( array( 'translation_set_id' => $set1->id, 'original_id' => $original->id, 'status' => 'current', 'warnings' => $warnings ) );

		$set2_current_translations = GP::$translation->for_export( $set2->project, $set2, array( 'status' => 'fuzzy' ) );
		$this->assertEquals( 0, count( $set2_current_translations ) );
	}

	function __string_status_current() {
		return 'current';
	}

	/**
	 * Tests that translation propagation doesn't create duplicates.
	 *
	 * @group propagating
	 */
	function test_propagate_across_projects_does_not_create_more_than_one_current() {
		$set1 = $this->factory->translation_set->create_with_project_and_locale( array(), array( 'name' => 'project_one' ) );
		$set2 = $this->factory->translation_set->create_with_project( array( 'locale' => $set1->locale ), array( 'name' => 'project_two' ) );
		$set3 = $this->factory->translation_set->create_with_project( array( 'locale' => $set1->locale ), array( 'name' => 'project_three' ) );

		$original1 = $this->factory->original->create( array( 'project_id' => $set1->project->id, 'status' => '+active', 'singular' => 'baba' ) );
		$original2 = $this->factory->original->create( array( 'project_id' => $set2->project->id, 'status' => '+active', 'singular' => 'baba' ) );
		$this->factory->original->create( array( 'project_id' => $set3->project_id, 'status' => '+active', 'singular' => 'baba' ) );

		add_filter( 'gp_translations_to_other_projects_status', array( $this, '__string_status_current' ) );
		$this->factory->translation->create( array( 'translation_set_id' => $set1->id, 'original_id' => $original1->id, 'status' => 'current' ) );
		$this->factory->translation->create( array( 'translation_set_id' => $set2->id, 'original_id' => $original2->id, 'status' => 'current' ) );
		remove_filter( 'gp_translations_to_other_projects_status', array( $this, '__string_status_current' ) );

		$set3_current_translations = GP::$translation->for_export( $set3->project, $set3, array( 'status' => 'current' ) );
		$this->assertEquals( 1, count( $set3_current_translations ) );
	}

	/**
	 * Tests that existing translations are used instead of the new one.
	 *
	 * @see https://github.com/GlotPress/GlotPress-WP/issues/252
	 */
	function test_copy_into_set_uses_equal_waiting_translations() {
		$set1 = $this->factory->translation_set->create_with_project_and_locale( array(), array( 'name' => 'project_one' ) );
		$set2 = $this->factory->translation_set->create_with_project( array( 'locale' => $set1->locale ), array( 'name' => 'project_two' ) );

		$original1 = $this->factory->original->create( array( 'project_id' => $set1->project->id, 'status' => '+active', 'singular' => 'baba' ) );
		$original2 = $this->factory->original->create( array( 'project_id' => $set2->project->id, 'status' => '+active', 'singular' => 'baba' ) );

		$translation1 = $this->factory->translation->create( array( 'translation_set_id' => $set1->id, 'original_id' => $original1->id ) );

		// Add the same translation as waiting to another set.
		$translation_waiting = $translation1->fields();
		$translation_waiting['translation_set_id'] = $set2->id;
		$translation_waiting['original_id'] = $original2->id;
		$translation_waiting['status'] = 'waiting';
		$this->factory->translation->create( $translation_waiting );

		$translation_propagation = GP_Translation_Propagation::get_instance();
		$translation_propagation->copy_translation_into_set( $translation1, $set2->id, $original2->id, 'current' );

		$set2_current_translations = GP::$translation->for_export( $set2->project, $set2, array( 'status' => 'current' ) );
		$this->assertEquals( 1, count( $set2_current_translations ) );

		$set2_current_translations = GP::$translation->for_export( $set2->project, $set2, array( 'status' => 'waiting' ) );
		$this->assertEquals( 0, count( $set2_current_translations ) );
	}
}
