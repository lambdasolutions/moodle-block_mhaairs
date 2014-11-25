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
 * Webservice client tester for MHAAIRS Gradebook Integration.
 *
 * @package     block_mhaairs
 * @copyright   2014 Itamar Tzadok <itamar@substantialmethods.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require('../../../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once("$CFG->libdir/externallib.php");
require_once("$CFG->dirroot/blocks/mhaairs/tests/client/testclient_forms.php");

$function = optional_param('function', 'block_mhaairs_gradebookservice', PARAM_PLUGIN);
$protocol = optional_param('protocol', 'rest', PARAM_ALPHA);
$authmethod = optional_param('authmethod', '', PARAM_ALPHA);

$PAGE->set_url('/blocks/mhaairs/tests/client/testclient.php');
$PAGE->navbar->ignore_active(true);
$PAGE->navbar->add(get_string('administrationsite'));
$PAGE->navbar->add(get_string('pluginname', 'block_mhaairs'));
$PAGE->navbar->add(get_string('testclient', 'webservice'),
        new moodle_url('/blocks/mhaairs/tests/client/testclient.php'));
if (!empty($function)) {
    $PAGE->navbar->add($function);
}

admin_externalpage_setup('testclient');

$class = $function.'_test_form';

$mform = new $class;
$mform->set_data(array('function' => $function, 'protocol' => $protocol));

if ($mform->is_cancelled()) {
    redirect('testclient.php');

} else if ($data = $mform->get_data()) {
    $functioninfo = external_function_info($function);

    // First load lib of selected protocol.
    require_once("$CFG->dirroot/webservice/$protocol/locallib.php");

    $testclientclass = "webservice_{$protocol}_test_client";
    if (!class_exists($testclientclass)) {
        throw new coding_exception('Missing WS test class in protocol '.$protocol);
    }
    $testclient = new $testclientclass();

    // Server url.
    $server = 'simpleserver.php';
    $requestparams = array();

    if ($authmethod == 'simple') {
        $requestparams['wsusername'] = urlencode($data->wsusername);
        $requestparams['wspassword'] = urlencode($data->wspassword);
    } else if ($authmethod == 'token') {
        $server = 'server.php';
        $requestparams['wstoken'] = urlencode($data->token);
    }
    if (!empty($data->moodlewsrestformat)) {
        $requestparams['moodlewsrestformat'] = $data->moodlewsrestformat;
    }
    $serverurl = new \moodle_url("/webservice/$protocol/$server", $requestparams);

    // Now get the function parameters.
    $params = $mform->get_params();

    // Now test the parameters, this also fixes PHP data types.
    $params = external_api::validate_parameters($functioninfo->parameters_desc, $params);
    $fullurl = new \moodle_url($serverurl, $params);

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('pluginname', 'webservice_'.$protocol).': '.$function);

    // Display the url.
    echo html_writer::tag('h3', 'URL');
    echo $OUTPUT->box_start();
    echo $fullurl->out(false);
    echo $OUTPUT->box_end();

    // Display the result.
    echo html_writer::tag('h3', 'Result');
    echo $OUTPUT->box_start();

    try {
        $response = $testclient->simpletest($serverurl->out(false), $function, $params);
        echo str_replace("\n", '<br />', s(var_export($response, true)));
    } catch (Exception $ex) {
        // TODO: handle exceptions and faults without exposing of the sensitive information such as debug traces!
        echo str_replace("\n", '<br />', s($ex));
    }

    echo $OUTPUT->box_end();
    $mform->display();
    echo $OUTPUT->footer();
    die;

} else {
    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('pluginname', 'webservice_'.$protocol).': '.$function);
    $mform->display();
    echo $OUTPUT->footer();
    die;
}
