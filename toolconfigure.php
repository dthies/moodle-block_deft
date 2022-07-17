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
 * This page allows the configuration of the external tool that is used connection service
 *
 * @package    block_deft
 * @copyright  2022 Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->dirroot.'/mod/lti/lib.php');
require_once($CFG->dirroot.'/mod/lti/locallib.php');

$cartridgeurl = optional_param('cartridgeurl', '', PARAM_URL);

// No guest autologin.
require_login(0, false);
admin_externalpage_setup('ltitoolconfigure');

$pageurl = new moodle_url('/blocks/deft/toolconfigure.php');
$PAGE->set_url($pageurl);
$PAGE->set_title("{$SITE->shortname}: " . get_string('toolregistration', 'mod_lti'));
$output = $PAGE->get_renderer('block_deft');

echo $output->header();

$page = new \block_deft\output\configuration();
echo $output->render($page);

echo $output->footer();
