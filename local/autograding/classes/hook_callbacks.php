<?php
declare(strict_types=1);
namespace local_autograding;

use core\hook\output\before_http_headers;

defined('MOODLE_INTERNAL') || die();

class hook_callbacks
{
    public static function before_http_headers(before_http_headers $hook): void
    {
        global $PAGE, $DB;

        if ($PAGE->pagetype !== 'mod-assign-grading' && $PAGE->pagetype !== 'mod-assign-view') {
            return;
        }

        $cmid = optional_param('id', 0, PARAM_INT);
        if ($cmid <= 0) {
            return;
        }

        $autogradingconfig = $DB->get_record('local_autograding', ['cmid' => $cmid]);
        if (!$autogradingconfig || (int) $autogradingconfig->autograding_option === 0) {
            return;
        }

        $context = \context_module::instance($cmid);
        if (!has_capability('mod/assign:grade', $context)) {
            return;
        }

        $url = new \moodle_url('/local/autograding/grading_progress.php', ['cmid' => $cmid]);
        $buttonhtml = '<a href="' . $url->out() . '" class="btn btn-primary ml-2">' .
            get_string('grading_progress_title', 'local_autograding') . '</a>';

        $PAGE->requires->js_amd_inline("
            require(['jquery'], function($) {
                $(document).ready(function() {
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
