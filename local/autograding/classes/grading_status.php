<?php
declare(strict_types=1);

/**
 * Grading status helper class for local_autograding plugin.
 *
 * Manages grading status records for tracking submission grading progress.
 *
 * @package    local_autograding
 * @copyright  2025 Nguyen Huu Trinh
 */

namespace local_autograding;

defined('MOODLE_INTERNAL') || die();

/**
 * Helper class for managing grading status records.
 */
class grading_status
{
    /** Status constants */
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILED = 'failed';

    /** Maximum retry attempts before permanent failure */
    public const MAX_ATTEMPTS = 5;

    /**
     * Create or update a status record for a submission.
     *
     * @param int $cmid Course module ID
     * @param int $userid User ID
     * @param int $submissionid Submission ID
     * @param string $status Status value
     * @return int The status record ID
     */
    public static function create_or_update(int $cmid, int $userid, int $submissionid, string $status = self::STATUS_PENDING): int
    {
        global $DB;

        $now = time();
        $existing = $DB->get_record('local_autograding_status', ['submissionid' => $submissionid]);

        if ($existing) {
            $existing->status = $status;
            $existing->timemodified = $now;
            $DB->update_record('local_autograding_status', $existing);
            return (int) $existing->id;
        }

        $record = new \stdClass();
        $record->cmid = $cmid;
        $record->userid = $userid;
        $record->submissionid = $submissionid;
        $record->status = $status;
        $record->attempts = 0;
        $record->timecreated = $now;
        $record->timemodified = $now;

        return (int) $DB->insert_record('local_autograding_status', $record);
    }

    /**
     * Update status to processing and increment attempt counter.
     *
     * @param int $submissionid Submission ID
     * @return bool True if updated
     */
    public static function set_processing(int $submissionid): bool
    {
        global $DB;

        $record = $DB->get_record('local_autograding_status', ['submissionid' => $submissionid]);
        if (!$record) {
            return false;
        }

        $record->status = self::STATUS_PROCESSING;
        $record->attempts = (int) $record->attempts + 1;
        $record->timemodified = time();

        return $DB->update_record('local_autograding_status', $record);
    }

    /**
     * Mark grading as successful.
     *
     * @param int $submissionid Submission ID
     * @return bool True if updated
     */
    public static function set_success(int $submissionid): bool
    {
        global $DB;

        $record = $DB->get_record('local_autograding_status', ['submissionid' => $submissionid]);
        if (!$record) {
            return false;
        }

        $record->status = self::STATUS_SUCCESS;
        $record->error_message = null;
        $record->timemodified = time();
        $record->timegraded = time();

        return $DB->update_record('local_autograding_status', $record);
    }

    /**
     * Mark grading as failed with error message.
     *
     * @param int $submissionid Submission ID
     * @param string $errorMessage Error message
     * @return bool True if updated
     */
    public static function set_failed(int $submissionid, string $errorMessage): bool
    {
        global $DB;

        $record = $DB->get_record('local_autograding_status', ['submissionid' => $submissionid]);
        if (!$record) {
            return false;
        }

        $record->status = self::STATUS_FAILED;
        $record->error_message = $errorMessage;
        $record->timemodified = time();

        return $DB->update_record('local_autograding_status', $record);
    }

    /**
     * Get status record by submission ID.
     *
     * @param int $submissionid Submission ID
     * @return object|false Status record or false
     */
    public static function get_by_submission(int $submissionid)
    {
        global $DB;
        return $DB->get_record('local_autograding_status', ['submissionid' => $submissionid]);
    }

    /**
     * Get all status records for an assignment.
     *
     * @param int $cmid Course module ID
     * @return array Array of status records with user info
     */
    public static function get_all_for_assignment(int $cmid): array
    {
        global $DB;

        // Include all name fields required by fullname() function.
        $sql = "SELECT s.*, u.firstname, u.lastname, u.firstnamephonetic, 
                       u.lastnamephonetic, u.middlename, u.alternatename, u.email
                FROM {local_autograding_status} s
                JOIN {user} u ON u.id = s.userid
                WHERE s.cmid = :cmid
                ORDER BY s.timemodified DESC";

        return $DB->get_records_sql($sql, ['cmid' => $cmid]);
    }

    /**
     * Get failed records that haven't been notified yet (for digest).
     *
     * @param int $since Timestamp to check from
     * @return array Array of failed records grouped by course
     */
    public static function get_failed_for_digest(int $since): array
    {
        global $DB;

        $sql = "SELECT s.*, cm.course, u.firstname, u.lastname, u.firstnamephonetic,
                       u.lastnamephonetic, u.middlename, u.alternatename
                FROM {local_autograding_status} s
                JOIN {course_modules} cm ON cm.id = s.cmid
                JOIN {user} u ON u.id = s.userid
                WHERE s.status = :status
                AND s.timemodified >= :since
                ORDER BY cm.course, s.cmid";

        return $DB->get_records_sql($sql, [
            'status' => self::STATUS_FAILED,
            'since' => $since,
        ]);
    }

    /**
     * Check if max attempts reached.
     *
     * @param int $submissionid Submission ID
     * @return bool True if max attempts reached
     */
    public static function is_max_attempts_reached(int $submissionid): bool
    {
        $record = self::get_by_submission($submissionid);
        if (!$record) {
            return false;
        }
        return (int) $record->attempts >= self::MAX_ATTEMPTS;
    }

    /**
     * Get current attempt count.
     *
     * @param int $submissionid Submission ID
     * @return int Current attempt count
     */
    public static function get_attempts(int $submissionid): int
    {
        $record = self::get_by_submission($submissionid);
        return $record ? (int) $record->attempts : 0;
    }

    /**
     * Reset status to pending for retry.
     *
     * @param int $submissionid Submission ID
     * @return bool True if updated
     */
    public static function reset_for_retry(int $submissionid): bool
    {
        global $DB;

        $record = $DB->get_record('local_autograding_status', ['submissionid' => $submissionid]);
        if (!$record) {
            return false;
        }

        $record->status = self::STATUS_PENDING;
        $record->attempts = 0;
        $record->error_message = null;
        $record->timemodified = time();

        return $DB->update_record('local_autograding_status', $record);
    }

    /**
     * Get summary counts for an assignment.
     *
     * @param int $cmid Course module ID
     * @return array Counts by status
     */
    public static function get_summary(int $cmid): array
    {
        global $DB;

        $sql = "SELECT status, COUNT(*) as count
                FROM {local_autograding_status}
                WHERE cmid = :cmid
                GROUP BY status";

        $results = $DB->get_records_sql($sql, ['cmid' => $cmid]);

        $summary = [
            self::STATUS_PENDING => 0,
            self::STATUS_PROCESSING => 0,
            self::STATUS_SUCCESS => 0,
            self::STATUS_FAILED => 0,
        ];

        foreach ($results as $row) {
            $summary[$row->status] = (int) $row->count;
        }

        return $summary;
    }
}
