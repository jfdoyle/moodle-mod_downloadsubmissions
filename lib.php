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

defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/mod/assign/locallib.php');

/**
 * This class provides functionality that extends the assign module.
 *
 * @package       mod_downloadsubmissions
 * @copyright     2017, John Doyle, Syllametrics <jdoyle@syllametrics.com>
 * @license       http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_downloadsubmissions extends assign {

    public function get_name() {
        return get_string('file', 'mod_downloadsubmissions');
    }


    /**
     * Download a zip file of all assignment submissions by user.
     *
     * @param array $userids Array of user ids to download assignment submissions in a zip file
     * @return string - If an error occurs, this will contain the error page.
    */
    protected function download_by_user($userids = false) {
        global $CFG, $DB;
        // More efficient to load this here.
        require_once($CFG->libdir.'/filelib.php');
        // Increase the server timeout to handle the creation and sending of large zip files.
        core_php_time_limit::raise();
        $this->require_view_grades();
        // Load all users with submit.
        $students = get_enrolled_users($this->context, "mod/assign:submit", null, 'u.*', null, null, null,
                $this->show_only_active_users());
        // Build a list of files to zip.
        $filesforzipping = array();
        $fs = get_file_storage();
        $groupmode = groups_get_activity_groupmode($this->get_course_module());
        // All users.
        $groupid = 0;
        $groupname = '';
        if ($groupmode) {
            $groupid = groups_get_activity_group($this->get_course_module(), true);
            $groupname = groups_get_group_name($groupid).'-';
        }
        // Construct the zip file name.
        $filename = clean_filename($this->get_course()->shortname . '-' .
                $this->get_instance()->name . '-' .
                $groupname.$this->get_course_module()->id . '.zip');
        // Get all the files for each student.
        foreach ($students as $student) {
            $userid = $student->id;
            // Download all assigments submission or only selected users.
            if ($userids and !in_array($userid, $userids)) {
                continue;
            }
            if ((groups_is_member($groupid, $userid) or !$groupmode or !$groupid)) {
                // Get the plugins to add their own files to the zip.
                $submissiongroup = false;
                $groupname = '';
                if ($this->get_instance()->teamsubmission) {
                    $submission = $this->get_group_submission($userid, 0, false);
                    $submissiongroup = $this->get_submission_group($userid);
                    if ($submissiongroup) {
                        $groupname = $submissiongroup->name . '-';
                    } else {
                        $groupname = get_string('defaultteam', 'assign') . '-';
                    }
                } else {
                    $submission = $this->get_user_submission($userid, false);
                }
                if ($this->is_blind_marking()) {
                    $prefix = str_replace('_', ' ', $groupname . get_string('participant', 'assign'));
                    $prefix = clean_filename($prefix . '_' . $this->get_uniqueid_for_user($userid));
                } else {
                    $prefix = str_replace('_', ' ', $groupname . fullname($student));
                    $prefix = clean_filename($prefix . '_' . $this->get_uniqueid_for_user($userid));
                }
                if ($submission) {
                    // Local variance vs. 3.1 core - do not download individual folders
                    $downloadasfolders = false; // get_user_preferences('assign_downloadasfolders', 1).
                    foreach ($this->submissionplugins as $plugin) {
                        if ($plugin->is_enabled() && $plugin->is_visible()) {
                            if ($downloadasfolders) {
                                // Create a folder for each user for each assignment plugin.
                                // This is the default behavior for version of Moodle >= 3.1.
                                $submission->exportfullpath = true;
                                $pluginfiles = $plugin->get_files($submission, $student);
                                foreach ($pluginfiles as $zipfilepath => $file) {
                                    $subtype = $plugin->get_subtype();
                                    $type = $plugin->get_type();
                                    $zipfilename = basename($zipfilepath);
                                    $prefixedfilename = clean_filename($prefix .
                                            '_' .
                                            $subtype .
                                            '_' .
                                            $type .
                                            '_');
                                    if ($type == 'file') {
                                        $pathfilename = $prefixedfilename . $file->get_filepath() . $zipfilename;
                                    } else if ($type == 'onlinetext') {
                                        $pathfilename = $prefixedfilename . '/' . $zipfilename;
                                    } else {
                                        $pathfilename = $prefixedfilename . '/' . $zipfilename;
                                    }
                                    $pathfilename = clean_param($pathfilename, PARAM_PATH);
                                    $filesforzipping[$pathfilename] = $file;
                                }
                            } else {
                                // Create a single folder for all users of all assignment plugins.
                                // This was the default behavior for version of Moodle < 3.1.
                                $submission->exportfullpath = false;
                                $pluginfiles = $plugin->get_files($submission, $student);
                                foreach ($pluginfiles as $zipfilename => $file) {
                                    $subtype = $plugin->get_subtype();
                                    $type = $plugin->get_type();
                                    $prefixedfilename = clean_filename($prefix .
                                            '_' .
                                            $subtype .
                                            '_' .
                                            $type .
                                            '_' .
                                            $zipfilename);
                                    $filesforzipping[$prefixedfilename] = $file;
                                }
                            }
                        }
                    }
                }
            }
        }
        $result = '';
        if (count($filesforzipping) == 0) {
            $header = new assign_header($this->get_instance(),
                    $this->get_context(),
                    '',
                    $this->get_course_module()->id,
                    get_string('downloadall', 'assign'));
            $result .= $this->get_renderer()->render($header);
            $result .= $this->get_renderer()->notification(get_string('nosubmission', 'assign'));
            $url = new moodle_url('/mod/assign/view.php', array('id' => $this->get_course_module()->id,
                    'action' => 'grading'));
            $result .= $this->get_renderer()->continue_button($url);
            $result .= $this->view_footer();
        } else if ($zipfile = $this->pack_files($filesforzipping)) {
            \mod_assign\event\all_submissions_downloaded::create_from_assign($this)->trigger();
            // Send file and delete after sending.
            send_temp_file($zipfile, $filename);
            // We will not get here - send_temp_file calls exit.
        }
        return $result;
    }
}

/**
 * Handler - observers will route here.
 * @param $eventdata array Event data
 * @throws coding_exception
 */
function dls_handle_event($eventdata) {
    global $DB, $CFG;
    $dls = new mod_downloadsubmissions();

    if ($eventdata->operation == 'downloadselected') {
        $users = $eventdata->selectedusers;
        $userlist = explode(',', $users);
        $dls->download_by_user($userlist);
    } else {
        $dls->download_by_user();
    }
}

