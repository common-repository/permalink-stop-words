/**
 * Populate Quick Edit form inputs with the hidden post status
 */
jQuery(document).ready(function () {

	function mdtPermalinkStopWordsExtraFields() {
		var $ = jQuery;
		var _edit = inlineEditPost.edit;
		inlineEditPost.edit = function (id) {
			var args = [].slice.call(arguments);
			_edit.apply(this, args);
			if (typeof(id) == 'object') {
				id = this.getId(id);
			}
			if (this.type == 'post') {
				var postRow = $('#post-' + id),
					hiddenPostStatus = postRow.find('.hidden_post_status').text(),
					hiddenPostStatusField = '<input type="hidden" name="hidden_post_status" class="ptitle" value="' + hiddenPostStatus + '">';

				var rowElement = $('#edit-' + id);

				rowElement.find('label').first().after(hiddenPostStatusField);

			}
		}
	}

	jQuery(mdtPermalinkStopWordsExtraFields);

});
