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
 * Page to context users for audio conference
 *
 * @package   block_deft
 * @copyright 2022 Daniel Thies
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use block_deft\venue_manager;
use block_deft\task;

$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);
$taskid = required_param('task', PARAM_INT);
$task = task::get_record(['id' => $taskid]);
$blockid = $task->get('instance');

$context = context_block::instance($blockid);
$PAGE->set_context($context);
$parentcontext = $context->get_parent_context();
if ($parentcontext->contextlevel == CONTEXT_MODULE) {
    $cm = get_coursemodule_from_id(null, $parentcontext->instanceid);
    $course = get_course($parentcontext->get_course_context()->instanceid);
    require_login($course, true, $cm);
    if (!empty($PAGE->activityheader)) {
        $PAGE->activityheader->disable();
    }
} else if ($parentcontext->contextlevel == CONTEXT_COURSE) {
    $course = get_course($parentcontext->instanceid);
    require_login($course);
}

require_capability('block/deft:joinvenue', $context);

$urlparams = array('task' => $taskid);
$baseurl = new moodle_url('/blocks/deft/venue.php', $urlparams);
$PAGE->set_url($baseurl);
$PAGE->set_pagelayout('popup');

$PAGE->set_title(get_string('venue', 'block_deft'));
$PAGE->set_heading(get_string('venue', 'block_deft'));

$PAGE->navbar->add(get_string('venue', 'block_deft'), $baseurl);

if (!empty($SESSION->deft_session)) {
    $peerid = $SESSION->deft_session->peerid;
    unset($SESSION->deft_session);
    venue_manager::close_peer($peerid);
}

$venue = new venue_manager($context, $task);
$output = $PAGE->get_renderer('block_deft');

echo $output->header();

echo $output->render($venue);

echo $output->footer();

$params = [
    'context' => $context,
    'objectid' => $taskid,
];

$event = \block_deft\event\venue_started::create($params);
$event->trigger();
