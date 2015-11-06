<?php
class Template
{
    private $left_delimiter  = '<!--{';
    private $right_delimiter  = '}-->';
    private $depth = 3;
    private $rand_id = '';
    private $template_dir = '';
    private $cache_dir = '';
	private $tag_with_end = array(
		array('name' => 'volist', 'has_attr' => true),
		array('name' => 'foreach', 'has_attr' => true),
		array('name' => 'for', 'has_attr' => true),
		array('name' => 'if', 'has_attr' => true),
		array('name' => 'switch', 'has_attr' => true),
		array('name' => 'case', 'has_attr' => true)
	);
	private $tag_without_end = array(
		array('name' => 'elseif', 'has_attr' => true),
		array('name' => 'else', 'has_attr' => false),
		array('name' => 'default', 'has_attr' => false)
	);

    public function Template($config)
	{
        $this->left_delimiter = empty($config['left_delimiter']) ? $this->liet_delimiter : $config['left_delimiter'];
        $this->right_delimiter = empty($config['right_delimiter']) ? $this->right_delimiter : $config['right_delimiter'];
        $this->depth = empty($config['depth']) ? $this->depth : $config['depth'];
        $this->template_dir = empty($config['template_dir']) ? $this->template_dir : $config['template_dir'];
        $this->cache_dir = empty($config['cache_dir']) ? $this->cache_dir : $config['cache_dir'];
    }

    private static function randStr($len)
	{
        $srand_str = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $srand_len = strlen($srand_str);
        $str = '';
        for($i = 0; $i < $len; $i++) {
            $str .= $srand_str[rand(0, $srand_len - 1)];
        }
        return $str;
    }

    private function parse_args($args)
	{
        $attrs = array();
        preg_match_all('/[^\s=]*=".*?"/', $args, $matches);
        foreach($matches[0] as $match) {
            list($attr, $val) = explode('=', $match, 2);
            $attrs[$attr] = trim($val, '"');
        }
        return $attrs;
    }

    private function expTransfer($matches)
	{
        $keys_arr = explode('.', substr($matches[2], 1));
        return $matches[1].array_reduce($keys_arr, function($str, $item){
            return $str.'[\''.$item.'\']';
        }, '');
    }

    private function trans_volist($matches) {
        $attrs = $this->parse_args($matches[1]);
        if(!isset($attrs['id'])) {
            $attrs['id'] = 'item';
        }
        $name = $attrs['name'];
        $name_part_arr = explode('.', $name);
        $first_part = '$'.array_shift($name_part_arr);
        $arr_var_str = array_reduce($name_part_arr, function($str, $index_name) {
            $str .= '[\''.$index_name.'\']';
            return $str;
        }, $first_part);
        if(isset($attrs['offset']) || isset($attrs['length'])) {
            $offset = isset($attrs['offset']) ? max(intval($attrs['offset']), 1) : 0;
            $length = isset($attrs['length']) ? intval($attrs['length']) : 0;
            if($length === 0) {
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

    private function trans_foreach($matches) {
        $attrs = $this->parse_args($matches[1]);
        if(!isset($attrs['key'])) {
            $attrs['key'] = 'key';
        }
        if(!isset($attrs['item'])) {
            $attrs['item'] = 'item';
        }
        $name = $attrs['name'];
        $name_part_arr = explode('.', $name);
        $first_part = '$'.array_shift($name_part_arr);
        $arr_var_str = array_reduce($name_part_arr, function($str, $index_name) {
            $str .= '[\''.$index_name.'\']';
            return $str;
        }, $first_part);
        $result = '<?php foreach('.$arr_var_str.' as $'.$attrs['key'].' => $'.$attrs['item'].'):?>';
        $result .= $matches[2];
        $result .= '<?php endforeach;?>';
        return $result;
    }

    private function trans_for($matches) {
        $attrs = $this->parse_args($matches[1]);
        if(!isset($attrs['name'])) {
            $attrs['name'] = 'i';
        }
        $step = isset($attrs['step']) ? max(intval($attrs['step']), 1) : 1;
        $result = '<?php for($'.$attrs['name'].' = '.$attrs['start'].'; $'.$attrs['name'].' < '.$attrs['end'].'; $'.$attrs['name'].' += '.$step.'):?>';
        $result .= $matches[2];
        $result .= '<?php endfor;?>';
        return $result;
    }

    private function trans_if ($matches) {
        $attrs = $this->parse_args($matches[1]);
        $condition = preg_replace_callback('/(\$[^\.]+)((?:\.\w+)+)/', array($this, 'expTransfer'), $attrs['condition']);
        $result = '<?php if('.$condition.'):?>';
        $result .= $matches[2];
        $result .= '<?php endif;?>';
        return $result;
    }

    private function trans_elseif ($matches) {
        $attrs = $this->parse_args($matches[1]);
        $condition = preg_replace_callback('/(\$[^\.]+)((?:\.\w+)+)/', array($this, 'expTransfer'), $attrs['condition']);
        $result = '<?php elseif('.$condition.'):?>';
        return $result;
    }

    private function trans_else () {
        $result = '<?php else:?>';
        return $result;
    }

    private function trans_switch ($matches) {
        $attrs = $this->parse_args($matches[1]);
        $name = $attrs['name'];
        $name_part_arr = explode('.', $name);
        $first_part = '$'.array_shift($name_part_arr);
        $arr_var_str = array_reduce($name_part_arr, function($str, $index_name) {
            $str .= '[\''.$index_name.'\']';
            return $str;
        }, $first_part);
        $result = '<?php switch('.$arr_var_str.'):?>';
        $result .= $matches[2];
        $result .= '<?php endswitch;?>';
        return $result;
    }

    private function trans_case ($matches) {
        $attrs = $this->parse_args($matches[1]);
        if(!isset($attrs['break'])) {
            $break = 1;
        } else {
            $break = intval($attrs['break']) > 0 ? 1 : 0;
        }
        $val = $attrs['value'];
        $val_arr = explode('|', $val);
        $val_arr = array_map(function($item) {
            return $item[0] === '$' ? preg_replace_callback('/(\$[^\.]+)((?:\.\w+)+)/', array($this, 'expTransfer'), $item) : $item;
        }, $val_arr);
        $result = array_reduce($val_arr, function($str, $val) {
            return $str.'<?php case '.$val.':?>';
        }, '');
        $result .= $matches[2];
        if($break === 1) {
            $result .= '<?php break;?>';
        }
        return $result;
    }

    private function trans_default() {
        $result = '<?php default:?>';
        return $result;
    }

    private function trans_include($matches) {
        $attrs = $this->parse_args($matches[1]);
        $file_name = $attrs['file'];
        $include_tmp_content = file_get_contents($file_name);
        return $this->parse($include_tmp_content);
    }

    private function phpTrans($content) {
		$left_delimiter = preg_quote($this->left_delimiter);
		$right_delimiter = preg_quote($this->right_delimiter);
        $pattern = '/'.$left_delimiter .'php'.$right_delimiter .'(.*?)'.$left_delimiter .'\/php'.$right_delimiter .'/s';
        return preg_replace_callback($pattern, function($matches) {
			$result = '<?php ';
			$result .= $matches[1];
			$result .= ' ?>';
			return $result;
		}, $content);
    }

    private function varTransfer($content)
	{
        $pattern = '/{{(.*?)}}/';
        return preg_replace_callback($pattern, function($matches) {
			$var = preg_replace_callback('/(\$[^\.]+)((?:\.\w+)+)/', array($this, 'expTransfer'), $matches[1]);
			$result = '<?php echo '.$var.';?>';
			return $result;
		}, $content);
    }

    private function literalTrans($content, $flag = 0)
	{
        if($flag === 0) {
            $this->rand_id = self::randStr(6);
        }
        $pattern = '/'.$this->left_delimiter .'literal'.$this->right_delimiter .'(.*?)'.$this->left_delimiter .'\/literal'.$this->right_delimiter .'/s';
        return preg_replace_callback($pattern, function($matches) use ($flag) {
            $source = array($this->left_delimiter , $this->right_delimiter , '{{', '}}');
            $destin = array('[@'.$this->rand_id, $this->rand_id.'@]', '{@'.$this->rand_id, $this->rand_id.'@}');
            if($flag === 0) {
                return $this->left_delimiter .'literal'.$this->right_delimiter .str_replace($source, $destin, $matches[1]).$this->left_delimiter .'/literal'.$this->right_delimiter ;
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

    private function parse_include($content) {
        $pattern = '/'.$this->left_delimiter .'include\s+(.*?)\/'.$this->right_delimiter .'/s';
        return preg_replace_callback($pattern, array($this, 'trans_include'), $content);
    }

	private function tagWithEndTrans($content)
	{
		foreach($this->tag_with_end as $tag) {
			$left_delimiter = preg_quote($this->left_delimiter);
			$right_delimiter = preg_quote($this->right_delimiter);
			$pattern = '/(?>'.$left_delimiter .$tag['name'].($tag['has_attr'] ? '\s+(.*?)' : '').$right_delimiter .')((?:.(?!'.$left_delimiter .$tag['name'].'.*?'.$right_delimiter .'))*?)'.$left_delimiter .'\/'.$tag['name'].$right_delimiter .'/s';
			$content = preg_replace_callback($pattern, array($this, 'trans_'.$tag['name']), $content);
		}
		return $content;
	}

	private function tagWithoutEndTrans($content)
	{
		foreach($this->tag_without_end as $tag) {
			$left_delimiter = preg_quote($this->left_delimiter);
			$right_delimiter = preg_quote($this->right_delimiter);
			$pattern = '/'.$left_delimiter .$tag['name'].($tag['has_attr'] ? '\s+(.*?)' : '').'\/'.$right_delimiter .'/s';
			$content = preg_replace_callback($pattern, array($this, 'trans_'.$tag['name']), $content);
		}
		return $content;
	}

    public function parse($content) {
        $content = $this->parse_include($content);
        $content = $this->literalTrans($content);
        for($i = 0; $i < $this->depth; $i++) {
            $content = $this->tagWithEndTrans($content);
        }
        $content = $this->tagWithoutEndTrans($content);
        $content = $this->varTransfer($content);
        $content = $this->phpTrans($content);
        $content = $this->literalTrans($content, 1);
        $content = $this->commentTrans($content);
        return $content;
    }
}
