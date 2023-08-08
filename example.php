<?
	dl("php-gtk2.dll");
	require_once "class_knj_irc.php";
	
	//making the object.
	$irc = new knj_irc();
	
	//connection to a server.
	$irc->connect_server("efnet.xs4all.nl", 6667);
	
	//setting nick-name on server.
	$irc->login("BotAnna");
	
	//joining a channel.
	$irc->chan_join("#spammere");
	
	//sending a message on a channel.
	//$irc->msg("#spammere", "test");
	
	//CONNECTING TO AN EVENT
	/*
		When you want to capture an even, like when someone sents a message on a channel, you would want to use 
		the connect-method on the knj_irc-object. This methods takes two arguments. A string identifying which 
		event you want to capture, and a string or a array second, which would identify wich function or object-method 
		should be called, when the event takes place.
		
		If you want to capture a message with a function do something like:
		$irc->connect("msg", "event_msg");
		
		You should then create the function "event_msg" with 3 arguments: channel, user and message.
		
		If you want to capture a message with a method on a object do something like:
		$irc->connect("msg", array($object, "event_msg"));
		
		When a message is received, knj_irc will call "event_msg"-method on $object with the same 3 arguments: 
		channel, user and message.
	*/
	
	//this is how, you get knj_irc to tell you, when it has received a user-list (when joining a new channel).
	$irc->connect("chan_userlist", "event_userlist");
	function event_userlist($chan, $userlist){
		echo "Got userlist from a channel: " . $chan . "\n";
		//print_r($userlist);
	}
	
	//this is how, you get knj_irc to tell you, that someone have changed their nick.
	$irc->connect("nickchange", "event_nickchange");
	function event_nickchange($oldnick, $newnick){
		echo "Nickchange\n\n";
		
		global $irc;
		$irc->msg("#spammere", "So " . $newnick . " - you think it is funny to change nick?");
	}
	
	//this is how, you get knj_irc to tell you, when someone have wrote something.
	$irc->connect("msg", "event_msg");
	function event_msg($chan, $user, $msg){
		global $irc;
		
		echo "New msg.\n";
		echo "Channel: " . $chan . "\n";
		echo "User: " . $user . "\n";
		echo "Msg: " . $msg . "\n\n";
		
		if (substr($msg, 0, (strlen($irc->nick) + 1)) == ($irc->nick . ":")){
			$irc->msg("#" . $chan, "Will you please stop talking to me, " . $user . "!");
			echo "wrote back.\n\n";
		}
	}
	
	//this is how, you get knj_irc to tell you, when you have been kicked.
	$irc->connect("kickme", "event_kickme");
	function event_kickme($chan, $by_user, $my_nick, $reason){
		echo "i have been kicked.\n";
		echo "by: " . $by_user . "\n";
		echo "myself: " . $my_nick . "\n";
		echo "reason: " . $reason . "\n\n";
	}
	
	//this is how, you get knj_irc to tell you, when someone have been kicked.
	$irc->connect("kick", "event_kicksomeone");
	function event_kicksomeone($chan, $by_user, $someone, $reason){
		echo "someone have been kicked.\n";
		echo "by: " . $by_user . "\n";
		echo "have been kicked: " . $someone . "\n";
		echo "reason: " . $reason . "\n\n";
	}
	
	//this is how, you get knj_irc to tell you, when someone have been banned/unbanned (nick-support not yet implented).
	$irc->connect("ban", "event_ban");
	function event_ban($chan, $by_user, $nick, $hostmask){
		echo "someone have been banned.\n";
		echo "by: " . $by_user . "\n";
		echo "have been banned: " . $nick . ":" . $hostmask . "\n\n";
	}
	
	$irc->connect("unban", "event_unban");
	function event_unban($chan, $by_user, $nick, $hostmask){
		echo "someone have been unbanned.\n";
		echo "by: " . $by_user . "\n";
		echo "have been unbanned: " . $nick . ":" . $hostmask . "\n\n";
	}
	
	//this is how, you get knj_irc to tell you, when we have been pinged by the server.
	$irc->connect("ping", "event_ping");
	function event_ping(){
		echo "we have been pinged.\n\n";
	}
	
	//this is how, you get knj_irc to tell you, when the topic has been changed.
	$irc->connect("topic_change", "event_topicchange");
	function event_topicchange($channel, $by_user, $fromtopic, $newtopic){
		global $irc;
		
		echo "the topic has been changed.\n";
		echo "by: " . $by_user . "\n";
		echo "old topic: " . $fromtopic . "\n";
		echo "new topic: " . $newtopic . "\n\n";
		
		$irc->msg("#" . $channel, "why did you change the topic, " . $by_user . "?");
	}
	
	//this is how, you get knj_irc to tell you, when a new user have joined the channel.
	$irc->connect("join", "event_join");
	function event_join($channel, $user){
		global $irc;
		
		if ($user != $irc->nick){
			echo "a new user have joined #" . $channel . "\n";
			echo "nick: " . $user . "\n\n";
			
			$irc->msg("#" . $channel, "welcome to #" . $channel . " " . $user);
		}
	}
	
	//this is how, you get knj_irc to tell you, when someone have left a channel.
	$irc->connect("part", "event_part");
	function event_part($channel, $user){
		echo "a user have left the channel: #" . $channel . "\n";
		echo "nick: " . $user . "\n\n";
	}
	
	//this is how, you get knj_irc to tell you, when someone have sent a private message to you.
	$irc->connect("privmsg", "event_privmsg");
	function event_privmsg($from_user, $message){
		global $irc;
		
		echo "we got a private message from " . $from_user . "\n";
		echo "msg: " . $message . "\n\n";
		
		if ($from_user == "kaspernj"){
			if (preg_match("/^msg (\S+) ([\S\s]+)$/", $message, $match)){
				$irc->msg($match[1], $match[2]);
				$irc->msg($from_user, "Message was sent.");
			}else{
				$irc->msg($from_user, "Sorry - I cant understand that.");
			}
		}else{
			//$irc->msg($from_user, "shut up please - i dont want to talk to you.");
			$irc->msg("kaspernj", $from_user . ": " . $message);
		}
	}
	
	//this is how, you get knj_irc to tell you, when someone have quit irc.
	$irc->connect("quit", "event_quit");
	function event_quit($user, $message){
		echo $user . " have quit irc with this message: " . $message . "\n\n";
	}
	
	//this is how, you get knj_irc to tell you, when someone have dcc'ed you.
	$irc->connect("dcc", "event_dcc");
	function event_dcc($user, $ip, $filename){
		echo $user . " is trying to DCC a file.\n";
		echo "IP: " . $ip . "\n";
		echo "Filename: " . $filename . "\n\n";
	}
	
	//leaving a channel.
	//$irc->chan_leave("#spammers");
	
	//disconnecting.
	//$irc->disconnect();
	
	Gtk::main();
?>