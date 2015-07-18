/*
 * Config Page
 */
Installer.Pages.configForm.title = 'Configuration';
Installer.Pages.configForm.nextButton = 'Continue';

/*!
 * Configuration Form (Step 2)
 */

Installer.Pages.configForm.activeCategory = null;

Installer.Pages.configForm.sections = [
	{ code: 'general', label: 'General', category: 'General', action: 'validateGeneralConfig', partial: 'config/general' },
	{ code: 'database', label: 'Database', category: 'General', action: 'validateDatabase', partial: 'config/database' },
	{ code: 'admin', label: 'Administrator', category: 'General', action: 'validateAdminAccount', partial: 'config/admin' },
	{ code: 'advanced', label: 'Advanced', category: 'Advanced', action: 'validateAdvancedConfig', partial: 'config/advanced' },
	{ code: 'mail', label: 'Mail', category: 'Advanced', action: 'validateMailConfig', partial: 'config/mail' }
];

Installer.Pages.configForm.init = function() {

	var configForm = $('#configForm').addClass('animate fade_in');

	Installer.renderSections(Installer.Pages.configForm.sections);

	var configFormFailed   = $('#configFormFailed').hide(),
		configFormDatabase = $('#configFormDatabase'),
		configFormMail     = $('#configFormMail');

	configFormDatabase.renderPartial('config/database/mysql');
	configFormMail.renderPartial('config/mail/mail');

	// Set the encryption code with a random string
	$('#advEncryptionKey').val(Installer.Pages.configForm.randomString(16));
};

Installer.Pages.configForm.next = function() {

	var eventChain = [],
		configFormFailed = $('#configFormFailed').hide().removeClass('animate fade_in');

	$('.section-area').removeClass('fail');

	/*
	 * Validate each section
	 */
	$.each(Installer.Pages.configForm.sections, function(index, section) {
		eventChain.push(function() {

			Installer.Data.config = $('#' + section.code + 'Form').serializeObject();
			
			return $('#' + section.code + 'Form').sendRequest(section.action).fail(function(data) {

				configFormFailed.show().addClass('animate fade_in');
				configFormFailed.renderPartial('config/fail', { label: section.label, reason: jQuery.parseJSON(data.responseText).message});

				var sectionElement = $('.section-area[data-section-code="'+section.code+'"]').addClass('fail');
				configFormFailed.appendTo(sectionElement);

				Installer.showSection(section.code);

				// Scroll browser to the bottom of the error
				var scrollTo = configFormFailed.offset().top - $(window).height() + configFormFailed.height() + 10;
				$('body, html').animate({ scrollTop: scrollTo });
			});
		});
	});

	$.waterfall.apply(this, eventChain).done(function() {
		Installer.showPage('starterForm');
	})
};

Installer.Pages.configForm.toggleDatabase = function(el) {
	
    var selectedValue = $(el).val(),
        configFormDatabase = $('#configFormDatabase'),
        databasePartial = 'config/database/' + selectedValue;

    configFormDatabase.renderPartial(databasePartial);
};

Installer.Pages.configForm.toggleMail = function(el) {
	
    var selectedValue = $(el).val(),
        configFormMail = $('#configFormMail'),
        mailPartial = 'config/mail/' + selectedValue;

    configFormMail.renderPartial(mailPartial);
};

Installer.Pages.configForm.randomString = function(length) {

	var charSet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789',
		randomString = '';

	for (var i = 0; i < length; i++) {
		var randomPos = Math.floor(Math.random() * charSet.length);
		randomString += charSet.substring(randomPos, randomPos + 1);
	}

	return randomString;
}