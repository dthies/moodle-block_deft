<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Plugin upgrade steps are defined here.
 *
 * @package     block_deft
 * @category    upgrade
 * @copyright   2022 Daniel Thies <dethies@gmail.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Execute block_deft upgrade from the given old version.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_block_deft_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2022111414) {

        // Define table block_deft_peer to be created.
        $table = new xmldb_table('block_deft_peer');

        // Adding fields to table block_deft_peer.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('taskid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('sessionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '20', null, null, null, null);
        $table->add_field('mute', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('status', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');

        // Adding keys to table block_deft_peer.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('taskid', XMLDB_KEY_FOREIGN, ['taskid'], 'block_deft', ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_key('sessionid', XMLDB_KEY_FOREIGN, ['sessionid'], 'session', ['id']);

        // Conditionally launch create table for block_deft_peer.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Define table block_deft_signal to be created.
        $table = new xmldb_table('block_deft_signal');

        // Adding fields to table block_deft_signal.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('type', XMLDB_TYPE_CHAR, '32', null, null, null, null);
        $table->add_field('frompeer', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('topeer', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('message', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '20', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table block_deft_signal.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('frompeer', XMLDB_KEY_FOREIGN, ['frompeer'], 'block_deft_peer', ['id']);
        $table->add_key('topeer', XMLDB_KEY_FOREIGN, ['topeer'], 'block_deft_peer', ['id']);

        // Conditionally launch create table for block_deft_signal.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Deft savepoint reached.
        upgrade_block_savepoint(true, 2022111414, 'deft');
    }

    return true;
}
