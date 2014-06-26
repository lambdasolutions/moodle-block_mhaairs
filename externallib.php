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
require_once($CFG->libdir . "/externallib.php");
require_once($CFG->libdir . "/gradelib.php");

/**
 * Block MHAAIRS AAIRS Integrated Web Services implementation.
 *
 * @package     block_mhaairs
 * @copyright   2014 Itamar Tzadok <itamar@substantialmethods.com>
 * @copyright   2013-2014 Moodlerooms inc.
 * @author      Teresa Hardy <thardy@moodlerooms.com>
 * @author      Darko MIletic <dmiletic@moodlerooms.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_mhaairs_gradebookservice_external extends external_api {

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
    public static function gradebookservice($source = 'mod/assignment', $courseid ='courseid', $itemtype = 'mod',
                                            $itemmodule = 'assignment', $iteminstance = '0', $itemnumber = '0',
                                            $grades = null, $itemdetails = null) {
        global $USER, $DB;

        // Gradebook sync must be enabled by admin in the block's site configuration.
        $syncgrades = get_config('core', 'block_mhaairs_sync_gradebook');
        if (!$syncgrades) {
            return GRADE_UPDATE_FAILED;
        }

        $badchars = ";'-";

        // Context validation.
        // OPTIONAL but in most web service it should be present.
        $context = context_user::instance($USER->id);
        self::validate_context($context);

        // Capability checking.
        // OPTIONAL but in most web service it should be present.
        require_capability('moodle/user:viewdetails', $context, null, true, 'cannotviewprofile');

        // Decode item details and check for problems.
        $itemdetails = json_decode(urldecode($itemdetails), true);

        $cancreategradeitem = false;

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

            $idonly = in_array($itemdetails['identity_type'], array('internal', 'lti'), true);
            $course = self::get_course($courseid, $idonly);
            if ($course === false) {
                // We got invalid course id!
                return GRADE_UPDATE_FAILED;
            }
            $courseid = $course->id;
            $itemdetails['courseid'] = $course->id;

            if (!empty($itemdetails['categoryid']) && $itemdetails['categoryid'] != 'null') {
                self::handle_grade_category($itemdetails, $courseid);
            }

            // Can we fully create grade_item with available data if needed?
            $fields = array('courseid', 'categoryid', 'itemname',
                            'itemtype', 'idnumber', 'gradetype',
                            'grademax', 'iteminfo');
            $cancreategradeitem = true;
            foreach ($fields as $field) {
                if (!array_key_exists($field, $itemdetails)) {
                    $cancreategradeitem = false;
                    break;
                }
            }

        } else {
            $itemdetails = null;
            $course = self::get_course($courseid);
            if ($course === false) {
                // No valid course specified.
                return GRADE_UPDATE_FAILED;
            }
        }

        if (($grades != "null") && ($grades != null)) {
            $grades = json_decode(urldecode($grades), true);
            if (is_array($grades)) {
                self::check_valid($grades, 'userid'  , 'string', $badchars);
                self::check_valid($grades, 'rawgrade', 'int');

                if (empty($itemdetails['identity_type']) || ($itemdetails['identity_type'] != 'lti')) {
                    // Map userID to numerical userID.
                    $user = $DB->get_field('user', 'id', array('username' => $grades['userid']));
                    if ($user !== false) {
                        $grades['userid'] = $user;
                    }
                }
            } else {
                $grades = null;
            }
        } else {
            $grades = null;
        }

        if (!$cancreategradeitem) {
            // Check if grade item exists the same way grade_update does.
            $grparams = compact('courseid', 'itemtype', 'itemmodule',
                                'iteminstance', 'itemnumber');
            $gritems = grade_item::fetch_all($grparams);
            if ($gritems === false) {
                return GRADE_UPDATE_FAILED;
            }
        }

        // Run the update grade function which creates / updates the grade.
        $result = grade_update($source, $courseid, $itemtype, $itemmodule,
                               $iteminstance, $itemnumber, $grades, $itemdetails);

        if (!empty($itemdetails['categoryid']) && ($itemdetails['categoryid'] != 'null')) {
            // Optional.
            try {
                $gradeitem = new grade_item(array('idnumber' => $itemdetails['idnumber'], 'courseid' => $courseid));
                if (!empty($gradeitem->id)) {
                    // Change the category of the Grade we just updated/created.
                    $gradeitem->categoryid = (int)$itemdetails['categoryid'];
                    $gradeitem->update();
                }
            } catch (Exception $e) {
                // Silence the exception.
            }
        }

        return $result;
    }

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function gradebookservice_parameters() {
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
    public static function gradebookservice_returns() {
        return new external_value(PARAM_TEXT, '0 for success anything else for failure');
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
    protected function handle_grade_category(&$itemdetails, $courseid) {
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
    private static function get_course($courseid, $idonly = false) {
        global $DB;

        $course = false;
        $select = '';
        $params = array();

        // If courseid is numeric we search by course id.
        $numericid = is_numeric($courseid) ? $courseid : 0;
        if ($numericid > 0) {
            $select = 'id = ?';
            $params[] = $numericid;
        }

        // We search also by the course idnumber if required.
        if (!$idonly and $courseid) {
            if (!empty($select)) {
                $select .= ' OR ';
            }
            $select .= 'idnumber = ?';
            $params[] = $courseid;
        }

        // Fetch the course record.
        if (!empty($select)) {
            $course = $DB->get_record_select('course', $select, $params, '*', IGNORE_MULTIPLE);
        }
        return $course;
    }

}
