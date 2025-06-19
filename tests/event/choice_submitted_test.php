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
final class choice_submitted_test extends advanced_testcase {
    /**
     * Test choice_submitted event.
     * @covers \block_deft\event\choice_submitted
     */
    public function test_choice_submitted(): void {
        $this->resetAfterTest();

        $this->setAdminUser();

        /** @var \block_deft_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('block_deft');
        $this->assertInstanceOf('block_deft_generator', $generator);
        $this->assertEquals('deft', $generator->get_blockname());

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $bi = $generator->create_instance(['parentcontextid' => \context_course::instance($course->id)->id]);

        $task = $generator->create_task($bi->id, [
            'type' => 'choice',
        ], [
            'question' => 'One, Two, Three...',
            'option' => [
                'Rock',
                'Paper',
                'Scissors',
            ],
        ]);

        // Triggering and capturing the event.
        $sink = $this->redirectEvents();

        block_deft_output_fragment_choose([
            'context' => \context_block::instance($bi->id),
            'id' => (string)$task->get('id'),
            'option' => 1,
        ]);

        $events = $sink->get_events();
        $this->assertCount(1, $events);
        $event = reset($events);

        // Checking that the event contains the expected values.
        $this->assertInstanceOf('\block_deft\event\choice_submitted', $event);
        $this->assertEquals(context_block::instance($bi->id), $event->get_context());
        $this->assertEquals($task->get('id'), $event->objectid);
        $this->assertEventContextNotUsed($event);
    }
}
