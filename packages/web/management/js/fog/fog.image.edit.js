$(function() {
		// Show hide based on checked state.
		$('#hostNotInMe').hide();
		$('#hostNoImage').hide();
		$('#hostMeShow').click(function() {
			$('#hostNotInMe').toggle();
			});
		$('#hostNoShow').click(function() {
			$('#hostNoImage').toggle();
			});
		$('.toggle-checkbox1').click(function() {
			$('input.toggle-host1:checkbox').attr('checked', ($(this).attr('checked') ? 'checked' : false));
			});
		$('.toggle-checkbox2').click(function() {
			$('input.toggle-host2:checkbox').attr('checked', ($(this).attr('checked') ? 'checked' : false));
			});
		$('#groupNotInMe').hide();
		$('#groupNoImage').hide();
		$('#groupMeShow').click(function() {
			$('#groupNotInMe').toggle();
			});
		$('#groupNoShow').click(function() {
				$('#groupNoImage').toggle();
				});
		$('.toggle-checkbox1').click(function() {
				$('input.toggle-group1:checkbox').attr('checked', ($(this).attr('checked') ? 'checked' : false));
				});
		$('.toggle-checkbox2').click(function() {
				$('input.toggle-group2:checkbox').attr('checked', ($(this).attr('checked') ? 'checked' : false));
				});
});
