<?php
class Template {
	private $tag_begin = '<!--{';
	private $tag_end = '}-->';
	private $level = 3;
	private $rand_id = '';

	private static function rand_str($len) {
		$srand_str = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
		$srand_len = strlen($srand_str);
		$str = '';
		for($i = 0; $i < $len; $i++) {
			$str .= $srand_str[rand(0, $srand_len - 1)];
		}
		return $str;
	}

	private function parse_args($args) {
		$attrs = array();
		preg_match_all('/[^\s=]*=".*?"/', $args, $matches);
		foreach($matches[0] as $match) {
			list($attr, $val) = explode('=', $match, 2);
			$attrs[$attr] = trim($val, '"');
		}
		return $attrs;
	}

	private function parse_cond_var($matches) {
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
		$condition = preg_replace_callback('/(\$[^\.]+)((?:\.\w+)+)/', array($this, 'parse_cond_var'), $attrs['condition']);
		$result = '<?php if('.$condition.'):?>';
		$result .= $matches[2];
		$result .= '<?php endif;?>';
		return $result;
	}

	private function trans_elseif ($matches) {
		$attrs = $this->parse_args($matches[1]);
		$condition = preg_replace_callback('/(\$[^\.]+)((?:\.\w+)+)/', array($this, 'parse_cond_var'), $attrs['condition']);
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
			return $item[0] === '$' ? preg_replace_callback('/(\$[^\.]+)((?:\.\w+)+)/', array($this, 'parse_cond_var'), $item) : $item;
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

	private function trans_php($matches) {
		$result = '<?php ';
		$result .= $matches[1];
		$result .= ' ?>';
		return $result;
	}

	private function trans_var($matches) {
		$var = preg_replace_callback('/(\$[^\.]+)((?:\.\w+)+)/', array($this, 'parse_cond_var'), $matches[1]);
		$result = '<?php echo '.$var.';?>';
		return $result;
	}

	private function trans_include($matches) {
		$attrs = $this->parse_args($matches[1]);
		$file_name = $attrs['file'];
		$include_tmp_content = file_get_contents($file_name);
		return $this->parse($include_tmp_content);
	}

	private function parse_volist($content) {
		$pattern = '/(?>'.$this->tag_begin.'volist\s+(.*?)'.$this->tag_end.')((?:.(?!'.$this->tag_begin.'volist.*?'.$this->tag_end.'))*?)'.$this->tag_begin.'\/volist'.$this->tag_end.'/s';
		return preg_replace_callback($pattern, array($this, 'trans_volist'), $content);
	}

	private function parse_foreach($content) {
		$pattern = '/(?>'.$this->tag_begin.'foreach\s+(.*?)'.$this->tag_end.')((?:.(?!'.$this->tag_begin.'foreach.*?'.$this->tag_end.'))*?)'.$this->tag_begin.'\/foreach'.$this->tag_end.'/s';
		return preg_replace_callback($pattern, array($this, 'trans_foreach'), $content);
	}

	private function parse_for($content) {
		$pattern = '/(?>'.$this->tag_begin.'for\s+(.*?)'.$this->tag_end.')((?:.(?!'.$this->tag_begin.'for.*?'.$this->tag_end.'))*?)'.$this->tag_begin.'\/for'.$this->tag_end.'/s';
		return preg_replace_callback($pattern, array($this, 'trans_for'), $content);
	}

	private function parse_if($content) {
		$pattern = '/(?>'.$this->tag_begin.'if\s+(.*?)'.$this->tag_end.')((?:.(?!'.$this->tag_begin.'if.*?'.$this->tag_end.'))*?)'.$this->tag_begin.'\/if'.$this->tag_end.'/s';
		return preg_replace_callback($pattern, array($this, 'trans_if'), $content);
	}

	private function parse_elseif($content) {
		$pattern = '/'.$this->tag_begin.'elseif\s+(.*?)\/'.$this->tag_end.'/s';
		return preg_replace_callback($pattern, array($this, 'trans_elseif'), $content);
	}

	private function parse_else($content) {
		$pattern = '/'.$this->tag_begin.'else\/'.$this->tag_end.'/s';
		return preg_replace_callback($pattern, array($this, 'trans_else'), $content);
	}

	private function parse_switch($content) {
		$pattern = '/(?>'.$this->tag_begin.'switch\s+(.*?)'.$this->tag_end.')((?:.(?!'.$this->tag_begin.'switch.*?'.$this->tag_end.'))*?)'.$this->tag_begin.'\/switch'.$this->tag_end.'/s';
		return preg_replace_callback($pattern, array($this, 'trans_switch'), $content);
	}

	private function parse_case($content) {
		$pattern = '/(?>'.$this->tag_begin.'case\s+(.*?)'.$this->tag_end.')((?:.(?!'.$this->tag_begin.'case.*?'.$this->tag_end.'))*?)'.$this->tag_begin.'\/case'.$this->tag_end.'/s';
		return preg_replace_callback($pattern, array($this, 'trans_case'), $content);
	}

	private function parse_default($content) {
		$pattern = '/'.$this->tag_begin.'default\/'.$this->tag_end.'/';
		return preg_replace_callback($pattern, array($this, 'trans_default'), $content);
	}

	private function parse_php($content) {
		$pattern = '/'.$this->tag_begin.'php'.$this->tag_end.'(.*?)'.$this->tag_begin.'\/php'.$this->tag_end.'/s';
		return preg_replace_callback($pattern, array($this, 'trans_php'), $content);
	}

	private function parse_var($content) {
		$pattern = '/{{(.*?)}}/';
		return preg_replace_callback($pattern, array($this, 'trans_var'), $content);
	}

	private function literal_transfer($content, $flag = 0) {
		if($flag === 0) {
			$this->rand_id = self::rand_str(6);
		}
		$pattern = '/'.$this->tag_begin.'literal'.$this->tag_end.'(.*?)'.$this->tag_begin.'\/literal'.$this->tag_end.'/s';
		return preg_replace_callback($pattern, function($matches) use ($flag) {
			$source = array($this->tag_begin, $this->tag_end, '{{', '}}');
			$destin = array('[@'.$this->rand_id, $this->rand_id.'@]', '{@'.$this->rand_id, $this->rand_id.'@}');
			if($flag === 0) {
				return $this->tag_begin.'literal'.$this->tag_end.str_replace($source, $destin, $matches[1]).$this->tag_begin.'/literal'.$this->tag_end;
			} else {
				return str_replace($destin, $source, $matches[1]);
			}
		}, $content);
	}

	private function parse_comment($content) {
		$content = preg_replace('/'.$this->tag_begin.'\/\/.*?'.$this->tag_end.'/m', '', $content);
		$content = preg_replace('/'.$this->tag_begin.'\/\*.*?'.$this->tag_end.'\*\//m', '', $content);
		return $content;
	}

	private function parse_include($content) {
		$pattern = '/'.$this->tag_begin.'include\s+(.*?)\/'.$this->tag_end.'/s';
		return preg_replace_callback($pattern, array($this, 'trans_include'), $content);
	}

	public function parse($content) {
		$content = $this->parse_include($content);
		$content = $this->literal_transfer($content);
		for($i = 0; $i < $this->level; $i++) {
			$content = $this->parse_volist($content);
			$content = $this->parse_foreach($content);
			$content = $this->parse_for($content);
			$content = $this->parse_if($content);
			$content = $this->parse_switch($content);
			$content = $this->parse_case($content);
		}
		$content = $this->parse_elseif($content);
		$content = $this->parse_else($content);
		$content = $this->parse_default($content);
		$content = $this->parse_var($content);
		$content = $this->parse_php($content);
		$content = $this->literal_transfer($content, 1);
		$content = $this->parse_comment($content);
		return $content;
	}
}