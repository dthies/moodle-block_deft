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

use block_deft\task;

/**
 * Recently Deft response block data generator class.
 *
 * @package    block_deft
 * @category   test
 * @copyright  2022 Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_deft_generator extends testing_block_generator {
    /**
     * Create task
     *
     * @param int $instanceid Block instance id
     * @param array $options
     * @param array $data
     * @return task
     */
    public function create_task(int $instanceid, $options = [], $data = []): task {
        global $USER;

        $options = (object)$options;

        $record = new stdClass();
        $record->instance = $instanceid;
        $record->type = $options->type ?? 'text';
        $record->sortorder = task::count_records(['instance' => $instanceid]);
        $record->timecreated = $options->timecreated ?? 100;
        $record->timemodified = $options->timecreated ?? 100;
        $record->usercreated = $USER->id;
        $record->usermodified = $USER->id;
        $record->visible = 0;
        $record->configdata = json_encode($data);
        $record->statedata = $options->statedata ?? '{}';

        $task = new task(0, $record);

        $task->create();

        return $task;
    }
}
