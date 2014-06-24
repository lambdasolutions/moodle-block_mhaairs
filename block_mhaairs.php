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
 * This file contains the mhaairs block class.
 *
 * @package     block_mhaairs
 * @copyright   2014 Itamar Tzadok <itamar@substantialmethods.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') or die();
global $CFG;
require_once($CFG->libdir.'/blocklib.php');
require_once($CFG->dirroot.'/blocks/mhaairs/lib.php');
require_once($CFG->dirroot.'/blocks/mhaairs/block_mhaairs_util.php');

/**
 * Class for the mhaairs-moodle integration.
 *
 * @package     block_mhaairs
 * @copyright   2014 Itamar Tzadok <itamar@substantialmethods.com>
 * @copyright   2013-2014 Moodlerooms inc.
 * @author      Teresa Hardy <thardy@moodlerooms.com>
 * @author      Darko Miletic <dmiletic@moodlerooms.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_mhaairs extends block_base {
    const LINK_SERVICES = 'services';
    const LINK_HELP     = 'help';
    /**
     * Initializes block title as the plugin name.
     *
     * @return void
     */
    public function init() {
        $this->title = get_string('pluginname', __CLASS__);
    }
    /**
     * Returns true to indicate that this block has a settings.php
     * file.
     *
     * @return bool Always true.
     */
    public function has_config() {
        return true;
    }
    /**
     * Returns the block display content.
     *
     * @return stdClass The block content object.
     */
    public function get_content() {
        global $CFG, $COURSE;
        if ($this->content !== null) {
            return $this->content;
        }
        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';
        // Must be logged in to see the block.
        if (!isloggedin()) {
            return $this->content;
        }
        // Must be in a course context to see the block.
        $courselevel = ($this->page->context->contextlevel == CONTEXT_COURSE);
        $courseinstance = ($this->page->context->instanceid == $this->page->course->id);
        if (!$courselevel or !$courseinstance) {
            return $this->content;
        }
        $context = $this->page->context;
        // Prepare configuration warning.
        $configwarning = html_writer::tag(
            'div',
             get_string('blocknotconfig', __CLASS__),
             array('class' => 'block_mhaairs_warning')
         );
        // Weather current user can see the block with incomplete config.
        $canmanipulateblock = has_capability('block/mhaairs:addinstance', $context);
        // Must have customer number and secret configured.
        if (empty($CFG->block_mhaairs_customer_number) || empty($CFG->block_mhaairs_shared_secret)) {
            if ($canmanipulateblock) {
                $this->content->text = $configwarning;
            }
            return $this->content;
        }
        // MAIN CONTENT
        // Add content or config warning to main content.
        if ($maincontent = $this->get_content_text()) {
            $this->content->text = $maincontent;
        } else {
            if ($canmanipulateblock) {
                $this->content->text = $configwarning;
            }
            return $this->content;
        }
        // FOOTER
        // Add help links to footer if applicable.
        if ($helplinks = $this->get_help_links()) {
            foreach ($helplinks as $hlink) {
                $hlink = html_writer::tag('div', $hlink, array('class' => 'helplink'));
                $this->content->footer .= $hlink;
            }
        }
        return $this->content;
    }
    /**
     * Returns the main part of the block display content.
     * In this version this contains service links.
     *
     * @return string HTML fragment.
     */
    protected function get_content_text() {
        $services = $this->get_services();
        if ($services === false) {
            return null;
        }
        $blocklinks = '';
        $imagealt = get_string('imagealt');
        $targetw = array('target' => '_blank');
        $course = $this->page->course;
        foreach ($services as $aserv) {
            // Icon.
            $iconparams = array(
                'src' => $aserv['ServiceIconUrl'],
                'class' => 'serviceicon',
                'alt' => $imagealt
            );
            $icon = html_writer::tag('img', '', $iconparams);
            // Url.
            $urlparams = array(
                'url' => mh_hex_encode($aserv['ServiceUrl']),
                'id'  => mh_hex_encode($aserv['ServiceID']),
                'cid' => $course->id
            );
            $url = new moodle_url('/blocks/mhaairs/redirect.php', $urlparams);
            // Link.
            $link = html_writer::link($url, $aserv['ServiceName'], $targetw);

            $blocklinks .= html_writer::tag('div', $icon. $link, array('class' => 'servicelink'));
        }
        return $blocklinks;
    }
    /**
     * Returns a list of help links the user is permitted to see.
     *
     * @return array Array of HTML link fragments.
     */
    protected function get_help_links() {
        // Make sure we are in the right context.
        $courselevel = ($this->page->context->contextlevel == CONTEXT_COURSE);
        $courseinstance = ($this->page->context->instanceid == $this->page->course->id);
        if (!$courselevel or !$courseinstance) {
            return array();
        }
        // Get the Help urls if enabled.
        $helpurls = block_mhaairs_getlinks(self::LINK_HELP);
        if ($helpurls === false) {
            return array();
        }
        $helplinks = array();
        $context = $this->page->context;
        $targetw = array('target' => '_blank');
        // Admin help link.
        $adminhelp = has_capability('block/mhaairs:viewadmindoc', $context);
        if ($adminhelp) {
            $adminhelplink = html_writer::link(
                $helpurls['AdminHelpUrl'],
                get_string('adminhelplabel', __CLASS__),
                $targetw
            );
            $helplinks[] = $adminhelplink;
        }
        // Teacher help link.
        $teacherhelp = has_capability('block/mhaairs:viewteacherdoc', $context);
        if ($teacherhelp) {
            $instrhelplink = html_writer::link(
                $helpurls['InstructorHelpUrl'],
                get_string('instrhelplabel', __CLASS__),
                $targetw);
            $helplinks[] = $instrhelplink;
        }
        return $helplinks;
    }
    /**
     * Returns list of services to display in the block content,
     * or false if no services are available.
     * For each services, returns:
     *     ServiceID        string id
     *  ServiceIconUrl    string url of an image
     *  ServiceName        string name
     *  ServiceUrl        string url
     *
     * @return array|false Array of arrays.
     */
    public function get_services() {
        global $CFG;
        $result = false;
        // Some services must be configured by admin for display.
        if (empty($CFG->block_mhaairs_display_services)) {
            return $result;
        }
        // Get the data of all available services.
        $services = block_mhaairs_getlinks(self::LINK_SERVICES);
        if ($services === false) {
            return $result;
        }
        // Compile the list of services to display.
        // If the block has been configured the instructor's selection may be
        // cached in which case the list to display would be the intersection
        // between the list from the site configuration and the list from
        // the block configuration.
        $permittedlist = explode(',', $CFG->block_mhaairs_display_services);
        asort($permittedlist);
        $finallist = $permittedlist;
        if (!empty($this->config)) {
            $localelements = array_keys(get_object_vars($this->config), true);
            if (empty($localelements)) {
                return $result;
            }
            $finallist = array_intersect($permittedlist, $localelements);
        }
        natcasesort($finallist);
        // Collate service data for displayed services.
        $result = array();
        foreach ($finallist as $serviceid) {
            foreach ($services['Tools'] as $vset) {
                if ($vset['ServiceID'] == $serviceid) {
                    $result[] = $vset;
                }
            }
        }
        return $result;
    }
}
