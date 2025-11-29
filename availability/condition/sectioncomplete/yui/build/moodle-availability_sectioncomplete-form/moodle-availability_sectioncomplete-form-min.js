YUI.add('moodle-availability_sectioncomplete-form', function(Y) {
M.availability_sectioncomplete = M.availability_sectioncomplete || {};
M.availability_sectioncomplete.form = Y.Object(M.core_availability.plugin);
M.availability_sectioncomplete.form.sections = null;
M.availability_sectioncomplete.form.initInner = function(sections) { this.sections = sections || []; };
M.availability_sectioncomplete.form.getNode = function(json) {
    var html = '<label><span class="pr-3">' + M.util.get_string('section', 'availability_sectioncomplete') + '</span> <select class="custom-select" name="section" title="' + M.util.get_string('section', 'availability_sectioncomplete') + '">';
    html += '<option value="">Choose...</option>';
    if (this.sections) { for (var i = 0; i < this.sections.length; i++) { var sec = this.sections[i]; var selected = (json.section === sec.number) ? ' selected' : ''; html += '<option value="' + sec.number + '"' + selected + '>' + Y.Escape.html(sec.name) + ' (' + sec.activitycount + ' activities)</option>'; } }
    html += '</select></label>';
    html += '<br><label class="form-inline"><span class="pr-3">' + M.util.get_string('mincompletions', 'availability_sectioncomplete') + '</span> <input type="number" class="form-control" name="mincompletions" min="1" value="' + (json.mincompletions || 1) + '" style="width: 80px;" title="' + M.util.get_string('mincompletions', 'availability_sectioncomplete') + '"/></label>';
    var node = Y.Node.create('<span class="form-inline">' + html + '</span>');
    if (!M.availability_sectioncomplete.form.addedEvents) { M.availability_sectioncomplete.form.addedEvents = true; var root = Y.one('.availability-field'); if (root) { root.delegate('change', function() { M.core_availability.form.update(); }, '.availability_sectioncomplete select, .availability_sectioncomplete input'); } }
    return node;
};
M.availability_sectioncomplete.form.fillValue = function(value, node) { value.section = parseInt(node.one('select[name=section]').get('value'), 10); value.mincompletions = parseInt(node.one('input[name=mincompletions]').get('value'), 10) || 1; };
M.availability_sectioncomplete.form.fillErrors = function(errors, node) { var section = node.one('select[name=section]').get('value'); if (section === '' || isNaN(parseInt(section, 10))) { errors.push('availability_sectioncomplete:error_invalidsection'); } };
}, '@VERSION@', {"requires": ["base", "node", "event", "moodle-core_availability-form"]});
