<?php

require_once('../../config.php');
global $CFG, $USER;
require_once($CFG->dirroot.'/blocks/mhaairs/block_mhaairs_util.php');

/*
 * We can not use PARAM_INT for filtering since it filters values up to PHP integer maximum,
 * which can be less than database coulmn maximum
 * for example on Linux complied PHP 32-bit signed maxint is 2147483647
 * on Linux x64 compiled PHP 64-bit signed maxint is 9223372036854775807
 * on Windows both offical PHP 32-bit and unofficial 64-bit have maxint of 2147483647
 * in case of mysql Moodle uses by default signed BIGINT for all id columns which has maxint of 9223372036854775807
 * 64bit version
 *
 */

$courseid = required_param('cid', PARAM_ALPHANUM);
if (!is_numeric($courseid)) {
    print_error('invalidaccessparameter');
}
require_login($courseid);
global $COURSE;

$url        = required_param('url', PARAM_ALPHANUM);
$service_id = required_param('id' , PARAM_ALPHANUM);
$url        = mh_hex_decode($url);
$service_id = mh_hex_decode($service_id);
$course     = $COURSE;

$courseid = empty($course->idnumber) ? $course->id : $course->idnumber;
$context = context_course::instance($course->id);
$rolename = null;
if ($roles = get_user_roles($context, $USER->id)) {
    foreach ($roles as $role) {
        $rolename = empty($role->name) ? $role->shortname : $role->name;
        if ($rolename == 'teacher' || $rolename == 'editingteacher') {
            $rolename = 'instructor';
            break;
        }
    }
    if ($rolename != null && $rolename != 'instructor') {
        $rolename = 'student';
    }
}
$token = mh_create_token2($CFG->block_mhaairs_customer_number,
                            $USER->username,
                            urlencode($USER->firstname.' '.$USER->lastname),
                            $courseid,
                            $course->id,
                            $service_id,
                            $rolename,
                            urlencode($course->shortname));
$encoded_token = mh_encode_token2($token, $CFG->block_mhaairs_shared_secret);

$url = new moodle_url($url, array('token' => $encoded_token));
redirect($url);
