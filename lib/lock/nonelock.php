<?php
/**
 * Dummy locking class when no locking is selected.
 *
 * @package    block
 * @subpackage mhaairs
 * @copyright  2014 Moodlerooms inc.
 * @author     Darko Miletic <dmiletic@moodlerooms.com>
 */

defined('MOODLE_INTERNAL') or die();

global $CFG;
require_once($CFG->dirroot.'/blocks/mhaairs/lib/lock/abstractlock.php');

class block_mhaairs_nonelock extends block_mhaairs_lock_abstract {

    /**
     * @return bool
     */
    public function locked() {
        return true;
    }

    /**
     * @param bool $force
     * @return bool
     */
    public function unlock($force = false) {
        return true;
    }

    /**
     * @return bool
     */
    public function lock() {
        return true;
    }
}
