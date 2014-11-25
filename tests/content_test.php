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
 * PHPUnit block content tests.
 *
 * @package     block_mhaairs
 * @category    phpunit
 * @copyright   2014 Itamar Tzadok <itamar@substantialmethods.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();


/**
 * PHPUnit block content test case.
 *
 * @package     block_mhaairs
 * @category    phpunit
 * @group       block_mhaairs
 * @group       block_mhaairs_content
 * @copyright   2014 Itamar Tzadok <itamar@substantialmethods.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_mhaairs_content_testcase extends advanced_testcase {
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
        $this->course = $this->getDataGenerator()->create_course();
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
     * Tests the block content without integration configuration.
     *
     * @return void
     */
    public function test_content_no_configuration() {
        global $PAGE;

        $blockname = 'mhaairs';

        // Admin should see a warning message.
        $this->set_user('admin');
        $block = block_instance($blockname, $this->bi, $PAGE);
        $content = $block->get_content();
        $this->assertEquals($block->get_warning_message('sitenotconfig'), $content->text);

        // Teacher should see a warning message.
        $this->set_user('teacher');
        $block = block_instance($blockname, $this->bi, $PAGE);
        $content = $block->get_content();
        $this->assertEquals($block->get_warning_message('sitenotconfig'), $content->text);

        // Assistant should see a warning message.
        $this->set_user('assistant');
        $block = block_instance($blockname, $this->bi, $PAGE);
        $content = $block->get_content();
        $this->assertEquals('', $content->text);

        // Student should see nothing.
        $this->set_user('student');
        $block = block_instance($blockname, $this->bi, $PAGE);
        $content = $block->get_content();
        $this->assertEquals('', $content->text);
    }

    /**
     * Tests the block content with integration configuration omitting services.
     *
     * @return void
     */
    public function test_content_no_services() {
        global $PAGE;

        $blockname = 'mhaairs';

        $config = array();
        $config['block_mhaairs_customer_number'] = 'Test Customer';
        $config['block_mhaairs_shared_secret'] = '1234';

        // Admin should see a warning message.
        $this->set_user('admin');
        $block = block_instance($blockname, $this->bi, $PAGE);
        $block->set_phpunit_test_config($config);
        $content = $block->get_content();
        $this->assertEquals($block->get_warning_message('sitenotconfig'), $content->text);

        // Teacher should see a warning message.
        $this->set_user('teacher');
        $block = block_instance($blockname, $this->bi, $PAGE);
        $block->set_phpunit_test_config($config);
        $content = $block->get_content();
        $this->assertEquals($block->get_warning_message('sitenotconfig'), $content->text);

        // Assistant should see a warning message.
        $this->set_user('assistant');
        $block = block_instance($blockname, $this->bi, $PAGE);
        $block->set_phpunit_test_config($config);
        $content = $block->get_content();
        $this->assertEquals('', $content->text);

        // Student should see nothing.
        $this->set_user('student');
        $block = block_instance($blockname, $this->bi, $PAGE);
        $block->set_phpunit_test_config($config);
        $content = $block->get_content();
        $this->assertEquals('', $content->text);
    }

    /**
     * Tests the block content with services enabled site level but not
     * block level.
     *
     * @return void
     */
    public function test_content_with_site_services() {
        global $PAGE;

        $blockname = 'mhaairs';

        $config = array();
        $config['block_mhaairs_customer_number'] = 'Test Customer';
        $config['block_mhaairs_shared_secret'] = '1234';
        $config['block_mhaairs_display_services'] = 'Test Service';

        // Admin should see link to service.
        $this->set_user('admin');
        $block = block_instance($blockname, $this->bi, $PAGE);
        $block->set_phpunit_test_config($config);
        $content = $block->get_content();
        $this->assertEquals($block->get_warning_message('blocknotconfig'), $content->text);

        // Teacher should see link to service.
        $this->set_user('teacher');
        $block = block_instance($blockname, $this->bi, $PAGE);
        $block->set_phpunit_test_config($config);
        $content = $block->get_content();
        $this->assertEquals($block->get_warning_message('blocknotconfig'), $content->text);

        // Assistant should see link to service.
        $this->set_user('assistant');
        $block = block_instance($blockname, $this->bi, $PAGE);
        $block->set_phpunit_test_config($config);
        $content = $block->get_content();
        $this->assertEquals('', $content->text);

        // Student should see link to service.
        $this->set_user('student');
        $block = block_instance($blockname, $this->bi, $PAGE);
        $block->set_phpunit_test_config($config);
        $content = $block->get_content();
        $this->assertEquals('', $content->text);
    }

    /**
     * Tests the block content with services enabled site level and
     * block level.
     *
     * @return void
     */
    public function test_content_with_block_services() {
        global $DB, $PAGE;

        $blockname = 'mhaairs';

        // Site config.
        $config = array();
        $config['block_mhaairs_customer_number'] = 'Test Customer';
        $config['block_mhaairs_shared_secret'] = '1234';
        $config['block_mhaairs_display_services'] = 'TestService';

        // Test service data.
        $testservicedata = array(
            'ServiceIconUrl' => null,
            'ServiceUrl' => null,
            'ServiceID' => 'TestService',
            'ServiceName' => 'Test Service',
        );
        $servicedata = array('Tools' => array($testservicedata));
        $config['service_data'] = $servicedata;

        // Service enabling in the block.
        $blockconfig = (object) array('TestService' => 1);
        $blockconfigdata = base64_encode(serialize($blockconfig));
        $this->bi->configdata = $blockconfigdata;

        // Admin should see link to service.
        $this->set_user('admin');
        $block = block_instance($blockname, $this->bi, $PAGE);
        $block->set_phpunit_test_config($config);
        $content = $block->get_content();
        $this->assertContains('Test Service', $content->text);

        // Teacher should see link to service.
        $this->set_user('teacher');
        $block = block_instance($blockname, $this->bi, $PAGE);
        $block->set_phpunit_test_config($config);
        $content = $block->get_content();
        $this->assertContains('Test Service', $content->text);

        // Assistant should see link to service.
        $this->set_user('assistant');
        $block = block_instance($blockname, $this->bi, $PAGE);
        $block->set_phpunit_test_config($config);
        $content = $block->get_content();
        $this->assertContains('Test Service', $content->text);

        // Student should see link to service.
        $this->set_user('student');
        $block = block_instance($blockname, $this->bi, $PAGE);
        $block->set_phpunit_test_config($config);
        $content = $block->get_content();
        $this->assertContains('Test Service', $content->text);
    }

}
