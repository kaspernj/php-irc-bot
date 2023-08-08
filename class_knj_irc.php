<?
	//Written by knj <kaspernj@gmail.com>
	
	class knj_irc{
		function connect_server($server, $port){
			if (!class_exists("gtk")){
				echo "PHP-GTK2 is not loaded. Attempting to autoload...\n";
				
				if (!dl("php-gtk2.dll")){
					echo "Failed to load PHP-GTK2. Could not connect.\n";
					return false;
				}
			}
			
			$this->fp = fsockopen($server, $port);
			
			if (!$this->fp){
				$this->errormsg = "Could not connect to the server.";
				return false;
			}else{
				$this->io_sockread = Gtk::io_add_watch($this->fp, Gtk::IO_IN, array($this, "readsocket"));
				return true;
			}
		}
		
		function disconnect(){
			fwrite($this->fp, "QUIT\n");
			fclose($this->fp);
			
			unset($this->fp);
			unset($this->io_sockread);
		}
		
		function connect($event, $function){
			$runable = false;
			
			if (
				$event == "msg" ||
				$event == "chan_userlist" ||
				$event == "nickchange" ||
				$event == "kick" ||
				$event == "kickme" || 
				$event == "ban" ||
				$event == "unban" ||
				$event == "ping" ||
				$event == "topic_change" ||
				$event == "join" ||
				$event == "part" ||
				$event == "privmsg" ||
				$event == "quit" ||
				$event == "dcc"
			){
				$runable = true;
			}
			
			if ($runable == false){
				echo "No connect-sequence by the name of: " . $event . "\n";
				exit;
			}
			
			$this->events[$event][] = $function;
		}
		
		//function for making events runable by making eval()-able code and running (eval()'ing) it.
		private function event_run($mode, $arg1 = null, $arg2 = null, $arg3 = null, $arg4 = null){
			if ($arg1 !== null){
				$args[1] = $arg1;
			}
			
			if ($arg2 !== null){
				$args[2] = $arg2;
			}
			
			if ($arg3 !== null){
				$args[3] = $arg3;
			}
			
			if ($arg4 !== null){
				$args[4] = $arg4;
			}
			
			if ($args){
				$first = true;
				foreach($args AS $key => $value){
					if ($first == true){
						$first = false;
					}else{
						$args_string .= ", ";
					}
					
					$args_string .= "\$arg" . $key;
				}
			}
			
			if ($this->events[$mode]){
				foreach($this->events[$mode] AS $event){
					if (is_array($event)){
						$eval = "\$event[0]->" . $event[1] . "(" . $args_string . ");";
						eval($eval);
					}else{
						$eval = $event . "(" . $args_string . ");";
						eval($eval);
					}
				}
				
				return true;
			}else{
				return false;
			}
		}
		
		//must be set to public, or PHP-GTK2 cannot run it...
		function readsocket(){
			if (!$this->fp){
				//socket must be closed - we are not connected, stop thread.
				return false;
			}
			
			$lines = explode("\n", fread($this->fp, 4096));
			
			foreach($lines AS $line){
				$line = str_replace("\r", "", $line);
				
				if ($line){
					if (preg_match("/353 " . $this->nick . " (=|@) (#[\S]+) :([\s\S]+)/", $line, $match)){
						//setting the channel-user-list.
						if (preg_match_all("/(@|\+|)([\S]+)/", $match[3], $match_channelusers)){
							foreach($match_channelusers[0] AS $key => $value){
								$this->channels[$match[2]][users][$match_channelusers[2][$key]] = array(
									"rank" => $match_channelusers[1][$key],
									"nick" => $match_channelusers[2][$key]
								);
							}
						}else{
							//an error has occured.
							echo "Couldnt match channelusers.\n";
							exit;
						}
						
						//Making event runable.
						$this->event_run("chan_userlist", $match[2], $this->channels["#" . $match[2]]);
					}elseif(preg_match("/^:(\S+)!\S+ PRIVMSG (#[\S]+) :([\s\S]+)$/", $line, $match)){
						//Making msg-event runable.
						//2 is channel, 1 is user, 3 is msg.
						
						$this->event_run("msg", $match[2], $match[1], $match[3]);
					}elseif(preg_match("/^:(\S+)!\S+ NICK :(\S+)$/", $line, $match)){
						//If the changed nick is present on any of the channels, the nick must be removed and reset.
						foreach($this->channels AS $channel => $usrs_array){
							foreach($usrs_array AS $nick => $usr_array){
								if ($nick == $match[1]){
									$this->channels[$channel][users][$match[2]] = $usr_array;
									$this->channels[$channel][users][$match[2]][nick] = $match[2];
									
									unset($this->channels[$channel][users][$nick]);
								}
							}
						}
						
						//Making event runable.
						$this->event_run("nickchange", $match[1], $match[2]);
					}elseif(preg_match("/^:(\S+)![\S]+ KICK #(\S+) (\S+) :([\s\S]+)$/", $line, $match)){
						if ($match[3] == $this->nick){
							//we have been kicked. making event runable.
							$this->event_run("kickme", $match[2], $match[1], $match[3], $match[4]);
						}
						
						//making event runable.
						$this->event_run("kick", $match[2], $match[1], $match[3], $match[4]);
					}elseif(preg_match("/^:(\S+)![\S]+ MODE (#\S+) (-|\+)b (\S+)$/", $line, $match)){
						//someone have been banned or unbanned.
						if ($match[3] == "+"){
							$this->event_run("ban", $match[2], $match[1], "no_nick", $match[4]);
						}elseif($match[3] == "-"){
							$this->event_run("unban", $match[2], $match[1], "no_nick", $match[4]);
						}
					}elseif(preg_match("/^PING :\S+$/", $line, $match)){
						//we got pinged.
						fwrite($this->fp, "PONG\n");
						$this->event_run("ping");
					}elseif(preg_match("/^:(\S+)!\S+ TOPIC (#\S+) :([\S\s]+)$/", $line, $match)){
						//someone have changed the topic of the channel.
						$oldtopic = $this->channels[$match[2]][topic];
						
						if (!$oldtopic){
							$oldtopic = false;
						}
						
						$this->event_run("topic_change", $match[2], $match[1], $oldtopic, $match[3]);
						$this->channels[$match[2]][topic] = $match[3];
					}elseif(preg_match("/^:\S+ 332 " . $this->nick . " (#\S+) :([\s\S]+)$/", $line, $match)){
						$this->channels[$match[1]][topic] = $match[2];
						echo "topic set to " . $match[2] . " on " . $match[1] . "\n";
					}elseif(preg_match("/^:\S+ 333 " . $this->nick . " (#\S+) (\S+)!\S+ [0-9]+$/", $line, $match)){
						$this->channels[$match[1]][owner] = $match[2];
						echo "owner set to " . $match[2] . " on " . $match[1] . "\n";
					}elseif(preg_match("/^:(\S+)!\S+ JOIN :(#\S+)$/", $line, $match)){
						//new user have joined.
						//2 is channel. 1 is the user, who have joined.
						
						$this->channels[$match[2]][users][$match[1]][nick] = $match[1];
						$this->event_run("join", $match[2], $match[1]);
					}elseif(preg_match("/^(\S+)!\S+ PART (#\S+)$/", $line, $match)){
						//a user have parted from a channel.
						//2 is the channel. 1 is the user, who have left.
						
						unset($this->channels[$match[2]][users][$match[1]]);
						$this->event_run("part", $match[2], $match[1]);
						
						print_r($this->channels);
					}elseif(preg_match("/^:(\S+)!\S+ PRIVMSG (\S+) :([\s\S]+)$/", $line, $match)){
						//we have got a private message (if it was to a channel, it would have been catched by another expression).
						//1 is the user. 3 is the message
						
						$this->event_run("privmsg", $match[1], $match[3]);
					}elseif(preg_match("/^:(\S+)!\S+ QUIT :([\s\S]*)$/", $line, $match)){
						//someone have quit irc.
						//1 is the nick. 2 is the quit-message.
						
						$this->event_run("quit", $match[1], $match[2]);
					}elseif(preg_match("/^:(\S+)!\S+ NOTICE " . $this->nick . " :DCC Send ([\S\s]+) \(([0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3})\)$/", $line, $match)){
						//someone is trying to send us a file.
						//1 is the nick. 2 is the file-name. 3 is the IP-address.
						
						$this->event_run("dcc", $match[1], $match[3], $match[2]);
					}else{
						echo "nu: " . $line . "\n";
					}
				}
			}
			
			return true;
		}
		
		function login($name){
			fwrite($this->fp, "NICK " . $name . "\n");
			fwrite($this->fp, "USER " . $name . " 0 * :Noname\n");
			
			$this->nick = $name;
			
			return true;
		}
		
		function chan_join($channels){
			if (!is_array($channels)){
				$channels = array($channels);
			}
			
			foreach($channels AS $value){
				$this->channels[$value][topic] = $value;
			}
			
			fwrite($this->fp, "JOIN " . implode(",", $channels) . "\n");
			
			return true;
		}
		
		function chan_leave($channel){
			if (!is_array($channel)){
				$channel = array($channel);
			}
			
			foreach($channel AS $value){
				fwrite($this->fp, "PART " . $value . "\n");
				unset($this->channel_usrs[$value]);
			}
			
			return true;
		}
		
		function msg($chan, $msg){
			fwrite($this->fp, "PRIVMSG " . $chan . " :" . $msg . "\n");
			return true;
		}
	}
?>