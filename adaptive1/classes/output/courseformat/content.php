<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

namespace format_adaptive1\output\courseformat;

use core_courseformat\output\local\content as content_base;
use renderer_base;
use stdClass;
use context_course;

/**
 * Content output class for adaptive format.
 */
class content extends content_base {

    /**
     * Helper log function for debugging directly in this class
     */
    private function console_log($label, $data = null) {
        global $CFG;
        // Bỏ comment dòng dưới nếu muốn tắt log khi không debug
        // if (empty($CFG->debugdisplay)) { return; }

        $json_data = json_encode($data);
        echo "<script>console.log('%c[CONTENT] $label', 'color: #0077FF; font-weight: bold;', $json_data);</script>";
    }

    /**
     * Export content for template with adaptive filtering.
     */
    public function export_for_template(renderer_base $output): stdClass {
        global $USER;

        // Get the base data from parent.
        $data = parent::export_for_template($output);

        // Get course context.
        $course = $this->format->get_course();
        $context = context_course::instance($course->id);

        // If user has editing capability (teacher/admin), show everything.
        if (has_capability('moodle/course:update', $context)) {
            return $data;
        }

        // Student view - apply adaptive filtering.
        $userid = $USER->id;
        $modinfo = get_fast_modinfo($course, $userid);
        
        $this->console_log("START FILTERING FOR STUDENT", ["User ID" => $userid]);

        // Filter sections.
        if (!empty($data->sections)) {
            $filteredsections = [];

            foreach ($data->sections as $sectiondata) {
                // Get section object.
                $section = $modinfo->get_section_info($sectiondata->num);
                
                $this->console_log("DATA CỦA SECTION " . ($sectiondata->num ?? 'Unknown'), $sectiondata);
                if (!$section) { continue; }

                // Check if student can access this section.
                $can_view_section = \format_adaptive1\utils::check_section_conditions($course, $userid, $section);
                
                if (!$can_view_section) {
                    // Log section bị ẩn
                    $this->console_log("Section HIDDEN: " . $sectiondata->num);
                    continue;
                }

                // Filter modules within this section.
                // if (!empty($sectiondata->cmlist->cms)) {
                //     $filteredcms = [];

                //     foreach ($sectiondata->cmlist->cms as $cmdata) {
                //         // Get course module object.
                //         // $cmitem = $cmdata->cmitem;
                //         $cm = $modinfo->get_cm($cmdata->cmitem->id);

                //         // $this->console_log("Checking Module: " . $cm);
                //         if (!$cm) { 
                //             $this->console_log("Không có cm");
                //             continue; 
                //         }

                //         // LOG: Đang kiểm tra module nào
                //         // $this->console_log("Checking Module: " . $cmdata->cmitem->name);

                //         // Check if student can access this module.
                //         $can_view_module = \format_adaptive1\utils::check_module_conditions($course, $userid, $cm, $section);

                //         if (!$can_view_module) {
                //             // Log module bị ẩn -> Đây là chỗ quan trọng bạn cần xem
                //             $this->console_log("--> Module HIDDEN (Removed form list): " . $cmdata->cmitem->name);
                //             continue;
                //         }

                //         $filteredcms[] = $cmdata;
                //     }

                //     // Re-index the cms array.
                //     $sectiondata->cmlist->cms = array_values($filteredcms);
                // } else{
                //     $this->console_log("Không có module trong section", $sectiondata->num);
                // }

                $filteredsections[] = $sectiondata;
            }

            // Re-index the sections array.
            $data->sections = array_values($filteredsections);
        }

        return $data;
    }
}