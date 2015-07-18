/*!
 * Install Progress (Step 5)
 */

 /*
* Final Pages
*/
Installer.Pages.installComplete.title = 'Congratulations!';

Installer.Pages.installComplete.beforeShow = function() {

	var backendUri = Installer.Data.config.backend_uri,
		baseUrl = Installer.baseUrl;

		if (baseUrl.charAt(baseUrl.length - 1) == '/')
			baseUrl = baseUrl.substr(0, baseUrl.length - 1);

	Installer.Pages.installComplete.baseUrl = Installer.baseUrl;
	Installer.Pages.installComplete.backendUrl = baseUrl + backendUri;
}