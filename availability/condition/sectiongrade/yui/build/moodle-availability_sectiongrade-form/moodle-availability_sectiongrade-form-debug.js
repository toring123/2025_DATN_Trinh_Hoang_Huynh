YUI.add('moodle-availability_sectiongrade-form', function(Y) {

M.availability_sectiongrade = M.availability_sectiongrade || {};

M.availability_sectiongrade.form = Y.Object(M.core_availability.plugin);

M.availability_sectiongrade.form.sections = null;

M.availability_sectiongrade.form.initInner = function(sections) {
    this.sections = sections || [];
};

M.availability_sectiongrade.form.getNode = function(json) {
    var html = '<label><span class="pr-3">' + M.util.get_string('section', 'availability_sectiongrade') + 
               '</span> <select class="custom-select" name="section" title="' + 
               M.util.get_string('section', 'availability_sectiongrade') + '">';
    
    html += '<option value="">Choose...</option>';
    
    if (this.sections) {
        for (var i = 0; i < this.sections.length; i++) {
            var sec = this.sections[i];
            var selected = (json.section === sec.number) ? ' selected' : '';
            html += '<option value="' + sec.number + '"' + selected + '>' + 
                    Y.Escape.html(sec.name) + '</option>';
        }
    }
    
    html += '</select></label>';
    html += '<br><label class="form-inline"><span class="pr-3">' + 
            M.util.get_string('mingrade', 'availability_sectiongrade') + 
            '</span> <input type="number" class="form-control" name="mingrade" ' +
            'min="0" max="100" step="0.1" value="' + (json.mingrade || 50) + '" style="width: 100px;" ' +
            'title="' + M.util.get_string('mingrade', 'availability_sectiongrade') + 
            '"/> %</label>';
    
    var node = Y.Node.create('<span class="form-inline">' + html + '</span>');
    
    if (!M.availability_sectiongrade.form.addedEvents) {
        M.availability_sectiongrade.form.addedEvents = true;
        var root = Y.one('.availability-field');
        if (root) {
            root.delegate('change', function() {
                M.core_availability.form.update();
            }, '.availability_sectiongrade select, .availability_sectiongrade input');
        }
    }
    
    return node;
};

M.availability_sectiongrade.form.fillValue = function(value, node) {
    value.section = parseInt(node.one('select[name=section]').get('value'), 10);
    value.mingrade = parseFloat(node.one('input[name=mingrade]').get('value')) || 50;
};

M.availability_sectiongrade.form.fillErrors = function(errors, node) {
    var section = node.one('select[name=section]').get('value');
    if (section === '' || isNaN(parseInt(section, 10))) {
        errors.push('availability_sectiongrade:error_invalidsection');
    }
};

}, '@VERSION@', {"requires": ["base", "node", "event", "moodle-core_availability-form"]});
