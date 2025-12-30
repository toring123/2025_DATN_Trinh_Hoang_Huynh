YUI.add('moodle-availability_diffcomplete-form', function(Y) {

M.availability_diffcomplete = M.availability_diffcomplete || {};

M.availability_diffcomplete.form = Y.Object(M.core_availability.plugin);

M.availability_diffcomplete.form.counts = null;
M.availability_diffcomplete.form.numLevels = 4;
M.availability_diffcomplete.form.prefix = 'diff';

M.availability_diffcomplete.form.initInner = function(counts, numLevels, prefix) {
    this.counts = counts || {};
    this.numLevels = numLevels || 4;
    this.prefix = prefix || 'diff';
};

M.availability_diffcomplete.form.getNode = function(json) {
    var html = '<div class="availability_diffcomplete-fields">';
    
    for (var i = 1; i <= this.numLevels; i++) {
        var tagname = this.prefix + i;
        var count = this.counts[tagname] || 0;
        var value = json[tagname] || 0;
        
        html += '<label class="form-inline d-block mb-1">';
        html += '<span class="pr-3">Difficulty ' + i + ' (' + count + ' available)</span> ';
        html += '<input type="number" class="form-control" name="' + tagname + '" min="0" ';
        html += 'value="' + value + '" style="width: 80px;"/>';
        html += '</label>';
    }
    
    html += '</div>';
    
    var node = Y.Node.create('<span class="form-inline">' + html + '</span>');
    
    if (!M.availability_diffcomplete.form.addedEvents) {
        M.availability_diffcomplete.form.addedEvents = true;
        var root = Y.one('.availability-field');
        if (root) {
            root.delegate('change', function() {
                M.core_availability.form.update();
            }, '.availability_diffcomplete input');
        }
    }
    
    return node;
};

M.availability_diffcomplete.form.fillValue = function(value, node) {
    for (var i = 1; i <= this.numLevels; i++) {
        var tagname = this.prefix + i;
        var input = node.one('input[name=' + tagname + ']');
        if (input) {
            value[tagname] = parseInt(input.get('value'), 10) || 0;
        }
    }
};

M.availability_diffcomplete.form.fillErrors = function(errors, node) {
};

}, '@VERSION@', {"requires": ["base", "node", "event", "moodle-core_availability-form"]});
