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
 * This file contains the mhaairs block admin settings definition.
 *
 * @package     block_mhaairs
 * @copyright   2014 Itamar Tzadok <itamar@substantialmethods.com>
 * @copyright   2013 Moodlerooms inc.
 * @author      Teresa Hardy <thardy@moodlerooms.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if (!$ADMIN->fulltree) {
    return;
}

require_once($CFG->dirroot.'/blocks/mhaairs/settingslib.php');

$settings->add(new admin_setting_configcheckbox(
        'block_mhaairs_sslonly',
        new lang_string('sslonlylabel', 'block_mhaairs'),
        '', 0
));

$settings->add(new admin_setting_configtext(
        'block_mhaairs_customer_number',
        new lang_string('customernumberlabel', 'block_mhaairs'),
        '',
        '',
        PARAM_ALPHANUMEXT
));

$settings->add(new admin_setting_configtext(
        'block_mhaairs_shared_secret',
        new lang_string('secretlabel', 'block_mhaairs'),
        '',
        '',
        PARAM_ALPHANUMEXT
));


$adminurl = new moodle_url('/admin/settings.php');
if ($PAGE->url->compare($adminurl, URL_MATCH_BASE)) {
    $settings->add(new admin_setting_configmulticheckbox_mhaairs (
            'block_mhaairs_display_services',
            new lang_string('services_displaylabel', 'block_mhaairs'),
            new lang_string('services_desc', 'block_mhaairs')
    ));
}

$settings->add(new admin_setting_configcheckbox(
        'block_mhaairs_display_helplinks',
        new lang_string('mhaairs_displayhelp', 'block_mhaairs'),
        new lang_string('mhaairs_displayhelpdesc', 'block_mhaairs'),
        1
));

$settings->add(new admin_setting_configcheckbox(
        'block_mhaairs_sync_gradebook',
        new lang_string('mhaairs_syncgradebook', 'block_mhaairs'),
        new lang_string('mhaairs_syncgradebookdesc', 'block_mhaairs'),
        1
));

$settings->add(new admin_setting_configselect(
        'block_mhaairs_locktype',
        new lang_string('mhaairs_locktype', 'block_mhaairs'),
        new lang_string('mhaairs_locktypedesc', 'block_mhaairs'),
        'nonelock',
        array('nonelock' => 'No locking', 'filelock' => 'File locking', 'redislock' => 'Redis locking')
));
