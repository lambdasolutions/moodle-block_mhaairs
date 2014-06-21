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
 * Block MHAAIRS Improved
 *
 * @package    block
 * @subpackage mhaairs
 * @copyright  2013-2014 Moodlerooms inc.
 * @author     Teresa Hardy <thardy@moodlerooms.com>
 * @author     Darko Miletic <dmiletic@moodlerooms.com>
 */

defined('MOODLE_INTERNAL') or die();

class block_mhaairs_edit_form extends block_edit_form {

    protected function specific_definition($mform) {
        global $CFG;

        // Section header title according to language file.
        $mform->addElement('header',
                           'configheader',
                           get_string('availableservices', 'block_mhaairs'));

        if (empty($CFG->block_mhaairs_display_services)) {
            $content = html_writer::tag('div',
                                        get_string('noservicesmsg', 'block_mhaairs'),
                                        array('class' => 'block_mhaairs_warning notifyproblem'));
            $mform->addElement('html', $content);
            return;
        }

        $first = true;
        $services_arr = array_map('trim', explode(',', $CFG->block_mhaairs_display_services));
        natcasesort($services_arr);
        $prelabel = get_string('edit_prelabel', 'block_mhaairs');
        foreach ($services_arr as $service) {
            $settingname = "config_$service";
            $mform->addElement('advcheckbox',
                               $settingname,
                               $prelabel,
                               "&nbsp;$service");
            $mform->setDefault($settingname, 1);
            if ($first) {
                $prelabel = '&nbsp;';
                $first = false;
            }
        }
    }

}
