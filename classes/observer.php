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
 * Event observers used in Download Submissions plugin.
 *
 * @copyright  2017 Syllametrics, John Doyle <jdoyle@syllametrics.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();

class mod_download_submissions_observer {
    /**
     * Observer function to handle the all submissions downloaded event in mod_assign.
     * @param \mod_assign\event\all_submissions_downloaded $event
     */
    public static function download_initiated(
    	\mod_assign\event\all_submissions_downloaded $event) {
        global $CFG;
        require_once($CFG->dirroot . '/local/mod_downloadsubmissions/lib.php');
        dls_handle_event($event->get_data());
    }
   
}