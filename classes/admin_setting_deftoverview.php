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

namespace block_deft;

defined('MOODLE_INTERNAL') || die();

use admin_setting;
use html_writer;
use html_table;
use lang_string;
use moodle_url;
use stdClass;

require_once($CFG->dirroot . '/mod/lti/locallib.php');

/**
 * Special class for setting up Deftly service
 *
 * @package block_deft
 * @copyright Daniel Thies
 */
class admin_setting_deftoverview extends admin_setting {

    /**
     * Calls parent::__construct with specific arguments
     */
    public function __construct() {
        $this->nosave = true;
        parent::__construct('blockdeftoverviewui',
            new lang_string('overview', 'block_deft'), '', '');
    }

    /**
     * Always returns true, does nothing
     *
     * @return true
     */
    public function get_setting() {
        return true;
    }

    /**
     * Always returns true, does nothing
     *
     * @return true
     */
    public function get_defaultsetting() {
        return true;
    }

    /**
     * Always returns '', does not write anything
     *
     * @param string $data Unused
     * @return string Always returns ''
     */
    public function write_setting($data) {
        // Do not write any setting.
        return '';
    }

    /**
     * Builds the XHTML to display the control
     *
     * @param string $data Unused
     * @param string $query
     * @return string
     */
    public function output_html($data, $query='') {
        global $DB, $OUTPUT;

        $return = "";
        $url = new moodle_url("/blocks/deft/toolconfigure.php");

        $ltitype = $DB->get_record('lti_types', ['tooldomain' => 'deftly.us']);
        if (!get_config('block_deft', 'enableupdating')) {
            $return = new lang_string('enableservicemessage', 'block_deft');
        } else if ($ltitype) {
            $return .= new lang_string('statusok', 'block_deft');
            $endpoint = 'https://deftly.us/admin/tool/deft/message.php';

            $requestparams = [
                'action' => 'report',
                'contextid' => 0,
            ];
            $jwt = lti_sign_jwt($requestparams, $endpoint, $ltitype->clientid);
            $requestparams = array_merge($requestparams, $jwt);
            $query = html_entity_decode(http_build_query($requestparams));

            $response = json_decode(file_get_contents(
                $endpoint . '?' . $query
            )) ?? new stdClass();
            $response->enableupdating = true;
            $response->registered = !!$DB->get_field('lti_types', 'clientid', ['tooldomain' => 'deftly.us']);
            $response->testurl = $url->out(true);
            $return .= $OUTPUT->render_from_template('block_deft/report', $response);
        } else {
            $return .= $OUTPUT->render_from_template('block_deft/activation', [
                'url' => $url->out(true),
            ]);
        }

        $defaultinfo = null;
        return format_admin_setting($this, $this->visiblename, $return, $this->description, true, '', $defaultinfo, $query);
    }
}
