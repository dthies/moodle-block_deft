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
 * WebSocket manager
 *
 * @package    block_deft
 * @copyright  2022 Daniel Thies <dethies@gmail.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_deft;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/lti/locallib.php');

use cache;
use context;
use moodle_exception;
use stdClass;

require_once($CFG->dirroot . '/mod/lti/locallib.php');

/**
 * Web socket manager
 *
 * @package    block_deft
 * @copyright  2022 Daniel Thies <dethies@gmail.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class socket {

    /**
     * @var Area
     */
    protected const AREA = 'main';

    /**
     * @var Component
     */
    protected const COMPONENT = 'block_deft';

    /**
     * @var Endpoint Server endpoint
     */
    protected const ENDPOINT = 'https://deftly.us/admin/tool/deft/message.php';

    /**
     * @var $context Context associate with plugin instance
     */
    protected $context = null;

    /**
     * @var $iceservers ICE server configuration supplied by service
     */
    protected $iceservers = null;

    /**
     * @var $int itemid Optional id to distinguish socket context
     */
    protected $itemid = null;

    /**
     * Constructor
     *
     * @param context $context Context of block
     * @param int|null $itemid Optional item id
     */
    public function __construct (context $context, ?int $itemid = null) {
        $this->context = $context;
        $this->itemid = $itemid;
    }

    /**
     * Sends message to clients
     *
     * @return stdClass|null
     */
    public function dispatch(): ?stdClass {

        return $this->execute([
            'action' => 'update'
        ]);
    }

    /**
     * Execute service request
     *
     * @param array $requestparams Data to send including action type and any options
     * @return stdClass|null the reply from the server
     */
    public function execute(array $requestparams): ?stdClass {
        global $DB;

        if (!get_config('block_deft', 'enableupdating')) {
            return null;
        }

        $this->validate();

        $requestparams = [
            'contextid' => $this->context->id,
            'component' => self::COMPONENT,
            'area' => self::AREA,
        ] + $requestparams;

        $clientid = $DB->get_field_select('lti_types', 'clientid', "tooldomain = 'deftly.us'");

        $jwt = lti_sign_jwt($requestparams, self::ENDPOINT, $clientid);

        $requestparams = array_merge($requestparams, $jwt);

        $query = html_entity_decode(http_build_query($requestparams));

        return json_decode(file_get_contents(
            self::ENDPOINT . '?' . $query
        ));
    }

    /**
     * Supply the token receievd to authenticate connection
     */
    public function get_token() {
        if (!get_config('block_deft', 'enableupdating')) {
            return;
        }

        $this->validate();

        $cache = cache::make('block_deft', 'tokens');

        $cached = $cache->get($this->context->id);

        if (!empty($cached) && $cached->expiry > time()) {
            $this->iceservers = $cached->iceservers ?? '';
            return $cached->token;
        }

        // Prevent other clients requesting token for a bit.
        $cache->set($this->context->id, (object) [
            'token' => '',
            'expiry' => time() + 5,
            'iceservers' => $cached->iceservers ?? '',
        ]);

        $response = $this->execute([
            'action' => 'token',
            'contextid' => $this->context->id,
        ]);

        if (empty($response)) {
            return null;
        }
        $this->iceservers = $response->iceservers;

        $cache->set($this->context->id, $response);

        return $response->token;
    }

    /**
     * Return ICE servers
     *
     * @return array Server information
     */
    public function ice_servers() {
        $this->get_token();

        return $this->iceservers;
    }

    /**
     * Handle an event
     *
     * @param \core\event\base $event
     */
    public static function observe(\core\event\base $event) {
        $class = get_called_class();
        $socket = new $class($event->get_context());

        try {
            $socket->validate();
            $socket->dispatch();
        } catch (moodle_exception $e) {
            return;
        }

    }

    /**
     * Validate context and availabilty
     */
    public function validate() {
        if (
            $this->context->contextlevel != CONTEXT_BLOCK
            && $this->context->contextlevel != CONTEXT_SYSTEM
        ) {
            throw new moodle_exception('invalidcontext');
        }

        if (
            !$instance = block_instance_by_id($this->context->instanceid)
            || !$instance->visible
        ) {
            throw new moodle_exception('blockunavailable');
        }
    }
}
