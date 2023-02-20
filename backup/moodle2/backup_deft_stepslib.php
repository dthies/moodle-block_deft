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
 * Backup steps for block_deft are defined here.
 *
 * @package     block_deft
 * @category    backup
 * @copyright   2022 Daniel Thies <dethies@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// More information about the backup process: {@link https://docs.moodle.org/dev/Backup_API}.
// More information about the restore process: {@link https://docs.moodle.org/dev/Restore_API}.

/**
 * Define the complete structure for backup, with file and id annotations.
 */
class backup_deft_block_structure_step extends backup_block_structure_step {

    /**
     * Defines the structure of the resulting xml file.
     *
     * @return backup_nested_element The structure wrapped in the block tag.
     */
    protected function define_structure() {

        // To know if we are including userinfo.
        $userinfo = $this->get_setting_value('users');

        // Define structure.
        $root = new backup_nested_element('tasks', array('id'), null);

        $task = new backup_nested_element('task', array('id'), [
            'instance',
            'configdata',
            'statedata',
            'sortorder',
            'type',
            'visible',
        ]);

        $responses = new backup_nested_element('responses');

        $response = new backup_nested_element('response', ['id'], [
            'task',
            'response',
            'timecreated',
            'timemodified',
            'userid',
        ], null);

        // Build the tree with these elements with $root as the root of the backup tree.
        $root->add_child($task);
        $task->add_child($responses);
        $responses->add_child($response);

        // Define the source tables for the elements.
        $root->set_source_array([(object)['id' => $this->task->get_blockid()]]);

        $task->set_source_table('block_deft', [
            'instance' => backup::VAR_PARENTID,
        ]);

        // The rest only happen if we are including user info.
        if ($userinfo) {
            $response->set_source_table('block_deft_response', [
                'task' => '../../id',
            ]);
        }

        // Define id annotations.
        $response->annotate_ids('user', 'userid');

        // Define file annotations.
        $task->annotate_files('block_deft', 'venue', 'id');

        return $this->prepare_block_structure($root);
    }
}
