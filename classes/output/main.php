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
 * Class containing data for deft response block.
 *
 * @package     block_deft
 * @copyright   2022 Daniel Thies <dethies@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_deft\output;

defined('MOODLE_INTERNAL') || die();

use cache;
use moodle_url;
use renderable;
use renderer_base;
use stdClass;
use templatable;
use block_deft\socket;
use block_deft\task;

require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->libdir . '/completionlib.php');

/**
 * Class containing data for deft choice block.
 *
 * @copyright 2022 Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class main implements renderable, templatable {

    /**
     * Constructor.
     *
     * @param int $context The context of the block.
     * @param object $config block configuration
     */
    public function __construct($context, $config) {
        $this->context = $context;
        $this->config = $config;
        $this->socket = new socket($context);
        $this->view = new view($context);
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output) {
        $manageurl = new moodle_url('/blocks/deft/manage.php', ['id' => $this->context->instanceid]);

        return $this->view->export_for_template($output) + [
            'canuse' => has_capability('block/deft:manage', $this->context),
            'manageurl' => $manageurl->out(true),
            'throttle' => get_config('block_deft', 'throttle'),
            'token' => $this->socket->get_token(),
        ];
    }
}
