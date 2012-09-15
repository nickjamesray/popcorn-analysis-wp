

jQuery(function(jQuery) {
	
	jQuery('.subject_upload_image_button').click(function() {
		formfield = jQuery('.subject_upload_image');
		preview = jQuery('.subject_preview_image');
		tb_show('', 'media-upload.php?type=image&TB_iframe=true');
		window.send_to_editor = function(html) {
			imgurl = jQuery('img',html).attr('src');
			classes = jQuery('img', html).attr('class');
			id = classes.replace(/(.*?)wp-image-/, '');
			formfield.val(id);
			preview.attr('src', imgurl);
			jQuery('#subjectUpload').hide();
			jQuery('#subjectPreview').show();
			jQuery('.subject_clear_image_button').show();
			tb_remove();
		}
		return false;
	});
	
	jQuery('.subject_clear_image_button').click(function() {
	//	var defaultImage = jQuery(this).parent().siblings('.subject_default_image').text();
		jQuery('.subject_upload_image').val('');
		jQuery('#subjectPreview').hide();
		jQuery('#subjectUpload').show();
		jQuery('.subject_clear_image_button').hide();
	//	jQuery(this).parent().siblings('.subject_preview_image').attr('src', defaultImage);
		return false;
	});
	
	if(jQuery('.subject_preview_image').attr('src')==''){
		jQuery('.subject_clear_image_button').hide();
	}else{
	
	}
	

});

