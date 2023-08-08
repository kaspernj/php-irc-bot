<?
	dl("php-gtk2.dll");
	
	require_once "class_knj_irc.php";
	require_once "class_knj_irc_share.php";
	
	$irc = new knj_irc();
	$irc->connect_server("efnet.xs4all.nl", 6667);
	$irc->login("BotAnna");
	$irc->chan_join("#spammere");
	
	$irc_share = new knj_irc_share($irc);
	$irc_share->addshare("Linux", "D:/Downloads/Shared");
	$irc_share->addshare("Musik", "D:/Musik");
	
	Gtk::main();
?>