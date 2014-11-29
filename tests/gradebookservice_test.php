<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * PHPUnit Mhaairs gradebook service tests.
 *
 * @package     block_mhaairs
 * @category    phpunit
 * @copyright   2014 Itamar Tzadok <itamar@substantialmethods.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once("$CFG->dirroot/blocks/mhaairs/externallib.php");

/**
 * PHPUnit mhaairs gradebook service test case.
 *
 * @package     block_mhaairs
 * @category    phpunit
 * @group       block_mhaairs
 * @group       block_mhaairs_service
 * @group       block_mhaairs_gradebookservice
 * @copyright   2014 Itamar Tzadok <itamar@substantialmethods.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_mhaairs_gradebookservice_testcase extends advanced_testcase {
    protected $course;
    protected $bi;
    protected $guest;
    protected $teacher;
    protected $assistant;
    protected $student;

    /**
     * Test set up.
     *
     * This is executed before running any test in this file.
     */
    public function setUp() {
        global $DB, $PAGE;

        $this->resetAfterTest();

        // Create a course we are going to add the block to.
        // This is Test course 1 | tc_1.
        // Add idnumber tc1, so that we can test identity type.
        $record = array('idnumber' => 'tc1');
        $this->course = $this->getDataGenerator()->create_course($record);
        $courseid = $this->course->id;

        // Set the page.
        $PAGE->set_course($this->course);
        $contextid = $PAGE->context->id;

        // Create an instance of the block in the course.
        $generator = $this->getDataGenerator()->get_plugin_generator('block_mhaairs');
        $record = array('parentcontextid' => $contextid, 'pagetypepattern' => '*');
        $this->bi = $generator->create_instance($record);

        // Create users and enroll them in the course.
        $roles = $DB->get_records_menu('role', array(), '', 'shortname,id');

        // Teacher.
        $user = $this->getDataGenerator()->create_user(array('username' => 'teacher'));
        $this->getDataGenerator()->enrol_user($user->id, $courseid, $roles['editingteacher']);
        $this->teacher = $user;

        // Assistant.
        $user = $this->getDataGenerator()->create_user(array('username' => 'assistant'));
        $this->getDataGenerator()->enrol_user($user->id, $courseid, $roles['teacher']);
        $this->assistant = $user;

        // Student.
        $user = $this->getDataGenerator()->create_user(array('username' => 'student'));
        $this->getDataGenerator()->enrol_user($user->id, $courseid, $roles['student']);
        $this->student = $user;

        // Guest.
        $user = $DB->get_record('user', array('username' => 'guest'));
        $this->guest = $user;
    }

    /**
     * Sets the user.
     *
     * @return void
     */
    protected function set_user($username) {
        if ($username == 'admin') {
            $this->setAdminUser();
        } else if ($username == 'guest') {
            $this->setGuestUser();
        } else {
            $this->setUser($this->$username);
        }
    }

    /**
     * Asserts equals grade item values and expected values.
     *
     * @param grade_item $gitem
     * @param array $expected
     * @return void
     */
    protected function grade_item_assert_equals($gitem, array $expected) {
        // Some fields should be asserted separately.
        unset($expected['courseid']);
        unset($expected['categoryid']);
        
        $this->assertEquals($this->course->id, $gitem->courseid);
        foreach ($gitem as $var => $value) {
            if (array_key_exists($var, $expected)) {
                $this->assertEquals($expected[$var], $value);
            }
        }
    }

    /**
     * Gradebookservice update grade should fail when sync grades is disabled
     * in the plugin site settings.
     *
     * @return void
     */
    public function test_update_grade_no_sync() {
        global $DB;

        $callback = 'block_mhaairs_gradebookservice_external::gradebookservice';
        $this->set_user('admin');

        // Item details.
        $itemdetails = array(
            'categoryid' => '',
            'courseid' => $this->course->id,
            'identity_type' => '',
            'itemname' => 'testassignment',
            'itemtype' => 'mod',
            'idnumber' => 0,
            'gradetype' => GRADE_TYPE_VALUE,
            'grademax' => 100,
            'iteminfo' => '',
        );
        $itemdetailsjson = urlencode(json_encode($itemdetails));

        // Service params.
        $serviceparams = array(
            'source' => 'mod/assignment', // Source.
            'courseid' => $this->course->id, // Course id.
            'itemtype' => 'mod', // Item type.
            'itemmodule' => 'assignment', // Item module.
            'iteminstance' => '0', // Item instance.
            'itemnumber' => '0', // Item number.
            'grades' => null, // Grades.
            'itemdetails' => $itemdetailsjson, // Item details.
        );

        // No sync.
        $DB->set_field('config', 'value', 0, array('name' => 'block_mhaairs_sync_gradebook'));

        $result = call_user_func_array($callback, $serviceparams);
        $this->assertEquals(GRADE_UPDATE_FAILED, $result);
    }

    /**
     * Gradebookservice update grade should fail when the target course is not found.
     *
     * @return void
     */
    public function test_update_grade_invalid_course() {
        global $DB;

        $callback = 'block_mhaairs_gradebookservice_external::gradebookservice';
        $this->set_user('admin');

        // There is only one course in this test,
        // So get an id that is different from the course's.
        $courseid = $this->course->id + 1;
        
        // Item details.
        $itemdetails = array(
            'categoryid' => '',
            'courseid' => $this->course->id,
            'identity_type' => '',
            'itemname' => 'testassignment',
            'itemtype' => 'mod',
            'idnumber' => 0,
            'gradetype' => GRADE_TYPE_VALUE,
            'grademax' => 100,
            'iteminfo' => '',
        );
        $itemdetailsjson = urlencode(json_encode($itemdetails));

        // Service params.
        $serviceparams = array(
            'source' => 'mod/assignment', // Source.
            'courseid' => $courseid, // Course id.
            'itemtype' => 'mod', // Item type.
            'itemmodule' => 'assignment', // Item module.
            'iteminstance' => '0', // Item instance.
            'itemnumber' => '0', // Item number.
            'grades' => null, // Grades.
            'itemdetails' => $itemdetailsjson, // Item details.
        );

        $result = call_user_func_array($callback, $serviceparams);
        $this->assertEquals(GRADE_UPDATE_FAILED, $result);
    }

    /**
     * Tests the gradebookservice update grade function for adding,
     * updating and deleting an item.
     *
     * @return void
     */
    public function test_update_grade_item() {
        global $DB;

        $callback = 'block_mhaairs_gradebookservice_external::gradebookservice';
        $this->set_user('admin');

        // Item details.
        $itemdetails = array(
            'categoryid' => '',
            'courseid' => '',
            'identity_type' => '',
            'itemname' => 'testassignment',
            'itemtype' => 'mod',
            'idnumber' => 0,
            'gradetype' => GRADE_TYPE_VALUE,
            'grademax' => 100,
            'iteminfo' => '',
        );
        $itemdetailsjson = urlencode(json_encode($itemdetails));

        // Service params.
        $serviceparams = array(
            'source' => 'mod/assignment', // Source.
            'courseid' => $this->course->id, // Course id.
            'itemtype' => 'mod', // Item type.
            'itemmodule' => 'assignment', // Item module.
            'iteminstance' => '0', // Item instance.
            'itemnumber' => '0', // Item number.
            'grades' => null, // Grades.
            'itemdetails' => $itemdetailsjson, // Item details.
        );

        // Grade item fetch params.
        $giparams = array(
            'itemtype' => 'mod',
            'itemmodule' => 'assignment',
            'iteminstance' => 0,
            'courseid' => $this->course->id,
            'itemnumber' => 0
        );

        // CREATE.
        $result = call_user_func_array($callback, $serviceparams);
        $this->assertEquals(GRADE_UPDATE_OK, $result);

        // Check grade item created.
        // Expected 2: one for the course and one for the item.
        $this->assertEquals(2, $DB->count_records('grade_items'));

        $gitem = grade_item::fetch($giparams);
        $this->grade_item_assert_equals($gitem, $itemdetails);

        // UPDATE.
        // Identify course by idnumber.
        $serviceparams['courseid'] = 'tc1';
        // Item details.
        $itemdetails['grademax'] = 95;
        $itemdetailsjson = urlencode(json_encode($itemdetails));
        $serviceparams['itemdetails'] = $itemdetailsjson;

        $result = call_user_func_array($callback, $serviceparams);
        $this->assertEquals(GRADE_UPDATE_OK, $result);

        // Check grade item updated.
        // Expected 2: one for the course and one for the item.
        $this->assertEquals(2, $DB->count_records('grade_items'));

        $gitem = grade_item::fetch($giparams);
        $this->grade_item_assert_equals($gitem, $itemdetails);

        // UPDATE the should fail.
        // Identify course by idnumber.
        $serviceparams['courseid'] = 'tc1';
        // Try to update by id only.
        $itemdetails['identity_type'] = 'internal';
        $itemdetailsjson = urlencode(json_encode($itemdetails));
        $serviceparams['itemdetails'] = $itemdetailsjson;

        $result = call_user_func_array($callback, $serviceparams);
        $this->assertEquals(GRADE_UPDATE_FAILED, $result);

        // DELETE ITEM.
        // Identify course by id.
        $serviceparams['courseid'] = $this->course->id;
        // Set delete in item details.
        $itemdetails['deleted'] = true;
        $itemdetailsjson = urlencode(json_encode($itemdetails));
        $serviceparams['itemdetails'] = $itemdetailsjson;

        $result = call_user_func_array($callback, $serviceparams);
        $this->assertEquals(GRADE_UPDATE_OK, $result);

        $this->assertEquals(1, $DB->count_records('grade_items'));
    }
}
