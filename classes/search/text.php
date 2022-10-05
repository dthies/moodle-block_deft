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
 * Search area for block_deft blocks
 *
 * @package block_deft
 * @copyright 2022 Daniel Thies
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_deft\search;

use context;
use context_block;
use core_search\document;
use core_search\document_icon;
use core_search\manager;
use core_search\moodle_recordset;
use block_deft\task;

/**
 * Search area for block_deft blocks
 *
 * @package block_deft
 * @copyright 2022 Daniel Thies
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class text extends \core_search\base_block {

    /**
     * Gets recordset of all blocks of this type modified since given time within the given context.
     *
     * See base class for detailed requirements. This implementation includes the key fields
     * from block_instances.
     *
     * This can be overridden to do something totally different if the block's data is stored in
     * other tables.
     *
     * If there are certain instances of the block which should not be included in the search index
     * then you can override get_indexing_restrictions; by default this excludes rows with empty
     * configdata.
     *
     * @param int $modifiedfrom Return only records modified after this date
     * @param \context|null $context Context to find blocks within
     * @return false|\moodle_recordset|null
     */
    public function get_document_recordset($modifiedfrom = 0, \context $context = null) {
        global $DB;

        // Get context restrictions.
        list ($contextjoin, $contextparams) = $this->get_context_restriction_sql($context, 'bi');

        // Get custom restrictions for block type.
        list ($restrictions, $restrictionparams) = $this->get_indexing_restrictions();
        if ($restrictions) {
            $restrictions = 'AND ' . $restrictions;
        }

        // Query for all entries in block_instances for this type of block, within the specified
        // context. The query is based on the one from get_recordset_by_timestamp and applies the
        // same restrictions.
        return $DB->get_recordset_sql("
                SELECT bd.id, bd.timemodified, bd.timecreated, bd.configdata,
                       c.id AS courseid, x.id AS contextid
                  FROM {block_instances} bi
                       $contextjoin
                  JOIN {context} x ON x.instanceid = bi.id AND x.contextlevel = ?
                  JOIN {context} parent ON parent.id = bi.parentcontextid
                  JOIN {block_deft} bd ON bd.instance = bi.id
             LEFT JOIN {course_modules} cm ON cm.id = parent.instanceid AND parent.contextlevel = ?
                  JOIN {course} c ON c.id = cm.course
                       OR (c.id = parent.instanceid AND parent.contextlevel = ?)
                 WHERE bd.timemodified >= ?
                       AND bi.blockname = ?
                       AND (parent.contextlevel = ? AND (" . $DB->sql_like('bi.pagetypepattern', '?') . "
                           OR bi.pagetypepattern IN ('site-index', 'course-*', '*')))
                       AND bd.type = 'text'
                       $restrictions
              ORDER BY bd.timemodified ASC",
                array_merge($contextparams, [CONTEXT_BLOCK, CONTEXT_MODULE, CONTEXT_COURSE,
                    $modifiedfrom, $this->get_block_name(), CONTEXT_COURSE, 'course-view-%'],
                $restrictionparams));
    }

    /**
     * Returns the document related with the provided record.
     *
     * This method receives a record with the document id and other info returned by get_recordset_by_timestamp
     * or get_recordset_by_contexts that might be useful here. The idea is to restrict database queries to
     * minimum as this function will be called for each document to index. As an alternative, use cached data.
     *
     * Internally it should use \core_search\document to standarise the documents before sending them to the search engine.
     *
     * Search areas should send plain text to the search engine, use the following function to convert any user
     * input data to plain text: content_to_text
     *
     * Valid keys for the options array are:
     *     indexfiles => File indexing is enabled if true.
     *     lastindexedtime => The last time this area was indexed. 0 if never indexed.
     *
     * The lastindexedtime value is not set if indexing a specific context rather than the whole
     * system.
     *
     * @param \stdClass $record A record containing, at least, the indexed document id and a modified timestamp
     * @param array     $options Options for document creation
     * @return \core_search\document
     */
    public function get_document($record, $options = array()) {
        // Create empty document.
        $doc = \core_search\document_factory::instance($record->id,
                $this->componentname, $this->areaname);

        // Get stdclass object with data from DB.
        $data = json_decode($record->configdata);

        // Get content.
        $content = content_to_text($data->content, FORMAT_MOODLE);
        $doc->set('content', $content);

        if (!empty($data->name)) {
            // If there is a name, use it as title.
            $doc->set('title', content_to_text($data->name, false));
        } else {
            // If there is no name, use the content text again.
            $doc->set('title', shorten_text($content));
        }

        // Set standard fields.
        $doc->set('contextid', $record->contextid);
        $doc->set('type', \core_search\manager::TYPE_TEXT);
        $doc->set('courseid', $record->courseid);
        $doc->set('modified', $record->timemodified);
        $doc->set('owneruserid', \core_search\manager::NO_OWNER_ID);

        // Mark document new if appropriate.
        if (isset($options['lastindexedtime']) &&
                ($options['lastindexedtime'] < $record->timecreated)) {
            // If the document was created after the last index time, it must be new.
            $doc->set_is_new(true);
        }

        return $doc;
    }

    /**
     * Returns restrictions on which block_instances rows to return. By default, excludes rows
     * that have empty configdata.
     *
     * If no restriction is required, you could return ['', []].
     *
     * @return array 2-element array of SQL restriction and params for it
     */
    protected function get_indexing_restrictions() {
        return ['', []];
    }

    /**
     * Returns a url to the document, it might match self::get_context_url().
     *
     * @param \core_search\document $doc
     * @return \moodle_url
     */
    public function get_doc_url(\core_search\document $doc) {
        global $DB;

        // Load block instance and find cmid if there is one.
        $contextid = preg_replace('~^.*-~', '', $doc->get('contextid'));
        $context = context::instance_by_id($contextid);
        $blockinstanceid = $context->instanceid;
        $instance = $this->get_block_instance($blockinstanceid);
        $courseid = $doc->get('courseid');
        $anchor = 'inst' . $blockinstanceid;

        // Check if the block is at course or module level.
        if ($instance->cmid) {
            // No module-level page types are supported at present so the search system won't return
            // them. But let's put some example code here to indicate how it could work.
            debugging('Unexpected module-level page type for block ' . $blockinstanceid . ': ' .
                    $instance->pagetypepattern, DEBUG_DEVELOPER);
            $modinfo = get_fast_modinfo($courseid);
            $cm = $modinfo->get_cm($instance->cmid);
            return new \moodle_url($cm->url, null, $anchor);
        } else {
            // The block is at course level. Let's check the page type, although in practice we
            // currently only support the course main page.
            if ($instance->pagetypepattern === '*' || $instance->pagetypepattern === 'course-*' ||
                    preg_match('~^course-view-(.*)$~', $instance->pagetypepattern)) {
                return new \moodle_url('/course/view.php', ['id' => $courseid], $anchor);
            } else if ($instance->pagetypepattern === 'site-index') {
                return new \moodle_url('/', ['redirect' => 0], $anchor);
            } else {
                debugging('Unexpected page type for block ' . $blockinstanceid . ': ' .
                        $instance->pagetypepattern, DEBUG_DEVELOPER);
                return new \moodle_url('/course/view.php', ['id' => $courseid], $anchor);
            }
        }
    }

    /**
     * This can be used in subclasses to change ordering within the get_contexts_to_reindex
     * function.
     *
     * It returns 2 values:
     * - Extra SQL joins (tables block_instances 'bi' and context 'x' already exist).
     * - An ORDER BY value which must use aggregate functions, by default 'MAX(bi.timemodified) DESC'.
     *
     * Note the query already includes a GROUP BY on the context fields, so if your joins result
     * in multiple rows, you can use aggregate functions in the ORDER BY. See forum for an example.
     *
     * @return string[] Array with 2 elements; extra joins for the query, and ORDER BY value
     */
    protected function get_contexts_to_reindex_extra_sql() {
        return ['JOIN {block_deft} bd ON bd.instance = bi.id', 'MAX(bd.timemodified) DESC'];
    }

    /**
     * Checks access for a document in this search area.
     *
     * If you override this function for a block, you should call this base class version first
     * as it will check that the block is still visible to users in a supported location.
     *
     * @param int $id Document id
     * @return int manager:ACCESS_xx constant
     */
    public function check_access($id) {
        global $DB;

        // Load block instance and find cmid if there is one.
        $blockinstanceid = $DB->get_field('block_deft', 'instance', ['id' => $id]);
        $instance = $this->get_block_instance($blockinstanceid, IGNORE_MISSING);
        if (!$instance) {
            // This generally won't happen because if the block has been deleted then we won't have
            // included its context in the search area list, but just in case.
            return manager::ACCESS_DELETED;
        }

        // Check block has not been moved to an unsupported area since it was indexed. (At the
        // moment, only blocks within site and course context are supported, also only certain
        // page types).
        if (!$instance->courseid ||
                !self::is_supported_page_type_at_course_context($instance->pagetypepattern)) {
            return manager::ACCESS_DELETED;
        }

        // Note we do not need to check if the block was hidden or if the user has access to the
        // context, because those checks are included in the list of search contexts user can access
        // that is calculated in manager.php every time they do a query.
        $task = task::get_record(['id' => $id]);
        $state = $task->get_state();
        $context = context_block::instance($blockinstanceid);
        if (
            empty($state->visible)
            && !has_capability('block/deft:manage', $context)
        ) {
            return manager::ACCESS_DENIED;
        }

        return manager::ACCESS_GRANTED;
    }

    /**
     * Returns an icon instance for the document.
     *
     * @param \core_search\document $doc
     * @return \core_search\document_icon
     */
    public function get_doc_icon(document $doc) : document_icon {
        return new document_icon('f/visual_blocks');
    }
}
