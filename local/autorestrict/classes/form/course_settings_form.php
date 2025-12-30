<?php
namespace local_autorestrict\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

class course_settings_form extends \moodleform {
    public function definition() {
        $mform = $this->_form;
        $courseid = $this->_customdata['courseid'];

        $mform->addElement('hidden', 'courseid', $courseid);
        $mform->setType('courseid', PARAM_INT);

        $mform->addElement('advcheckbox', 'enabled', get_string('enabled', 'local_autorestrict'));
        $mform->addHelpButton('enabled', 'enabled', 'local_autorestrict');
        $mform->setDefault('enabled', 0);

        $mform->addElement('advcheckbox', 'hide_completely', get_string('hide_completely', 'local_autorestrict'));
        $mform->addHelpButton('hide_completely', 'hide_completely', 'local_autorestrict');
        $mform->setDefault('hide_completely', 1);
        $mform->disabledIf('hide_completely', 'enabled', 'notchecked');

        $mform->addElement('header', 'section_header', get_string('section_settings', 'local_autorestrict'));

        $mform->addElement('advcheckbox', 'require_previous_section', get_string('require_previous_section', 'local_autorestrict'));
        $mform->addHelpButton('require_previous_section', 'require_previous_section', 'local_autorestrict');
        $mform->setDefault('require_previous_section', 1);
        $mform->disabledIf('require_previous_section', 'enabled', 'notchecked');
        
        $mform->addElement('text', 'min_section_completions', get_string('min_section_completions', 'local_autorestrict'));
        $mform->setType('min_section_completions', PARAM_INT);
        $mform->setDefault('min_section_completions', 1);
        $mform->disabledIf('min_section_completions', 'enabled', 'notchecked');
        $mform->disabledIf('min_section_completions', 'require_previous_section', 'notchecked');

        $mform->addElement('advcheckbox', 'require_previous_grade', get_string('require_previous_grade', 'local_autorestrict'));
        $mform->addHelpButton('require_previous_grade', 'require_previous_grade', 'local_autorestrict');
        $mform->setDefault('require_previous_grade', 0);
        $mform->disabledIf('require_previous_grade', 'enabled', 'notchecked');

        $mform->addElement('text', 'min_section_grade', get_string('min_section_grade', 'local_autorestrict'));
        $mform->setType('min_section_grade', PARAM_FLOAT);
        $mform->setDefault('min_section_grade', 50);
        $mform->disabledIf('min_section_grade', 'enabled', 'notchecked');
        $mform->disabledIf('min_section_grade', 'require_previous_grade', 'notchecked');

        $mform->addElement('header', 'difficulty_header', get_string('difficulty_settings', 'local_autorestrict'));

        $mform->addElement('advcheckbox', 'require_difficulty_progression', get_string('require_difficulty_progression', 'local_autorestrict'));
        $mform->addHelpButton('require_difficulty_progression', 'require_difficulty_progression', 'local_autorestrict');
        $mform->setDefault('require_difficulty_progression', 1);
        $mform->disabledIf('require_difficulty_progression', 'enabled', 'notchecked');

        $mform->addElement('static', 'diff_desc', '', get_string('difficulty_settings_desc', 'local_autorestrict'));

        $mform->addElement('text', 'min_diff1_for_diff2', get_string('min_diff1_for_diff2', 'local_autorestrict'));
        $mform->setType('min_diff1_for_diff2', PARAM_INT);
        $mform->setDefault('min_diff1_for_diff2', 2);
        $mform->disabledIf('min_diff1_for_diff2', 'enabled', 'notchecked');
        $mform->disabledIf('min_diff1_for_diff2', 'require_difficulty_progression', 'notchecked');

        $mform->addElement('text', 'min_diff1_for_diff3', get_string('min_diff1_for_diff3', 'local_autorestrict'));
        $mform->setType('min_diff1_for_diff3', PARAM_INT);
        $mform->setDefault('min_diff1_for_diff3', 3);
        $mform->disabledIf('min_diff1_for_diff3', 'enabled', 'notchecked');
        $mform->disabledIf('min_diff1_for_diff3', 'require_difficulty_progression', 'notchecked');

        $mform->addElement('text', 'min_diff2_for_diff3', get_string('min_diff2_for_diff3', 'local_autorestrict'));
        $mform->setType('min_diff2_for_diff3', PARAM_INT);
        $mform->setDefault('min_diff2_for_diff3', 2);
        $mform->disabledIf('min_diff2_for_diff3', 'enabled', 'notchecked');
        $mform->disabledIf('min_diff2_for_diff3', 'require_difficulty_progression', 'notchecked');

        $mform->addElement('text', 'min_diff1_for_diff4', get_string('min_diff1_for_diff4', 'local_autorestrict'));
        $mform->setType('min_diff1_for_diff4', PARAM_INT);
        $mform->setDefault('min_diff1_for_diff4', 4);
        $mform->disabledIf('min_diff1_for_diff4', 'enabled', 'notchecked');
        $mform->disabledIf('min_diff1_for_diff4', 'require_difficulty_progression', 'notchecked');

        $mform->addElement('text', 'min_diff2_for_diff4', get_string('min_diff2_for_diff4', 'local_autorestrict'));
        $mform->setType('min_diff2_for_diff4', PARAM_INT);
        $mform->setDefault('min_diff2_for_diff4', 3);
        $mform->disabledIf('min_diff2_for_diff4', 'enabled', 'notchecked');
        $mform->disabledIf('min_diff2_for_diff4', 'require_difficulty_progression', 'notchecked');

        $mform->addElement('text', 'min_diff3_for_diff4', get_string('min_diff3_for_diff4', 'local_autorestrict'));
        $mform->setType('min_diff3_for_diff4', PARAM_INT);
        $mform->setDefault('min_diff3_for_diff4', 2);
        $mform->disabledIf('min_diff3_for_diff4', 'enabled', 'notchecked');
        $mform->disabledIf('min_diff3_for_diff4', 'require_difficulty_progression', 'notchecked');

        $mform->addElement('header', 'section_difficulty_header', get_string('section_difficulty_settings', 'local_autorestrict'));
        $mform->addElement('static', 'section_diff_desc', '', get_string('section_difficulty_settings_desc', 'local_autorestrict'));

        $mform->addElement('text', 'section_min_diff1_for_diff2', get_string('section_min_diff1_for_diff2', 'local_autorestrict'));
        $mform->setType('section_min_diff1_for_diff2', PARAM_INT);
        $mform->setDefault('section_min_diff1_for_diff2', 1);
        $mform->disabledIf('section_min_diff1_for_diff2', 'enabled', 'notchecked');
        $mform->disabledIf('section_min_diff1_for_diff2', 'require_difficulty_progression', 'notchecked');

        $mform->addElement('text', 'section_min_diff1_for_diff3', get_string('section_min_diff1_for_diff3', 'local_autorestrict'));
        $mform->setType('section_min_diff1_for_diff3', PARAM_INT);
        $mform->setDefault('section_min_diff1_for_diff3', 1);
        $mform->disabledIf('section_min_diff1_for_diff3', 'enabled', 'notchecked');
        $mform->disabledIf('section_min_diff1_for_diff3', 'require_difficulty_progression', 'notchecked');

        $mform->addElement('text', 'section_min_diff2_for_diff3', get_string('section_min_diff2_for_diff3', 'local_autorestrict'));
        $mform->setType('section_min_diff2_for_diff3', PARAM_INT);
        $mform->setDefault('section_min_diff2_for_diff3', 1);
        $mform->disabledIf('section_min_diff2_for_diff3', 'enabled', 'notchecked');
        $mform->disabledIf('section_min_diff2_for_diff3', 'require_difficulty_progression', 'notchecked');

        $mform->addElement('text', 'section_min_diff1_for_diff4', get_string('section_min_diff1_for_diff4', 'local_autorestrict'));
        $mform->setType('section_min_diff1_for_diff4', PARAM_INT);
        $mform->setDefault('section_min_diff1_for_diff4', 1);
        $mform->disabledIf('section_min_diff1_for_diff4', 'enabled', 'notchecked');
        $mform->disabledIf('section_min_diff1_for_diff4', 'require_difficulty_progression', 'notchecked');

        $mform->addElement('text', 'section_min_diff2_for_diff4', get_string('section_min_diff2_for_diff4', 'local_autorestrict'));
        $mform->setType('section_min_diff2_for_diff4', PARAM_INT);
        $mform->setDefault('section_min_diff2_for_diff4', 1);
        $mform->disabledIf('section_min_diff2_for_diff4', 'enabled', 'notchecked');
        $mform->disabledIf('section_min_diff2_for_diff4', 'require_difficulty_progression', 'notchecked');

        $mform->addElement('text', 'section_min_diff3_for_diff4', get_string('section_min_diff3_for_diff4', 'local_autorestrict'));
        $mform->setType('section_min_diff3_for_diff4', PARAM_INT);
        $mform->setDefault('section_min_diff3_for_diff4', 1);
        $mform->disabledIf('section_min_diff3_for_diff4', 'enabled', 'notchecked');
        $mform->disabledIf('section_min_diff3_for_diff4', 'require_difficulty_progression', 'notchecked');

        $this->add_action_buttons();
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (!empty($data['min_section_completions']) && $data['min_section_completions'] < 0) {
            $errors['min_section_completions'] = get_string('error_negative', 'local_autorestrict');
        }

        if (!empty($data['min_section_grade']) && ($data['min_section_grade'] < 0 || $data['min_section_grade'] > 100)) {
            $errors['min_section_grade'] = get_string('error_grade_range', 'local_autorestrict');
        }

        return $errors;
    }
}
