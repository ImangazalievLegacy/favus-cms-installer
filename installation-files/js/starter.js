/*!
 * Starter Form (Step 3)
 */

/*
* Starter Page
*/
Installer.Pages.starterForm.title = 'Getting started';

Installer.Pages.starterForm.init = function() {
	var starterForm = $('#starterForm').addClass('animate fade_in');
}

Installer.Pages.starterForm.next = function() {
}

Installer.Pages.starterForm.start = function() {
	Installer.showPage('installProgress');
}