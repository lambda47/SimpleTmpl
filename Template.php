<?php
/**
 * @author	Lambda47 <liwei8747@163.com>
 * @version	1.0
 * @link	https://github.com/lambda47/SimpleTmpl
 */
namespace SimpleTmpl;

/**
 * 模板引擎
 * @package SimpleTmpl
 */
class Template
{
	/**
	 * 模板引擎单例模式对象
	 * @var SimpleTmpl
	 * @static
	 * @access private
	 */
    static private $instance = null;   
    /**
     * 默认配置
     *
     * left_delimiter	=> (string)模板标签起始分隔符
     * right_delimiter	=> (string)模板标签结束分隔符
     * depth			=> (integer)闭合标签嵌套深度
     * template_dir		=> (string)模板文件存放目录
     * cache_dir		=> (string)模板文件缓存存放目录
     * template_suffix	=> (string)模板文件默认后缀名
     *
     * @var array
     * @access private
     */
    private $config = array(
        'left_delimiter' => '<!--{',
        'right_delimiter' => '}-->',
        'depth' => 3,
        'template_dir' => './',
        'cache_dir' => './',
        'template_suffix' => 'phtml'
    );

    /**
     * 构造函数
     * @access private
     * @return void
     */
    private function __construct()
    {

    }

    /**
     * 获取模板引擎代理模式对象
     * @access public
     * @static
     * @return Template
     */
    public static function getInstance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new Template();
        }
        return self::$instance;
    }

    /**
     * 读取模板文件
     * @access public
     * @param string $file 模板文件名
     * @return string
     */
    public function readFile($file)
    {
        $file_path = $this->config['template_dir'].$file.'.'.$this->config['template_suffix'];
        return file_get_contents($file_path);
    }

    /**
     * 模板引擎设置
     * @access public
     * @param array $config 配置
     * @return void
     */
    public function setConfig($config)
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * 获取模板解析后php代码
     *
     * 如果存在缓存直接读取缓存文件，否者解析模板，并生成缓存
     *
     * @access public
     * @param string $file 模板文件名
     * @return string
     */
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

    /**
     * 输出模板解析后php代码
     * @access public
     * @param string $file 模板文件名
     * @return void
     */
    public function display($file)
    {
        echo $this->render($file);
    }
}
