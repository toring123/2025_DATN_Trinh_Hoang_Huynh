<?php
namespace local_autorestrict;

defined('MOODLE_INTERNAL') || die();

class observer {
    
    public static function course_module_created(\core\event\course_module_created $event) {
        self::apply_restriction_to_module($event->objectid, $event->courseid);
    }
    
    protected static function apply_restriction_to_module($cmid, $courseid) {
        global $DB;
        
        $cm = $DB->get_record('course_modules', ['id' => $cmid]);
        if (!$cm) {
            return;
        }
        
        $section = $DB->get_record('course_sections', ['id' => $cm->section]);
        if (!$section) {
            return;
        }
        
        $config = self::get_course_config($courseid);
        if (!$config || empty($config->enabled)) {
            return;
        }
        
        $tag = self::get_module_difficulty_tag($cmid);
        
        $availability = self::build_availability_conditions($section->section, $tag, $config);
        
        if ($availability) {
            $DB->set_field('course_modules', 'availability', json_encode($availability), ['id' => $cmid]);
        } else {
            $DB->set_field('course_modules', 'availability', null, ['id' => $cmid]);
        }
        
        \course_modinfo::purge_course_cache($courseid);
    }
    
    public static function get_course_config($courseid) {
        global $DB;
        return $DB->get_record('local_autorestrict_course', ['courseid' => $courseid]);
    }
    
    public static function apply_to_all_modules($courseid, $config, $overwrite = false) {
        global $DB;
        
        $results = ['applied' => 0, 'skipped' => 0, 'errors' => 0];
        
        $sql = "SELECT cm.id, cm.availability, cs.section
                FROM {course_modules} cm
                JOIN {course_sections} cs ON cs.id = cm.section
                WHERE cm.course = :courseid
                ORDER BY cs.section, cm.id";
        
        $modules = $DB->get_records_sql($sql, ['courseid' => $courseid]);
        
        foreach ($modules as $cm) {
            if (!$overwrite && !empty($cm->availability)) {
                $results['skipped']++;
                continue;
            }
            
            $tag = self::get_module_difficulty_tag($cm->id);
            
            $availability = self::build_availability_conditions($cm->section, $tag, $config);
            
            if ($availability) {
                try {
                    $DB->set_field('course_modules', 'availability', json_encode($availability), ['id' => $cm->id]);
                    $results['applied']++;
                } catch (\Exception $e) {
                    $results['errors']++;
                }
            } else {
                $results['skipped']++;
            }
        }
        
        \course_modinfo::purge_course_cache($courseid);
        
        return $results;
    }
    
    public static function apply_to_all_sections($courseid, $config, $overwrite = false) {
        global $DB;
        
        $results = ['applied' => 0, 'skipped' => 0, 'errors' => 0];
        
        $sections = $DB->get_records('course_sections', ['course' => $courseid], 'section ASC');
        
        foreach ($sections as $section) {
            if (!$overwrite && !empty($section->availability)) {
                $results['skipped']++;
                continue;
            }
            
            $availability = self::build_section_availability($section->section, $config);
            
            if ($availability) {
                try {
                    $DB->set_field('course_sections', 'availability', json_encode($availability), ['id' => $section->id]);
                    $results['applied']++;
                } catch (\Exception $e) {
                    $results['errors']++;
                }
            } else {
                $results['skipped']++;
            }
        }
        
        \course_modinfo::purge_course_cache($courseid);
        
        return $results;
    }
    
    public static function clear_all_module_restrictions($courseid) {
        global $DB;
        
        $count = $DB->count_records_select('course_modules', 
            'course = :courseid AND availability IS NOT NULL AND availability != :empty',
            ['courseid' => $courseid, 'empty' => '']);
        
        $DB->set_field('course_modules', 'availability', null, ['course' => $courseid]);
        \course_modinfo::purge_course_cache($courseid);
        
        return $count;
    }
    
    public static function clear_all_section_restrictions($courseid) {
        global $DB;
        
        $count = $DB->count_records_select('course_sections', 
            'course = :courseid AND availability IS NOT NULL AND availability != :empty',
            ['courseid' => $courseid, 'empty' => '']);
        
        $DB->set_field('course_sections', 'availability', null, ['course' => $courseid]);
        \course_modinfo::purge_course_cache($courseid);
        
        return $count;
    }
    
    protected static function build_section_availability($sectionnumber, $config) {
        $conditions = [];
        
        if ($sectionnumber <= 1) {
            return null;
        }
        
        if (!empty($config->require_previous_section)) {
            $previousSection = $sectionnumber - 1;
            $minCompletions = !empty($config->min_section_completions) ? (int)$config->min_section_completions : 1;
            
            $conditions[] = [
                'type' => 'sectioncomplete',
                'section' => $previousSection,
                'mincompletions' => $minCompletions
            ];
        }
        
        if (!empty($config->require_previous_grade)) {
            $previousSection = $sectionnumber - 1;
            $minGrade = !empty($config->min_section_grade) ? (float)$config->min_section_grade : 50;
            
            $conditions[] = [
                'type' => 'sectiongrade',
                'section' => $previousSection,
                'mingrade' => $minGrade
            ];
        }
        
        if (empty($conditions)) {
            return null;
        }
        
        $showWhenNotMet = empty($config->hide_completely);
        
        return [
            'op' => '&',
            'c' => $conditions,
            'showc' => array_fill(0, count($conditions), $showWhenNotMet)
        ];
    }
    
    protected static function get_module_difficulty_tag($cmid) {
        global $DB;
        
        $sql = "SELECT t.name
                FROM {tag} t
                JOIN {tag_instance} ti ON ti.tagid = t.id
                WHERE ti.itemid = :cmid
                AND ti.itemtype = 'course_modules'
                AND t.name IN ('diff1', 'diff2', 'diff3', 'diff4')
                ORDER BY t.name DESC";
        
        $tags = $DB->get_records_sql($sql, ['cmid' => $cmid]);
        
        if (empty($tags)) {
            return null;
        }
        
        foreach ($tags as $tag) {
            return $tag->name;
        }
        
        return null;
    }
    
    public static function set_module_difficulty($cmid, $difftag) {
        global $DB;
        
        $cm = $DB->get_record('course_modules', ['id' => $cmid]);
        if (!$cm) {
            return false;
        }
        
        $context = \context_module::instance($cmid);
        
        $existingTags = ['diff1', 'diff2', 'diff3', 'diff4'];
        foreach ($existingTags as $tag) {
            \core_tag_tag::remove_item_tag('core', 'course_modules', $cmid, $tag);
        }
        
        if (!empty($difftag) && in_array($difftag, $existingTags)) {
            \core_tag_tag::add_item_tag('core', 'course_modules', $cmid, $context, $difftag);
        }
        
        return true;
    }
    
    public static function bulk_set_difficulty($cmids, $difftag, $courseid) {
        $results = ['success' => 0, 'failed' => 0];
        
        foreach ($cmids as $cmid) {
            if (self::set_module_difficulty($cmid, $difftag)) {
                self::apply_restriction_to_module($cmid, $courseid);
                $results['success']++;
            } else {
                $results['failed']++;
            }
        }
        
        return $results;
    }
    
    protected static function build_availability_conditions($sectionnumber, $difftag, $config) {
        $conditions = [];
        
        if ($sectionnumber >= 2) {
            if (!empty($config->require_previous_section)) {
                $previousSection = $sectionnumber - 1;
                $minCompletions = !empty($config->min_section_completions) ? (int)$config->min_section_completions : 1;
                
                $conditions[] = [
                    'type' => 'sectioncomplete',
                    'section' => $previousSection,
                    'mincompletions' => $minCompletions
                ];
            }
            
            if (!empty($config->require_previous_grade)) {
                $previousSection = $sectionnumber - 1;
                $minGrade = !empty($config->min_section_grade) ? (float)$config->min_section_grade : 50;
                
                $conditions[] = [
                    'type' => 'sectiongrade',
                    'section' => $previousSection,
                    'mingrade' => $minGrade
                ];
            }
        }
        
        if (!empty($config->require_difficulty_progression) && $difftag) {
            $diffRequirements = self::get_difficulty_requirements($difftag, $config, $sectionnumber);
            if ($diffRequirements) {
                $conditions[] = $diffRequirements;
            }
        }
        
        if (empty($conditions)) {
            return null;
        }
        
        $showWhenNotMet = empty($config->hide_completely);
        
        return [
            'op' => '&',
            'c' => $conditions,
            'showc' => array_fill(0, count($conditions), $showWhenNotMet)
        ];
    }
    
    protected static function get_difficulty_requirements($difftag, $config, $sectionnumber) {
        $requirements = [
            'type' => 'diffcomplete',
            'diff1' => 0,
            'diff2' => 0,
            'diff3' => 0,
            'diff4' => 0,
            'section' => $sectionnumber,
            'sectiondiff1' => 0,
            'sectiondiff2' => 0,
            'sectiondiff3' => 0,
            'sectiondiff4' => 0,
        ];
        
        switch ($difftag) {
            case 'diff2':
                $requirements['diff1'] = !empty($config->min_diff1_for_diff2) ? (int)$config->min_diff1_for_diff2 : 2;
                $requirements['sectiondiff1'] = !empty($config->section_min_diff1_for_diff2) ? (int)$config->section_min_diff1_for_diff2 : 1;
                break;
            case 'diff3':
                $requirements['diff1'] = !empty($config->min_diff1_for_diff3) ? (int)$config->min_diff1_for_diff3 : 3;
                $requirements['diff2'] = !empty($config->min_diff2_for_diff3) ? (int)$config->min_diff2_for_diff3 : 2;
                $requirements['sectiondiff1'] = !empty($config->section_min_diff1_for_diff3) ? (int)$config->section_min_diff1_for_diff3 : 1;
                $requirements['sectiondiff2'] = !empty($config->section_min_diff2_for_diff3) ? (int)$config->section_min_diff2_for_diff3 : 1;
                break;
            case 'diff4':
                $requirements['diff1'] = !empty($config->min_diff1_for_diff4) ? (int)$config->min_diff1_for_diff4 : 4;
                $requirements['diff2'] = !empty($config->min_diff2_for_diff4) ? (int)$config->min_diff2_for_diff4 : 3;
                $requirements['diff3'] = !empty($config->min_diff3_for_diff4) ? (int)$config->min_diff3_for_diff4 : 2;
                $requirements['sectiondiff1'] = !empty($config->section_min_diff1_for_diff4) ? (int)$config->section_min_diff1_for_diff4 : 1;
                $requirements['sectiondiff2'] = !empty($config->section_min_diff2_for_diff4) ? (int)$config->section_min_diff2_for_diff4 : 1;
                $requirements['sectiondiff3'] = !empty($config->section_min_diff3_for_diff4) ? (int)$config->section_min_diff3_for_diff4 : 1;
                break;
            default:
                return null;
        }
        
        return $requirements;
    }
    
    public static function course_module_completion_updated(\core\event\course_module_completion_updated $event) {
        $courseid = $event->courseid;
        
        $config = self::get_course_config($courseid);
        if (!$config || empty($config->enabled)) {
            return;
        }
        
        \course_modinfo::purge_course_cache($courseid);
        
        $cache = \cache::make('core', 'coursemodinfo');
        $cache->delete($courseid);
        
        $navicache = \cache::make('core', 'navigation_expandcourse');
        $navicache->purge();
        
        $ccicache = \cache::make('core', 'course_content_items');
        $ccicache->delete($courseid);
    }
}
