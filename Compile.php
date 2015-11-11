<?php
/**
 * @author  Lambda47 <liwei8747@163.com>
 * @version 1.0
 * @link    https://github.com/lambda47/SimpleTmpl
 */
namespace SimpleTmpl;

/**
 * 用于literal标签解析的常量
 */
define('PARSE', 'SIMPLE_TMPL_PARSE');
define('RESTORE', 'SIMPLE_TMPL_RESTORE');

/**
 * 模板解析类
 * @package SimpleTmpl
 */
class Compile
{
    /**
     * 模板标签起始分隔符
     * @var string
     * @access private
     */
    private $left_delimiter  = '<!--{';
    /**
     * 模板标签结束分隔符
     * @var string
     * @access private
     */
    private $right_delimiter  = '}-->';
    /**
     * 闭合标签嵌套深度
     * @var int
     * @access private
     */
    private $depth = 3;
    /**
     * 用于literal标签解析的随机字符串
     * @var string
     * @access private
     */
    private $rand_id = '';
    /**
     * 读取模板文件的函数
     * @var callback
     * @access private
     */
    private $read_file_handler;
    /**
     * 标签列表
     *
     * 包含所有执行解析的标签
     * name		=> (string)表示标签名
     * has_attr	=> (boolean)表示是否有属性
     * with_end	=> (boolean)表示标签是否闭合
     *
     * @var array
     * @access private
     */
    private $tags = array(
        array('name' => 'volist', 'has_attr' => true, 'with_end' => true),
        array('name' => 'foreach', 'has_attr' => true, 'with_end' => true),
        array('name' => 'for', 'has_attr' => true, 'with_end' => true),
        array('name' => 'if', 'has_attr' => true, 'with_end' => true),
        array('name' => 'switch', 'has_attr' => true, 'with_end' => true),
        array('name' => 'case', 'has_attr' => true, 'with_end' => true),
        array('name' => 'elseif', 'has_attr' => true, 'with_end' => false),
        array('name' => 'else', 'has_attr' => false, 'with_end' => false),
        array('name' => 'default', 'has_attr' => false, 'with_end' => false)
    );

    /**
     * 构造函数
     * @access public
     * @param array $config 相关配置
     * @return void
     */
    public function __construct($config)
    {
        $this->left_delimiter = empty($config['left_delimiter']) ? $this->liet_delimiter : $config['left_delimiter'];
        $this->right_delimiter = empty($config['right_delimiter']) ? $this->right_delimiter
            : $config['right_delimiter'];
        $this->depth = empty($config['depth']) ? $this->depth : $config['depth'];
        $this->read_file_handler = $config['read_file_handler'];
    }

    /**
     * 生成随机字符串
     * @access public
     * @static
     * @param integer $len 生成字符串长度
     * @return string
     */
    private static function randStr($len)
    {
        $srand_str = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $srand_len = strlen($srand_str);
        $str = '';
        for ($i = 0; $i < $len; $i++) {
            $str .= $srand_str[rand(0, $srand_len - 1)];
        }

        return $str;
    }

    /**
     * 标签属性解析
     * @access private
     * @param string $attrs_str 包含全部属性的字符串
     * @return array
     */
    private function attrHandler($attrs_str)
    {
        $attrs = array();
        preg_match_all('/[^\s=]*=".*?"/', $attrs_str, $matches);
        foreach ($matches[0] as $match) {
            list($attr, $val) = explode('=', $match, 2);
            $attrs[$attr] = trim($val, '"');
        }

        return $attrs;
    }

    /**
     * 变量、简单表达式解析
     * @access private
     * @param array $matches 正则表达式匹配结果
     * @return string
     */
    private function expTrans($matches)
    {
        $keys_arr = explode('.', substr($matches[2], 1));

        return $matches[1].array_reduce($keys_arr, function ($str, $item) {
            return $str.'[\''.$item.'\']';
        }, '');
    }

    /**
     * volist标签解析
     * @access private
     * @param array $matches 正则表达式匹配结果
     * @return string
     */
    private function volistHandler($matches)
    {
        $attrs = $this->attrHandler($matches[1]);
        if (!isset($attrs['id'])) {
            $attrs['id'] = 'item';
        }
        $name = $attrs['name'];
        $name_part_arr = explode('.', $name);
        $first_part = '$'.array_shift($name_part_arr);
        $arr_var_str = array_reduce($name_part_arr, function ($str, $index_name) {
            $str .= '[\''.$index_name.'\']';

            return $str;
        }, $first_part);
        if (isset($attrs['offset']) || isset($attrs['length'])) {
            $offset = isset($attrs['offset']) ? max(intval($attrs['offset']), 1) : 0;
            $length = isset($attrs['length']) ? intval($attrs['length']) : 0;
            if ($length === 0) {
                $arr_exp = 'array_slice('.$arr_var_str.', '.$offset.', NULL, true)';
            } else {
                $arr_exp = 'array_slice('.$arr_var_str.', '.$offset.', '.$length.', true)';
            }
        } else {
            $arr_exp = $arr_var_str;
        }
        $result =  '<?php foreach('.$arr_exp.' as $key => $'.$attrs['id'].'):?>';
        $result .= $matches[2];
        $result .= '<?php endforeach;?>';

        return $result;
    }

    /**
     * foreach标签解析
     * @access private
     * @param array $matches 正则表达式匹配结果
     * @return string
     */
    private function foreachHandler($matches)
    {
        $attrs = $this->attrHandler($matches[1]);
        if (!isset($attrs['key'])) {
            $attrs['key'] = 'key';
        }
        if (!isset($attrs['item'])) {
            $attrs['item'] = 'item';
        }
        $name = $attrs['name'];
        $name_part_arr = explode('.', $name);
        $first_part = '$'.array_shift($name_part_arr);
        $arr_var_str = array_reduce($name_part_arr, function ($str, $index_name) {
            $str .= '[\''.$index_name.'\']';

            return $str;
        }, $first_part);
        $result = '<?php foreach('.$arr_var_str.' as $'.$attrs['key'].' => $'.$attrs['item'].'):?>';
        $result .= $matches[2];
        $result .= '<?php endforeach;?>';

        return $result;
    }

    /**
     * for标签解析
     * @access private
     * @param array $matches 正则表达式匹配结果
     * @return string
     */
    private function forHandler($matches)
    {
        $attrs = $this->attrHandler($matches[1]);
        if (!isset($attrs['name'])) {
            $attrs['name'] = 'i';
        }
        $step = isset($attrs['step']) ? max(intval($attrs['step']), 1) : 1;
        $result = '<?php for($'.$attrs['name'].' = '.$attrs['start'].'; $'.$attrs['name'].
            ' < '.$attrs['end'].'; $'.$attrs['name'].' += '.$step.'):?>';
        $result .= $matches[2];
        $result .= '<?php endfor;?>';

        return $result;
    }

    /**
     * if标签解析
     * @access private
     * @param array $matches 正则表达式匹配结果
     * @return string
     */
    private function ifHandler($matches)
    {
        $attrs = $this->attrHandler($matches[1]);
        $condition = preg_replace_callback('/(\$[^\.]+)((?:\.\w+)+)/', array($this, 'expTrans'), $attrs['condition']);
        $result = '<?php if('.$condition.'):?>';
        $result .= $matches[2];
        $result .= '<?php endif;?>';

        return $result;
    }

    /**
     * elseif标签解析
     * @access private
     * @param array $matches 正则表达式匹配结果
     * @return string
     */
    private function elseifHandler($matches)
    {
        $attrs = $this->attrHandler($matches[1]);
        $condition = preg_replace_callback('/(\$[^\.]+)((?:\.\w+)+)/', array($this, 'expTrans'), $attrs['condition']);
        $result = '<?php elseif('.$condition.'):?>';

        return $result;
    }

    /**
     * else标签解析
     * @access private
     * @return string
     */
    private function elseHandler()
    {
        $result = '<?php else:?>';

        return $result;
    }

    /**
     * switch标签解析
     * @access private
     * @param array $matches 正则表达式匹配结果
     * @return string
     */
    private function switchHandler($matches)
    {
        $attrs = $this->attrHandler($matches[1]);
        $name = $attrs['name'];
        $name_part_arr = explode('.', $name);
        $first_part = '$'.array_shift($name_part_arr);
        $arr_var_str = array_reduce($name_part_arr, function ($str, $index_name) {
            $str .= '[\''.$index_name.'\']';

            return $str;
        }, $first_part);
        $result = '<?php switch('.$arr_var_str.'):?>';
        $result .= $matches[2];
        $result .= '<?php endswitch;?>';

        return $result;
    }

    /**
     * case标签解析
     * @access private
     * @param array $matches 正则表达式匹配结果
     * @return string
     */
    private function caseHandler($matches)
    {
        $attrs = $this->attrHandler($matches[1]);
        if (!isset($attrs['break'])) {
            $break = 1;
        } else {
            $break = intval($attrs['break']) > 0 ? 1 : 0;
        }
        $val = $attrs['value'];
        $val_arr = explode('|', $val);
        $val_arr = array_map(function ($item) {
            return $item[0] === '$' ? preg_replace_callback(
                '/(\$[^\.]+)((?:\.\w+)+)/',
                array($this, 'expTrans'),
                $item
            ) : $item;
        }, $val_arr);
        $result = array_reduce($val_arr, function ($str, $val) {
            return $str.'<?php case '.$val.':?>';
        }, '');
        $result .= $matches[2];
        if ($break === 1) {
            $result .= '<?php break;?>';
        }

        return $result;
    }

    /**
     * default标签解析
     * @access private
     * @return string
     */
    private function defaultHandler()
    {
        $result = '<?php default:?>';

        return $result;
    }

    /**
     * php标签解析
     * @access private
     * @param string $content 模板文件文本
     * @return string
     */
    private function phpTrans($content)
    {
        $left_delimiter = preg_quote($this->left_delimiter);
        $right_delimiter = preg_quote($this->right_delimiter);
        $pattern = '/'.$left_delimiter .'php'.$right_delimiter .'(.*?)'.$left_delimiter .'\/php'.$right_delimiter .'/s';

        return preg_replace_callback($pattern, function ($matches) {
            $result = '<?php ';
            $result .= $matches[1];
            $result .= ' ?>';

            return $result;
        }, $content);
    }

    /**
     * 变量标签识别
     * @access private
     * @param string $content 模板文件文本
     * @return string
     */
    private function varTrans($content)
    {
        $pattern = '/{{(.*?)}}/';

        return preg_replace_callback($pattern, function ($matches) {
            $var = preg_replace_callback('/(\$[^\.]+)((?:\.\w+)+)/', array($this, 'expTrans'), $matches[1]);
            $result = '<?php echo '.$var.';?>';

            return $result;
        }, $content);
    }

    /**
     * literal标签解析
     * 
     * 分为两个处理流程，第一个处理流程将literal标签内的其他标签替换为特殊字符，
     * 第二个处理流程恢复第一个处理流程中替换的内容。
     * @param string $content 模板文件文本
     * @param string $flag 处理流程标志
     * @return mixed
     */
    private function literalTrans($content, $flag = PARSE)
    {
        if ($flag === PARSE) {
            $this->rand_id = self::randStr(6);
        }
        $pattern = '/'.$this->left_delimiter .'literal'.$this->right_delimiter .'(.*?)'.
            $this->left_delimiter .'\/literal'.$this->right_delimiter .'/s';

        return preg_replace_callback($pattern, function ($matches) use ($flag) {
            $source = array($this->left_delimiter , $this->right_delimiter , '{{', '}}');
            $destin = array('[@'.$this->rand_id, $this->rand_id.'@]', '{@'.$this->rand_id, $this->rand_id.'@}');
            if ($flag === PARSE) {
                return $this->left_delimiter .'literal'.$this->right_delimiter .
                    str_replace($source, $destin, $matches[1]).
                    $this->left_delimiter .'/literal'.$this->right_delimiter ;
            } else {
                return str_replace($destin, $source, $matches[1]);
            }
        }, $content);
    }

    /**
     * 删除模板注释标签
     * @access private
     * @param string $content 模板文件文本
     * @return string
     */
    private function commentTrans($content)
    {
        $content = preg_replace('/'.$this->left_delimiter .'\/\/.*?'.$this->right_delimiter .'/m', '', $content);
        $content = preg_replace('/'.$this->left_delimiter .'\/\*.*?\*\/'.$this->right_delimiter .'/s', '', $content);

        return $content;
    }

    /**
     * 将模板中include标签替换成导入模板内容
     * @access private
     * @param string 模板文件文本
     * @return string
     */
    private function includeExpanse($content)
    {
        $pattern = '/'.$this->left_delimiter .'include\s+(.*?)\/'.$this->right_delimiter .'/s';

        return preg_replace_callback($pattern, function ($matches) {
            $attrs = $this->attrHandler($matches[1]);
            $file_name = $attrs['file'];
            $include_tmp_content = call_user_func_array($this->read_file_handler, array($file_name));

            return $this->trans($include_tmp_content);
        }, $content);
    }

    /**
     * 识别标签
     *
     * 识别$tags中定义的标签，调用对应的标签解析方法
     * @access private
     * @param string 模板文件文本
     * @return string
     */
    private function tagTrans($content)
    {
        foreach ($this->tags as $tag) {
            $left_delimiter = preg_quote($this->left_delimiter);
            $right_delimiter = preg_quote($this->right_delimiter);
            if ($tag['with_end']) {
                $pattern = '/(?>'.$left_delimiter .$tag['name'].($tag['has_attr'] ? '\s+(.*?)' : '').
                    $right_delimiter .')((?:.(?!'.$left_delimiter .$tag['name'].'.*?'.$right_delimiter .'))*?)'.
                    $left_delimiter .'\/'.$tag['name'].$right_delimiter .'/s';
                for ($i = 0; $i < $this->depth; $i++) {
                    $content = preg_replace_callback($pattern, array($this, $tag['name'].'Handler'), $content);
                }
            } else {
                $pattern = '/'.$left_delimiter .$tag['name'].($tag['has_attr'] ? '\s+(.*?)' : '').'\/'.
                    $right_delimiter .'/s';
                $content = preg_replace_callback($pattern, array($this, $tag['name'].'Handler'), $content);
            }
        }

        return $content;
    }

    /**
     * 解析模板
     * @access public
     * @param string 模板文件文本
     * @return string
     */
    public function trans($content)
    {
        $content = $this->includeExpanse($content);
        $content = $this->literalTrans($content, PARSE);
        $content = $this->tagTrans($content);
        $content = $this->varTrans($content);
        $content = $this->phpTrans($content);
        $content = $this->literalTrans($content, RESTORE);
        $content = $this->commentTrans($content);

        return $content;
    }
}
