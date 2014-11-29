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
 * PHPUnit Mhaairs util service tests.
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
 * PHPUnit mhaairs util service test case.
 *
 * @package     block_mhaairs
 * @category    phpunit
 * @group       block_mhaairs
 * @group       block_mhaairs_service
 * @group       block_mhaairs_utilservice
 * @copyright   2014 Itamar Tzadok <itamar@substantialmethods.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_mhaairs_utilservice_testcase extends advanced_testcase {
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
     * Get user info should fail when ssl is required and the connection
     * is not secured.
     *
     * @return void
     */
    public function test_get_user_info_no_ssl() {
        global $DB;

        $callback = 'block_mhaairs_utilservice_external::get_user_info';
        $this->set_user('admin');

        // Require ssl.
        $DB->set_field('config', 'value', 1, array('name' => 'block_mhaairs_sslonly'));

        // Service params.
        $serviceparams = array(
            'token' => null, // Token.
            'identitytype' => null, // Identity type.
        );

        $result = call_user_func_array($callback, $serviceparams);
        $this->assertEquals(MHUserInfo::FAILURE, $result->status);
        $this->assertEquals('error: connection must be secured with SSL', $result->message);
    }

    /**
     * Get user info should fail if is passed an invalid token with secret.
     * It should be allowed to continue othewise.
     *
     * @return void
     */
    public function test_get_user_info_invalid_token() {
        global $DB;

        $callback = 'block_mhaairs_utilservice_external::get_user_info';
        $this->set_user('admin');

        $secret = 'DF4#R66';
        $userid = $this->student->username;
        $equal = true;

        // SECRET NOT CONFIGURED.

        // Invalid token missing userid.
        // Should fail with or without a correct secret.
        $this->assert_invalid_token('time='. MHUtil::get_time_stamp());
        $this->assert_invalid_token('time='. MHUtil::get_time_stamp(), $secret);

        // Invalid token with userid.
        // Should NOT fail with or without secret.
        $this->assert_invalid_token("userid=$userid", null, !$equal);
        $this->assert_invalid_token("userid=$userid", $secret, !$equal);

        // SECRET CONFIGURED.
        $DB->set_field('config', 'value', $secret, array('name' => 'block_mhaairs_shared_secret'));

        // Invalid token missing userid.
        // Should fail with or without a correct secret.
        $this->assert_invalid_token('time='. MHUtil::get_time_stamp());
        $this->assert_invalid_token('time='. MHUtil::get_time_stamp(), $secret);

        // Invalid token with userid.
        // Should NOT fail with or without a correct secret.
        $this->assert_invalid_token("userid=$userid", null, !$equal);
        $this->assert_invalid_token("userid=$userid", $secret, !$equal);
        $this->assert_invalid_token(MHUtil::create_token($userid), $secret. '7', !$equal);

    }

    /**
     * Get user info should fail if the requested user is not found.
     * User can be requested by internal userid (identitytype = internal),
     * or by username (identitytype != internal).
     *
     * @return void
     */
    public function test_get_user_info_invalid_user() {
        global $DB;

        $callback = 'block_mhaairs_utilservice_external::get_user_info';
        $this->set_user('admin');

        $secret = 'DF4#R66';
        $fakeuserid = 278939;
        $fakeusername = 'johndoe';
        $realuserid = $this->student->id;
        $realusername = $this->student->username;

        // Dataset for assert_user_not_found.
        // Each array item is a list of arguments the consitute a test case
        // where the service should fail with user not found message.
        // The list of arguments consists of user id, secret, identity type.
        $fixture = __DIR__. '/fixtures/tc_get_user_info_user_not_found.csv';
        $dataset = $this->createCsvDataSet(array('cases' => $fixture));
        $rows = $dataset->getTable('cases');
        $columns = $dataset->getTableMetaData('cases')->getColumns();

        $cases = array();
        for ($r = 0; $r < $rows->getRowCount(); $r++) {
            $cases[] = (object) array_combine($columns, $rows->getRow($r));
        }

        // SECRET NOT CONFIGURED.
        foreach ($cases as $case) {
            $auserid = ${$case->userid};
            $asecret = $case->secret == 'secret' ? ${$case->secret} : $case->secret;
            $this->assert_user_not_found($auserid, $asecret, $case->identitytype);
        }

        // SECRET CONFIGURED.
        $DB->set_field('config', 'value', $secret, array('name' => 'block_mhaairs_shared_secret'));
        foreach ($cases as $case) {
            $auserid = ${$case->userid};
            $asecret = $case->secret == 'secret' ? ${$case->secret} : $case->secret;
            $this->assert_user_not_found($auserid, $asecret, $case->identitytype);
        }

    }

    /**
     * Get user info should return user record and roles by course for the target user.
     * User can be requested by internal userid (identitytype = internal),
     * or by username (identitytype != internal).
     *
     * @return void
     */
    public function test_get_user_info_valid_user() {
        global $DB;

        $callback = 'block_mhaairs_utilservice_external::get_user_info';
        $this->set_user('admin');

        $secret = 'DF4#R66';
        $fakeuserid = 278939;
        $fakeusername = 'johndoe';
        $realuserid = $this->student->id;
        $realusername = $this->student->username;

        // Users dataset.
        // Username, course (idnumber), editingteacher, teacher, student.
        $fixture = __DIR__. '/fixtures/tc_get_user_info_users.csv';
        $dataset = $this->createCsvDataSet(array('cases' => $fixture));
        $rows = $dataset->getTable('cases');
        $columns = $dataset->getTableMetaData('cases')->getColumns();

        $cases = array();
        for ($r = 0; $r < $rows->getRowCount(); $r++) {
            $cases[] = (object) array_combine($columns, $rows->getRow($r));
        }

        // Add users and enrollments.
        $users = array();
        $courses = array();

        $roles = $DB->get_records_menu('role', array(), '', 'shortname,id');
        foreach ($cases as $case) {
            // Add the user if needed.
            if (!array_key_exists($case->username, $users)) {
                $userparams = array('username' => $case->username);
                $user = $this->getDataGenerator()->create_user($userparams);
                $users[$user->username] = $user;
            }
            $userid = $users[$case->username]->id;

            // Add the course if needed.
            if (!array_key_exists($case->course, $courses)) {
                $record = array('idnumber' => $case->course);
                $course = $this->getDataGenerator()->create_course($record);
                $courses[$course->idnumber] = $course;
            }
            $courseid = $courses[$case->course]->id;
            
            // Add enrollments.
            foreach ($roles as $shortname => $roleid) {
                if (!empty($case->$shortname)) {
                    $this->getDataGenerator()->enrol_user($userid, $courseid, $roleid);
                }
            }
        }

        // Tese cases dataset.
        // User id, secret, identity type.
        $fixture = __DIR__. '/fixtures/tc_get_user_info_user_found.csv';
        $dataset = $this->createCsvDataSet(array('cases' => $fixture));
        $rows = $dataset->getTable('cases');
        $columns = $dataset->getTableMetaData('cases')->getColumns();

        $cases = array();
        for ($r = 0; $r < $rows->getRowCount(); $r++) {
            $cases[] = (object) array_combine($columns, $rows->getRow($r));
        }

        // SECRET NOT CONFIGURED.
        foreach ($cases as $case) {
            $internal = ($case->identitytype == 'internal');
            $auserid = ($internal ? $users[$case->username]->id : $case->username);
            $asecret = $case->secret == 'secret' ? ${$case->secret} : $case->secret;
            $this->assert_user_found($auserid, $asecret, $case);
        }

        // SECRET CONFIGURED.
        $DB->set_field('config', 'value', $secret, array('name' => 'block_mhaairs_shared_secret'));
        foreach ($cases as $case) {
            $internal = ($case->identitytype == 'internal');
            $auserid = ($internal ? $users[$case->username]->id : $case->username);
            $asecret = $case->secret == 'secret' ? ${$case->secret} : $case->secret;
            $this->assert_user_found($auserid, $asecret, $case);
        }

    }

    /**
     * Asserts invalid token against get_user_info with the specified token and secret.
     * If secret is omitted, try to take the secret from the configuration. The secret
     * parameter is used for creating the encoded token.
     *
     * @param string $token
     * @param string $secret
     * @param boolean $equal Determines the assertion type (assertEquals | assertNotEquals).
     * @return void
     */
    protected function assert_invalid_token($token, $secret = null, $equal = true) {
        // The service function.
        $callback = 'block_mhaairs_utilservice_external::get_user_info';

        // Encode the token.
        $encodedtoken = MHUtil::encode_token2($token, $secret);

        // Service params.
        $serviceparams = array(
            'token' => $encodedtoken, // Token.
            'identitytype' => null, // Identity type.
        );

        $result = call_user_func_array($callback, $serviceparams);
        if ($equal) {
            $this->assertEquals('error: token is invalid', $result->message);
            $this->assertEquals(MHUserInfo::FAILURE, $result->status);
        } else {
            $this->assertNotEquals('error: token is invalid', $result->message);
        }
    }

    /**
     * Asserts invalid token against get_user_info with the specified token and secret.
     * If secret is omitted, try to take the secret from the configuration. The secret
     * parameter is used for creating the encoded token.
     *
     * @param string $userid
     * @param string $secret
     * @param string $identitytype
     * @return void
     */
    protected function assert_user_not_found($userid, $secret = null, $identitytype = null) {
        // The service function.
        $callback = 'block_mhaairs_utilservice_external::get_user_info';
        $uservar = MHUtil::get_user_var($identitytype);

        // Create the token.
        $token = MHUtil::create_token($userid);
        $encodedtoken = MHUtil::encode_token2($token, $secret);

        // Service params.
        $serviceparams = array(
            'token' => $encodedtoken, // Token.
            'identitytype' => $identitytype, // Identity type.
        );

        $result = call_user_func_array($callback, $serviceparams);
        $this->assertEquals(MHUserInfo::FAILURE, $result->status);
        $this->assertEquals("error: user with $uservar '$userid' not found", $result->message);
        $this->assertEquals(null, $result->user);
    }

    /**
     * Asserts invalid token against get_user_info with the specified token and secret.
     * If secret is omitted, try to take the secret from the configuration. The secret
     * parameter is used for creating the encoded token.
     *
     * @param string $userid
     * @param string $secret
     * @param string $identitytype
     * @return void
     */
    protected function assert_user_found($userid, $secret, $case) {
        // The service function.
        $callback = 'block_mhaairs_utilservice_external::get_user_info';
        $uservar = MHUtil::get_user_var($case->identitytype);

        // Create the token.
        $token = MHUtil::create_token($userid);
        $encodedtoken = MHUtil::encode_token2($token, $secret);

        // Service params.
        $serviceparams = array(
            'token' => $encodedtoken, // Token.
            'identitytype' => $case->identitytype, // Identity type.
        );

        $result = call_user_func_array($callback, $serviceparams);
        $this->assertEquals(MHUserInfo::SUCCESS, $result->status);
        $this->assertEquals('', $result->message);
        $this->assertEquals($case->username, $result->user->username);
        $coursecount = 0;
        foreach (array('tc2', 'tc3', 'tc4', 'tc5') as $tc) {
            if (!empty($case->$tc)) {
                $coursecount += count(explode(',', $case->$tc));
            }
        }
        $this->assertEquals($coursecount, count($result->courses));
    }

}