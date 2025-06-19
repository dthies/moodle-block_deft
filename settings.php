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
        $settings->add(new admin_setting_heading(
            'block_deft/updatesettings',
            new lang_string('updatesettings', 'block_deft'),
            new lang_string('updatesettings_desc', 'block_deft')
        ));

        $bridges = [
            new lang_string('none'),
            new lang_string('deftbridge', 'block_deft'),
            new lang_string('jitsibridge', 'block_deft'),
        ];
        $settings->add(new admin_setting_configselect(
            'block_deft/enableupdating',
            new lang_string('enableupdating', 'block_deft'),
            new lang_string('enableupdating_desc', 'block_deft'),
            0,
            $bridges
        ));

        $settings->add(new admin_setting_configtext_with_maxlength(
            'block_deft/throttle',
            new lang_string('throttle', 'block_deft'),
            new lang_string('throttle_desc', 'block_deft'),
            100,
            PARAM_INT,
            null,
            10
        ));

        // Venue options heading.
        $settings->add(new admin_setting_heading(
            'block_deft/venuesettings',
            new lang_string('venuesettings', 'block_deft'),
            new lang_string('venuesettings_desc', 'block_deft')
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
            'block_deft/noisesuppression',
            new lang_string('noisesuppression', 'block_deft'),
            new lang_string('noisesuppression_desc', 'block_deft'),
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
        $settings->add(new admin_setting_configselect(
            'block_deft/samplerate',
            new lang_string('samplerate', 'block_deft'),
            new lang_string('samplerate_desc', 'block_deft'),
            11025,
            $options
        ));

        $link = '<a href="https://deftly.us" target="_blank">deftly.us</a>';
        $settings->add(new admin_setting_configcheckbox(
            'block_deft/enablebridge',
            new lang_string('enablebridge', 'block_deft'),
            new lang_string('enablebridge_desc', 'block_deft', $link),
            0,
        ));

        $settings->add(new admin_setting_configcheckbox(
            'block_deft/enablevideo',
            new lang_string('enablevideo', 'block_deft'),
            new lang_string('enablevideo_desc', 'block_deft', $link),
            0
        ));

        $settings->add(new admin_setting_heading(
            'block_deft/deftsettings',
            new lang_string('deftsettings', 'block_deft'),
            new lang_string('deftsettings_desc', 'block_deft')
        ));
        $settings->add(new admin_setting_deftoverview());

        // Jitsi venue settings.
        $setting = new admin_setting_heading(
            'block_deft/jitsisetings',
            new lang_string('jitsisettings', 'block_deft'),
            new lang_string('jitsisettings_desc', 'block_deft')
        );
        $settings->add($setting);

        $setting = new admin_setting_configtext(
            'block_deft/jitsiserver',
            new lang_string('server', 'block_deft'),
            new lang_string('server_desc', 'block_deft'),
            '',
            PARAM_HOST
        );
        $settings->add($setting);

        $setting = new admin_setting_configtext(
            'block_deft/appid',
            new lang_string('appid', 'block_deft'),
            new lang_string('appid_desc', 'block_deft'),
            '',
            PARAM_HOST
        );
        $settings->add($setting);

        $setting = new admin_setting_configtext(
            'block_deft/secret',
            new lang_string('secret', 'block_deft'),
            new lang_string('secret_desc', 'block_deft'),
            '',
            PARAM_HOST
        );
        $settings->add($setting);
    }
}
