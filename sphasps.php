<?php 

include_once('tokeniser.php');

class Sphasps
{
	protected $variables = array(), $mixins = array(), $variables_bak = array();
	protected $dir, $file;
	protected static $sp;
	protected $tok;
	
	
	public static function parse($file)
	{
		
		self::$sp = new self;
		
		self::$sp->dir = dirname($file);
		self::$sp->file = $file;
		
		$code = self::$sp->parsefile($file);
		$css = self::$sp->build($code);

		return $css;
	}
	
	public static function test($file)
	{
		
		echo "<pre style='background: #f3f3f3; padding: 1em; margin-bottom: 0.5em; line-height: 1.8em;'>";
		$css = self::parse($file);
		echo "</pre>";
		echo "<pre style='background: #eee; padding: 1em;'>".$css. "</pre>";
		
	}
		
	protected function parsefile($file)
	{
		$root_token = self::$sp->tokenize($file);
		
		self::$sp->parsedefs($root_token->children);
		
		self::$sp->reorder($root_token);
		$code = self::$sp->parseblock($root_token->children);
		
		return $code;
	}
	
	protected function parsefiledefs($file)
	{
		$root_token = self::$sp->tokenize($file);
		
		self::$sp->parsedefs($root_token->children);
		
	}
	
	protected function tokenize($file)
	{
		$src = file_get_contents($file);
		
		if(!self::$sp->tok) self::$sp->tok = new Sphasps_Tokeniser;
		
		return self::$sp->tok->tokenize($src);
		
	}
	
	/**
	 * Parse mixin and variable definitions and store the values
	 * 
	 * @param $tokens
	 */
	protected function parsedefs(&$tokens)
	{
		if($tokens && is_a($tokens, "Token")) $tokens = array($tokens);
		if($tokens) foreach($tokens as $in => $token)
		{
			switch($token->type)
			{
				case "variable":
					
					if(preg_match("/^(.*)!default *$/", $token->data[1], $m))
					{
							if(!$this->variables[$token->data[0]])
								$this->variables[$token->data[0]] = $this->parsevalue($m[1]);
					}
					else
						$this->variables[$token->data[0]] = $this->parsevalue($token->data[1]);
					
					break;
				case "mixindef":
					
					$this->reorder($token->children);
					$this->mixins[$token->data[0]] = $token;
					
					break;
			}
		}
		
	}
	
	/**
	 * Arrange properties before nested selectors
	 * 
	 */
	protected function reorder(&$tokens)
	{
		$ts = $tokens;
		$io=0;
		if($ts) foreach($ts as $in => $token)
		{
			switch($token->type)
			{
				case "selector":
					$o = array_splice($tokens, $in-$io++, 1);
					//echo "<< " . array_shift($o) . "<br />";
					//echo "== " . $token . "<br />";
					$tokens[] = $token;
					break;
			}
			
			if($token->children) $this->reorder($token->children);
		}
	}
	
	protected function parseblock(&$tokens)
	{
		$code = array();
		
		if($tokens) foreach($tokens as $in => $token)
		{
			#echo $token . "<br />";
			switch($token->type)
			{
				case "variable":
				case "mixindef":
					$this->parsedefs($token);
					break;
				case "comment":
					// no output
					break;
				
				case "csscomment":
					
					if($token->children) 
					{
						$code[] = $token->value;
						foreach($token->children as $t)
						{
							$code[] = " * " . $t->value;
						}
						$code[] = " */";
					}
					else $code[] = $token->value . " */";
					
					break;
				
				case "mixin":
					$mixin = $this->mixins[$token->data[0]];
					
					$mt = $this->mixins[$token->data[0]]->children;
					
					// Arguments
					$mixvars = preg_split("/, */", $mixin->data[1]);
					$mixvar_values = preg_split("/, */", $token->data[1]);
					
					foreach($mixvars as $mi => $mixvar)
					{
						$mixvar = ltrim($mixvar, "$!");
						if($this->variables[$mixvar]) 
							$this->variables_bak[$mixvar] = $this->variables[$mixvar];
						
						$this->variables[$mixvar] = $mixvar_values[$mi];
					}
					
					foreach($mt as &$mtc) $mtc->parent = $token->parent;
					$code[] = $this->parseblock($mt);
					
					// Remove mixin arguments from variables and 
					// restore global variables hidden by mixin arguments
					foreach($mixvars as $mi => $mixvar)
					{
						$mixvar = ltrim($mixvar, "$!");
						if($this->variables_bak[$mixvar])
						{
							$this->variables[$mixvar] = $this->variables_bak[$mixvar];
							unset($this->variables_bak[$mixvar]);
						}
						else unset($this->variables[$mixvar]);
						
					}
					
					break;
				
				case "command":
					
					switch($token->data[0])
					{
						case "for":
							if(!$token->children) break;
							
							if(!preg_match('/[!$]([a-z0-9_-]+) from (\d+) (to|through) (\d+)/', $token->data[1], $m) &&
							   !preg_match('/[!$]([a-z0-9_-]+) *[:=] *(\d+) *(\.\.) *(\d+)/', $token->data[1], $m))
							   break;

							$var = $m[1];
							$from = $m[2];
							$to = $m[4];
							$through = ($m[3] == "through") || ($m[3] == "..");
							
							for($i = $from; $i < ($through ? $to+1 : $to); $i++)
							{
								$this->variables[$var] = $i;
								$code[] = $this->parseblock($token->children);
							}
							
							break;
							
						case "while":
							if(!$token->children) break;
							while(eval("return " . $this->parsevalue($token->data[1]) . ";"))
							{
								$code[] = $this->parseblock($token->children);
								
							}
							
								
							break;
							
						case "if":
							if(!$token->children) break;
							if(eval("return " . $this->parsevalue($token->data[1]) . ";"))
								$code[] = $this->parseblock($token->children);
								
							break;
						
						case "import":
								
								$file = trim($token->data[1], ' "\';');
								if(substr($file, -4) != ".css")
								{
									if(file_exists($this->dir."/".$file))
									{
										$code[] = $this->parsefile($this->dir."/".$file);
									}
										
									elseif(file_exists($this->dir."/_".$file))
									{
										$this->parsefiledefs($this->dir."/_".$file);
									}
								}
								else
									$code[] = $token->value;
								break;

						case "warn":
							$warning = true;
						case "debug":
							
							echo "[{$this->file}:{$token->line}] " . ($warning ? "WARNING: ":"") . ltrim($this->parsevalue($token->data[1])) . "\n";
								
							break;
							
						default: $code[] = $token->value;
					}
					
					break;
				
				case "property":
					
					$token->name = $this->parseinterpolation($token->data[0]);
					
					if($token->data[1])
					{
						if($token->parent->type == "property")
							$code[] = $token->parent->name . "-".$token->name . ": " 
									. $this->parsevalue($token->data[1]) . ";";
							
						else	
							$code[] = $token->name . ":" . $this->parsevalue($token->data[1]) . ";";
					}

					if($token->children)
						$code[] = $this->parseblock($token->children);
					
					break;
				
				case "selector":
					
					$si = $this->parseinterpolation($token->data[0]);
					$token->selectors = preg_split("/ *, */", $si);
					
					$prefix = "";
					$close = false;
					$p = $token;
					
					$sl = 0;
					$pf = array();
					$ps = array();
					
					while(($p = $p->parent) && $p->type == "selector")
					{
						if(!$ps) $psn = $p->selectors;
						else
						{
							$psn = array();
							foreach($p->selectors as $s)
							{			
								 foreach($ps as $pp)
								 {
								 		$psn[] = $s . " " . $pp;
								 }
							}
						}
						
						$close = true;
						
						$ps = $psn;
					}
					
					$sels = array();
					
					foreach($token->selectors as $s)
						{
							if(strpos($s, "&") !== false)
							{
								foreach($ps as $p)
									$sels[] = str_replace("&", $p, $s);
							}
							else
								foreach($ps as $p)
									$sels[] = $p . " " . $s;
						}
					
					if(!$sels) $sels = $token->selectors;
					
					if($close) $code[] = "}"; 
					$code[] = implode(", ", $sels);
					$code[] = " {";
					
					$code[] = $this->parseblock($token->children);
					
					if(!$close) $code[] = "}";
					
					break;
				
			}
			
		}
				
		return $code;
	}
	
	/**
	 * Format the CSS code 
	 * @param array $code Lines of CSS code
	 * @param int $level Current indentation level
	 * @return string Final CSS
	 */
	protected function build($code, $level = 0)
	{
		$o = "";
		if(is_array($code))
		{
			foreach($code as $c) $o .= $this->build($c, $level+1);
		}
		else $o .= str_repeat(" ", $level) . $code . "\n";
		
		return $o;
	}
	
	/**
	 * Parse expressions in values
	 * @param string $val
	 * @return string
	 */
	protected function parsevalue($val)
	{
		
		// parentheses 
		do
		{
			$c = 0;		
		$val = preg_replace("/(?<![a-z_])\(([^()]+)\)/ie", '$this->parsevalue(\'$1\')', $val, 1, $c);
		} while($c);
		
		// variables
		$val = preg_replace('/[$!]([a-z0-9_-]+)/ie', '("$0" == "!important") ? "$0" : $this->variables["$1"]', $val);
		
		do
		{
			$c = 0;
			$val = preg_replace('/(-?[0-9]+) *(px|em|%|) *([\/*]) *(-?[0-9]+)( *\2)?/ie', '($1 $3 $4) . "$2"', $val, 1, $c);
			
			if(!$c) $val = preg_replace('/(-?[0-9]+) *(px|em|%|) *([-+]) *(-?[0-9]+)( *\2)?/ie', '($1 $3 $4) . "$2"', $val, 1, $c);
				
		} while($c);
		
		return $val;
	}
	
	protected function parseinterpolation($val)
	{
		return preg_replace('/#{([^}]+)}/e', '$this->parsevalue(\'$1\')', $val);
		
	}
	
}
	
?>