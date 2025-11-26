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
 * Section output class for adaptive format - handles single section view.
 *
 * @package    format_adaptive1
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace format_adaptive1\output\courseformat\content;

use core_courseformat\output\local\content\section as section_base;
use renderer_base;
use stdClass;
use context_course;

/**
 * Section content output class for adaptive format.
 * 
 * This class handles the rendering of individual section pages (course/section.php)
 * and applies the adaptive learning filtering logic to hide modules based on
 * difficulty progression and completion requirements.
 */
class section extends section_base {

    /**
     * Helper log function for debugging directly in this class
     * 
     * @param string $label The label for the log entry
     * @param mixed $data Optional data to log
     */
    private function console_log($label, $data = null) {
        global $CFG;
        // Uncomment the line below to disable logging when not debugging
        // if (empty($CFG->debugdisplay)) { return; }

        $json_data = json_encode($data);
        echo "<script>console.log('%c[SECTION] $label', 'color: #FF6600; font-weight: bold;', $json_data);</script>";
    }

    /**
     * Export section content for template with adaptive filtering.
     *
     * This method overrides the parent to apply adaptive learning logic
     * for students viewing a single section page.
     *
     * @param renderer_base $output The renderer object
     * @return stdClass The data for rendering
     */
    public function export_for_template(renderer_base $output): stdClass {
        global $USER;

        // Get the base data from parent class
        $data = parent::export_for_template($output);

        // Get course and section information
        $format = $this->format;
        $course = $format->get_course();
        $section = $this->section;
        $context = context_course::instance($course->id);

        $this->console_log("SINGLE SECTION VIEW - Section #" . $section->section);

        // If user has editing capability (teacher/admin), show everything
        if (has_capability('moodle/course:update', $context)) {
            $this->console_log("User is Teacher/Admin - Show all modules");
            return $data;
        }

        // Student view - apply adaptive filtering
        $userid = $USER->id;
        $modinfo = get_fast_modinfo($course, $userid);

        $this->console_log("START FILTERING FOR STUDENT", [
            "User ID" => $userid,
            "Section Number" => $section->section
        ]);

        // First, check if the student can view this section at all
        $can_view_section = \format_adaptive1\utils::check_section_conditions($course, $userid, $section);

        if (!$can_view_section) {
            $this->console_log("SECTION ACCESS DENIED", [
                "Section" => $section->section,
                "Reason" => "Does not meet section unlock requirements"
            ]);
            
            // Clear all modules if section shouldn't be visible
            // Note: In practice, Moodle's access control should prevent reaching here,
            // but we handle it for completeness
            if (isset($data->cmlist) && isset($data->cmlist->cms)) {
                $data->cmlist->cms = [];
            }
            return $data;
        }

        $this->console_log("Section access granted - Now filtering modules");

        // Filter modules within this section
        if (isset($data->cmlist) && isset($data->cmlist->cms) && !empty($data->cmlist->cms)) {
            $filteredcms = [];
            $hidden_count = 0;
            $visible_count = 0;

            foreach ($data->cmlist->cms as $cmdata) {
                // The cmdata object has a 'cmitem' property that contains the actual module data
                // Access the ID through cmitem->id
                if (!isset($cmdata->cmitem) || !isset($cmdata->cmitem->id)) {
                    $this->console_log("Invalid cmdata structure", $cmdata);
                    continue;
                }

                $cmid = $cmdata->cmitem->id;

                // Get course module object
                $cm = $modinfo->get_cm($cmid);

                if (!$cm) {
                    $this->console_log("Module not found in modinfo", ["CM ID" => $cmid]);
                    continue;
                }

                // Log which module we're checking
                $this->console_log("Checking Module", [
                    "Name" => $cm->name,
                    "ID" => $cm->id,
                    "Module Type" => $cm->modname
                ]);

                // Check if student can access this module based on difficulty
                $can_view_module = \format_adaptive1\utils::check_module_conditions(
                    $course, 
                    $userid, 
                    $cm, 
                    $section
                );

                if (!$can_view_module) {
                    // Module should be hidden
                    $this->console_log("--> Module HIDDEN", [
                        "Name" => $cm->name,
                        "Difficulty" => \format_adaptive1\utils::get_module_difficulty($cm),
                        "Reason" => "Does not meet difficulty progression requirements"
                    ]);
                    $hidden_count++;
                    continue;
                }

                // Module is visible
                $this->console_log("--> Module VISIBLE", [
                    "Name" => $cm->name,
                    "Difficulty" => \format_adaptive1\utils::get_module_difficulty($cm)
                ]);
                $visible_count++;
                $filteredcms[] = $cmdata;
            }

            // Replace the cms array with filtered results
            $data->cmlist->cms = array_values($filteredcms);

            $this->console_log("FILTERING COMPLETE", [
                "Total Modules Processed" => ($hidden_count + $visible_count),
                "Visible Modules" => $visible_count,
                "Hidden Modules" => $hidden_count
            ]);
        } else {
            $this->console_log("No modules found in section cmlist");
        }

        return $data;
    }
}