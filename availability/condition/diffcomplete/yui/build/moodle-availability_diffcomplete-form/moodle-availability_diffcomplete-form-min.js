YUI.add('moodle-availability_diffcomplete-form', function(Y) {
M.availability_diffcomplete = M.availability_diffcomplete || {};
M.availability_diffcomplete.form = Y.Object(M.core_availability.plugin);
M.availability_diffcomplete.form.counts = null;
M.availability_diffcomplete.form.initInner = function(counts) { this.counts = counts || {diff1: 0, diff2: 0, diff3: 0, diff4: 0}; };
M.availability_diffcomplete.form.getNode = function(json) {
    var html = '<div class="availability_diffcomplete-fields">';
    html += '<label class="form-inline d-block mb-1"><span class="pr-3">' + M.util.get_string('diff1', 'availability_diffcomplete') + ' (' + this.counts.diff1 + ' available)</span> <input type="number" class="form-control" name="diff1" min="0" value="' + (json.diff1 || 0) + '" style="width: 80px;"/></label>';
    html += '<label class="form-inline d-block mb-1"><span class="pr-3">' + M.util.get_string('diff2', 'availability_diffcomplete') + ' (' + this.counts.diff2 + ' available)</span> <input type="number" class="form-control" name="diff2" min="0" value="' + (json.diff2 || 0) + '" style="width: 80px;"/></label>';
    html += '<label class="form-inline d-block mb-1"><span class="pr-3">' + M.util.get_string('diff3', 'availability_diffcomplete') + ' (' + this.counts.diff3 + ' available)</span> <input type="number" class="form-control" name="diff3" min="0" value="' + (json.diff3 || 0) + '" style="width: 80px;"/></label>';
    html += '<label class="form-inline d-block mb-1"><span class="pr-3">' + M.util.get_string('diff4', 'availability_diffcomplete') + ' (' + this.counts.diff4 + ' available)</span> <input type="number" class="form-control" name="diff4" min="0" value="' + (json.diff4 || 0) + '" style="width: 80px;"/></label>';
    html += '</div>';
    var node = Y.Node.create('<span class="form-inline">' + html + '</span>');
    if (!M.availability_diffcomplete.form.addedEvents) { M.availability_diffcomplete.form.addedEvents = true; var root = Y.one('.availability-field'); if (root) { root.delegate('change', function() { M.core_availability.form.update(); }, '.availability_diffcomplete input'); } }
    return node;
};
M.availability_diffcomplete.form.fillValue = function(value, node) { value.diff1 = parseInt(node.one('input[name=diff1]').get('value'), 10) || 0; value.diff2 = parseInt(node.one('input[name=diff2]').get('value'), 10) || 0; value.diff3 = parseInt(node.one('input[name=diff3]').get('value'), 10) || 0; value.diff4 = parseInt(node.one('input[name=diff4]').get('value'), 10) || 0; };
M.availability_diffcomplete.form.fillErrors = function(errors, node) { };
}, '@VERSION@', {"requires": ["base", "node", "event", "moodle-core_availability-form"]});
