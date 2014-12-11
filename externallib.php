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
 * This file contains the external api class for the mhaairs-moodle integration.
 *
 * @package     block_mhaairs
 * @copyright   2014 Itamar Tzadok <itamar@substantialmethods.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') or die();

global $CFG;
require_once("$CFG->libdir/externallib.php");
require_once("$CFG->libdir/gradelib.php");
require_once("$CFG->dirroot/blocks/mhaairs/block_mhaairs_util.php");

/**
 * Block mhaairs gradebook web service.
 *
 * @package     block_mhaairs
 * @copyright   2014 Itamar Tzadok <itamar@substantialmethods.com>
 * @copyright   2013-2014 Moodlerooms inc.
 * @author      Teresa Hardy <thardy@moodlerooms.com>
 * @author      Darko MIletic <dmiletic@moodlerooms.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_mhaairs_gradebookservice_external extends external_api {

    // UPDATE GRADE.
    /**
     * Allows external services to push grades into the course gradebook.
     *
     * @param string $source
     * @param string $courseid
     * @param string $itemtype
     * @param string $itemmodule
     * @param string $iteminstance
     * @param string $itemnumber
     * @param string $grades
     * @param string $itemdetails
     * @return mixed
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     */
    public static function update_grade($source = 'mhaairs', $courseid ='courseid', $itemtype = 'mod',
                                            $itemmodule = 'assignment', $iteminstance = '0', $itemnumber = '0',
                                            $grades = null, $itemdetails = null) {
        global $USER, $DB;

        $logger = MHLog::instance();
        $logger->log('==================================');
        $logger->log('New webservice request started on '. $logger->time_stamp);
        $logger->log('Entry parameters:');
        $logger->log("source = {$source}");
        $logger->log("courseid = {$courseid}");
        $logger->log("itemtype = {$itemtype}");
        $logger->log("itemmodule = {$itemmodule}");
        $logger->log("iteminstance = {$iteminstance}");
        $logger->log("itemnumber = {$itemnumber}");
        $logger->log("grades = {$grades}");
        $logger->log("itemdetails = {$itemdetails}");

        // Gradebook sync must be enabled by admin in the block's site configuration.
        if (!$syncgrades = get_config('core', 'block_mhaairs_sync_gradebook')) {
            $logger->log('Grade sync is not enabled in global settings. Returning 1.');
            return GRADE_UPDATE_FAILED;
        }

        // Context validation.
        // OPTIONAL but in most web service it should be present.
        $context = context_user::instance($USER->id);
        self::validate_context($context);
        $logger->log('Context validated.');

        // Capability checking.
        // OPTIONAL but in most web service it should be present.
        require_capability('moodle/user:viewdetails', $context, null, true, 'cannotviewprofile');
        $logger->log('Capability validated.');

        // Decode item details and check for problems.
        $itemdetails = self::decode_item_details($itemdetails, $grades);
        $logger->log("Decoded itemdetails: ". var_export($itemdetails, true));

        // Get the course.
        $course = self::get_course($courseid, $itemdetails);
        if ($course === false) {
            // No valid course specified.
            $logger->log("Course id received was not correct. courseid = {$courseid}.");
            $logger->log('Returning '. GRADE_UPDATE_FAILED. '.');
            return GRADE_UPDATE_FAILED;
        }
        $courseid = $course->id;
        $logger->log('Course validated.');

        // Handle the category.
        $logger->log("Preparing to check and create grade category if needed.");
        if (!empty($itemdetails['categoryid']) && $itemdetails['categoryid'] != 'null') {
            self::handle_grade_category($itemdetails, $courseid);
        }

        // Can we fully create grade_item with available data if needed?
        $cancreategradeitem = self::can_create_grade_item($itemdetails);

        // Decode grades and check for problems.
        $logger->log("Preparing to check if any grades where sent.");
        $grades = self::decode_grades($grades, $itemdetails);
        $logger->log("Decoded grades: ". var_export($grades, true));

        if (!$cancreategradeitem) {
            $logger->log('We do not have enough information to create new grades.');
            $logger->log('Checking if grade item already exists.');
            // Check if grade item exists the same way grade_update does.
            if (!self::get_grade_item($courseid, $itemtype, $itemmodule, $iteminstance, $itemnumber)) {
                $logger->log('No grade item available. Returning '. GRADE_UPDATE_FAILED. '.');
                return GRADE_UPDATE_FAILED;
            }
        }

        // Run the update grade function which creates / updates the grade.
        $result = grade_update($source, $courseid, $itemtype, $itemmodule,
                               $iteminstance, $itemnumber, $grades, $itemdetails);

        $logger->log('Executed grade_update API. Returned result is '.$result);
        if (!empty($itemdetails['categoryid']) && ($itemdetails['categoryid'] != 'null')) {
            // Optional.
            try {
                $gradeitem = new grade_item(array('idnumber' => $itemdetails['idnumber'], 'courseid' => $courseid));
                if (!empty($gradeitem->id)) {
                    // Change the category of the Grade we just updated/created.
                    $gradeitem->categoryid = (int)$itemdetails['categoryid'];
                    $gradeitem->update();
                    $logger->log("Changed category of a grade we just updated or created {$gradeitem->id}.");
                }
            } catch (Exception $e) {
                // Silence the exception.
                $logdata = 'Failed to change category of a grade we just updated or created.';
                $logdata .= 'idnumber = '. $itemdetails['idnumber'];
                $logger->log($logdata);
            }
        }

        return $result;
    }

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function update_grade_parameters() {
        $params = array();

        // Source.
        $desc = 'string $source source of the grade such as "mod/assignment"';
        $params['source'] = new external_value(PARAM_TEXT, $desc, VALUE_DEFAULT, 'mod/assignment');

        // Courseid.
        $desc = 'string $courseid id of course';
        $params['courseid'] = new external_value(PARAM_TEXT, $desc, VALUE_DEFAULT, 'NULL');

        // Item type.
        $desc = 'string $itemtype type of grade item - mod, block';
        $params['itemtype'] = new external_value(PARAM_TEXT, $desc, VALUE_DEFAULT, 'mod');

        // Item module.
        $desc = 'string $itemmodule more specific then $itemtype - assignment,'.
                ' forum, etc.; maybe NULL for some item types';
        $params['itemmodule'] = new external_value(PARAM_TEXT, $desc, VALUE_DEFAULT, 'assignment');

        // Item instance.
        $desc = 'ID of the item module';
        $params['iteminstance'] = new external_value(PARAM_TEXT, $desc, VALUE_DEFAULT, '0');

        // Item number.
        $desc = 'int $itemnumber most probably 0, modules can use other '.
                'numbers when having more than one grades for each user';
        $params['itemnumber'] = new external_value(PARAM_TEXT, $desc, VALUE_DEFAULT, '0');

        // Grades.
        $desc = 'mixed $grades grade (object, array) or several grades '.
                '(arrays of arrays or objects), NULL if updating grade_item definition only';
        $params['grades'] = new external_value(PARAM_TEXT, $desc, VALUE_DEFAULT, 'NULL');

        // Item details.
        $desc = 'mixed $itemdetails object or array describing the grading item, NULL if no change';
        $params['itemdetails'] = new external_value(PARAM_TEXT, $desc, VALUE_DEFAULT, 'NULL');

        return new external_function_parameters($params);
    }

    /**
     * Returns description of method result value.
     *
     * @return external_description
     */
    public static function update_grade_returns() {
        return new external_value(PARAM_TEXT, '0 for success anything else for failure');
    }

    // GET GRADE.
    /**
     * Allows external services to push grades into the course gradebook.
     *
     * @param string $source
     * @param string $courseid
     * @param string $itemtype
     * @param string $itemmodule
     * @param string $iteminstance
     * @param string $itemnumber
     * @param string $grades
     * @param string $itemdetails
     * @return mixed
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     */
    public static function get_grade($source = 'mhaairs', $courseid ='courseid', $itemtype = 'mod',
                                            $itemmodule = 'assignment', $iteminstance = '0', $itemnumber = '0',
                                            $grades = null, $itemdetails = null) {
        global $USER, $DB;

        // Gradebook sync must be enabled by admin in the block's site configuration.
        if (!$syncgrades = get_config('core', 'block_mhaairs_sync_gradebook')) {
            return GRADE_UPDATE_FAILED;
        }

        // Context validation.
        // OPTIONAL but in most web service it should be present.
        $context = context_user::instance($USER->id);
        self::validate_context($context);

        // Capability checking.
        // OPTIONAL but in most web service it should be present.
        require_capability('moodle/user:viewdetails', $context, null, true, 'cannotviewprofile');

        // Decode item details and check for problems.
        $itemdetails = self::decode_item_details($itemdetails, $grades);

        // Get the course.
        $course = self::get_course($courseid, $itemdetails);
        if ($course === false) {
            // No valid course specified.
            return GRADE_UPDATE_FAILED;
        }
        $courseid = $course->id;

        // Check if grade item exists the same way grade_update does.
        if (!self::get_grade_item($courseid, $itemtype, $itemmodule, $iteminstance, $itemnumber)) {
            $logger->log('No grade item available. Returning '. GRADE_UPDATE_FAILED. '.');
            return GRADE_UPDATE_FAILED;
        }

        return null;
    }

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function get_grade_parameters() {
        return self::update_grade_parameters();
    }

    /**
     * Returns description of method result value.
     *
     * @return external_description
     */
    public static function get_grade_returns() {
        return new external_single_structure(
            array(
                'items'  => new external_multiple_structure(
                    new external_single_structure(
                        array(
                            'activityid' => new external_value(
                                PARAM_ALPHANUM, 'The ID of the activity or "course" for the course grade item'),
                            'itemnumber'  => new external_value(PARAM_INT, 'Will be 0 unless the module has multiple grades'),
                            'scaleid' => new external_value(PARAM_INT, 'The ID of the custom scale or 0'),
                            'name' => new external_value(PARAM_RAW, 'The module name'),
                            'grademin' => new external_value(PARAM_FLOAT, 'Minimum grade'),
                            'grademax' => new external_value(PARAM_FLOAT, 'Maximum grade'),
                            'gradepass' => new external_value(PARAM_FLOAT, 'The passing grade threshold'),
                            'locked' => new external_value(PARAM_INT, '0 means not locked, > 1 is a date to lock until'),
                            'hidden' => new external_value(PARAM_INT, '0 means not hidden, > 1 is a date to hide until'),
                            'grades' => new external_multiple_structure(
                                new external_single_structure(
                                    array(
                                        'userid' => new external_value(
                                            PARAM_INT, 'Student ID'),
                                        'grade' => new external_value(
                                            PARAM_FLOAT, 'Student grade'),
                                    )
                                )
                            ),
                        )
                    )
                ),
            )
        );
    }

    // DEPRACATED: GRADEBOOKSERVICE.
    /**
     * Allows external services to push grades into the course gradebook.
     * Alias for {@link block_mhaairs_gradebookservice_external::update_grade()}.
     *
     * @return mixed
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     */
    public static function gradebookservice($source = 'mod/assignment', $courseid ='courseid', $itemtype = 'mod',
                                            $itemmodule = 'assignment', $iteminstance = '0', $itemnumber = '0',
                                            $grades = null, $itemdetails = null) {
        return self::update_grade(
            $source,
            $courseid,
            $itemtype,
            $itemmodule,
            $iteminstance,
            $itemnumber,
            $grades,
            $itemdetails
        );
    }

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function gradebookservice_parameters() {
        return self::update_grade_parameters();
    }

    /**
     * Returns description of method result value.
     *
     * @return external_description
     */
    public static function gradebookservice_returns() {
        return self::update_grade_returns();
    }

    // UTILITY.
    /**
     *
     */
    protected static function can_create_grade_item($itemdetails) {
        $fields = array('courseid', 'categoryid', 'itemname',
                        'itemtype', 'idnumber', 'gradetype',
                        'grademax', 'iteminfo');
        foreach ($fields as $field) {
            if (!array_key_exists($field, $itemdetails)) {
                return false;
            }
        }
        return true;
    }

    /**
     *
     */
    protected static function decode_item_details($itemdetails, $grades, $badchars = ";'-") {
        $itemdetails = json_decode(urldecode($itemdetails), true);
        if ($itemdetails != "null" && $itemdetails != null) {
            // Check type of each parameter.
            self::check_valid($itemdetails, 'categoryid', 'string', $badchars);
            self::check_valid($itemdetails, 'courseid', 'string');
            self::check_valid($itemdetails, 'identity_type', 'string');
            self::check_valid($itemdetails, 'itemname', 'string');
            self::check_valid($itemdetails, 'itemtype', 'string', $badchars);
            if (!is_numeric($itemdetails['idnumber']) && ($grades == "null" || $grades == null)) {
                throw new invalid_parameter_exception("Parameter idnumber is of incorrect type!");
            }
            self::check_valid($itemdetails, 'gradetype', 'int');
            self::check_valid($itemdetails, 'grademax', 'int');
            self::check_valid($itemdetails, 'needsupdate', 'int');

            return $itemdetails;
        }
        return null;
    }

    /**
     *
     */
    protected static function decode_grades($grades, $itemdetails, $badchars = ";'-") {
        $grades = json_decode(urldecode($grades), true);
        if (($grades != "null") && ($grades != null)) {
            if (is_array($grades)) {
                self::check_valid($grades, 'userid'  , 'string', $badchars);
                self::check_valid($grades, 'rawgrade', 'int');

                if (empty($itemdetails['identity_type']) || ($itemdetails['identity_type'] != 'lti')) {
                    // Map userID to numerical userID.
                    $userid = $DB->get_field('user', 'id', array('username' => $grades['userid']));
                    if ($userid !== false) {
                        $grades['userid'] = $userid;
                    }
                }
                return $grades;
            }
        }
        return null;
    }

    /**
     * Adds the id of the target catgory to the item details.
     * If the category does not exists it is created.
     * If the category exists any duplicates are deleted (using the locks).
     *
     * @param array $itemdetails
     * @param int $courseid
     * @return void
     */
    protected static function handle_grade_category(&$itemdetails, $courseid) {
        global $CFG;

        require_once($CFG->dirroot.'/blocks/mhaairs/lib/lock/abstractlock.php');

        $instance = new block_mhaairs_locinst();

        // We have to be carefull about MDL-37055 and make sure grade categories and grade items are in order.
        $category = null;

        // Fetch all grade category items that match teh target grade category by fullname.
        // If we have more than one then we need to delete the duplicates.
        $categories = grade_category::fetch_all(array('fullname' => $itemdetails['categoryid'],
                                                      'courseid' => $courseid));
        // If the category exists we use it.
        if (!empty($categories)) {
            // The first is our target category.
            $category = array_shift($categories);

            // We delete any duplicates.
            if (!empty($categories)) {
                if ($instance->lock()->locked()) {
                    // We have exclusive lock so let's do it.
                    try {
                        foreach ($categories as $cat) {
                            if ($cat->set_parent($category->id)) {
                                $cat->delete();
                            }
                        }
                    } catch (Exception $e) {
                        // If we fail there is not much else we can do here.
                    }
                }
            }
        }

        // If the category does not exist we create it.
        if ($category === null) {
            $gradeaggregation = get_config('core', 'grade_aggregation');
            if ($gradeaggregation === false) {
                $gradeaggregation = GRADE_AGGREGATE_WEIGHTED_MEAN2;
            }
            // Parent category is automatically added(created) during insert.
            $category = new grade_category(array('fullname'    => $itemdetails['categoryid'],
                                                 'courseid'    => $courseid,
                                                 'hidden'      => false,
                                                 'aggregation' => $gradeaggregation,
                                            ), false);
            $category->insert();
        }

        // Use the category ID we retrieved.
        $itemdetails['categoryid'] = $category->id;
    }

    /**
     * Checks the type validity of the specified param.
     *
     * @param  array $params
     * @param  string $name
     * @param  string $type
     * @param  null|string $badchars
     * @throws invalid_parameter_exception
     * @return bool
     */
    private static function check_valid($params, $name, $type, $badchars = null) {
        if (!isset($params[$name])) {
            return true;
        }
        $result = true;
        $value = $params[$name];
        if ($type == 'string') {
            $result = is_string($value);
            if ($result && ($badchars !== null)) {
                $result = (strpbrk($value, $badchars) === false);
            }
            $result = $result && ($value !== null);
        }
        if ($type == 'int') {
            $result = is_numeric($value) && ($value !== null);
        }

        if (!$result) {
            throw new invalid_parameter_exception("Parameter {$name} is of incorrect type!");
        }

        return $result;
    }

    /**
     * Returns course object by id or idnumber, or false if not found.
     *
     * @param mixed $courseid
     * @param bool $idonly
     * @return false|stdClass
     */
    private static function get_course($courseid, &$itemdetails = null) {
        global $DB;

        $course = false;
        $where = array();
        $params = array();

        // We must have course id.
        if (empty($courseid)) {
            return false;
        }

        // Do we need to look up the course only by internal id?
        $idonly = false;
        if (!empty($itemdetails['identity_type'])) {
            $idonlyoptions = array('internal', 'lti');
            $idonly = in_array($itemdetails['identity_type'], $idonlyoptions, true);
        }

        // If courseid is numeric we search by course id.
        if (is_numeric($courseid) and $courseid > 0) {
            $where[] = 'id = ?';
            $params[] = (int) $courseid;
        }

        // We search also by the course idnumber if required.
        if (!$idonly) {
            $where[] = 'idnumber = ?';
            $params[] = $courseid;
        }

        // Fetch the course record.
        if (!empty($where)) {
            $select = implode(' OR ', $where);
            $course = $DB->get_record_select('course', $select, $params, '*', IGNORE_MULTIPLE);
        }

        // Update course id in item details.
        if ($course and $itemdetails) {
            $itemsdetails['courseid'] = $course->id;
        }

        return $course;
    }

    /**
     * Get a grade item
     * @param  int $courseid        Course id
     * @param  string $itemtype     Item type
     * @param  string $itemmodule   Item module
     * @param  int $iteminstance    Item instance
     * @param  int $itemnumber      Item number
     * @return grade_item           A grade_item instance
     */
    private static function get_grade_item($courseid, $itemtype, $itemmodule = null, $iteminstance = null, $itemnumber = null) {
        $gradeiteminstance = null;
        if ($itemtype == 'course') {
            $gradeiteminstance = grade_item::fetch(array('courseid' => $courseid, 'itemtype' => $itemtype));
        } else {
            $gradeiteminstance = grade_item::fetch(
                array('courseid' => $courseid, 'itemtype' => $itemtype,
                    'itemmodule' => $itemmodule, 'iteminstance' => $iteminstance, 'itemnumber' => $itemnumber));
        }
        return $gradeiteminstance;
    }

}

/**
 * Block mhaairs util web service.
 *
 * @package     block_mhaairs
 * @copyright   2014 Itamar Tzadok <itamar@substantialmethods.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_mhaairs_utilservice_external extends external_api {

    /**
     * Allows external applications to retrieve MHUserInfo by token.
     *
     * @param string $token
     * @return MHUserInfo object
     */
    public static function get_user_info($token, $identitytype = null) {
        // Require secured connection.
        if ($error = self::require_ssl()) {
            $userinfo = new MHUserInfo(MHUserInfo::FAILURE);
            $userinfo->message = $error;

            return $userinfo;
        }

        // Get the configured secret.
        $secret = self::get_secret();

        // Get the user info.
        $result = MHUtil::get_user_info($token, $secret, $identitytype);

        return $result;
    }

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function get_user_info_parameters() {
        $params = array();

        // Token.
        $desc = 'string $token Token';
        $params['token'] = new external_value(PARAM_TEXT, $desc);

        // Identity type.
        $desc = 'string $identitytype Indicates the user search var; if \'internal\' the user is searched by id;'.
                ' if anything else or empty, the user is searched by username.';
        $params['identitytype'] = new external_value(PARAM_TEXT, $desc, VALUE_DEFAULT, null);

        return new external_function_parameters($params);
    }

    /**
     * Returns description of method result value.
     *
     * @return external_description
     */
    public static function get_user_info_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_INT, 'Result status: 0|1 (SUCCESS|FAILURE).'),
                'user' => new external_single_structure(
                    array(
                    )
                ),
                'courses' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                        )
                    )
                ),
                'message' => new external_value(PARAM_TEXT, 'Error message on failure; empty on success.'),
            )
        );
    }

    /**
     * Allows external services to push grades into the course gradebook.
     * Alias for {@link block_mhaairs_gradebookservice_external::update_grade()}.
     *
     * @return mixed
     * @throws invalid_parameter_exception
     * @throws moodle_exception
     */
    public static function validate_login($token, $username, $password) {
        // Require secured connection.
        if ($error = self::require_ssl()) {
            $authresult = new MHAuthenticationResult(
                MHAuthenticationResult::FAILURE,
                '',
                $error
            );

            return $authresult;
        }

        // Get the configured secret.
        $secret = self::get_secret();

        // Validate login.
        $result = MHUtil::validate_login($token, $secret, $username, $password);

        return $result;
    }

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function validate_login_parameters() {
        $params = array();

        // Token.
        $desc = 'string $token Token';
        $params['token'] = new external_value(PARAM_TEXT, $desc);

        // Username.
        $desc = 'string $username Username';
        $params['username'] = new external_value(PARAM_TEXT, $desc);

        // Password.
        $desc = 'string $password Password';
        $params['password'] = new external_value(PARAM_TEXT, $desc);

        return new external_function_parameters($params);
    }

    /**
     * Returns description of method result value.
     *
     * @return external_description
     */
    public static function validate_login_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_INT, 'Result status: 0|1 (SUCCESS|FAILURE).'),
                'effectiveuserid' => new external_value(PARAM_TEXT, 'The validated user username.'),
                'redirecturl' => new external_value(PARAM_TEXT, 'Error message on failure; empty on success.'),
                'attributes' => new external_multiple_structure(
                    new external_single_structure(
                        array(
                        )
                    )
                ),
                'message' => new external_value(PARAM_TEXT, 'Error message on failure; empty on success.'),
            )
        );
    }

    /**
     * Checks if the plugin is configured to require ssl connection and verifies https connection
     * if needed. Returns null on success, and error message on failure (http access when ssl required).
     *
     * @return string|null
     */
    private static function require_ssl() {
        $notsecured = 'error: connection must be secured with SSL';
        $sslonly = get_config('core', 'block_mhaairs_sslonly');

        // Required only if enabled by admin.
        if (!$sslonly) {
            return null;
        }

        // No https, not secured.
        if (!isset($_SERVER['HTTPS'])) {
            return $notsecured;
        }

        $secured = filter_var($_SERVER['HTTPS'], FILTER_SANITIZE_STRING);
        if (empty($secured)) {
            return $notsecured;
        }

        return null;
    }

    /**
     * Returns the plugin configured shared secret.
     *
     * @return string
     */
    private static function get_secret() {
        if ($secret = get_config('core', 'block_mhaairs_shared_secret')) {
            return $secret;
        }

        return '';
    }

}
