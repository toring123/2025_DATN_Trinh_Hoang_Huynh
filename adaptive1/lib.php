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
 * Adaptive Learning course format.
 *
 * @package    format_adaptive1
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/course/format/topics/lib.php');

/**
 * Main class for the Adaptive Learning course format.
 *
 * @package    format_adaptive1
 * @copyright  2025 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_adaptive1 extends format_topics {

    /**
     * Returns the list of course format options and their properties.
     *
     * @param bool $foreditform Whether the format options are for edit form.
     * @return array Array of option definitions.
     */
    public function course_format_options($foreditform = false): array {
        static $courseformatoptions = false;
        
        if ($courseformatoptions === false) {
            $courseformatoptions = [
                'hiddensections' => [
                    'default' => 0,
                    'type' => PARAM_INT,
                ],
                'coursedisplay' => [
                    'default' => COURSE_DISPLAY_SINGLEPAGE,
                    'type' => PARAM_INT,
                ],
                'unlock_diff2_count' => [
                    'default' => 0,
                    'type' => PARAM_INT,
                ],
                'unlock_diff2_grade' => [
                    'default' => 0,
                    'type' => PARAM_INT,
                ],
                'unlock_diff3_count' => [
                    'default' => 0,
                    'type' => PARAM_INT,
                ],
                'unlock_diff3_grade' => [
                    'default' => 0,
                    'type' => PARAM_INT,
                ],
                'unlock_diff4_count' => [
                    'default' => 0,
                    'type' => PARAM_INT,
                ],
                'unlock_diff4_grade' => [
                    'default' => 0,
                    'type' => PARAM_INT,
                ],
                'unlock_nextsection_count' => [
                    'default' => 0,
                    'type' => PARAM_INT,
                ],
                'unlock_nextsection_grade' => [
                    'default' => 0,
                    'type' => PARAM_INT,
                ],
                'unlock_nextsection_coursegrade' => [
                    'default' => 0,
                    'type' => PARAM_INT,
                ],
            ];
        }
        
        if ($foreditform && !isset($courseformatoptions['hiddensections']['label'])) {
            // Add form-specific properties to each option.
            $courseformatoptions['hiddensections']['label'] = new lang_string('hiddensections');
            $courseformatoptions['hiddensections']['help'] = 'hiddensections';
            $courseformatoptions['hiddensections']['help_component'] = 'moodle';
            $courseformatoptions['hiddensections']['element_type'] = 'select';
            $courseformatoptions['hiddensections']['element_attributes'] = [
                [
                    0 => new lang_string('hiddensectionscollapsed'),
                    1 => new lang_string('hiddensectionsinvisible'),
                ],
            ];
            
            $courseformatoptions['coursedisplay']['label'] = new lang_string('coursedisplay');
            $courseformatoptions['coursedisplay']['element_type'] = 'select';
            $courseformatoptions['coursedisplay']['element_attributes'] = [
                [
                    COURSE_DISPLAY_SINGLEPAGE => new lang_string('coursedisplay_single'),
                    COURSE_DISPLAY_MULTIPAGE => new lang_string('coursedisplay_multi'),
                ],
            ];
            $courseformatoptions['coursedisplay']['help'] = 'coursedisplay';
            $courseformatoptions['coursedisplay']['help_component'] = 'moodle';
            
            $courseformatoptions['unlock_diff2_count']['label'] = new lang_string('unlock_diff2_count', 'format_adaptive1');
            $courseformatoptions['unlock_diff2_count']['help'] = 'unlock_diff2_count';
            $courseformatoptions['unlock_diff2_count']['help_component'] = 'format_adaptive1';
            $courseformatoptions['unlock_diff2_count']['element_type'] = 'text';
            $courseformatoptions['unlock_diff2_count']['element_attributes'] = [['size' => 5]];
            
            $courseformatoptions['unlock_diff2_grade']['label'] = new lang_string('unlock_diff2_grade', 'format_adaptive1');
            $courseformatoptions['unlock_diff2_grade']['help'] = 'unlock_diff2_grade';
            $courseformatoptions['unlock_diff2_grade']['help_component'] = 'format_adaptive1';
            $courseformatoptions['unlock_diff2_grade']['element_type'] = 'text';
            $courseformatoptions['unlock_diff2_grade']['element_attributes'] = [['size' => 5]];
            
            $courseformatoptions['unlock_diff3_count']['label'] = new lang_string('unlock_diff3_count', 'format_adaptive1');
            $courseformatoptions['unlock_diff3_count']['help'] = 'unlock_diff3_count';
            $courseformatoptions['unlock_diff3_count']['help_component'] = 'format_adaptive1';
            $courseformatoptions['unlock_diff3_count']['element_type'] = 'text';
            $courseformatoptions['unlock_diff3_count']['element_attributes'] = [['size' => 5]];
            
            $courseformatoptions['unlock_diff3_grade']['label'] = new lang_string('unlock_diff3_grade', 'format_adaptive1');
            $courseformatoptions['unlock_diff3_grade']['help'] = 'unlock_diff3_grade';
            $courseformatoptions['unlock_diff3_grade']['help_component'] = 'format_adaptive1';
            $courseformatoptions['unlock_diff3_grade']['element_type'] = 'text';
            $courseformatoptions['unlock_diff3_grade']['element_attributes'] = [['size' => 5]];
            
            $courseformatoptions['unlock_diff4_count']['label'] = new lang_string('unlock_diff4_count', 'format_adaptive1');
            $courseformatoptions['unlock_diff4_count']['help'] = 'unlock_diff4_count';
            $courseformatoptions['unlock_diff4_count']['help_component'] = 'format_adaptive1';
            $courseformatoptions['unlock_diff4_count']['element_type'] = 'text';
            $courseformatoptions['unlock_diff4_count']['element_attributes'] = [['size' => 5]];
            
            $courseformatoptions['unlock_diff4_grade']['label'] = new lang_string('unlock_diff4_grade', 'format_adaptive1');
            $courseformatoptions['unlock_diff4_grade']['help'] = 'unlock_diff4_grade';
            $courseformatoptions['unlock_diff4_grade']['help_component'] = 'format_adaptive1';
            $courseformatoptions['unlock_diff4_grade']['element_type'] = 'text';
            $courseformatoptions['unlock_diff4_grade']['element_attributes'] = [['size' => 5]];
            
            $courseformatoptions['unlock_nextsection_count']['label'] = new lang_string('unlock_nextsection_count', 'format_adaptive1');
            $courseformatoptions['unlock_nextsection_count']['help'] = 'unlock_nextsection_count';
            $courseformatoptions['unlock_nextsection_count']['help_component'] = 'format_adaptive1';
            $courseformatoptions['unlock_nextsection_count']['element_type'] = 'text';
            $courseformatoptions['unlock_nextsection_count']['element_attributes'] = [['size' => 5]];
            
            $courseformatoptions['unlock_nextsection_grade']['label'] = new lang_string('unlock_nextsection_grade', 'format_adaptive1');
            $courseformatoptions['unlock_nextsection_grade']['help'] = 'unlock_nextsection_grade';
            $courseformatoptions['unlock_nextsection_grade']['help_component'] = 'format_adaptive1';
            $courseformatoptions['unlock_nextsection_grade']['element_type'] = 'text';
            $courseformatoptions['unlock_nextsection_grade']['element_attributes'] = [['size' => 5]];
            
            $courseformatoptions['unlock_nextsection_coursegrade']['label'] = new lang_string('unlock_nextsection_coursegrade', 'format_adaptive1');
            $courseformatoptions['unlock_nextsection_coursegrade']['help'] = 'unlock_nextsection_coursegrade';
            $courseformatoptions['unlock_nextsection_coursegrade']['help_component'] = 'format_adaptive1';
            $courseformatoptions['unlock_nextsection_coursegrade']['element_type'] = 'text';
            $courseformatoptions['unlock_nextsection_coursegrade']['element_attributes'] = [['size' => 5]];
        }
        
        return $courseformatoptions;
    }

    /**
     * Adds format options elements to the course/section edit form.
     *
     * @param MoodleQuickForm $mform Form object.
     * @param bool $forsection Whether this is for a section.
     * @return array Array of element names added.
     */
    public function create_edit_form_elements(&$mform, $forsection = false): array {
        $elements = parent::create_edit_form_elements($mform, $forsection);

        if ($forsection) {
            return $elements;
        }

        // Lấy options dưới chế độ edit form
        $options = $this->course_format_options(true);

        foreach ($options as $optionname => $option) {
            if (!empty($option['label'])) {

                if ($mform->elementExists($optionname)) {
                    continue;
                }
                // Tạo element
                $mform->addElement(
                    $option['element_type'],
                    $optionname,
                    $option['label'],
                    $option['element_attributes'][0] ?? []
                );

                // Help button
                if (isset($option['help'])) {
                    $mform->addHelpButton(
                        $optionname,
                        $option['help'],
                        $option['help_component']
                    );
                }

                // Default value
                if (isset($option['default'])) {
                    $mform->setDefault($optionname, $option['default']);
                }

                $elements[] = $mform->getElement($optionname);
            }
        }

        return $elements;
    }

    /**
     * Returns the default section name.
     *
     * @param stdClass $section Section object.
     * @return string Section name.
     */
    public function get_default_section_name($section): string {
        if ($section->section == 0) {
            return get_string('section0name', 'format_topics');
        }
        return get_string('sectionname', 'format_adaptive1') . ' ' . $section->section;
    }

    /**
     * Returns whether this format uses sections.
     *
     * @return bool Always true.
     */
    public function uses_sections(): bool {
        return true;
    }

    /**
     * Returns AJAX support information.
     *
     * @return stdClass AJAX support object.
     */
    public function supports_ajax(): stdClass {
        $ajaxsupport = new stdClass();
        $ajaxsupport->capable = true;
        return $ajaxsupport;
    }

    /**
     * Returns whether this format uses course index.
     *
     * @return bool Always true.
     */
    public function uses_course_index(): bool {
        return true;
    }

    /**
     * Returns whether this format uses indentation.
     *
     * @return bool Always true.
     */
    public function uses_indentation(): bool {
        return true;
    }

    /**
     * Whether this format allows triple visibility state.
     *
     * @param stdClass $cm Course module.
     * @param stdClass $section Section.
     * @return bool Always true.
     */
    public function allow_stealth_module_visibility($cm, $section): bool {
        return true;
    }
    /**
     * Get the output class name for courseformat components.
     *
     * @param string $area The output area name.
     * @return string|null The output class name or null if not found.
     */
    // public function get_output_classname(string $area) {
    //     $namespace = 'format_adaptive1\\output\\courseformat';
        
    //     // Map areas to class names.
    //     $validareas = [
    //         'content' => 'content',
    //         'state' => 'state',
    //     ];
        
    //     if (isset($validareas[$area])) {
    //         $classname = "$namespace\\{$validareas[$area]}";
    //         if (class_exists($classname)) {
    //             return $classname;
    //         }
    //     }
        
    //     // Fall back to parent implementation.
    //     return parent::get_output_classname($area);
    // }
}