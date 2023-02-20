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
 * The data for the task needed to render updates
 *
 * @package    block_deft
 * @copyright  2022 Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_deft;

use block_deft\event\task_created;
use block_deft\event\task_deleted;
use block_deft\event\task_updated;
use cache;
use core\persistent;
use context_block;

/**
 * Class for loading/storing oauth2 refresh tokens from the DB.
 *
 * @copyright  2022 Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class task extends persistent {

    /**
     * @var TABLE Database table
     */
    const TABLE = 'block_deft';

    /**
     * Return the definition of the properties of this model.
     *
     * @return array
     */
    protected static function define_properties() {
        return [
            'instance' => [
                'type' => PARAM_INT
            ],
            'type' => [
                'type' => PARAM_ALPHA,
            ],
            'sortorder' => [
                'type' => PARAM_INT,
            ],
            'configdata' => [
                'type' => PARAM_RAW,
            ],
            'statedata' => [
                'type' => PARAM_RAW,
            ],
            'visible' => [
                'type' => PARAM_INT,
            ],
        ];
    }

    /**
     * Returns config as object
     *
     * @return stdClass
     */
    public function get_config() {
        return json_decode($this->get('configdata'));
    }

    /**
     * Returns state as object
     *
     * @return stdClass
     */
    public function get_state() {
        return json_decode($this->get('statedata'));
    }

    /**
     * Clear cache on create
     */
    public function before_create() {
        $this->clear_cache();
    }

    /**
     * Clear cache on update
     */
    public function before_update() {
        $this->clear_cache();

        $params = [
            'context' => context_block::instance($this->get('instance')),
            'objectid' => $this->get('id'),
        ];

        $event = task_updated::create($params);
        $event->trigger();
    }

    /**
     * Delete user responses for the task
     */
    public function before_delete() {
        global $DB;

        $id = $this->get('id');
        $context = context_block::instance($this->get('instance'));
        $this->clear_cache();

        $DB->delete_records('block_deft_response', ['task' => $id]);

        $DB->delete_records_select(
            'block_deft_signal',
            'frompeer IN (SELECT id FROM {block_deft_peer} WHERE taskid = :taskid)',
            ['taskid' => $id]
        );
        $DB->delete_records_select(
            'block_deft_signal',
            'topeer IN (SELECT id FROM {block_deft_peer} WHERE taskid = :taskid)',
            ['taskid' => $id]
        );
        $DB->delete_records('block_deft_peer', ['taskid' => $id]);

        comment::delete_comments([
            'contextid' => $context->id,
            'itemid' => $id,
        ]);

        $params = [
            'context' => $context,
            'objectid' => $this->get('id'),
        ];

        $event = task_deleted::create($params);
        $event->trigger();
    }

    /**
     * Clear cache when task data changed
     */
    public function clear_cache() {
        $cache = cache::make('block_deft', 'tasks');
        $cache->delete($this->get('instance'));
    }

    /**
     * Get all tasks associated with block
     *
     * @param int $blockid Block instance id
     * @return array Tasks for the block
     */
    public static function get_tasks(int $blockid) {
        $cache = cache::make('block_deft', 'tasks');
        $records = $cache->get($blockid);
        $tasks = [];

        foreach ($records as $record) {
            $task = new self();
            $task->from_record($record);
            $tasks[] = $tasks;
        }

        return $tasks;
    }

    /**
     * Hook to execute after a create.
     *
     * This is only intended to be used by child classes, do not put any logic here!
     *
     * @return void
     */
    protected function after_create() {

        $params = [
            'context' => context_block::instance($this->get('instance')),
            'objectid' => $this->get('id'),
        ];

        $event = task_created::create($params);
        $event->trigger();
    }
}
