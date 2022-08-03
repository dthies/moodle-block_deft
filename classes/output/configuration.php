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

use context_system;
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
                'contextid' => context_system::instance()->id,
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

        if (optional_param('registration', '', PARAM_ALPHA) == 'complete') {
            // Registration url failed. We need to complete manually.
            $type = new stdClass();
            $data = (object) [
                'lti_typename' => 'Deftly',
                'lti_ltiversion' => '1.3.0',
                'lti_keytype' => 'JWK_KEYSET',
                'lti_publickeyset' => 'https://deftly.us/enrol/lti/jwks.php',
                'lti_toolurl' => 'https://deftly.us/enrol/lti/launch.php',
                'lti_initiatelogin' => str_replace('register', 'login', json_decode($response)),
                'lti_contentitem' => 1,
                'lti_toolurl_ContentItemSelectionRequest' => 'https://deftly.us/enrol/lti/launch_deeplink.php',
                'lti_organizationid_default' => 'SITEID',
            ];
            $type->state = LTI_TOOL_STATE_CONFIGURED;
            lti_load_type_if_cartridge($data);
            $id = lti_add_type($type, $data);

            $clientid = $DB->get_field('lti_types', 'clientid', ['id' => $id]);
            $requestparams = [
                'action' => 'manual',
                'contextid' => 0,
                'clientid' => $clientid,
                'deploymentid' => $id,
            ];
            $jwt = lti_sign_jwt($requestparams, $endpoint, 'none');
            $requestparams = array_merge($requestparams, $jwt);
            $query = html_entity_decode(http_build_query($requestparams));

            $response = file_get_contents($endpoint . '?' . $query);

            return [
                'configured' => true,
                'contextid' => context_system::instance()->id,
                'returnurl' => $url->out(false),
            ];
        }
        return [
            'error' => optional_param('registration', '', PARAM_ALPHA) == 'complete',
            'registrationurl' => $registrationurl->out(false),
            'returnurl' => $url->out(false),
            'finishurl' => $finishurl->out(false),
        ];
    }
}
