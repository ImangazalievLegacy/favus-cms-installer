/*!
 * Install Progress (Step 4)
 */

 /*
 * Progress Page
 */
Installer.Pages.installProgress.title = 'Installation progress...'
Installer.Pages.installProgress.steps = [
	{ code: 'downloadVendor', label: 'Downloading vendor files' },
	{ code: 'downloadApplication', label: 'Downloading application files' },
	{ code: 'extractVendor', label: 'Unpacking vendor files' },
	{ code: 'extractApplication', label: 'Unpacking application files' },
	{ code: 'setupConfig', label: 'Building configuration files' },
	{ code: 'migrate', label: 'Migrating tables' },
	{ code: 'seed', label: 'Seeding' },
	{ code: 'setupRoles', label: 'Installing roles' },
	{ code: 'createAdminAccount', label: 'Creating admin account' },
	{ code: 'completeInstallation', label: 'Finishing installation' }
]

Installer.Pages.installProgress.activeStep = null;

Installer.Pages.installProgress.init = function() {

	var self = Installer.Pages.installProgress,
		eventChain = [];

	/*
	 * Process each step
	 */
	$.each(self.steps, function(index, step) {
		eventChain = self.spoolStep(step, eventChain);
	})

	self.run(eventChain);
}

Installer.Pages.installProgress.retry = function() {
	var self = Installer.Pages.installProgress,
		eventChain = [],
		skipStep = true;

	/*
	 * Process each step
	 */
	$.each(self.steps, function(index, step) {

		if (step == self.activeStep)
			skipStep = false;

		if (skipStep)
			return true; // Continue

		eventChain = self.spoolStep(step, eventChain);
	})

	self.run(eventChain);
}

Installer.Pages.installProgress.run = function(eventChain) {
	var installProgressFailed = $('#installProgressFailed').hide()

	$.waterfall.apply(this, eventChain).done(function() {
		Installer.showPage('installComplete');
	})
	.fail(function(reason) {
		Installer.setLoadingBar('failed');
		installProgressFailed.show().addClass('animate fade_in');
		installProgressFailed.renderPartial('progress/fail', { reason: reason });
	})
}

Installer.Pages.installProgress.spoolStep = function(step, eventChain) {
	var self = Installer.Pages.installProgress,
		result;

	/*
	 * Set the active step
	 */
	eventChain.push(function() {
		self.activeStep = step;
		return $.Deferred().resolve();
	})

	/*
	 * Step mutator exists
	 */
	if (self.execStep[step.code]) {
		result = self.execStep[step.code](step);
		if (!$.isArray(result)) result = [result];
		eventChain = $.merge(eventChain, result);
	}
	/*
	 * Fall back on default logic
	 */
	else {
		eventChain.push(function() {
			return self.execDefaultStep(step);
		})
	}

	return eventChain;
}

Installer.Pages.installProgress.execDefaultStep = function(step, options) {
	var deferred = $.Deferred(),
		options = options || {},
		postData = { step: step.code, meta: Installer.Data.meta };

	if (options.extraData)
		$.extend(true, postData, options.extraData);

	Installer.setLoadingBar(true, step.label);

	$.sendRequest('installStep', postData, { loadingIndicator: false })
		.fail(function(data) {
			deferred.reject(jQuery.parseJSON(data.responseText).message);
		})
		.done(function(data) {
			options.onSuccess && options.onSuccess(data);
			Installer.setLoadingBar(false);
			setTimeout(function() { deferred.resolve() }, 300);
		});

	return deferred;
}

Installer.Pages.installProgress.execIterationStep = function(step, handlerCode, collection) {
	var eventChain = [];

	// Item must contain a code property
	$.each(collection, function(index, item) {

		var data = { name: item.code };

		if (Installer.Data.project && Installer.Data.project.code)
			data.project_id = Installer.Data.project.code;

		eventChain.push(function() {
			return Installer.Pages.installProgress.execDefaultStep({
				code: handlerCode,
				label: step.label + item.code
			}, { extraData: data });
		})
	})

	return eventChain;
}

/*
 * Specific logic to execute for each step
 *
 * These must return an anonymous function, or an array of anonymous functions,
 * that each return a deferred object
 */

Installer.Pages.installProgress.execStep = {};

Installer.Pages.installProgress.execStep.setupConfig = function(step) {
	return function() {
		return Installer.Pages.installProgress.execDefaultStep(step, { extraData: Installer.Data.config });
	}
}

Installer.Pages.installProgress.execStep.createAdmin = function(step) {
	return function() {
		return Installer.Pages.installProgress.execDefaultStep(step, { extraData: Installer.Data.config });
	}
}

Installer.Pages.installProgress.execStep.setupProject = function(step) {
   return function() {
		var data = $.extend(true, {}, Installer.Data.project, { disableLog: true });
		return Installer.Pages.installProgress.execDefaultStep(step, { extraData: data });
	}
}

Installer.Pages.installProgress.execStep.finishInstall = function(step) {
	return function() {
		return Installer.Pages.installProgress.execDefaultStep(step, { extraData: Installer.Data.meta.core });
	}
}