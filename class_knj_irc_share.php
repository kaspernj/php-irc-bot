<?
	//Written by knj <kaspernj@gmail.com>
	
	class knj_irc_share{
		function __construct(knj_irc $irc){
			$this->irc = $irc;
			$this->irc->connect("msg", array($this, "msg"));
		}
		
		function addshare($title, $folder){
			$this->shares[] = array(
				"folder" => $folder,
				"title" => $title
			);
		}
		
		//To get the a users dir.
		function getfn($user){
			$shareid = $this->users[$user][share];
			$fn = $this->shares[$shareid][folder];
			
			if ($this->users[$user]['dir']){
				foreach($this->users[$user]['dir'] AS $value){
					$fn .= "/" . $value;
				}
			}
			
			return $fn;
		}
		
		//To get the "fake" users dir. A dir which can be shown, without showing the real dir on the machine.
		function getfn_fake($user){
			$fn = "/share" . ($this->users[$user][share] + 1);
			
			if ($this->users[$user]['dir']){
				foreach($this->users[$user]['dir'] AS $value){
					$fn .= "/" . $value;
				}
			}
			
			return $fn;
		}
		
		function msg($channel, $user, $msg){
			if (preg_match("/^" . $this->irc->nick . ": listshares$/", $msg, $match)){
				if (!$this->shares){
					//no shares was found.
					$this->irc->msg($channel, "Sorry " . $user . ", I havent shared anything.");
				}else{
					$this->irc->msg($channel, "Listing shares.");
					
					//listing shares.
					foreach($this->shares AS $key => $share){
						$this->irc->msg($channel, ($key + 1) . ": " . $share[title]);
					}
					
					$this->irc->msg($channel, "End of list.");
				}
			}elseif(preg_match("/^" . $this->irc->nick . ": cd \.\.$/", $msg, $match)){
				if (count($this->users[$user][dir]) == 0 || !$this->users[$user][dir]){
					unset($this->users[$user]);
				}else{
					foreach($this->users[$user][dir] AS $key => $value){
						//nothing.
					}
					
					unset($this->users[$user][dir][$key]);
					$fn = $this->getfn_fake($user);
				}
				
				$this->irc->msg($channel, "Changed to: " . $fn);
			}elseif(preg_match("/^" . $this->irc->nick . ": cd ([\s\S]+)$/", $msg, $match)){
				if (!$this->users[$user]['dir'] && is_numeric($match[1])){
					$number = $match[1] - 1;
					
					$this->users[$user][share] = $number;
				}else{
					$fn = $this->getfn($user);
					$fn .= "/" . $match[1];
					
					if (file_exists($fn) && is_dir($fn)){
						$this->users[$user][dir][] = $match[1];
					}else{
						$this->irc->msg($channel, $user . ": That isnt a directory.");
					}
				}
				
				$this->irc->msg($channel, "Changed to: " . $this->getfn_fake($user));
			}elseif(preg_match("/^" . $this->irc->nick . ": dir$/", $msg, $match)){
				$fn = $this->getfn($user);
				$fake = $this->getfn_fake($user);
				
				$this->irc->msg($channel, "Listing " . $fake);
				
				$fp = opendir($fn);
				while(($file = readdir($fp)) !== false){
					if ($file != "." && $file != ".."){
						$this->irc->msg($channel, $file);
						usleep(50000);
					}
				}
				
				$this->irc->msg($channel, "End of list.");
			}
		}
	}
?>