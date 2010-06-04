<?php 

/**
 * Convert lines to hierarchical tree of tokens
 * @author yscik
 *
 */
class Sphasps_Tokeniser
{
	/**
	 * Build the token hierarchy tree
	 * 
	 * @param string $sass
	 * @return Token root token
	 */
	public function tokenize($sass)
	{
		$tokens = array();
		
		$ws = "  ";
		$wsl = strlen($ws);
		
		// Line counter
		$line_c = 0;
		
		$level = 1;
		$root = new Token;
		$root->type = "root";
		$root->parent = $root;
		
		$last = $root;
		
		$lines = explode("\n", $sass);
		
		foreach($lines as $line)
		{
			$line_c++;
			
			// Skip empty lines
			if(!strlen(trim($line))) continue;
			
			// Count and strip indents
			for($i = 1; strncmp($line, $ws, $wsl) == 0; $i++)
			{
				$line = substr($line, $wsl);
			}
			
			$t = new Token;
			$t->level = $i;
			$t->line = $line_c;
			
			// Place the token in the hierarchy tree
			if($i > $level)
			{
				#echo ">";
				$t->parent = $last;
				$level = $i;
			}
			else if($i == $level)
			{
				#echo "=";
				$t->parent = $last->parent;
			}
			else if($i < $level)
			{
				#echo "<";
				$t->parent = $last->parent;
				while(--$level >= $i)
				{
					$t->parent = $t->parent->parent;
				}
				$level++;
			}
			
			$t->parent->children[] = $t;
			
			$t->value = $line;
			
			$last = $t;
			
			$this->id($t);
			$tokens[] = $t;
			//var_dump($t);
			#echo " " . $t . "<br />";
		}
		
		return $root;
	}
	
	/**
	 * Identify token type and extract data
	 * 
	 * @param Token $token
	 */
	protected function id(&$token)
	{
		$v = $token->value;

		$m = array();
		
		// Indentation error
		if($v[0] == " ")
		{
			$token->type = "error-indent";
			return;
		}
		 
		// Determine token type
		switch(1)
		{
			// Variable assingment
			case preg_match('/^[$!]([a-z0-9_-]+) *[:=] *(.*)$/i', $v, $m): 
				
				$token->type = "variable"; 
				break;
				
			// SASS comment
			case preg_match('|^//(.*)$|i', $v, $m): 
				
				$token->type = "comment"; 
				break;
			
			// CSS comment
			case preg_match('|^/\*(.*)$|i', $v, $m): 
				
				$token->type = "csscomment"; 
				break;
				
			// Mixin definition
			case preg_match('/^=([a-z0-9_-]+)(?:\((.*)\))?/i', $v, $m): 
				
				$token->type = "mixindef"; 
				break;
			
			// Mixin usage
			case preg_match('/^(?<!\\\\)(?:\+|@include +)([a-z0-9_-]+)(?:\((.*)\))?/i', $v, $m): 
				
				$token->type = "mixin"; 
				break;
			
			// Command
			case preg_match('/^@([a-z0-9_-]+)(.*)$/i', $v, $m): 
				
				$token->type = "command"; 
				break;
			
			// Property
			case preg_match('/^((?:[a-z0-9_-]+|#{[^{]+})+) *:(.*)$/i', $v, $m) 
			  || preg_match('/^:((?:[a-z0-9_-]+|#{[^{]+})+ )(.*)$/i', $v, $m): 
			  
				$token->type = "property"; 
				break;
			
			// Selector
			case preg_match('/^\\\\?([a-z0-9_#.&:,+* -]+)$/i', $v, $m): 
				
				$token->type = "selector"; 
				break;
				
			// Unrecognized
			default:
				$token->type = "error-unknown"; 
				break; 
		}
		
		// Save matches
		array_shift($m);
		$token->data = $m;
	}
	
}


class Token
{
	public $level, $parent, $type, $value, $data, $children, $line;
	
	public function __toString()
	{
		//return "[{$this->level}] " . ($this->parent ? "<{$this->parent}>" : "") . " {$this->value}";
		return str_repeat("&nbsp;&nbsp;&nbsp;&nbsp;", $this->level) . " [".$this->type."] " . $this->value . " ({$this->parent->value})" . " [".count($this->children)."]";
	}
}


?>