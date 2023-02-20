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
 * Form for modifying venue task status
 *
 * @package     block_deft
 * @copyright   2022 Daniel Thies <dethies@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_deft\form;

/**
 * Form for modifying venue task status
 *
 * @package     block_deft
 * @copyright   2022 Daniel Thies <dethies@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class status_venue extends status_task {

    /**
     * Form definition
     */
    public function definition() {
        parent::definition();

        $mform = $this->_form;

        $mform->addElement('advcheckbox', 'close', '', get_string('closevenue', 'block_deft'));
        $mform->setType('close', PARAM_BOOL);
        $mform->setDefault('close', get_config('block_deft', 'expandcomments'));
    }

    /**
     * Process the form submission, used if form was submitted via AJAX
     *
     * This method can return scalar values or arrays that can be json-encoded, they will be passed to the caller JS.
     *
     * @return mixed
     */
    public function process_dynamic_submission() {
        global $DB;
        if (($data = $this->get_data()) && !empty($data->close) && !empty($data->id)) {
            $peers = $DB->get_records_menu(
                'block_deft_peer',
                [
                    'status' => 0,
                    'taskid' => $data->id,
                ],
                '',
                'id, userid'
            );
            $context = $this->get_context_for_dynamic_submission();
            $peers = array_filter($peers, function($userid) use ($context) {
                return !has_capability('block/deft:moderate', $context, $userid);
            });
            if (!empty($peers)) {
                list($sql, $params) = $DB->get_in_or_equal(array_keys($peers));
                $DB->set_field_select('block_deft_peer', 'status', 1, "id $sql", $params);
                $params = [
                    'context' => $context,
                    'objectid' => $data->id,
                ];
                foreach ($peers as $peerid => $userid) {
                    $params['userid'] = $userid;
                    $params['objectid'] = $peerid;
                    $event = \block_deft\event\venue_ended::create($params);
                    $event->trigger();
                }
            }
        }
        return parent::process_dynamic_submission();
    }
}
