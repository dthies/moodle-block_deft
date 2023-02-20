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
 * Form for modifying comments task status
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
 * Form for modifying text task status
 *
 * @package     block_deft
 * @copyright   2022 Daniel Thies <dethies@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class status_comments extends status_task {

    /**
     * Form definition
     */
    public function definition() {
        parent::definition();

        $mform = $this->_form;

        $mform->addElement('advcheckbox', 'expandcomments', '', get_string('expandcomments', 'block_deft'));
        $mform->setType('expandcomments', PARAM_BOOL);
        $mform->setDefault('expandcomments', get_config('block_deft', 'expandcomments'));
        $mform->disabledIf('expandcomments', 'visible', 0);
    }
}
