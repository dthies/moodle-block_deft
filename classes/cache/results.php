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
 * Data source class for deft choice results
 *
 * @package    block_deft
 * @copyright  2022 Daniel Thies <dethies@gmail.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

declare(strict_types=1);

namespace block_deft\cache;

use cache_definition;
use cache_data_source;
use block_deft\task;

/**
 * Data source class for deft choice results
 *
 * @package    block_deft
 * @copyright  2022 Daniel Thies <dethies@gmail.com
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class results implements cache_data_source {
    /** @var overrides the singleton instance of this class. */
    protected static $instance = null;

    /**
     * Returns an instance of the data source class that the cache can use for loading data using the other methods
     * specified by this interface.
     *
     * @param cache_definition $definition
     * @return object
     */
    public static function get_instance_for_cache(cache_definition $definition): results {
        if (is_null(self::$instance)) {
            self::$instance = new results();
        }
        return self::$instance;
    }

    /**
     * Loads the data for the key provided ready formatted for caching.
     *
     * @param string|int $key The key to load.
     * @return mixed What ever data should be returned, or false if it can't be loaded.
     * @throws \coding_exception
     */
    public function load_for_cache($key) {
        global $DB;

        $ids = explode('x', $key);
        $taskid = array_shift($ids);
        $userid = reset($ids);

        if (!empty($userid)) {
            return $DB->get_record('block_deft_response', ['task' => $taskid, 'userid' => $userid]);
        }

        $results = $DB->get_records_sql(
            'SELECT response, COUNT(response) as count, MAX(timemodified) AS timemodified
               FROM {block_deft_response}
              WHERE task = :task
           GROUP BY response',
            ['task' => $taskid],
        );

        $task = new task($taskid);

        if ($task->get('type') == 'choice') {
            $options = [];
            $config = $task->get_config();
            foreach ($config->option as $option) {
                $options[$option] = $results[$option] ?? (object) [
                    'response' => $option,
                    'count' => 0,
                ];
            }
            $results = $options + $results;
            unset($results['']);
        }

        return [
            'responses' => $results,
            'timecreated' => time(),
        ];
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
