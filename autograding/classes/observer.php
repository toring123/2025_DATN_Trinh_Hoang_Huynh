<?php
declare(strict_types=1);

/**
 * Event observer class for local_autograding plugin.
 *
 * @package    local_autograding
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_autograding;

use core\event\course_module_created;
use core\event\course_module_updated;
use core\event\course_module_deleted;

defined('MOODLE_INTERNAL') || die();

/**
 * Event observer class.
 */
class observer {

    /**
     * Handle course module created event.
     *
     * @param course_module_created $event The event
     * @return void
     */
    public static function course_module_created(course_module_created $event): void {
        // Only process assign modules.
        if ($event->other['modulename'] !== 'assign') {
            return;
        }

        $cmid = (int)$event->objectid;
        if ($cmid <= 0) {
            return;
        }

        // The data should already be saved by local_autograding_coursemodule_edit_post_actions.
        // This is a backup in case that hook doesn't fire.
        self::save_from_request($cmid);
    }

    /**
     * Handle course module updated event.
     *
     * @param course_module_updated $event The event
     * @return void
     */
    public static function course_module_updated(course_module_updated $event): void {
        // Only process assign modules.
        if ($event->other['modulename'] !== 'assign') {
            return;
        }

        $cmid = (int)$event->objectid;
        if ($cmid <= 0) {
            return;
        }

        // The data should already be saved by local_autograding_coursemodule_edit_post_actions.
        // This is a backup in case that hook doesn't fire.
        self::save_from_request($cmid);
    }

    /**
     * Helper function to save data from request parameters.
     *
     * @param int $cmid Course module ID
     * @return void
     */
    private static function save_from_request(int $cmid): void {
        // Get the autograding option from the form data.
        $autogradingoption = optional_param('autograding_option', null, PARAM_INT);

        if ($autogradingoption === null) {
            return;
        }

        // Get the text answer if provided.
        $textanswer = null;
        if ($autogradingoption === 2) {
            $textanswer = optional_param('autograding_text_answer', '', PARAM_TEXT);
            $textanswer = trim($textanswer);
            
            // Ensure it's not empty for option 2.
            if (empty($textanswer)) {
                $textanswer = null;
            }
        }

        // Save the option with answer.
        local_autograding_save_option($cmid, $autogradingoption, $textanswer);
    }

    /**
     * Handle course module deleted event.
     *
     * @param course_module_deleted $event The event
     * @return void
     */
    public static function course_module_deleted(course_module_deleted $event): void {
        // Only process assign modules.
        if ($event->other['modulename'] !== 'assign') {
            return;
        }

        $cmid = $event->objectid;
        if ($cmid <= 0) {
            return;
        }

        // Delete the autograding option.
        local_autograding_delete_option($cmid);
    }
}