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

namespace block_deft\backup;

defined('MOODLE_INTERNAL') || die();

use block_deft\task;

global $CFG;
require_once($CFG->libdir . "/phpunit/classes/restore_date_testcase.php");

/**
 * Restore tasks tests.
 *
 * @package    block_deft
 * @copyright  2024 Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \backup_deft_block_structure_step
 * @covers     \restore_deft_block_structure_step
 * @group      block_deft
 */
final class restore_tasks_test extends \restore_date_testcase {
    /**
     * Test restore dates.
     */
    public function test_restore_tasks(): void {
        global $DB;

        $this->resetAfterTest(true);

        // Create courses.
        $course1 = $this->getDataGenerator()->create_course();

        /** @var \block_deft_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('block_deft');

        $block = $this->add_deft_block_in_context(\core\context\course::instance($course1->id));
        $task = $generator->create_task($block->instance->id);

        $this->assertEquals(1, count(task::get_records(['instance' => $block->instance->id])));

        // Do backup and restore.
        $newcourseid = $this->backup_and_restore($course1);

        $this->assertEquals(2, count(task::get_records([])));
    }

    /**
     * Creates a Deft response block on a context.
     *
     * @param \context $context The context on which we want to put the block.
     * @return \block_base The created block instance.
     * @throws \coding_exception
     */
    protected function add_deft_block_in_context(\context $context) {
        global $DB;

        $course = null;

        $page = new \moodle_page();
        $page->set_context($context);

        switch ($context->contextlevel) {
            case CONTEXT_SYSTEM:
                $page->set_pagelayout('frontpage');
                $page->set_pagetype('site-index');
                break;
            case CONTEXT_COURSE:
                $page->set_pagelayout('standard');
                $page->set_pagetype('course-view');
                $course = $DB->get_record('course', ['id' => $context->instanceid]);
                $page->set_course($course);
                break;
            case CONTEXT_MODULE:
                $page->set_pagelayout('standard');
                $mod = $DB->get_field_sql("SELECT m.name
                                             FROM {modules} m
                                             JOIN {course_modules} cm on cm.module = m.id
                                            WHERE cm.id = ?", [$context->instanceid]);
                $page->set_pagetype("mod-$mod-view");
                break;
            case CONTEXT_USER:
                $page->set_pagelayout('mydashboard');
                $page->set_pagetype('my-index');
                break;
            default:
                throw new coding_exception('Unsupported context for test');
        }

        $page->blocks->load_blocks();

        $page->blocks->add_block_at_end_of_default_region('deft');

        // We need to use another page object as load_blocks() only loads the blocks once.
        $page2 = new \moodle_page();
        $page2->set_context($page->context);
        $page2->set_pagelayout($page->pagelayout);
        $page2->set_pagetype($page->pagetype);
        if ($course) {
            $page2->set_course($course);
        }

        $page->blocks->load_blocks();
        $page2->blocks->load_blocks();
        $blocks = $page2->blocks->get_blocks_for_region($page2->blocks->get_default_region());
        $block = end($blocks);

        $block = block_instance('deft', $block->instance);

        return $block;
    }
}
