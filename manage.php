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
 * Page to let a user manage tasks for deft block
 *
 * @package   block_deft
 * @copyright 2022 Daniel Thies
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use block_deft\manager;
use block_deft\output\main;

$returnurl = optional_param('returnurl', '', PARAM_LOCALURL);
$id = required_param('id', PARAM_INT);

$context = context_block::instance($id);
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

$managetasks = has_capability('block/deft:edit', $context);
if (!$managetasks) {
    require_capability('block/deft:manage', $context);
}

$urlparams = array('id' => $id);
$extraparams = '';
if ($returnurl) {
    $urlparams['returnurl'] = $returnurl;
    $extraparams = '&returnurl=' . $returnurl;
}
$baseurl = new moodle_url('/blocks/deft/manage.php', $urlparams);
$PAGE->set_url($baseurl);
$PAGE->set_pagelayout('report');

$strmanage = get_string('managetasks', 'block_deft');

$PAGE->set_title($strmanage);
$PAGE->set_heading($strmanage);

$managetasks = new moodle_url('/blocks/deft/manage.php', $urlparams);
$PAGE->navbar->add(get_string('manage', 'block_deft'), $managetasks);

$manager = new manager($context);
$output = $PAGE->get_renderer('block_deft');

echo $output->header();

echo $output->render($manager);

echo $output->footer();
