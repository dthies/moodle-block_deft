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
 * Mobie addon definition
 *
 * @package     block_deft
 * @copyright   2022 Daniel Thies <dethies@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$addons = [
    'block_deft' => [
        'handlers' => [
            'blockdeft' => [
                'displaydata' => [
                    'icon' => $CFG->wwwroot . '/blocks/deft/pix/icon.png',
                    'class' => '',
                    'type' => 'template',
                ],

                'delegate' => 'CoreBlockDelegate',
                'init' => 'init',
                'method' => 'mobile_content_view',
            ],
        ],
        'lang' => [ // Language strings that are used in all the handlers.
            ['pluginname', 'block_deft'],
            ['close', 'block_deft'],
            ['count', 'block_deft'],
            ['delete', 'moodle'],
            ['guests', 'block_deft'],
            ['join', 'block_deft'],
            ['lowerhand', 'block_deft'],
            ['microphoneon', 'block_deft'],
            ['mute', 'block_deft'],
            ['post', 'moodle'],
            ['raisehand', 'block_deft'],
            ['response', 'block_deft'],
            ['select', 'moodle'],
            ['unmute', 'block_deft'],
        ],
    ],
];
