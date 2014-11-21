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
 * This file contains utility functions for the mhaairs-moodle integration.
 *
 * @package     block_mhaairs
 * @copyright   2014 Itamar Tzadok <itamar@substantialmethods.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('TOKEN_VALIDITY_INTERVAL', 300);

/**
 * Class for the mhaairs util api.
 *
 * @package     block_mhaairs
 * @copyright   2014 Itamar Tzadok <itamar@substantialmethods.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class MHUtil {
    /**
     * Returns formatted GMT/UTC data/time.
     *
     * @return string
     */
    public static function get_time_stamp() {
        return gmdate("Y-m-d\TH:i:sP");
    }

    /**
     * Returns a token string with of user id, formatted time and course id (optional).
     *
     * @param int $userid
     * @param int $courseid
     * @return string
     */
    public static function create_token($userid, $courseid = 0) {
        $result = 'userid='.$userid.';time='. self::get_time_stamp();
        if ($courseid) {
            $result = 'courseid='.$courseid.';'.$result;
        }
        return $result;
    }

    /**
     * Returns a token string consisting of key=value pairs of customer number, user id
     * and username and time, as well as additional optional parameters.
     *
     * @param string $customer
     * @param int $userid
     * @param string $username
     * @param string $courseid
     * @param string $courseinternalid
     * @param string $linktype
     * @param string $rolename
     * @param string $coursename
     * @return string
     */
    public static function create_token2($customer, $userid, $username, $courseid = null,
                $courseinternalid = null, $linktype = null, $rolename = null, $coursename = null) {
        $parameters = array('customer' => $customer,
                            'userid'   => $userid,
                            'username' => $username,
                            'time'     => self::get_time_stamp());

        if (!empty($courseid)) {
            $parameters['courseid'] = $courseid;
        }
        if (!empty($courseinternalid)) {
            $parameters['courseinternalid'] = $courseinternalid;
        }
        if (!empty($linktype)) {
            $parameters['linktype'] = $linktype;
        }

        if (!empty($rolename)) {
            $parameters['role'] = $rolename;
        }

        if (!empty($coursename)) {
            $parameters['coursename'] = $coursename;
        }

        $result = '';
        foreach ($parameters as $name => $value) {
            if (!empty($result)) {
                $result .= '&';
            }
            $result .= "$name=$value";
        }

        return $result;
    }

    /**
     *
     */
    public static function encode_token($token, $secret, $alg = 'md5') {
        return self::hex_encode(''.md5($token.$secret).';'.$token);
    }

    /**
     *
     */
    public static function encode_token2($token, $secret, $alg = 'md5') {
        return self::hex_encode(''.md5($token.$secret).';'.$token);
    }

    /**
     *
     */
    public static function get_token($token) {
        try {
            $pos = strpos($token, ';');
            return substr($token, $pos + 1, strlen($token) - $pos);
        } catch (Exception $e) {
            return '';
        }
    }

    /**
     * Returns the hash part of the token.
     *
     * @return string
     */
    public static function get_hash($token) {
        try {
            $pos = strpos($token, ';');
            return substr($token, 0, $pos);
        } catch (Exception $e) {
            return '';
        }
    }

    /**
     * Returns the token value for the given name.
     *
     * @return string
     */
    public static function get_token_value($token, $name) {
        try {
            $parts = explode(';', $token);
            foreach ($parts as $part) {
                $pair = explode('=', $part);
                if (count($pair) > 0) {
                    if (0 === strcasecmp($pair[0], $name)) {
                        return $pair[1];
                    }
                }
            }
        } catch (Exception $e) {
            // Ignore.
        }
        return false;
    }

    /**
     * Returns true if the given token is valid, and false otherwise.
     *
     * @return bool
     */
    public static function is_token_valid($tokentext, $secret, $delay = 25200, $alg = 'md5', &$trace = '') {
        $trace = $trace.";token validation";
        try {
            $decodedtoken = self::hex_decode($tokentext);
            $trace = $trace.";decoded_token=".$decodedtoken;
            $token = self::get_token($decodedtoken);
            $hash = self::get_hash($decodedtoken);
            $trace = $trace.";hash=".$hash;
            $truehash = md5($token.$secret);
            if ($truehash === $hash) {
                $trace = $trace."the hash is good;";
                $tokentimetext = self::get_token_value($decodedtoken, "time");
                $tokentime = strtotime($tokentimetext);
                $currenttime = time();
                $interval = ((int)$currenttime) - ((int)$tokentime);
                $trace = $trace.";interval=".$interval;
                return ($interval < $delay && $interval >= -$delay);
            } else {
                $trace = $trace."the hash is bad;";
            }
        } catch (Exception $e) {
            $trace = $trace.'Exception in is_token_valid:'.$e->getMessage();
        }
        return false;
    }

    /**
     * Returns hexadecimal representation of the given string.
     *
     * @param string $data
     * @return bool|string
     */
    public static function hex_encode($data) {
        try {
            $result = bin2hex($data);
        } catch (Exception $e) {
            $result = false;
        }
        return $result;
    }

    /**
     * Returns the binary representation of the given data or FALSE on failure.
     * Alias for {@link MHUtil::hex2bin()}.
     *
     * @param string $data
     * @return string|bool
     */
    public static function hex_decode($data) {
        try {
            return self::hex2bin($data);
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Returns the json representation of the given variable.
     *
     * @param mix $var
     * @return string
     */
    public static function var2json($var) {
        if (function_exists('json_encode')) {
            return json_encode($var);
        } else {
            // Handling primitive types.
            if (is_int($var) || is_float($var)) {
                return $var;
            }
            if (is_bool($var)) {
                return ($var) ? "true" : "false";
            }
            if (is_null($var)) {
                return "null";
            }
            if (is_string($var)) {
                return '"'.addcslashes($var, '"').'"';
            }

            if (is_object($var)) {
                $construct = array();
                foreach ($var as $key => $value) {
                    $propname = addslashes($key);
                    $propvalue = self::var2json( $value );
                    // Add to staging array.
                    $construct[] = "\"$propname\":$propvalue";
                }
                // Format JSON 'object'.
                $result = "{" . implode( ",", $construct ) . "}";
                return $result;
            }
            $associative = count( array_diff( array_keys($var), array_keys( array_keys( $var )) ));
            if ( $associative) {
                // If the array is a vector (not associative) format JSON 'object'.
                $construct = array();
                foreach ($var as $key => $value) {
                    $keyname = '';
                    if ( is_int($key)) {
                        $keyname = "key_$key";
                    } else {
                        $keyname = addslashes("$key");
                    }
                    $keyvalue = self::var2json( $value );
                    $construct[] = '"'.$keyname.'":'.$keyvalue;
                }
                $result = "{" . implode( ",", $construct ) . "}";
            } else {
                // If the array is a vector (not associative) format JSON 'array'.
                $construct = array();
                foreach ($var as $value) {
                    $construct[] = self::var2json( $value );
                }
                $result = "[" . implode( ",", $construct ) . "]";
            }
            return $result;
        }
    }

    /**
     * Returns the hexadecimal representation of the given string.
     *
     * @param string
     * @return string
     */
    public static function hex2bin($str) {
        $bin = "";
        $i = 0;
        do {
            $bin .= chr(hexdec($str{$i}.$str{($i + 1)}));
            $i += 2;
        } while ($i < strlen($str));
        return $bin;
    }

    /**
     *
     */
    public static function handle_illegal_chars($str) {
        if (strpos($str, "-") !== false) {
            $str = '`'.$str.'`';
        }
        $str = str_replace(";", "\;", str_replace("'", "''", $str));
        return $str;
    }

    /**
     * Validates that a user that corresponds to the token, secret, username and password,
     * can log in. Returns the authentication result.
     *
     * @return MHAuthenticationResult
     */
    public static function validate_login($token, $secret, $username, $password) {
        $trace = '';
        $result = new MHAuthenticationResult(MHAuthenticationResult::FAILURE, '', '');
        if (self::is_token_valid($token, $secret, TOKEN_VALIDITY_INTERVAL, 'md5', $trace) || empty($secret)) {
            $user = authenticate_user_login($username, $password);
            if ($user != false) {
                $result = new MHAuthenticationResult(MHAuthenticationResult::SUCCESS, $user->username, '');
            } else {
                $result = new MHAuthenticationResult(MHAuthenticationResult::FAILURE, '', $trace.'User Authentication Failed');
            }
        }
        return $result;
    }

    /**
     * Returns the user info for the given token and secret.
     *
     * @param string $token
     * @param string $secret
     * @return MHUserInfo
     */
    public static function get_user_info($token, $secret) {
        global $DB;

        $trace = '';
        $userinfo = new MHUserInfo(MHUserInfo::FAILURE);
        $userinfo->message = 'error:token is invalid';
        $userid = null;

        if (self::is_token_valid($token, $secret, TOKEN_VALIDITY_INTERVAL, 'md5', $trace) || empty($secret)) {
            try {
                $userinfo = new MHUserInfo(MHUserInfo::SUCCESS);
                $username = self::get_token_value(self::hex_decode($token), "userid");
                $user = null;
                if (!empty($username)) {
                    $studentroles = $DB->get_records('role', array('archetype' => 'student'));
                    $editingteacherroles = $DB->get_records('role', array('archetype' => 'editingteacher'));
                    $teacherroles = $DB->get_records('role', array('archetype' => 'teacher'));
                    $user = $DB->get_record("user", array("username" => $username) );
                    $userid = $user->id;
                    $userinfo->set_user($user);
                    $trace = $trace.';user is set';

                    $courses = enrol_get_users_courses($userid, true);
                    foreach ($courses as $course) {
                        $context = get_context_instance(CONTEXT_COURSE, $course->id);
                        foreach ($editingteacherroles as $role) {
                            $roleid = $role->id;
                            $conds = array('roleid' => $roleid, 'contextid' => $context->id, 'userid' => $userid);
                            $ras = $DB->get_records('role_assignments', $conds);
                            if (count($ras) > 0) {
                                $userinfo->add_course($course, 'instructor');
                            }
                        }
                        foreach ($teacherroles as $role) {
                            $roleid = $role->id;
                            $conds = array('roleid' => $roleid, 'contextid' => $context->id, 'userid' => $userid);
                            $ras = $DB->get_records('role_assignments', $conds);
                            if (count($ras) > 0) {
                                $userinfo->add_course($course, 'instructor');
                            }
                        }
                        foreach ($studentroles as $role) {
                            $roleid = $role->id;
                            $conds = array('roleid' => $roleid, 'contextid' => $context->id, 'userid' => $userid);
                            $ras = $DB->get_records('role_assignments', $conds);
                            if (count($ras) > 0) {
                                $userinfo->add_course($course, 'student');
                            }
                        }
                    }
                    $trace = $trace.';courses are set';
                    $userinfo->message = '';
                }
            } catch (Exception $e) {
                $userinfo = new MHUserInfo(MHUserInfo::FAILURE);
                $userinfo->message = "ex:".$e->getMessage()." trace:".$trace;
                $userinfo->username = $username;
                $userinfo->userid = $userid;
            }
        } else {
            $userinfo->message = "trace:".$trace;
        }
        return $userinfo;

    }

}

/**
 *
 */
class MHUserInfo {
    const SUCCESS = 0;
    const FAILURE = 1;

    public $status;
    public $user;
    public $courses;
    public $message;

    public function __construct($status) {
        $this->status = $status;
        $this->courses = array();
    }

    public function add_courses($courses, $rolename) {
        foreach ($courses as $course) {
            $lcourse = clone $course;
            $lcourse->rolename = $rolename;
            array_push($this->courses, $lcourse);
        }
    }

    public function add_course($course, $rolename) {
        $lcourse = clone $course;
        $lcourse->rolename = $rolename;
        array_push($this->courses, $lcourse);
    }

    public function set_user($user) {
        $this->user = $user;
        if ($this->user) {
            $this->user->password = null;
        }
    }
}

/**
 *
 */
class MHAuthenticationResult {
    const SUCCESS = 0;
    const FAILURE = 1;

    public $status;
    public $effectiveuserid;
    public $redirecturl;
    public $attributes;
    public $message;

    public function __construct($status, $effectiveuserid, $errordetails) {
        $this->status = $status;
        $this->effectiveuserid = $effectiveuserid;
        $this->attributes = array();
        $this->message = $errordetails;
    }
}

