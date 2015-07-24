<?php

class Installer
{
	/**
	 * @var string Application base path.
	 */
	protected $baseDirectory;

	/**
	 * @var string A temporary working directory.
	 */
	protected $tempDirectory;

	/**
	 * @var string Expected path where configuration files can be found.
	 */
	protected $configDirectory;

	/**
	 * @var InstallerRewrite Configuration rewriter object.
	 */
	protected $rewriter;

	protected $logFile;

	/**
	 * Create a Installer instance.
	 * 
	 * @return void
	 */
	function __construct() 
	{
		$this->baseDirectory   = PATH_INSTALL;
		$this->tempDirectory   = PATH_INSTALL . '/../installation-files/temp';
		$this->configDirectory = $this->baseDirectory . '/app/config';
		$this->logFile = $this->tempDirectory . '/install.log';

		$this->rewriter = new ConfigRewriter;

		$this->logPost();
	}

	/**
	 * Getting the query variable.
	 * 
	 * @param string $var name of the field
	 * @param mixed $default default value
	 * @return mixed
	 */
	protected function post($var, $default = null)
	{
		if (array_key_exists($var, $_REQUEST))
		{
			$result = $_REQUEST[$var];

			if (is_string($result)) $result = trim($result);
			return $result;
		}

		return $default;
	}

	public function sessionStart()
	{
		if (session_status() !== PHP_SESSION_ACTIVE)
		{
			session_start();
		}
	}

	/**
	 * Getting the session variable.
	 * 
	 * @param string $key
	 * @param mixed $default default value
	 * @return mixed
	 */
	protected function sessionGet($key, $default = null)
	{
		$this->sessionStart();

		if (array_key_exists($key, $_SESSION))
		{
			$result = $_SESSION[$key];

			return $result;
		}

		return $default;
	}

	/**
	 * Save value in session.
	 * 
	 * @param string $key
	 * @param mixed $value
	 * @return mixed
	 */
	protected function sessionSet($key, $value)
	{
		$this->sessionStart();

		$_SESSION[$key] = $value;

		return $this;
	}

	protected function sessionSaveConfig($name, $keys)
	{
		$data = [];

		foreach ($keys as $key => $value) {
			
			$data[$key] = $this->post($value);

		}

		$serialized = serialize($data);
		
		return $this->sessionSet($name, $serialized);
	}

	protected function sessionGetConfig($name, $key = null)
	{
		$unserialized = unserialize($this->sessionGet($name));

		if ($key !== null)
		{
			return (array_key_exists($key, $unserialized)) ? $unserialized[$key] : null;
		}
		
		return $unserialized;
	}

	protected function cleanSession()
	{
		session_start();
		session_unset();
	}

	/**
	 * Preparing for installation.
	 * 
	 * @return boolean
	 */
	public function prepare()
	{
		$this->cleanSession();
		$this->cleanLog();

		return true;
	}

	/**
	 * Check PHP version.
	 * 
	 * @return boolean
	 */
	public function checkPhpVersion()
	{
		return version_compare(PHP_VERSION , "5.4", ">=");
	}

	/**
	 * Check requirement(s).
	 * 
	 * @return boolean
	 */
	public function checkRequirement($code = '*')
	{
		$result = false;

		if ($code == '*')
		{
			$result = is_writable(PATH_INSTALL) && is_writable($this->logFile) && defined('PDO::ATTR_DRIVER_NAME') and extension_loaded('mcrypt') and extension_loaded('openssl') and extension_loaded('gd') and function_exists('curl_init') and defined('CURLOPT_FOLLOWLOCATION') and class_exists('ZipArchive');
		}
		
		switch ($code) {

			case 'phpVersion':
				$result = $this->checkPhpVersion();
				break;
			case 'curlLibrary':
				$result = function_exists('curl_init') && defined('CURLOPT_FOLLOWLOCATION');
				break;
			case 'liveConnection':
				$result = true;
				break;
			case 'writePermission':
				$result = is_writable(PATH_INSTALL) && is_writable($this->logFile);
				break;
			case 'pdoLibrary':
				$result = defined('PDO::ATTR_DRIVER_NAME');
				break;
			case 'mcryptLibrary':
				$result = extension_loaded('mcrypt');
				break;
			case 'mbstringLibrary':
				$result = extension_loaded('mbstring');
				break;
			case 'sslLibrary':
				$result = extension_loaded('openssl');
				break;
			case 'gdLibrary':
				$result = extension_loaded('gd');
				break;
			case 'zipLibrary':
				$result = class_exists('ZipArchive');
				break;
		}

		return $result;
	}

	/**
	 * Checks general information about shop received from the user.
	 * 
	 * @return boolean
	 */
	public function validateGeneralConfig()
	{
		if (!is_string($this->post('sitename')) or !strlen($this->post('sitename')))
			throw new InstallerException('Please enter correct sitename', 'sitename');

		$this->sessionSaveConfig('general', ['sitename' => 'sitename']);

		return true;
	}

	/**
	 * Checks database config received from the user.
	 * 
	 * @return boolean
	 */
	public function validateDatabase($config)
	{
		extract($config);

		switch ($type) {

			case 'mysql':
				$dsn = 'mysql:host='.$host.';dbname='.$name;
				if ($port) $dsn .= ";port=".$port;
				break;

			case 'pgsql':
				$_host = ($host) ? 'host='.$host.';' : '';
				$dsn = 'pgsql:'.$_host.'dbname='.$name;
				if ($port) $dsn .= ";port=".$port;
				break;

			case 'sqlite':
				$dsn = 'sqlite:'.$name;
				$this->validateSqliteFile($name);
				break;

			case 'sqlsrv':
				$availableDrivers = PDO::getAvailableDrivers();
				$_port = $port ? ','.$port : '';
				if (in_array('dblib', $availableDrivers))
					$dsn = 'dblib:host='.$host.$_port.';dbname='.$name;
				else {
					$_name = ($name != '') ? ';Database='.$name : '';
					$dsn = 'dblib:host='.$host.$_port.$_name;
				}
			break;
		}

		try {
			$this->log('DSN: ' . $dsn);
			$db = new PDO($dsn, $user, $pass, array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION));

		}
		catch (PDOException $e) {

			$this->log('Connection failed: ' . $e->getMessage());
			throw new Exception('Connection failed: ' . $e->getMessage());
		}

		/*
		 * Check the database is empty
		 */
		if ($type == 'sqlite')
		{
			$fetch = $db->query("SELECT name FROM sqlite_master WHERE type='table'", PDO::FETCH_NUM);
		}
		elseif ($type == 'pgsql')
		{
			$fetch = $db->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'", PDO::FETCH_NUM);
		}
		else
		{
			$fetch = $db->query('SHOW TABLES', PDO::FETCH_NUM);
		}

		$tables = 0;

		while ($result = $fetch->fetch()) $tables++;

		if ($tables > 0)
		{
			throw new Exception(sprintf('Database "%s" is not empty. Please empty the database or specify another database.', $name));
		}

		$this->sessionSaveConfig('database', ['driver' => 'driver', 'host' => 'db_host', 'database' => 'db_name', 'username' => 'db_user', 'password' => 'db_pass', 'port' => 'db_port', 'prefix' => 'prefix']);

		return true;
	}

	/**
	 * Checks administrator's information received from the user.
	 * 
	 * @return boolean
	 */
	public function validateAdminAccount()
	{
		if (!strlen($this->post('admin_email')))
			throw new InstallerException('Please specify administrator email address', 'admin_email');

		if (!filter_var($this->post('admin_email'), FILTER_VALIDATE_EMAIL))
			throw new InstallerException('Please specify valid email address', 'admin_email');

		if (!strlen($this->post('admin_login')))
			throw new InstallerException('Please specify username', 'admin_login');

		if (!strlen($this->post('admin_password')))
			throw new InstallerException('Please specify password', 'admin_password');

		if (!strlen($this->post('admin_confirm_password')))
			throw new InstallerException('Please confirm chosen password', 'admin_confirm_password');

		if (strcmp($this->post('admin_password'), $this->post('admin_confirm_password')))
			throw new InstallerException('Specified password does not match the confirmed password', 'admin_password');

		$this->sessionSaveConfig('admin', ['email' => 'admin_email', 'password' => 'admin_password', 'login' => 'admin_login']);

		return true;
	}

	/**
	 * Checks advanced config received from the user.
	 * 
	 * @return boolean
	 */
	public function validateAdvancedConfig()
	{
		if (!strlen($this->post('encryption_key')))
			throw new InstallerException('Please specify encryption key', 'encryption_key');

		$validKeyLengths = [16, 24, 32];
		if (!in_array(strlen($this->post('encryption_key')), $validKeyLengths))
			throw new InstallerException('The encryption key should be of a valid length ('.implode(', ', $validKeyLengths).').', 'encryption_key');

		$this->sessionSaveConfig('advanced', ['encryption_key' => 'encryption_key']);

		return true;
	}

	/**
	 * Checks mail config received from the user.
	 * 
	 * @return boolean
	 */
	public function validateMailConfig()
	{
		$driver = $this->post('mail_driver', 'mail');

		if ($driver == 'smtp')
		{
			if (!strlen($this->post('smtp_host')))
				throw new InstallerException('Please specify a SMTP host', 'smtp_host');

			if (is_integer($this->post('smtp_port')) or !in_array($this->post('smtp_port'), [465, 587]))
				throw new InstallerException('Please specify a correct SMTP encryption protocol', 'smtp_port');

			if (!strlen($this->post('smtp_username')))
				throw new InstallerException('Please specify SMTP username', 'smtp_username');

			if (!filter_var($this->post('smtp_username'), FILTER_VALIDATE_EMAIL))
				throw new InstallerException('Please specify valid SMTP username', 'smtp_username');

			if (!strlen($this->post('smtp_password')))
				throw new InstallerException('Please specify SMTP password', 'smtp_password');

			$_REQUEST['smtp_encryption'] = ($this->post('smtp_encryption') == 465) ? 'ssl' : 'tls';

			$this->sessionSaveConfig('mail', ['smtp_host' => 'smtp_host', 'smtp_port' => 'smtp_port', 'smtp_encryption' => 'smtp_encryption', 'smtp_username' => 'smtp_username', 'smtp_password' => 'smtp_password']);
		}

		$this->sessionSaveConfig('mail', ['driver' => 'driver', 'from' => 'from_address', 'sender' => 'sender']);

		return true;
	}

	/**
	 * Returns path to temporarity file.
	 * 
	 * @param string $filename
	 * @return string
	 */
	protected function getFilePath($filename)
	{
		$name = $filename . '.zip';

		return $this->tempDirectory . '/' . $name;
	}

	/**
	 * Unzip temporarity file to destination folder.
	 * 
	 * @param string $filename
	 * @return string
	 */
	protected function unzipFile($filename, $directory = null)
	{
		$source      = $this->getFilePath($filename);
		$destination = $this->baseDirectory;

		if ($directory)
		{
			$destination .= '/' . $directory;
		}

		if (!file_exists($source))
		{
			throw new Exception("File '{$source}' not found");
		}

		if (!file_exists($destination))
		{
			if (!mkdir($destination, 0777, true))
			{
				throw new Exception("Failed to create folder '{$destination}'");
			}
		}

		$zip = new ZipArchive;

		if ($zip->open($source) === true)
		{
			$zip->extractTo($destination);
			$zip->close();

			return true;
		}

		return false;
	}

	public function extractVendor()
	{
		if (is_dir($this->baseDirectory . '/vendor')) return true;		

		$this->unzipFile('vendor');

		if ($result === false)
		{
			throw new Exception('Unable to open vendor archive file');
		}

		if (!is_dir($this->baseDirectory . '/vendor'))
		{
			throw new Exception('Could not extract vendor files');
		}

		return true;
	}

	public function extractApplication()
	{
		if (is_dir($this->baseDirectory . '/app') and is_dir($this->baseDirectory . '/bootstrap') and is_dir($this->baseDirectory . '/public')) return true;

		$this->unzipFile('application');

		if ($result === false)
		{
			throw new Exception('Unable to open application archive file');
		}

		if (!is_dir($this->baseDirectory . '/app') or !is_dir($this->baseDirectory . '/bootstrap') or !is_dir($this->baseDirectory . '/public'))
		{
			throw new Exception('Could not extract application files');
		}

		return true;
	}

	protected function bootFramework($withServiceProviders = false)
	{
		$this->log('Framework booting');

		$autoloadFile = $this->baseDirectory.'/bootstrap/autoload.php';
		$startFile = $this->baseDirectory.'/bootstrap/start.php';

		if (!file_exists($autoloadFile))
			throw new Exception('Unable to find autoloader: ~/bootstrap/autoload.php');

		require_once $autoloadFile;

		if (!file_exists($startFile))
			throw new Exception('Unable to find start loader: ~/bootstrap/start.php');

		$app = require_once $startFile;

		$this->log($autoloadFile);
		$this->log($startFile);

		if ($withServiceProviders)
		{
			$app->boot();
		}

		$this->log('Framework booted');

		return $this;
	}

	protected function loadSystemInstaller($withServiceProviders = false)
	{
		$this->log('System installer loading');

		$this->bootFramework($withServiceProviders);

		$installer = new Favus\Installation\Installer();

		$this->log('System installer loaded');

		return $installer;
	}

	public function writeConfig($name, $data)
	{
		if (App::isLocal())
		{
			foreach ($data as $key => $value) {
				try {
					$this->rewriter->toFile($this->configDirectory . '/local/' . $name . '.php', [$key => $value]);
					
				} catch (Exception $e) {
					$this->rewriter->toFile($this->configDirectory . '/' . $name . '.php', [$key => $value]);
				}
			}
		}
		else
		{
			$this->rewriter->toFile($this->configDirectory . '/' . $name . '.php', $data);
		}
	}

	public function setupConfig()
	{
		$this->bootFramework();

		$this->writeConfig('app', array(

			'debug' => false,
			'key'   => $this->sessionGetConfig('advanced', 'encryption_key'),
		));

		$this->writeConfig('site/general', array(

			'sitename' => $this->sessionGetConfig('general', 'sitename'),
		));

		$key = $this->sessionGetConfig('database', 'driver');

		$this->writeConfig('database', array(

			'default' => $this->sessionGetConfig('database', 'driver'),
			'connections.' . $key . '.driver'   => $this->sessionGetConfig('database', 'driver'),
			'connections.' . $key . '.database' => $this->sessionGetConfig('database', 'database'),
			'connections.' . $key . '.prefix'   => $this->sessionGetConfig('database', 'prefix'),
		));

		if ($this->sessionGetConfig('database', 'driver') !== 'sqlite')
		{
			$this->writeConfig('database', array(

				'connections.' . $key . '.username'  => $this->sessionGetConfig('database', 'username'),
				'connections.' . $key . '.password'  => $this->sessionGetConfig('database', 'password'),
			));
		}

		if ($this->sessionGetConfig('mail', 'driver') == 'smtp')
		{
			$this->writeConfig('database', array(

				'default' => $this->sessionGetConfig('mail', 'driver'),
			));
		}

		$this->writeConfig('mail', array(

			'driver' => $this->sessionGetConfig('mail', 'driver'),
			'from.address'   => $this->sessionGetConfig('mail', 'from'),
			'from.name' => $this->sessionGetConfig('mail', 'sender'),

		));

		if ($this->sessionGetConfig('mail', 'driver') == 'smtp')
		{
			$this->writeConfig('mail', array(

				'host'       => $this->sessionGetConfig('mail', 'smtp_host'),
				'port'       => $this->sessionGetConfig('mail', 'smtp_port'),
				'encryption' => $this->sessionGetConfig('mail', 'smtp_encryption'),
				'username'   => $this->sessionGetConfig('mail', 'smtp_username'),
				'password'   => $this->sessionGetConfig('mail', 'smtp_password'),

			));
		}

		return true;
	}

	public function migrate()
	{
		$this->log('Start migrating');
		$output = $this->loadSystemInstaller()->migrate();
		$this->log('.============================ CONSOLE OUTPUT ============================.', false);
		$this->log($output);
		$this->log('Migrated');

		return true;
	}

	public function seed()
	{
		$this->log('Start seeding');
		$output = $this->loadSystemInstaller()->seed();
		$this->log('.============================ CONSOLE OUTPUT ============================.', false);
		$this->log($output);
		$this->log('Seeded');

		return true;
	}

	public function setupRoles()
	{
		$this->log('Start roles installing');
		$this->loadSystemInstaller(true)->setupRoles();
		$this->log('Roles installed');

		return true;
	}

	public function createAdminAccount()
	{
		$email    = $this->sessionGetConfig('admin', 'email');
		$username = $this->sessionGetConfig('admin', 'login');
		$password = $this->sessionGetConfig('admin', 'password');

		$this->log('Email: %s', $email);
		$this->log('Username: %s', $username);
		$this->log('Password: %s', $password);

		$this->log('Creating admin account');
		$this->loadSystemInstaller(true)->createAdminAccount($email, $username, $password);
		$this->log('Admin account created');

		return true;
	}

	protected function deleteTemporaryFiles()
	{
		// @unlink($this->tempDirectory . '/vendor.zip');
		// @unlink($this->tempDirectory . '/application.zip');
	}

	protected function copyHtaccess()
	{
		$source = __DIR__ . '/../others/htaccess';
		$destination = $this->baseDirectory . '/.htaccess';

		if (file_exists($source) and !file_exists($destination))
		{
			if (!copy($source, $destination))
			{
				throw new Exception('Failed to copy .htaccess file');
			}
		}
		else
		{
			if (!file_exists($source))
			{
				throw new Exception(sprintf('.htaccess file not found in "%s"', $source));
			}
		}
	}

	public function completeInstallation()
	{
		$this->cleanSession();
		$this->deleteTemporaryFiles();
		$this->copyHtaccess();
		$this->cleanLog();

		return true;
	}

	public function log()
	{
		$args = func_get_args();
		$message = array_shift($args);

		$last = func_num_args() - 2;
		$time = (isset($args[$last]) and ($args[$last] === false)) ? false : true;

		if (is_array($message)) { $message = implode(PHP_EOL, $message); }

		if ($time)
		{
			$message = "[" . date("Y/m/d h:i:s", time()) . "] " . vsprintf($message, $args) . PHP_EOL;
		}
		else
		{
			array_pop($args);
			$message = vsprintf($message, $args) . PHP_EOL;
		}

		file_put_contents($this->logFile, $message, FILE_APPEND);
	}

	protected function logPost()
	{
		if (!isset($_POST) || !count($_POST)) return;

		$postData = $_POST;

		$this->log('.============================ POST REQUEST ==========================.', false);
		$this->log('Postback payload: %s', print_r($postData, true));
	}

	protected function cleanLog()
	{
		$message = '';

		file_put_contents($this->logFile, $message);
	}
}