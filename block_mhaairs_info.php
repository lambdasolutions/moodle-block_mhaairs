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


require_once('../../config.php');
global $CFG;
require_once($CFG->libdir .'/accesslib.php');
require_once($CFG->libdir .'/datalib.php');
require_once($CFG->libdir .'/moodlelib.php');
require_once($CFG->dirroot.'/blocks/mhaairs/block_mhaairs_util.php');

global $CFG, $COURSE, $USER, $_SERVER;

$block_request_base = "/blocks/mhaairs/";

$test_user_id = "moodleinstructor";
$test_course_id = "testcourse123";
$test_time_stamp = mh_get_time_stamp();

echo "<p>time stamp:<b>".$test_time_stamp."</b></p>";
echo "<p>test user id:<b>".$test_user_id."</b></p>";
echo "<p>test course id:<b>".$test_course_id."</b></p>";

/**/
$customer = $CFG->block_mhaairs_customer_number;
$shared_secret = $CFG->block_mhaairs_shared_secret;
$base = $CFG->block_mhaairs_base_address;
$request_token = mh_create_token($test_user_id);
$encoded_request_token = mh_encode_token2($request_token, $shared_secret);
//echo "<p>request token:<b>".$request_token."</b></p>";
//echo "<p>encoded request token:<b>".$encoded_request_token."</b></p>";
echo "<p>the token is valid:<b>".(mh_is_token_valid($encoded_request_token, $shared_secret)?"true":"false")."</b></p>";

$get_user_info_url = $block_request_base."block_mhaairs_action.php?action=GetUserInfo&token=".$encoded_request_token;

"<p>encoded request token:<b>".$encoded_request_token."</b></p>";
echo "<a href='".$get_user_info_url."' target='blank'>get user info</a>";


//phpinfo(INFO_MODULES);
