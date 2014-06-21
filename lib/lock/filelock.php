<?php
/**
 * File locking classes
 * Note: This will not work on network shared file systems like NFS
 *
 * @package    block
 * @subpackage mhaairs
 * @copyright  2014 Moodlerooms inc.
 * @author     Darko Miletic <dmiletic@moodlerooms.com>
 */

defined('MOODLE_INTERNAL') or die();

global $CFG;
require_once($CFG->dirroot.'/blocks/mhaairs/lib/lock/abstractlock.php');

class block_mhaairs_filelock extends block_mhaairs_lock_abstract {
    const LOCKFILE = 'mhaairslock';

    /**
     * @var null|resource
     */
    private $handle = null;
    /**
     * @var null|string
     */
    private $filepath = null;

    /**
     * @param bool $lock
     */
    public function __construct($lock = true) {
        if ($lock) {
            $this->lock();
        }
    }

    public function __destruct() {
        $this->unlock();
    }

    /**
     * @param bool $force
     * @return bool
     */
    public function unlock($force = false) {
        if ($force || $this->locked()) {
            if (flock($this->handle, LOCK_UN | LOCK_NB)) {
                fclose($this->handle);
                $this->handle = null;
                $this->filepath = null;
                return true;
            }
        }
        return false;
    }

    /**
     * @return bool
     */
    public function lock() {
        if ($this->locked()) {
            return false;
        }

        $tempdir = get_config('core', 'tempdir');
        $filepath = $tempdir.DIRECTORY_SEPARATOR.self::LOCKFILE;
        $handle = fopen($filepath, 'w+');
        $result = ($handle !== false);
        if ($result) {
            $result = flock($handle, LOCK_EX | LOCK_NB);
            if ($result) {
                $this->handle = $handle;
                $this->filepath = $filepath;
            } else {
                fclose($handle);
            }
        }
        return $result;
    }

    /**
     * @return bool
     */
    public function locked() {
        return ($this->handle !== null);
    }
}
