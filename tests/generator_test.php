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

/**
 * PHPUnit data generator testcase
 *
 * @package    block_deft
 * @copyright  2024 Daniel Thies <dethies@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @group      block_deft
 * @covers     \block_deft_generator
 */
final class generator_test extends \advanced_testcase {
    public function test_generator(): void {
        global $DB;

        $this->resetAfterTest(true);

        $beforeblocks = $DB->count_records('block_instances');
        $beforecontexts = $DB->count_records('context');

        /** @var \block_deft_generator $generator */
        $generator = $this->getDataGenerator()->get_plugin_generator('block_deft');
        $this->assertInstanceOf('block_deft_generator', $generator);
        $this->assertEquals('deft', $generator->get_blockname());

        $generator->create_instance();
        $generator->create_instance();
        $bi = $generator->create_instance();
        $this->assertEquals($beforeblocks + 3, $DB->count_records('block_instances'));
        $task = $generator->create_task($bi->id);

        $this->assertEquals(1, count(task::get_records(['instance' => $bi->id])));
    }
}
