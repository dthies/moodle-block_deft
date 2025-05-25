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

namespace block_deft\event;

use advanced_testcase;
use context_course;
use context_module;
use context_block;
use block_deft\task;

/**
 * Events test.
 *
 * @package    block_deft
 * @copyright  2024 Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      block_deft
 */
final class task_created_test extends advanced_testcase {
    /**
     * Test task_created event.
     * @covers \block_deft\event\task_created
     */
    public function test_task_created(): void {
        $this->resetAfterTest();

        $this->setAdminUser();

        /** @var \block_deft_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('block_deft');
        $this->assertInstanceOf('block_deft_generator', $generator);
        $this->assertEquals('deft', $generator->get_blockname());

        $bi = $generator->create_instance();

        // Triggering and capturing the event.
        $sink = $this->redirectEvents();
        $task = $generator->create_task($bi->id);

        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\block_deft\event\task_created', $event);
        $this->assertEquals(context_block::instance($bi->id), $event->get_context());
        $this->assertEquals($task->get('id'), $event->objectid);
        $this->assertEventContextNotUsed($event);
    }
}
