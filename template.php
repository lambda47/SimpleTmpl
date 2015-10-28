<?php
class Template {
	private $tag_begin = '<!--{';
	private $tag_end = '}-->';
	private $level = 3;

	private function parse_args($args) {
		$attrs = array();
		preg_match_all('/[^\s=]*=[^\s]*/', $args, $matches);
		foreach($matches[0] as $match) {
			list($attr, $val) = explode('=', $match);
			$attrs[$attr] = $val;
		}
		return $attrs;
	}

	private function trans_volist($matches) {
		$attrs = $this->parse_args($matches[1]);
		if(!isset($attrs['id'])) {
			$attrs['id'] = 'index';
		}
		if(!isset($attrs['val'])) {
			$attrs['val'] = 'item';
		}
		$result =  '<?php foreach($'.$attrs['name'].' as $'.$attrs['id'].' => $'.$attrs['val'].'):?>';
		$result .= $matches[2];
		$result .= '<?php endforeach;?>';
		return $result;
	}

	private function trans_if ($matches) {

	}

	private function trans_elseif () {

	}

	private function trans_else () {

	}

	private function parse_volist($content) {
		$pattern = '/'.$this->tag_begin.'volist\s*(.*)'.$this->tag_end.'(.*)'.$this->tag_begin.'\/volist'.$this->tag_end.'/s';
		return preg_replace_callback($pattern, array($this, 'trans_volist'), $content);
	}

	private function parse_if($content) {
		return $content;
	}

	private function parse_elseif($content) {
		return $content;
	}

	private function parse_else($content) {
		return $content;
	}

	public function parse($content) {
		for($i = 0; $i < $this->level; $i++) {
			$content = $this->parse_volist($content);
			$content = $this->parse_if($content);
		}
		$content = $this->parse_elseif($content);
		$content = $this->parse_else($content);
		return $content;
	}
}