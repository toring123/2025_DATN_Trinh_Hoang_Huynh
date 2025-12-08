/**
 * JavaScript for form editing adaptive tag conditions.
 *
 * @module moodle-availability_adaptivetag-form
 */
M.availability_adaptivetag = M.availability_adaptivetag || {};

M.availability_adaptivetag.form = Y.Object(M.core_availability.plugin);

M.availability_adaptivetag.form.availableTags = null;

M.availability_adaptivetag.form.initInner = function(tags) {
    this.availableTags = tags || [];
};

M.availability_adaptivetag.form.getNode = function(json) {
    var html = '<label><span class="pr-3">' + M.util.get_string('tag', 'availability_adaptivetag') + 
               '</span> <select class="custom-select" name="tag" title="' + 
               M.util.get_string('tag', 'availability_adaptivetag') + '">';
    
    html += '<option value="">Choose...</option>';
    
    if (this.availableTags) {
        for (var i = 0; i < this.availableTags.length; i++) {
            var tag = this.availableTags[i];
            var selected = (json.tag === tag) ? ' selected' : '';
            html += '<option value="' + Y.Escape.html(tag) + '"' + selected + '>' + 
                    Y.Escape.html(tag) + '</option>';
        }
    }
    
    html += '</select></label>';
    html += '<br><label class="form-inline"><span class="pr-3">' + 
            M.util.get_string('mincompletions', 'availability_adaptivetag') + 
            '</span> <input type="number" class="form-control" name="mincompletions" ' +
            'min="1" value="' + (json.mincompletions || 1) + '" style="width: 80px;" ' +
            'title="' + M.util.get_string('mincompletions', 'availability_adaptivetag') + 
            '"/></label>';
    
    var node = Y.Node.create('<span class="form-inline">' + html + '</span>');
    
    if (!M.availability_adaptivetag.form.addedEvents) {
        M.availability_adaptivetag.form.addedEvents = true;
        var root = Y.one('.availability-field');
        if (root) {
            root.delegate('change', function() {
                M.core_availability.form.update();
            }, '.availability_adaptivetag select, .availability_adaptivetag input');
        }
    }
    
    return node;
};

M.availability_adaptivetag.form.fillValue = function(value, node) {
    value.tag = node.one('select[name=tag]').get('value');
    value.mincompletions = parseInt(node.one('input[name=mincompletions]').get('value'), 10) || 1;
};

M.availability_adaptivetag.form.fillErrors = function(errors, node) {
    var tag = node.one('select[name=tag]').get('value');
    if (!tag || tag === '') {
        errors.push('availability_adaptivetag:error_invalidtag');
    }
};
