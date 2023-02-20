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
 * Plugin administration pages are defined here.
 *
 * @package     block_deft
 * @category    admin
 * @copyright   2022 Daniel Thies <dethies@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use block_deft\admin_setting_deftoverview;

if ($hassiteconfig) {
    if ($ADMIN->fulltree) {
        $settings->add(new admin_setting_configcheckbox(
            'block_deft/enableupdating',
            new lang_string('enableupdating', 'block_deft'),
            new lang_string('enableupdating_desc', 'block_deft'),
            0
        ));

        $settings->add(new admin_setting_deftoverview());

        $settings->add(new admin_setting_configtext_with_maxlength('block_deft/throttle',
            new lang_string('throttle', 'block_deft'),
            new lang_string('throttle_desc', 'block_deft'), 100, PARAM_INT, null, 10)
        );

        // Venue options heading.
        $settings->add(new admin_setting_heading(
            'block_deft/venuesettings',
            new lang_string('venuesettings', 'block_deft'),
            ''
        ));

        $settings->add(new admin_setting_configcheckbox(
            'block_deft/echocancellation',
            new lang_string('echocancellation', 'block_deft'),
            new lang_string('echocancellation_desc', 'block_deft'),
            1
        ));

        $settings->add(new admin_setting_configcheckbox(
            'block_deft/autogaincontrol',
            new lang_string('autogaincontrol', 'block_deft'),
            new lang_string('autogaincontrol_desc', 'block_deft'),
            1
        ));

        $settings->add(new admin_setting_configcheckbox(
            'block_deft/noisesuppresion',
            new lang_string('noisesuppresion', 'block_deft'),
            new lang_string('noisesuppresion_desc', 'block_deft'),
            1
        ));

        $options = [
            8000 => 8000,
            11025 => 11025,
            22050 => 22050,
            44100 => 44100,
            48000 => 48000,
            96000 => 96000,
        ];
        $settings->add(new admin_setting_configselect('block_deft/samplerate',
            new lang_string('samplerate', 'block_deft'),
            new lang_string('samplerate_desc', 'block_deft'),
            11025,
            $options)
        );
    }
}
