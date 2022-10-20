<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * All the steps to restore block_deft are defined here.
 *
 * @package     block_deft
 * @category    backup
 * @copyright   2022 Daniel Thies <dethies@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// More information about the backup process: {@link https://docs.moodle.org/dev/Backup_API}.
// More information about the restore process: {@link https://docs.moodle.org/dev/Restore_API}.

/**
 * Defines the structure step to restore one deft block.
 */
class restore_deft_block_structure_step extends restore_structure_step {

    /**
     * Defines the structure to be restored.
     */
    protected function define_structure() {

        $paths = array();
        $userinfo = $this->get_setting_value('users');

        $paths[] = new restore_path_element('task', '/block/tasks/task');
        if ($userinfo) {
            $paths[] = new restore_path_element('response', '/block/tasks/task/responses/response');
        }

        return $paths;
    }

    /**
     * Process data for restoring task
     *
     * @param array $data the table data
     */
    public function process_task($data) {
        global $DB;

        // For any reason (non multiple, dupe detected...) block not restored, return.
        if (!$this->task->get_blockid()) {
            return;
        }

        $data = (object)$data;
        $oldid = $data->id;
        $timenow = time();
        $data->userid = $this->task->get_userid();
        $data->timecreated = $timenow;
        $data->timemodified = $timenow;
        $data->instance = $this->task->get_blockid();
        $taskid = $DB->insert_record('block_deft', $data);
        $this->set_mapping('task', $oldid, $taskid);
    }

    /**
     * Process data for response
     *
     * @param array $data the table data
     */
    public function process_response($data) {
        global $DB;

        // For any reason (non multiple, dupe detected...) block not restored, return.
        if (!$this->task->get_blockid()) {
            return;
        }

        $data = (object) $data;
        $data->userid = $this->get_mappingid('user', $data->userid);
        $data->task = $this->get_mappingid('task', $data->task);
        $DB->insert_record('block_deft_response', $data);
    }


    /**
     * Defines post-execution actions.
     */
    protected function after_execute() {

        return;
    }
}
