<?php

class Ajax
{
	/**
	 * @var Installer
	 */
	protected $installer;
	
	function __construct()
	{
		$this->installer = new Installer;
	}

	/**
	 * Handle AJAX request.
	 * 
	 * @return boolean
	 */
	public function handle()
	{
		$action = $this->post('action');

		switch ($action) {

			case 'prepare':
				$result = $this->installer->prepare();
				break;
			case 'checkRequirement':
				$code = $this->post('code');

				$result = $this->installer->checkRequirement($code);
				break;
			case 'validateGeneralConfig': 

				$result = $this->installer->validateGeneralConfig();
				break;
			case 'validateDatabase': 
				
				$result = $this->validateDatabase();
				break;
			case 'validateAdminAccount': 

				$result = $this->installer->validateAdminAccount();
				break;
			case 'validateAdvancedConfig': 

				$result = $this->installer->validateAdvancedConfig();
				break;
			case 'validateMailConfig': 

				$result = $this->installer->validateMailConfig();
				break;
			case 'installStep': 

				$result = $this->installStep();
				break;
			default:
				throw new Exception(sprintf('Unknown command "%s"', $action));
				break;
		}

		return $result;
	}

	/**
	 * Getting the query variables.
	 * 
	 * @param string $var name of field
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

	/**
	 * Return installer instance.
	 * 
	 * @return Installer
	 */
	public function getInstaller()
	{
		return $this->installer;
	}

	/**
	 * System installation.
	 * 
	 * @return boolean
	 */
	public function installStep()
	{
		$installStep = $this->post('step');

		$this->installer->log('Install step: %s', $installStep);

		switch ($installStep) {

			case 'downloadVendor': 

				$result = true;
				break;
			case 'downloadApplication': 

				$result = true;
				break;
			case 'extractVendor':
				$result = $this->installer->extractVendor();
				break;
			case 'extractApplication':
				$result = $this->installer->extractApplication();
				break;
			case 'setupConfig': 

				$result = $this->installer->setupConfig();
				break;
			case 'migrate':

				$result = $this->installer->migrate();	
				break;
			case 'seed':

				$result = $this->installer->seed();
				break;
			case 'setupRoles':

				$result = $this->installer->setupRoles();
				break;
			case 'createAdminAccount':
			
				$result = $this->installer->createAdminAccount();
				break;
			case 'completeInstallation':
				
				$result = $this->installer->completeInstallation();
				break;
			default:
				throw new Exception(sprintf('Unknown step "%s"', $installStep));
				break;
		}

		$this->installer->log('Step %s +OK', $installStep);

		return $result;
	}

	/**
	 * Checks database config received from the user.
	 * 
	 * @return boolean
	 */
	protected function validateDatabase()
	{	
		if ($this->post('db_type') != 'sqlite' && !strlen($this->post('db_host')))
		{
			throw new InstallerException('Please specify a database host', 'db_host');
		}

		if (!strlen($this->post('db_name')))
		{
			throw new InstallerException('Please specify the database name', 'db_name');
		}
	
		$config = array_merge(array(
			'type'   => null,
			'host'   => null,
			'name'   => null,
			'port'   => null,
			'user'   => null,
			'pass'   => null,
			'prefix' => null,
		), array(
			'type'   => $this->post('driver', 'mysql'),
			'host'   => $this->post('db_host'),
			'name'   => $this->post('db_name'),
			'user'   => $this->post('db_user'),
			'pass'   => $this->post('db_pass'),
			'port'   => $this->post('db_port'),
			'prefix' => $this->post('prefix', ''),
		));

		return $this->installer->validateDatabase($config);
	}
}