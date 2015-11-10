<?php
namespace SimpleTmpl;

define('PARSE', 'SIMPLE_TMPL_PARSE');
define('RESTORE', 'SIMPLE_TMPL_RESTORE');

class Compile
{
    private $left_delimiter  = '<!--{';
    private $right_delimiter  = '}-->';
    private $depth = 3;
    private $rand_id = '';
    private $read_file_handler;
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

    public function __construct($config)
    {
        $this->left_delimiter = empty($config['left_delimiter']) ? $this->liet_delimiter : $config['left_delimiter'];
        $this->right_delimiter = empty($config['right_delimiter']) ? $this->right_delimiter
            : $config['right_delimiter'];
        $this->depth = empty($config['depth']) ? $this->depth : $config['depth'];
        $this->read_file_handler = $config['read_file_handler'];
    }

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

    private function expTrans($matches)
    {
        $keys_arr = explode('.', substr($matches[2], 1));
        return $matches[1].array_reduce($keys_arr, function ($str, $item) {
            return $str.'[\''.$item.'\']';
        }, '');
    }

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

    private function ifHandler($matches)
    {
        $attrs = $this->attrHandler($matches[1]);
        $condition = preg_replace_callback('/(\$[^\.]+)((?:\.\w+)+)/', array($this, 'expTrans'), $attrs['condition']);
        $result = '<?php if('.$condition.'):?>';
        $result .= $matches[2];
        $result .= '<?php endif;?>';
        return $result;
    }

    private function elseifHandler($matches)
    {
        $attrs = $this->attrHandler($matches[1]);
        $condition = preg_replace_callback('/(\$[^\.]+)((?:\.\w+)+)/', array($this, 'expTrans'), $attrs['condition']);
        $result = '<?php elseif('.$condition.'):?>';
        return $result;
    }

    private function elseHandler()
    {
        $result = '<?php else:?>';
        return $result;
    }

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

    private function defaultHandler()
    {
        $result = '<?php default:?>';
        return $result;
    }

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

    private function varTrans($content)
    {
        $pattern = '/{{(.*?)}}/';
        return preg_replace_callback($pattern, function ($matches) {
            $var = preg_replace_callback('/(\$[^\.]+)((?:\.\w+)+)/', array($this, 'expTrans'), $matches[1]);
            $result = '<?php echo '.$var.';?>';
            return $result;
        }, $content);
    }

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

    private function commentTrans($content)
    {
        $content = preg_replace('/'.$this->left_delimiter .'\/\/.*?'.$this->right_delimiter .'/m', '', $content);
        $content = preg_replace('/'.$this->left_delimiter .'\/\*.*?\*\/'.$this->right_delimiter .'/s', '', $content);
        return $content;
    }

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
