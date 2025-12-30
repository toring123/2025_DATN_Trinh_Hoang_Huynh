<?php
declare(strict_types=1);

/**
 * Hook callbacks for local_autograding plugin.
 *
 * @package    local_autograding
 * @copyright  2025 Nguyen Huu Trinh
 */

namespace local_autograding;

use core\hook\output\before_http_headers;

defined('MOODLE_INTERNAL') || die();

/**
 * Hook callbacks for local_autograding plugin.
 */
class hook_callbacks
{
    /**
     * Callback for before_http_headers hook.
     *
     * Injects the grading progress button on assignment pages.
     *
     * @param before_http_headers $hook The hook instance
     */
    public static function before_http_headers(before_http_headers $hook): void
    {
        global $PAGE, $DB;

        // Only on assign grading page.
        if ($PAGE->pagetype !== 'mod-assign-grading' && $PAGE->pagetype !== 'mod-assign-view') {
            return;
        }

        // Get cmid from URL.
        $cmid = optional_param('id', 0, PARAM_INT);
        if ($cmid <= 0) {
            return;
        }

        // Check if autograding is enabled.
        $autogradingconfig = $DB->get_record('local_autograding', ['cmid' => $cmid]);
        if (!$autogradingconfig || (int) $autogradingconfig->autograding_option === 0) {
            return;
        }

        // Check capability.
        $context = \context_module::instance($cmid);
        if (!has_capability('mod/assign:grade', $context)) {
            return;
        }

        // Inject the button via JavaScript.
        $url = new \moodle_url('/local/autograding/grading_progress.php', ['cmid' => $cmid]);
        $buttonhtml = '<a href="' . $url->out() . '" class="btn btn-primary ml-2">' .
            get_string('grading_progress_title', 'local_autograding') . '</a>';

        $PAGE->requires->js_amd_inline("
            require(['jquery'], function($) {
                $(document).ready(function() {
                    // Try to add button near grading actions.
                    var targetSelectors = [
                        '.path-mod-assign .submissionlinks',
                        '.path-mod-assign .gradingactions',
                        '.path-mod-assign #page-content .region-main .action-buttons',
                        '.path-mod-assign .tertiary-navigation',
                        '.path-mod-assign #region-main-box h2:first'
                    ];

                    var inserted = false;
                    for (var i = 0; i < targetSelectors.length && !inserted; i++) {
                        var target = $(targetSelectors[i]);
                        if (target.length > 0) {
                            target.first().after('" . addslashes($buttonhtml) . "');
                            inserted = true;
                        }
                    }

                    // Fallback: add to the grading table action bar if exists.
                    if (!inserted) {
                        var actionbar = $('.gradingtable .submission-grading');
                        if (actionbar.length > 0) {
                            actionbar.prepend('" . addslashes($buttonhtml) . "');
                        }
                    }
                });
            });
        ");
    }
}
