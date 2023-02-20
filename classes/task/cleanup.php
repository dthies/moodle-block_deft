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
 * Task for cleaning venue for Deft response block
 *
 * @package   block_deft
 * @copyright Daniel Thies
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_deft\task;

/**
 * Task for cleaning venue for Deft response block
 *
 * @package   block_deft
 * @copyright Daniel Thies
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cleanup extends \core\task\scheduled_task {

    /**
     * Name for this task.
     *
     * @return string
     */
    public function get_name() {
        return get_string('cleanuptask', 'block_deft');
    }

    /**
     * Remove old entries from table block_deft_signal and block_deft_peer
     */
    public function execute() {
        global $DB;

        $count = count($DB->get_records_select(
            'block_deft_peer',
            'id IN (SELECT p.id
                      FROM {block_deft_peer} p
                 LEFT JOIN {sessions} s ON p.sessionid = s.id
                     WHERE s.id IS NULL)'
        ));

        if (!$count) {
            return;
        }

        $DB->delete_records_select(
            'block_deft_signal',
            'frompeer IN (SELECT p.id
                            FROM {block_deft_peer} p
                       LEFT JOIN {sessions} s ON p.sessionid = s.id
                           WHERE s.id IS NULL)'
        );

        $DB->delete_records_select(
            'block_deft_signal',
            'topeer IN (SELECT p.id
                          FROM {block_deft_peer} p
                     LEFT JOIN {sessions} s ON p.sessionid = s.id
                         WHERE s.id IS NULL)'
        );

        $count = $DB->delete_records_select(
            'block_deft_peer',
            'id IN (SELECT p.id
                      FROM {block_deft_peer} p
                 LEFT JOIN {sessions} s ON p.sessionid = s.id
                     WHERE s.id IS NULL)'
        );

        mtrace("$count old peers deleted");
    }
}
