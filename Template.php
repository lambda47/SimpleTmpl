<?php
namespace SimpleTmpl;

class Template
{
	static private $instance = null;
	private $config = array(
		'left_delimiter' => '<!--{',
    	'right_delimiter' => '}-->',
    	'depth' => 3,
		'template_dir' => './',
    	'cache_dir' => './'
	);

	private function Template()
	{
	}

	static public function getInstance()
	{
		if(is_null(self::$instance)) {
			self::$instance = new Template();
		}
		return self::$instance;
	}

	public function readFile($file)
	{
		$file_path = $this->config['template_dir'].$file.'.phtml';
		return file_get_contents($file_path);
	}

	public function setConfig($config)
	{
		$this->config = array_merge($this->config, $config);
	}

	public function display($file)
	{
		$content = $this->readFile($file);
		$compile = new Compile(array(
			'left_delimiter' => $this->config['left_delimiter'],
			'right_delimiter' => $this->config['right_delimiter'],
			'depth' => $this->config['depth'],
			'read_file_handler' => array($this, 'readFile')
		));
		return $compile->trans($content);
	}
}

