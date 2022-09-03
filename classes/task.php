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

use cache;
use core\persistent;
use comment;
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

        comment::delete_comments([
            'contextid' => $context->id,
            'itemid' => $id,
        ]);
    }

    /**
     * Clear cache when task data changed
     */
    public function clear_cache() {
        $cache = cache::make('block_deft', 'tasks');
        $cache->delete($this->get('instance'));
    }
}
