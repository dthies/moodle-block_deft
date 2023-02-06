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
 * Form to modify choice status
 *
 * @package     block_deft
 * @copyright   2022 Daniel Thies <dethies@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_deft\form;

use core_form\dynamic_form;
use moodle_exception;
use moodle_url;
use block_deft\task;

/**
 * Form to modify choice status
 *
 * @package     block_deft
 * @copyright   2022 Daniel Thies <dethies@gmail.com>
 */
class status_choice extends status_task {

    /**
     * Form definition
     */
    public function definition() {
        parent::definition();

        $mform = $this->_form;

        $mform->addElement('advcheckbox', 'preventresponse', '', get_string('preventresponse', 'block_deft'));
        $mform->setType('preventresponse', PARAM_BOOL);
        $mform->setDefault('preventresponse', get_config('block_deft', 'addcomments'));
        $mform->disabledIf('preventresponse', 'visible', 0);

        $mform->addElement('advcheckbox', 'showsummary', '', get_string('showsummary', 'block_deft'));
        $mform->setType('showsummary', PARAM_BOOL);
        $mform->setDefault('showsummary', get_config('block_deft', 'addcomments'));
        $mform->disabledIf('showsummary', 'visible', 0);
    }
}
