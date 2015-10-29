<?php
class Template {
	private $tag_begin = '<!--{';
	private $tag_end = '}-->';
	private $level = 3;

	private function parse_args($args) {
		$attrs = array();
		preg_match_all('/[^\s=]*=".*?"/', $args, $matches);
		foreach($matches[0] as $match) {
			list($attr, $val) = explode('=', $match, 2);
			$attrs[$attr] = trim($val, '"');
		}
		return $attrs;
	}

	private function trans_volist($matches) {
		$attrs = $this->parse_args($matches[1]);
		if(!isset($attrs['id'])) {
			$attrs['id'] = 'item';
		}
		$result =  '<?php foreach($'.$attrs['name'].' as $key => $'.$attrs['id'].'):?>';
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
		$result = '<?php foreach($'.$attrs['name'].' as $'.$attrs['key'].' => $'.$attrs['item'].'):?>';
		$result .= $matches[2];
		$result .= '<?php endforeach;?>';
		return $result;
	}

	private function trans_if ($matches) {
		$attrs = $this->parse_args($matches[1]);
		$result = '<?php if('.$attrs['condition'].'):?>';
		$result .= $matches[2];
		$result .= '<?php endif;?>';
		return $result;
	}

	private function trans_elseif ($matches) {
		$attrs = $this->parse_args($matches[1]);
		$result = '<?php elseif('.$attrs['condition'].'):?>';
		return $result;
	}

	private function trans_else () {
		$result = '<?php else:?>';
		return $result;
	}

	private function trans_var($matches) {
		$var = preg_replace('/\.([^\.]+)/', "['$1']", $matches[1]);
		$result = '<?php echo '.$var.';?>';
		return $result;
	}

	private function parse_volist($content) {
		$pattern = '/'.$this->tag_begin.'volist\s+(.*?)'.$this->tag_end.'(.*)'.$this->tag_begin.'\/volist'.$this->tag_end.'/s';
		return preg_replace_callback($pattern, array($this, 'trans_volist'), $content);
	}

	private function parse_foreach($content) {
		$pattern = '/'.$this->tag_begin.'foreach\s+(.*?)'.$this->tag_end.'(.*)'.$this->tag_begin.'\/foreach'.$this->tag_end.'/s';
		return preg_replace_callback($pattern, array($this, 'trans_foreach'), $content);
	}

	private function parse_if($content) {
		$pattern = '/'.$this->tag_begin.'if\s+(.*?)'.$this->tag_end.'(.*)'.$this->tag_begin.'\/if'.$this->tag_end.'/s';
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

	private function parse_var($content) {
		$pattern = '/{{(.*?)}}/';
		return preg_replace_callback($pattern, array($this, 'trans_var'), $content);
	}

	public function parse($content) {
		for($i = 0; $i < $this->level; $i++) {
			$content = $this->parse_volist($content);
			$content = $this->parse_foreach($content);
			$content = $this->parse_if($content);
		}
		$content = $this->parse_elseif($content);
		$content = $this->parse_else($content);
		$content = $this->parse_var($content);

		return $content;
	}
}