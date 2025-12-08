<?php
/**
 * Language strings for section grade condition
 *
 * @package    availability_sectiongrade
 * @copyright  2025
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'Restriction by section average grade';
$string['title'] = 'Section Grade';
$string['description'] = 'Require students to achieve a minimum average grade in a specific section.';

$string['section'] = 'Section';
$string['mingrade'] = 'Minimum average grade (%)';
$string['error_invalidsection'] = 'Please select a valid section.';

$string['requires_grade'] = 'Your average grade in section {$a->section} is at least {$a->grade}%';
$string['requires_notgrade'] = 'Your average grade in section {$a->section} is less than {$a->grade}%';

$string['missing_grade'] = 'You need an average grade of at least {$a->grade}% in section {$a->section} to access this content.';
