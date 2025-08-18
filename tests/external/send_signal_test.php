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

namespace block_deft\external;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/webservice/tests/helpers.php');

use core_external\external_api;
use externallib_advanced_testcase;
use stdClass;
use context_block;
use course_modinfo;
use block_deft\venue_manager;

/**
 * External function test for send_signal
 *
 * @package    block_deft
 * @copyright  2024 Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \block_deft\external\send_signal
 * @group      block_deft
 */
final class send_signal_test extends externallib_advanced_testcase {
    /**
     * Test test_send_signal invalid id.
     */
    public function test_send_signal_invalid_id(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Setup scenario.
        $scenario = $this->setup_scenario();

        $this->expectException('moodle_exception');
        send_signal::execute(1, [], 0);
    }

    /**
     * Test test_send_signal user not enrolled.
     */
    public function test_send_signal_user_not_enrolled(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        // Setup scenario.
        $scenario = $this->setup_scenario();

        // Test not-enrolled user.
        $usernotenrolled = self::getDataGenerator()->create_user();
        $this->setUser($usernotenrolled);
        $this->expectException('moodle_exception');
        $manager = new venue_manager($scenario->contextblock, $scenario->task);
        send_signal::execute($scenario->contextblock->id, [], 0);
        $this->assertTrue($result['status']);
    }

    /**
     * Test test_send_signal user student.
     */
    public function test_send_signal_user_student(): void {
        global $SESSION;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Setup scenario.
        $scenario = $this->setup_scenario();

        $this->setUser($scenario->student);

        $manager = new venue_manager($scenario->contextblock, $scenario->task);

        // Trigger and capture the event.
        $sink = $this->redirectEvents();

        $result = send_signal::execute($scenario->contextblock->id, [], 0);
        $result = external_api::clean_returnvalue(send_signal::execute_returns(), $result);
        $this->assertEqualsCanonicalizing([
            'messages' => [],
            'feed' => '',
            'peers' => [
                $SESSION->deft_session->peerid,
            ],
            'settings' => [
                [
                    'peerid' => $SESSION->deft_session->peerid,
                    'message' => false,
                    'type' => false,
                    'username' => '',
                ],
            ],
        ], $result);
    }

    /**
     * Test test_send_signal user missing capabilities.
     */
    public function test_send_signal_user_missing_capabilities(): void {
        global $DB;

        $this->resetAfterTest();
        $this->setAdminUser();

        // Setup scenario.
        $scenario = $this->setup_scenario();

        $studentrole = $DB->get_record('role', ['shortname' => 'student']);
        // Test user with no capabilities.
        // We need a explicit prohibit since this capability is only defined in authenticated user and guest roles.
        assign_capability('block/deft:joinvenue', CAP_PROHIBIT, $studentrole->id, $scenario->contextcourse->id);
        // Empty all the caches that may be affected  by this change.
        accesslib_clear_all_caches_for_unit_testing();
        course_modinfo::clear_instance_cache();

        $this->setUser($scenario->student);
        $manager = new venue_manager($scenario->contextblock, $scenario->task);

        $this->expectException('moodle_exception');
        send_signal::execute($scenario->contextblock->id, [], 0);
    }

    /**
     * Create a scenario to use into the tests.
     *
     * @return stdClass $scenario
     */
    protected function setup_scenario() {

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $contextcourse = \core\context\course::instance($course->id);

        $scenario = new stdClass();
        $scenario->contextcourse = $contextcourse;
        $scenario->student = $student;

        /** @var \block_deft_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('block_deft');

        $scenario->instance = $generator->create_instance(['parentcontextid' => $scenario->contextcourse->id]);
        $scenario->contextblock = context_block::instance($scenario->instance->id);
        $scenario->task = $generator->create_task($scenario->instance->id, [
            'type' => 'venue',
            'visible' => 1,
        ], [
            'intro' => [
                'text' => '',
                'format' => FORMAT_MOODLE,
            ],
            'connection' => 'peer',
            'content' => '',
            'limit' => 10,
            'windowoption' => 'openinpopup',
        ]);

        return $scenario;
    }
}
