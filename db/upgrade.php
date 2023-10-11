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

    if ($oldversion < 2023040401) {

        // Define table block_deft_room to be created.
        $table = new xmldb_table('block_deft_room');

        // Adding fields to table block_deft_room.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('component', XMLDB_TYPE_CHAR, '40', null, null, null, null);
        $table->add_field('itemid', XMLDB_TYPE_INTEGER, '10', null, null, null, null);
        $table->add_field('roomid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('server', XMLDB_TYPE_CHAR, '80', null, null, null, null);
        $table->add_field('secret', XMLDB_TYPE_CHAR, '40', null, null, null, null);
        $table->add_field('usermodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('maximum', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
        $table->add_field('data', XMLDB_TYPE_TEXT, null, null, null, null, null);

        // Adding keys to table block_deft_room.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('usermodified', XMLDB_KEY_FOREIGN, ['usermodified'], 'user', ['id']);
        $table->add_key('comitem', XMLDB_KEY_UNIQUE, ['component', 'itemid']);

        // Conditionally launch create table for block_deft_room.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Deft savepoint reached.
        upgrade_block_savepoint(true, 2023040401, 'deft');
    }

    if ($oldversion < 2023042908) {

        // Define field type to be added to block_deft_peer.
        $table = new xmldb_table('block_deft_peer');
        $field = new xmldb_field('type', XMLDB_TYPE_CHAR, '40', null, null, null, 'venue', 'status');

        // Conditionally launch add field type.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Deft savepoint reached.
        upgrade_block_savepoint(true, 2023042908, 'deft');
    }

    if ($oldversion < 2023042910) {

        // Define field token to be added to block_deft_room.
        $table = new xmldb_table('block_deft_room');
        $field = new xmldb_field('token', XMLDB_TYPE_CHAR, '40', null, null, null, null, 'data');

        // Conditionally launch add field token.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Define key sessionid (foreign) to be dropped form block_deft_peer.
        $table = new xmldb_table('block_deft_peer');
        $key = new xmldb_key('sessionid', XMLDB_KEY_FOREIGN, ['sessionid'], 'sessions', ['id']);

        // Launch drop key sessionid.
        $dbman->drop_key($table, $key);

        // Changing nullability of field sessionid on table block_deft_peer to null.
        $table = new xmldb_table('block_deft_peer');
        $field = new xmldb_field('sessionid', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'taskid');

        // Launch change of nullability for field sessionid.
        $dbman->change_field_notnull($table, $field);

        // Define key sessionid (foreign) to be added to block_deft_peer.
        $table = new xmldb_table('block_deft_peer');
        $key = new xmldb_key('sessionid', XMLDB_KEY_FOREIGN, ['sessionid'], 'sessions', ['id']);

        // Launch add key sessionid.
        $dbman->add_key($table, $key);

        // Define field uuid to be added to block_deft_peer.
        $table = new xmldb_table('block_deft_peer');
        $field = new xmldb_field('uuid', XMLDB_TYPE_CHAR, '40', null, null, null, null, 'type');

        // Conditionally launch add field uuid.
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        // Deft savepoint reached.
        upgrade_block_savepoint(true, 2023042910, 'deft');
    }

    return true;
}
