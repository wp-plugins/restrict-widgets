jQuery(document).ready(function ($) {

	$('#widgets-left').append($('#widgets-options'));

	for(i in rwArgs.restrict_widgets) {
		$("div[id*='"+rwArgs.restrict_widgets[i]+"'] div").remove();
	}

	for(i in rwArgs.restrict_class) {
		$("div[id*='"+rwArgs.restrict_class[i]+"-__i__']").remove();
	}

	for(i in rwArgs.restrict_nonclass) {
		$("div[id*='_"+rwArgs.restrict_nonclass[i]+"']").remove();
	}

	if(rwArgs.restrict_orphan_sidebar == 1) {
		$('.orphan-sidebar').remove();
	}

	$('#options-available-widgets').chosen({placeholder_text: rwArgs.restrict_available_widgets});
	$('#options-widgets-selection').chosen({placeholder_text: rwArgs.restrict_widgets_selection});
	$('#options-widgets-sidebars').chosen({placeholder_text: rwArgs.restrict_sidebars});
	$('#options-widgets-roles').chosen({placeholder_text: rwArgs.restrict_roles});

	$(document).on('ready ajaxStop ajaxStart', function() {
		$('#widgets-right .restrict-widgets-hide, .inactive-sidebar .restrict-widgets-hide').chosen({
			allow_single_deselect: true,
			disable_search: true,
			width: '100%'
		});

		$('#widgets-right .restrict-widgets-select, .inactive-sidebar .restrict-widgets-select').chosen({
			placeholder_text: rwArgs.placeholder_text,
			width: '100%'
			});
	});
});	