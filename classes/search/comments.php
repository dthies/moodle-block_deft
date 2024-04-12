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
class comments extends text {
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
         [$contextjoin, $contextparams] = $this->get_context_restriction_sql($context, 'bi');

        // Get custom restrictions for block type.
         [$restrictions, $restrictionparams] = $this->get_indexing_restrictions();
        if ($restrictions) {
            $restrictions = 'AND ' . $restrictions;
        }

        // Query for all entries in block_instances for this type of block, within the specified
        // context. The query is based on the one from get_recordset_by_timestamp and applies the
        // same restrictions.
        return $DB->get_recordset_sql(
            "
                SELECT cmt.id, cmt.timecreated AS timemodified, cmt.content, bd.configdata, cmt.userid,
                       c.id AS courseid, x.id AS contextid
                  FROM {block_instances} bi
                       $contextjoin
                  JOIN {context} x ON x.instanceid = bi.id AND x.contextlevel = ?
                  JOIN {context} parent ON parent.id = bi.parentcontextid
                  JOIN {block_deft} bd ON bd.instance = bi.id
                  JOIN {comments} cmt ON bd.id = cmt.itemid
             LEFT JOIN {course_modules} cm ON cm.id = parent.instanceid AND parent.contextlevel = ?
                  JOIN {course} c ON c.id = cm.course
                       OR (c.id = parent.instanceid AND parent.contextlevel = ?)
                 WHERE cmt.timecreated >= ?
                       AND bi.blockname = ?
                       AND (parent.contextlevel = ? AND (" . $DB->sql_like('bi.pagetypepattern', '?') . "
                           OR bi.pagetypepattern IN ('site-index', 'course-*', '*')))
                       AND bd.type = 'comments'
                       AND cmt.component = 'block_deft'
                       AND cmt.commentarea = 'task'
                       $restrictions
              ORDER BY cmt.timecreated ASC",
            array_merge($contextparams, [
                    CONTEXT_BLOCK, CONTEXT_MODULE, CONTEXT_COURSE,
                    $modifiedfrom,
                    $this->get_block_name(),
                    CONTEXT_COURSE,
                    'course-view-%',
                ], $restrictionparams)
        );
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
     * input data to plain text:
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
    public function get_document($record, $options = []) {
        // Get stdclass object with data from DB.
        $data = json_decode($record->configdata);

        $data->content = $record->content;
        $record->configdata = json_encode($data);
        return parent::get_document($record, $options);
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
        return [
            "JOIN {block_deft} bd ON bd.instance = bi.id
             JOIN {comments} cmt ON cmt.itemid = bd.id AND cmt.commentarea = 'task' AND cmt.component = 'block_deft'",
            'MAX(bd.timemodified, cmt.timecreated) DESC',
        ];
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

        $comment = $DB->get_record('comments', [
            'id' => $id,
        ]);
        if (!$comment) {
            return manager::ACCESS_DELETED;
        }
        // Load block instance and find cmid if there is one.
        return parent::check_access($comment->itemid);
    }

    /**
     * Returns an icon instance for the document.
     *
     * @param \core_search\document $doc
     * @return \core_search\document_icon
     */
    public function get_doc_icon(document $doc): document_icon {
        return new document_icon('i/feedback');
    }
}
