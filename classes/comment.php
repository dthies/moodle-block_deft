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
 * Custom comments interface
 *
 * @package    block_deft
 * @copyright  2022 Daniel Thies <dethies@gmail.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_deft;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/comment/lib.php');

use context;
use moodle_exception;
use stdClass;

/**
 * Custom comments interface
 *
 * @package    block_deft
 * @copyright  2022 Daniel Thies <dethies@gmail.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class comment extends \comment {
    /**
     * Return matched comments
     *
     * @param  int $page
     * @param  str $sortdirection sort direction, ASC or DESC
     * @return array
     */
    public function xget_comments($page = '', $sortdirection = 'DESC') {
        $comments = parent::get_comments();
        foreach ($comments as $c) {
            $c->strftimeformat = get_string('strftimerecent', 'langconfig');
            $c->time = userdate($c->timecreated, $c->strftimeformat);
        }
        return $comments;
    }
}
