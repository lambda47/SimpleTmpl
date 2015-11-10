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
        'cache_dir' => './',
        'template_suffix' => 'phtml'
    );

    private function __construct()
    {

    }

    public static function getInstance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new Template();
        }
        return self::$instance;
    }

    public function readFile($file)
    {
        $file_path = $this->config['template_dir'].$file.'.'.$this->config['template_suffix'];
        return file_get_contents($file_path);
    }

    public function setConfig($config)
    {
        $this->config = array_merge($this->config, $config);
    }

    public function render($file)
    {
        $tmpl_content = $this->readFile($file);
        $compile = new Compile(array(
            'left_delimiter' => $this->config['left_delimiter'],
            'right_delimiter' => $this->config['right_delimiter'],
            'depth' => $this->config['depth'],
            'read_file_handler' => array($this, 'readFile')
        ));
        $tmpl_file = $this->config['template_dir'].$file.'.'.$this->config['template_suffix'];
        $tmpl_file_mtime = filemtime($tmpl_file);
        $cache_file = $this->config['cache_dir'].sha1($file).'.php';
        if (file_exists($cache_file)) {
            $cache_file_mtime = filemtime($cache_file);
        } else {
            $cache_file_mtime = 0;
        }
        if ($tmpl_file_mtime >= $cache_file_mtime) {
            $output = $compile->trans($tmpl_content);
            file_put_contents($cache_file, $output);
            return $output;
        } else {
            return file_get_contents($cache_file);
        }
    }

    public function display($file)
    {
        echo $this->render($file);
    }
}
