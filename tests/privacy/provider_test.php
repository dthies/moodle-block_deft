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

namespace block_deft\privacy;

use context_module;
use core_grades\component_gradeitem;
use mod_plenum\grades\plenum_gradeitem as gradeitem;
use gradingform_controller;
use core_privacy\tests\provider_testcase;
use block_deft\privacy\provider;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\writer;

/**
 * Tests for the hosting block privacy provider.
 *
 * @package   block_deft
 * @copyright Daniel Thies <dethies@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers    \block_deft\privacy\provider
 * @group     block_deft
 */
final class provider_test extends provider_testcase {
    /** @var array */
    protected $tasks = [];
    /** @var array */
    protected $users = [];
    /** @var array */
    protected $instance = null;
    /** @var array */
    protected $course = null;

    /**
     * Set up for each test.
     */
    public function setUp(): void {
        global $DB;

        parent::setUp();
        $this->resetAfterTest();

        $dg = $this->getDataGenerator();
        $this->course = $dg->create_course();
        $pg = $dg->get_plugin_generator('block_deft');

        $this->users[1] = $dg->create_and_enrol($this->course, 'teacher');
        $this->users[2] = $dg->create_and_enrol($this->course, 'student');
        $this->users[3] = $dg->create_and_enrol($this->course, 'student');
        $this->users[4] = $dg->create_and_enrol($this->course, 'student');

        $this->instance = $pg->create_instance(['parentcontextid' => \context_course::instance($this->course->id)->id]);

        // User 1.
        $this->setUser($this->users[1]);

        $task = $pg->create_task($this->instance->id, [
            'type' => 'choice',
        ], [
            'question' => 'One, Two, Three...',
            'option' => [
                'Rock',
                'Paper',
                'Scissors',
            ],
        ]);
        $this->tasks[1] = $task;

        // User 2.
        $this->setUser($this->users[2]);
        $pg->create_response($task, 1);

        // User 3.
        $this->setUser($this->users[3]);
        $pg->create_response($task, 2);
    }
    public function test_setup(): void {
        $this->assertTrue(true);
    }

    /**
     * Test getting the contexts for a user.
     */
    public function test_get_contexts_for_userid(): void {
        global $DB;

        // Get contexts for the first user.
        $contextids = provider::get_contexts_for_userid($this->users[1]->id)->get_contextids();
        $this->assertEqualsCanonicalizing([
            \context_block::instance($this->instance->id)->id,
        ], $contextids);

        // Get contexts for the second user.
        $contextids = provider::get_contexts_for_userid($this->users[2]->id)->get_contextids();
        $this->assertEqualsCanonicalizing([
            \context_block::instance($this->instance->id)->id,
        ], $contextids);

        // Get contexts for the third user.
        $contextids = provider::get_contexts_for_userid($this->users[3]->id)->get_contextids();
        $this->assertEqualsCanonicalizing([
            \context_block::instance($this->instance->id)->id,
        ], $contextids);

        // Get contexts for the fourth user.
        $contextids = provider::get_contexts_for_userid($this->users[4]->id)->get_contextids();
        $this->assertEqualsCanonicalizing([
        ], $contextids);

        return;
    }

    /**
     * Export data for user 1
     */
    public function test_export_user_data1(): void {
        // Export all contexts for the first user.
        $contextids = [\context_user::instance($this->users[1]->id)->id];
        $appctx = new approved_contextlist($this->users[1], 'block_deft', $contextids);
        provider::export_user_data($appctx);

        // Validate exported data for user 1.
        writer::reset();
        $this->setUser($this->users[1]);
        $context = \context_block::instance($this->instance->id);
        $component = 'block_deft';
        $writer = writer::with_context($context);
        $this->assertFalse($writer->has_any_data());

        $this->export_context_data_for_user($this->users[1]->id, $context, $component);
        $this->assertTrue($writer->has_any_data());

        $this->assertEquals([], $writer->get_data());
        $subcontext = [
            get_string('privacy:tasks', 'block_deft'),
        ];
        $data = $writer->get_data($subcontext);
        $this->assertEquals($this->users[1]->id, $data->usermodified);
    }

    /**
     * Export data for user 2
     */
    public function test_export_user_data2(): void {
        global $DB;

        // Export all contexts for the second user.
        $contextids = [\context_block::instance($this->instance->id)->id];
        $appctx = new approved_contextlist($this->users[2], 'block_deft', $contextids);
        provider::export_user_data($appctx);
        $this->assertCount(1, $appctx->get_contexts());

        // Validate exported data for user 2.
        writer::reset();
        $this->setUser($this->users[2]);
        $context = \context_block::instance($this->instance->id);
        $writer = writer::with_context($context);
        provider::export_user_data($appctx);
        $this->assertTrue($writer->has_any_data());
        $subcontext = [
            get_string('privacy:task', 'block_deft', $this->tasks[1]->get('id')),
            get_string('privacy:responses', 'block_deft'),
        ];
        $data = $writer->get_data($subcontext);
        $this->assertEquals('Paper', $data->response);
    }

    /**
     * Export data for user 3
     */
    public function test_export_user_data3(): void {
        // Export all contexts for the second user.
        $contextids = [\context_block::instance($this->instance->id)->id];
        $appctx = new approved_contextlist($this->users[3], 'block_deft', $contextids);
        provider::export_user_data($appctx);
        $this->assertCount(1, $appctx->get_contexts());

        // Validate exported data for user 3.
        writer::reset();
        $this->setUser($this->users[3]);
        $context = \context_block::instance($this->instance->id);
        $writer = writer::with_context($context);
        provider::export_user_data($appctx);
        $this->assertTrue($writer->has_any_data());
        $subcontext = [
            get_string('privacy:task', 'block_deft', $this->tasks[1]->get('id')),
            get_string('privacy:responses', 'block_deft'),
        ];
        $data = $writer->get_data($subcontext);
        $this->assertEquals('Scissors', $data->response);
    }

    /**
     * Test for delete_data_for_user().
     */
    public function test_delete_data_for_user(): void {
        // User 1.
        $context = \context_user::instance($this->users[1]->id);
        $appctx = new approved_contextlist(
            $this->users[1],
            'block_deft',
            [$context->id]
        );
        provider::delete_data_for_user($appctx);

        provider::export_user_data($appctx);
        $this->assertFalse(writer::with_context($context)->has_any_data());

        // User 2.
        writer::reset();
        $appctx = new approved_contextlist(
            $this->users[2],
            'block_deft',
            [SYSCONTEXTID]
        );
        provider::delete_data_for_user($appctx);

        provider::export_user_data($appctx);
        $this->assertFalse(writer::with_context(\context_system::instance())->has_any_data());
    }

    /**
     * Export data for user 4
     */
    public function test_export_user_data4(): void {
        // Export all contexts for the second user.
        $contextids = [\context_block::instance($this->instance->id)->id];
        $appctx = new approved_contextlist($this->users[4], 'block_deft', $contextids);
        provider::export_user_data($appctx);
        $this->assertCount(1, $appctx->get_contexts());

        // Validate exported data for user 4.
        writer::reset();
        $this->setUser($this->users[4]);
        $context = \context_block::instance($this->instance->id);
        $writer = writer::with_context($context);
        provider::export_user_data($appctx);
        $this->assertFalse($writer->has_any_data());
    }

    /**
     * Test for delete_data_for_all_users_in_context().
     */
    public function test_delete_data_for_all_users_in_context(): void {
        $context = \context_user::instance($this->users[1]->id);
        provider::delete_data_for_all_users_in_context($context);

        $appctx = new approved_contextlist(
            $this->users[1],
            'block_deft',
            [$context->id]
        );
        provider::export_user_data($appctx);
        $this->assertFalse(writer::with_context($context)->has_any_data());

        writer::reset();
        $appctx = new approved_contextlist($this->users[2], 'block_deft', [$context->id]);
        provider::export_user_data($appctx);
        $this->assertFalse(writer::with_context($context)->has_any_data());

    }
}
