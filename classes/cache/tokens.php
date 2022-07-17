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
 * Data source class for authorization tokens
 *
 * @package    block_deft
 * @copyright  2022 Daniel Thies <dethies@gmail.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace block_deft\cache;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/lti/locallib.php');

use cache_definition;
use cache_data_source;

/**
 * Data source class for authorization tokens
 *
 * @package    block_deft
 * @copyright  2022 Daniel Thies <dethies@gmail.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class tokens implements cache_data_source {

    /** @var overrides the singleton instance of this class. */
    protected static $instance = null;

    /**
     * Returns an instance of the data source class that the cache can use for loading data using the other methods
     * specified by this interface.
     *
     * @param cache_definition $definition
     * @return object
     */
    public static function get_instance_for_cache(cache_definition $definition): tokens {
        if (is_null(self::$instance)) {
            self::$instance = new tokens();
        }
        return self::$instance;
    }

    /**
     * Loads the data for the key provided ready formatted for caching.
     *
     * @param string|int $contextid The key to load.
     * @return mixed What ever data should be returned, or false if it can't be loaded.
     * @throws \coding_exception
     */
    public function load_for_cache($contextid) {
        global $DB;

        if (!get_config('block_deft', 'enableupdating')) {
            return;
        }

        $endpoint = 'https://deftly.us/admin/tool/deft/message.php';
        $clientid = $DB->get_field_select('lti_types', 'clientid', "tooldomain = 'deftly.us'");

        $requestparams = [
            'action' => 'token',
            'contextid' => $contextid,
        ];
        $jwt = lti_sign_jwt($requestparams, $endpoint, $clientid);
        $requestparams = array_merge($requestparams, $jwt);
        $query = html_entity_decode(http_build_query($requestparams));

        $response = json_decode(file_get_contents(
            $endpoint . '?' . $query
        ));
        return $response;
    }

    /**
     * Loads several keys for the cache.
     *
     * @param array $keys An array of keys each of which will be string|int.
     * @return array An array of matching data items.
     */
    public function load_many_for_cache(array $keys) {
        $results = [];

        foreach ($keys as $key) {
            $results[] = $this->load_for_cache($key);
        }

        return $results;
    }
}
