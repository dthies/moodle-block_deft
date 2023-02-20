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
 * File description.
 *
 * @package   block_deft
 * @copyright 2022 Daniel Thies <dethies@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [

    'block_deft_renew_token' => [
        'classname' => '\\block_deft\\external\\renew_token',
        'methodname' => 'execute',
        'description' => 'Get new token to access message service',
        'type' => 'read',
        'ajax' => true,
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
    ],

    'block_deft_send_signal' => [
        'classname' => '\\block_deft\\external\\send_signal',
        'methodname' => 'execute',
        'description' => 'Send WebRTC signal to peer',
        'type' => 'write',
        'ajax' => true,
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
    ],

    'block_deft_venue_settings' => [
        'classname' => '\\block_deft\\external\\venue_settings',
        'methodname' => 'execute',
        'description' => 'Modify WebRTC settigs',
        'type' => 'write',
        'ajax' => true,
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
    ],

    'block_deft_raise_hand' => [
        'classname' => '\\block_deft\\external\\raise_hand',
        'methodname' => 'execute',
        'description' => 'Log hand raising action',
        'type' => 'write',
        'ajax' => true,
        'services' => array(MOODLE_OFFICIAL_MOBILE_SERVICE),
    ],
];
