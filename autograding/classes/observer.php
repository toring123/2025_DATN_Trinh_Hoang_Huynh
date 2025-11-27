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
        global $DB;

        // Only process assign modules.
        if ($event->other['modulename'] !== 'assign') {
            return;
        }

        $cmid = $event->objectid;
        if ($cmid <= 0) {
            return;
        }

        // Get the autograding option from the form data.
        $data = $event->other['instanceid'] ?? null;
        
        // Try to get data from optional_param if available in the request.
        $autogradingoption = optional_param('autograding_option', null, PARAM_INT);

        if ($autogradingoption === null) {
            return;
        }

        // Save the option.
        local_autograding_save_option($cmid, $autogradingoption);
    }

    /**
     * Handle course module updated event.
     *
     * @param course_module_updated $event The event
     * @return void
     */
    public static function course_module_updated(course_module_updated $event): void {
        global $DB;

        // Only process assign modules.
        if ($event->other['modulename'] !== 'assign') {
            return;
        }

        $cmid = $event->objectid;
        if ($cmid <= 0) {
            return;
        }

        // Get the autograding option from the request.
        $autogradingoption = optional_param('autograding_option', null, PARAM_INT);

        if ($autogradingoption === null) {
            return;
        }

        // Save or update the option.
        local_autograding_save_option($cmid, $autogradingoption);
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