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
 * Class containing data for automatic service configuration
 *
 * @package    block_deft
 * @copyright  2022 Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_deft\output;

defined('MOODLE_INTERNAL') || die;

require_once($CFG->dirroot.'/mod/lti/locallib.php');

use moodle_url;
use renderable;
use templatable;
use renderer_base;
use stdClass;
use help_icon;

/**
 * Class containing data for tool_configure page
 *
 * @copyright  2022 Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class configuration implements renderable, templatable {
    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param renderer_base $output The renderer
     * @return stdClass
     */
    public function export_for_template(renderer_base $output) {
        global $DB;

        $url = new moodle_url('/admin/settings.php', [
            'section' => 'blocksettingdeft',
        ]);
        $finishurl = new moodle_url('/blocks/deft/toolconfigure.php', [
            'registration' => 'complete',
        ]);
        if (!get_config('block_deft', 'enableupdating')) {
            return [
                'disabled' => true,
                'returnurl' => $url->out(false),
            ];
        }

        $endpoint = 'https://deftly.us/admin/tool/deft/message.php';
        if ($clientid = $DB->get_field_select('lti_types', 'clientid', "tooldomain = 'deftly.us'")) {
            return [
                'configured' => true,
                'returnurl' => $url->out(false),
            ];
        }

        $requestparams = [
            'action' => 'register',
            'contextid' => 0,
        ];
        $jwt = lti_sign_jwt($requestparams, $endpoint, 'none');
        $requestparams = array_merge($requestparams, $jwt);
        $query = html_entity_decode(http_build_query($requestparams));

        $response = file_get_contents($endpoint . '?' . $query);
        $registrationurl = new moodle_url('/mod/lti/startltiadvregistration.php', [
            'url' => json_decode($response),
            'sesskey' => sesskey(),
        ]);
        return [
            'error' => optional_param('registration', '', PARAM_ALPHA) == 'complete',
            'registrationurl' => $registrationurl->out(false),
            'returnurl' => $url->out(false),
            'finishurl' => $finishurl->out(false),
        ];
    }
}
