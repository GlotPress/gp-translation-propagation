<?php

/**
 * @group routes
 */
class GP_Test_Route_Translation extends GP_UnitTestCase_Route {
	public $route_class = 'GP_Route_Translation';

	/**
	 * Tests that a translation gets propagated if all warnings are discarded.
	 *
	 * @see https://glotpress.trac.wordpress.org/ticket/327
	 */
	function test_discard_warning_edit_function() {
		$set1 = $this->factory->translation_set->create_with_project_and_locale( array(), array( 'name' => 'project_one' ) );
		$set2 = $this->factory->translation_set->create_with_project( array( 'locale' => $set1->locale ), array( 'name' => 'project_two' ) );

		$original1 = $this->factory->original->create( array( 'project_id' => $set1->project->id, 'status' => '+active', 'singular' => 'baba' ) );
		$this->factory->original->create( array( 'project_id' => $set2->project->id, 'status' => '+active', 'singular' => 'baba' ) );

		// Create a translation with two warnings.
		$warnings = array(
			0 => array( 'placeholder' => 'Missing %2$s placeholder in translation.' ),
			1 => array( 'should_begin_on_newline' => 'Original and translation should both begin on newline.' ),
		);
		$translation1 = $this->factory->translation->create( array( 'translation_set_id' => $set1->id, 'original_id' => $original1->id, 'status' => 'current', 'warnings' => $warnings ) );

		// Second original shouldn't translated yet because of two warnings.
		$set2_current_translations = GP::$translation->for_export( $set2->project, $set2, array( 'status' => 'current' ) );
		$this->assertEquals( 0, count( $set2_current_translations ) );

		$_POST['translation_id'] = $translation1->id;
		$_POST['index'] = 0;
		$_POST['key'] = 'placeholder';
		$this->route->discard_warning( $set2->project->path, $set2->locale, $set2->slug );

		// Second original shouldn't translated yet because of one warning.
		$set2_current_translations = GP::$translation->for_export( $set2->project, $set2, array( 'status' => 'current' ) );
		$this->assertEquals( 0, count( $set2_current_translations ) );

		$_POST['translation_id'] = $translation1->id;
		$_POST['index'] = 1;
		$_POST['key'] = 'should_begin_on_newline';
		$this->route->discard_warning( $set2->project->path, $set2->locale, $set2->slug );

		// Second original should be translated now.
		$set2_current_translations = GP::$translation->for_export( $set2->project, $set2, array( 'status' => 'fuzzy' ) );
		$this->assertEquals( 1, count( $set2_current_translations ) );
	}
}
