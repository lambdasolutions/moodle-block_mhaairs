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
 * @package     block_mhaairs
 * @copyright   2013 Moodlerooms inc.
 * @author      Teresa Hardy <thardy@moodlerooms.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// We defined the web service functions to install.
$functions = array(
        'block_mhaairs_gradebookservice' => array(
                'classname'   => 'block_mhaairs_gradebookservice_external',
                'methodname'  => 'gradebookservice',
                'classpath'   => 'blocks/mhaairs/externallib.php',
                'description' => 'Runs the grade_update() function',
                'type'        => 'read',
                'testclientpath' => 'blocks/mhaairs/testclient_forms.php'
        )
);

// We define the services to install as pre-build services. A pre-build service is not editable by administrator.
$services = array(
        'MHAAIRS Gradebook Service' => array(
                'functions'         => array ('block_mhaairs_gradebookservice'),
                'restrictedusers'   => 0,
                'enabled'           => 0
        )
);
