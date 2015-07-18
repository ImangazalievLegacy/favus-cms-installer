+function($, window) {
	'use strict';

	var Installer = {

		ActivePage: 'systemCheck',
		Pages: {

			systemCheck:     { isStep1: true, body: 'check' },
			configForm:	     { isStep2: true, body: 'config' },
			starterForm:     { isStep3: true, body: 'starter' },
			installProgress: { isStep4: true, body: 'progress' },
			installComplete: { isStep5: true, body: 'complete' }
		},
		ActiveSection: null,
		Sections: {},
		Events: {},
		Data: {
			meta:   null, // Meta information from the server
			config: null, // Configuration from the user
			project: null // Project for the installation
		},
		debug: true
	};

	Installer.apiUrl = $('meta[name="api-url"]').attr('content');
	Installer.baseUrl = $('meta[name="base-url"]').attr('content');

	Installer.apiRequest = function(action, parameters) {

		var parameters = parameters || {};

		parameters.action = action;

		if (this.debug)
		{
			parameters.debug = true;
		}

		var jqxhr = $.ajax({

			url: Installer.apiUrl,
			type: 'POST',
			data: parameters
		});

		return jqxhr;
	};

	Installer.Events.next = function() {
		
		var nextButton = $('#nextButton');
		
		if (nextButton.hasClass('disabled')) return;
	
		var pageEvent = Installer.Pages[Installer.ActivePage].next;
		pageEvent && pageEvent();
	};

	Installer.Events.retry = function() {

		var pageEvent = Installer.Pages[Installer.ActivePage].retry;
		pageEvent && pageEvent();
	};

	Installer.setLoadingBar = function(state, message) {

		var progressBarContainer = $('#progressBar'),
			progressBar = $('#progressBar .progress-bar:first'),
			progressBarMessage = $('#progressBarMessage');

		if (message)
			progressBarMessage.text(message);

		progressBar.removeClass('progress-bar-danger');
		progressBarContainer.removeClass('failed');

		if (state == 'failed') {
			progressBar.addClass('progress-bar-danger').removeClass('animate infinite_loader');
			progressBarContainer.addClass('failed');
		}
		else if (state) {
			progressBarContainer.addClass('loading').removeClass('loaded');
			progressBar.addClass('animate infinite_loader');
		}
		else {
			progressBarContainer.addClass('loaded').removeClass('loading');
			progressBar.removeClass('animate infinite_loader');
		}
	}

	Installer.showPage = function(pageId, noPush) {

		$('html, body').scrollTop(0);

		var page    = Installer.Pages[pageId],
			oldPage = (pageId != Installer.ActivePage) ? Installer.Pages[Installer.ActivePage] : null;

		/*
		 * Page events
		 */
		oldPage && oldPage.beforeUnload && oldPage.beforeUnload();

		Installer.ActivePage = pageId;

		page.beforeShow && page.beforeShow();

		$('#containerHeader').renderPartial('header', page);
		$('#containerTitle').renderPartial('title', page).find('.steps > .last.pass:first').addClass('animate fade_in');
		$('#containerFooter').renderPartial('footer', page);

		/*
		 * Check if the content container exists already, if not, create it
		 */
		var pageContainer = $('#containerBody').find('.pageContainer-' + pageId);

		if (!pageContainer.length) {

			pageContainer = $('<div />').addClass('pageContainer-' + pageId);
			pageContainer.renderPartial(page.body, page);
			$('#containerBody').append(pageContainer);
			page.init && page.init();
		}

		pageContainer.show().siblings().hide();

		// New page, add it to the history
		if (history.pushState && !noPush) {
			window.history.pushState({page:pageId}, '', window.location.pathname);
			page.isRendered = true;
		}
	};

	Installer.renderSections = function(sections, vars) {

		Installer.Sections = sections;

		$.each(sections, function(index, section) {
			Installer.renderSection(section, vars);
		})

		Installer.showSection(sections[0].code);
	};

	Installer.renderSection = function(section, vars) {

		var sectionElement = $('<div />').addClass('section-area').attr('data-section-code', section.code),
			stepContainer = $('#' + Installer.ActivePage),
			container = stepContainer.find('.section-content:first');

		if (!section.category) section.category = "NULL";

		sectionElement
			.renderPartial(section.partial, vars)
			.prepend($('<h3 />').text(section.label))
			.hide()
			.appendTo(container);

		/*
		 * Side navigation
		 */
		var sideNav = stepContainer.find('.section-side-nav:first'),
			menuItem = $('<li />').attr('data-section-code', section.code),
			menuItemLink = $('<a />').attr({ href: "javascript:Installer.showSection('"+section.code+"')"}).text(section.label),
			sideNavCategory = sideNav.find('[data-section-category="'+section.category+'"]:first'),
			sideNavCategoryTitle;

		if (sideNavCategory.length == 0) {

			sideNavCategory = $('<ul />').addClass('nav').attr('data-section-category', section.category);
			sideNavCategoryTitle = $('<h3 />').text(section.category);
			if (section.category == "NULL") sideNavCategoryTitle.text('');
			sideNav.append(sideNavCategoryTitle).append(sideNavCategory);
		}

		sideNavCategory.append(menuItem.append(menuItemLink));
	}

	Installer.renderSectionNav = function() {

		var stepContainer = $('#' + Installer.ActivePage),
			pageNav = stepContainer.find('.section-page-nav:first').empty(),
			sections = Installer.Sections;

		$.each(sections, function(index, section) {
			if (section.code == Installer.ActiveSection) {

				var nextStep = sections[index+1] ? sections[index+1] : null,
					lastStep = sections[index-1] ? sections[index-1] : null;

				if (lastStep && Installer.isSectionVisible(lastStep.code)) {
					$('<a />')
						.text(lastStep.label)
						.addClass('btn btn-default prev')
						.attr('href', "javascript:Installer.showSection('"+lastStep.code+"')")
						.appendTo(pageNav);
				}

				if (nextStep && Installer.isSectionVisible(nextStep.code)) {
					$('<a />')
						.text(nextStep.label)
						.addClass('btn btn-default next')
						.attr('href', "javascript:Installer.showSection('"+nextStep.code+"')")
						.appendTo(pageNav);
				}

				return false;
			}
		})
	}

	Installer.showSection = function(code) {
		var
			stepContainer = $('#' + Installer.ActivePage),
			sideNav = stepContainer.find('.section-side-nav:first'),
			menuItem = sideNav.find('[data-section-code="'+code+'"]:first'),
			container = stepContainer.find('.section-content:first'),
			sectionElement = container.find('[data-section-code="'+code+'"]:first');

		sideNav.find('li.active').removeClass('active');
		menuItem.addClass('active');
		sectionElement.show().siblings().hide();

		Installer.ActiveSection = code;
		Installer.renderSectionNav();
	};

	Installer.isSectionVisible = function(code) {
		return $('#' + Installer.ActivePage + ' [data-section-code="'+code+'"]:first').is(':visible');
	}

	window.Installer = Installer;

}(jQuery, window);

$.fn.extend({
	renderPartial: function(name, data, options) {

		var container = $(this),
			template = $('[data-partial="' + name + '"]'),
			contents = Mustache.to_html(template.html(), data);

		options = $.extend(true, {
			append: false
		}, options);

		if (options.append) container.append(contents)
		else container.html(contents);

		return this;
	},

	sendRequest: function(action, data, options) {

		var form = $(this),
			postData = form.serializeObject(),
			controlPanel = $('#formControlPanel'),
			nextButton = $('#nextButton');

		options = $.extend(true, {
			loadingIndicator: true
		}, options);

		if (options.loadingIndicator) {
			nextButton.attr('disabled', true);
			controlPanel.addClass('loading');
		}

		if (!data)
			data = {action: action}
		else
			data.action = action;

		if (data) $.extend(postData, data);

		console.log('Post Data: ');
		console.log(postData);

		var postObj = Installer.apiRequest(action, postData);

		postObj.always(function() {

			console.log('Response: ');
			console.log(arguments);

			if (options.loadingIndicator) {
				nextButton.attr('disabled', false);
				controlPanel.removeClass('loading');
			}
		});

		return postObj;
	},

	serializeObject: function() {

		var o = {};
		var a = this.serializeArray();
		$.each(a, function() {
			if (o[this.name] !== undefined) {
				if (!o[this.name].push) {
					o[this.name] = [o[this.name]];
				}
				o[this.name].push(this.value || '');
			} else {
				o[this.name] = this.value || '';
			}
		});
		return o;
	}
 });

$.extend({
    sendRequest: function(handler, data, options) {
        return $('<form />').sendRequest(handler, data, options);
    }
});

$(document).ready(function() {
	Installer.apiRequest('prepare');
	Installer.Pages.systemCheck.isRendered = true;
	Installer.showPage(Installer.ActivePage, true);
});