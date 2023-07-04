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
 * Class containing data for deft choice block.
 *
 * @package     block_deft
 * @copyright   2022 Daniel Thies <dethies@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace block_deft\output;

use cache;
use block_deft\comment;
use core\chart_bar;
use core\chart_pie;
use core\chart_series;
use moodle_url;
use renderable;
use renderer_base;
use stdClass;
use templatable;

/**
 * Class containing data for deft choice block.
 *
 * @copyright 2022 Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class summary extends text implements renderable, templatable {

    /**
     * Constructor.
     *
     * @param int $context The context of the block.
     * @param object $task record
     */
    public function __construct($context, $task) {
        $cache = cache::make('block_deft', 'results');

        $this->task = $task;
        $this->context = $context;
        $this->config = json_decode($task->configdata);
        $this->state = json_decode($task->statedata);
        $this->results = $cache->get($task->id);
    }

    /**
     * Export this data so it can be used as the context for a mustache template.
     *
     * @param \renderer_base $output
     * @return array
     */
    public function export_for_template(renderer_base $output) {
        if (!$this->state->showsummary) {
            return [];
        }

        if ($this->config->charttype) {
            $counts = new chart_series(
                get_string('responses', 'block_deft'),
                array_column($this->results['responses'], 'count')
            );
            $chart = new chart_pie();
            $chart->set_doughnut(true);
            $chart->add_series($counts);
            $chart->set_labels(array_column($this->results['responses'], 'response'));
        } else {
            $chart = new chart_bar();
            foreach ($this->results['responses'] as $result) {
                $counts = new chart_series($result->response, [$result->count]);
                $chart->add_series($counts);
            }
            $chart->set_labels([' ']);
        }

        $colorset = ['#f3c300', '#875692', '#f38400', '#a1caf1', '#be0032', '#c2b280', '#7f180d', '#008856',
            '#e68fac', '#0067a5'];
        $results = $this->results['responses'];
        $max = max(array_column($this->results['responses'], 'count'));
        $total = array_sum(array_column($this->results['responses'], 'count'));
        $i = 0;
        $sum = 0;
        $height = (count($results) * 50 + 10);
        foreach ($results as $result) {
            $result->height = $result->count / $max * $height;
            $result->fill = $colorset[$i % count($colorset)];
            $result->x = $i++ * 50 + 10;
            $result->y = $height + 40 - $result->height;
            $result->sum = $sum;
            $result->px = 90 + 45 * sin(2 * pi() * $sum / $total);
            $result->py = 60 - 45 * cos(2 * pi() * $sum / $total);
            $result->path = (int) ($sum < $total / 2);
            $sum += $result->count;
        }
        return [
            'chart' => $output->render_chart($chart, false),
            'lastmodified' => $this->results['timecreated'],
            'results' => $results,
            'task' => $this->task->id,
        ];
    }

    /**
     * Return last modified time
     *
     * @return float
     */
    public function last_modified() {
        $cache = cache::make('block_deft', 'results');
        $cached = $cache->get($this->task->id);
        if (!empty($cached) && $cached['timecreated']) {
            return $cached['timecreated'];
        } else {
            return time();
        }
    }
}
