$(function() {
	_FADE_SLOW   = 5000;
	_FADE_NORMAL = 1500;
	_FADE_FAST   = 700;
	_FADE_FLASH  = 300;

	/**
	 * Seotoaster popup dialog
	 */
	$(document).on('click', 'a.tpopup', function(e) {
		if(!loginCheck()) {
			return;
		}
		e.preventDefault();
		var link    = $(this);
		var pwidth  = link.data('pwidth') || 960;
		var pheight = link.data('pheight') || 580;
		var popup = $(document.createElement('iframe')).attr({'scrolling' : 'no', 'frameborder' : 'no', 'allowTransparency' : 'allowTransparency', 'id' : 'topastePopup'}).addClass('__tpopup rounded3px');
		popup.parent().css({background: 'none'});

		popup.dialog({
			width: pwidth,
			height: pheight,
			resizable : false,
			draggable : true,
			modal: true,
			open: function() {
				$(this).attr('src', link.data('url')).css({
						width    : pwidth + 'px',
						height   : pheight + 'px',
						padding  : '0px',
						margin   : '0px',
						overflow : 'hidden'
				});
				$('.ui-dialog-titlebar').remove();
			},
			close: function() {
				$(this).remove();
			}
		});
	});


	//seotoaster delete item link
	$(document).on('click', 'a._tdelete', function() {
		var url      = $(this).attr('href');
		var callback = $(this).data('callback');
		var elId     =  $(this).data('eid');
		if((typeof url == 'undefined') || !url || url == 'javascript:;') {
			url = $(this).data('url');
		}
		smoke.confirm('You are about to remove an item. Are you sure?', function(e) {
			if(e) {
				$.post(url, {id : elId}, function(response) {
					var responseText = (response.hasOwnProperty(responseText)) ? response.responseText : 'Removed.';
					showMessage(responseText, (!(typeof response.error == 'undefined' || !response.error)));
                    if(typeof callback != 'undefined') {
						eval(callback + '()');
					}
				})
			} else {
				$('.smoke-base').remove();
			}
		}, {classname:"errors", 'ok':'Yes', 'cancel':'No'});
	});

	//seotoaster close popup window button
	$(document).on('click', '.closebutton, .save-and-close', function() {
		if(window.parent.jQuery('.__tpopup').contents().find('div.seotoaster').hasClass('refreshOnClose')) {
			window.parent.location.reload();
			window.parent.jQuery('.__tpopup').dialog('close');
		}
		//parent.$('iframe').dialog('close');
		if(typeof window.parent.$('iframe').dialog != 'undefined') {
			window.parent.$('iframe').dialog('close');
		} else {
			console.log('Alarm! Something went wrong!');
		}

	});

	//seotoaster ajax form submiting
	$('form._fajax').on('submit', function(e) {
		e.preventDefault();
		var donotCleanInputs = [
			'#h1',
			'#header-title',
			'#url',
			'#nav-name',
			'#meta-description',
			'#meta-keywords',
			'#teaser-text'
		];
		var ajaxMessage = $('#ajax_msg');
		var form        = $(this);
		ajaxMessage.text('');
		$.ajax({
			url        : form.attr('action'),
			type       : 'post',
			dataType   : 'json',
			data       : form.serialize(),
			beforeSend : function() {
				showSpinner();
			},
			success : function(response) {
				if(!response.error) {
					if(form.hasClass('_reload')) {
						if(typeof response.responseText.redirectTo != 'undefined') {
							top.location.href = $('#website_url').val() + response.responseText.redirectTo;
							return;
						}
						top.location.reload();
						return;
					}
					//processing callback
					var callback = $(form).data('callback');
					if(typeof callback != 'undefined' && callback != null) {
						eval(callback + '()');
					}
					hideSpinner();
					showMessage(response.responseText);
				}
				else {
					if(!$(form).data('norefresh')) {
						$(form).find('input:text').not(donotCleanInputs.join(',')).val('');
					}
					hideSpinner();
					smoke.alert(response.responseText, {classname:"errors"});
				}
			},
			error: function(err) {
				$('.smoke-base').remove();
				showMessage('Error accured', true);
			}
		})
	})

	//seotoaster edit item link
	$(document).on('click', 'a._tedit', function(e) {
		e.preventDefault();
		var handleUrl = $(this).data('url');
		if(!handleUrl || handleUrl == 'undefined') {
			handleUrl = $(this).attr('href');
		}
		var eid = $(this).data('eid');
		$.post(handleUrl, {id: eid}, function(response) {
			//console.log(response.responseText.data);
			var formToLoad = $('#' + response.responseText.formId);
			for(var i in response.responseText.data) {
				$('[name=' + i + ']').val(response.responseText.data[i]);
				if(i == 'password') {
					$('[name=' + i + ']').val('');
				}
			}
		})

	});
	//seotoaster gallery links
	if(jQuery.fancybox) {
		$('a._lbox').fancybox();
	}
	//publishPages();
});


function loginCheck() {
	if($.cookie('PHPSESSID') === null) {
		showModalMessage('Session expired', 'Your session is expired! Please, login again', function() {
			top.location.href = $('#website_url').val();
		})
		return false;
	}
	return true;
}

function showMessage(msg, err) {
	if(err) {
		smoke.alert(msg, {classname:"errors"});
		return;
	}
	smoke.signal(msg);
	$('.smoke-base').delay(1300).slideUp();
}

function showConfirm(msg, yesCallback, noCallback) {
	smoke.confirm(msg, function(e) {
		if(e) {
			if(typeof yesCallback != 'undefined') {
				yesCallback();
			}
		} else {
		    if(typeof noCallback != 'undefined') {
			    noCallback();
		    }
		}
	}, {classname : 'errors', ok : 'Yes', cancel : 'No'});
}

function showSpinner() {
	smoke.signal('<img src="' + $('#website_url').val() + 'system/images/loading.gif" alt="working..." />', 30000);
}

function hideSpinner() {
	$('.smoke-base').hide();
}

function publishPages() {
	if(!top.$('#__tpopup').length) {
		$.get($('#website_url').val() + 'backend/backend_page/publishpages/');
	}
}