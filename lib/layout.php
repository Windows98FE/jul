<?php

function doforumlist($id, $name = '', $shownone = '', $skip = 0){
	global $loguser, $sql;
	
	if (!$name) {
		$forumlinks = "
		<table>
			<tr>
				<td class='font'>Forum jump: </td>
				<td>
					<form>
						<select onChange='parent.location=\"forum.php?id=\"+this.options[this.selectedIndex].value' style='position:relative;top:8px'>
		";
		$showhidden = (int) has_perm('display-hidden-forums');
		$showcustom = "!f.custom";
	} else {
		$forumlinks = "";
		$showhidden = 1;
		$showcustom = 1;
	}
	
	$forums = $sql->query("
		SELECT 	f.id, f.title, f.catid, f.hidden, f.custom, c.name catname, c.showalways
		FROM forums f
		LEFT JOIN categories c ON f.catid = c.id
		
		WHERE ($showcustom "./* by default, do not show custom forums*/"
		  AND (!f.hidden OR $showhidden) "./* by default, do not show hidden forums */"
		  AND (f.custom OR !ISNULL(c.id)) "./* do not show invalid forums (note that custom forums normally have no category id; so we make sure not to filter those)*/"
		  ".($skip ? "AND f.id != $skip" : "").") "./* if needed, skip over a specified forum*/"
		  OR f.id = $id
		ORDER BY f.custom, c.corder, f.catid, f.forder, f.id
	");
	
	$fperms = get_all_forum_perm($id, $loguser['id'], $loguser['group'], $name, $skip);
	
	$prev      = NULL;	// In case the current forum is in an invalid category, the non-existing category name won't be printed
	$customsep = NULL;
	while ($forum = $sql->fetch($forums)) {
		$canView = has_forum_perm('read', $fperms[$forum['id']]['set0']);
		// New category
		if ($forum['custom']) {
			if (!$customsep) {
				// The custom forums start here. No further categories will be present.
				$forumlinks .= "</optgroup><optgroup label=\"".($showcustom === 1 ? "Custom forums" : "Current forum")."\">";
				$customsep = true;
			}
		} else if (!$forum['custom'] && $prev != $forum['catid']) {
			if ($canView || $forum['showalways']) {
				$forumlinks .= "</optgroup><optgroup label=\"{$forum['catname']}\">";
				$prev = $forum['catid'];
			}
			//$prev = $forum['catid'];
		}
		
		if ($canView) {
			if ($forum['hidden']) {
				$forum['title'] = "({$forum['title']})";
			}
			$forumlinks .= "<option value={$forum['id']}".($forum['id'] == $id ? ' selected' : '').">".htmlspecialchars($forum['title'])."</option>";
		}
	}
	
	// Multi-use forum list
	if ($name) {
		if ($shownone) $forumlinks = "<option value=0>$shownone</option>$forumlinks";
		return "<select name='$name'>$forumlinks</select>";
	}
	$forumlinks .= "	</optgroup>
					</select>
				</form>
			</td>
		</tr>
	</table>";
	
	return $forumlinks;
}

// Note: -1 becomes NULL when inserted to a db and vice versa
function doschemeList($all = false, $sel = 0, $name = 'scheme'){
	global $sql;
	
	$schemes = $sql->query("SELECT * FROM schemes ".($all ? "ORDER BY special," : "WHERE special = 0 ORDER BY")." ord, id");
	
	if ($sel === NULL) $sel = '-1';
	$scheme[$sel] = "selected";
	
	$input 	= "";
	$prev	= 1; // Previous special value
	while($x = $sql->fetch($schemes)){
		// If we only fetch normal schemes don't bother separating between them.
		if ($all && $prev != $x['special']){
			$prev 	= $x['special'];
			$input .= "</optgroup><optgroup label='".($prev ? "Special" : "Normal")." schemes'>";
		}
		$input .= "<option value='{$x['id']}' ".filter_string($scheme[$x['id']]).">{$x['name']}</option>";
	}
	return "<select name='$name'>".($all ? "<option value='-1' ".filter_string($scheme['-1']).">None</option>" : "")."$input</optgroup></select>";
}

// When it comes to this kind of code being repeated across files...
function dothreadiconlist($iconid = NULL, $customicon = '') {
	
	// Check if we have selected one of the default thread icons
	$posticons = file('posticons.dat');
	
	if (isset($iconid) && $iconid != -1) {
		$selected = trim($posticons[$iconid]);
	} else {
		$selected = trim($customicon);
	}
	$customicon = $selected;
	$posticonlist = "";
	
	for ($i = 0; isset($posticons[$i]);) {
		
		$posticons[$i] = trim($posticons[$i]);
		// Does the icon match?
		if($selected == $posticons[$i]){
			$checked    = 'checked=1';
			$customicon	= '';					// If so, blank out the custom icon
		} else {
			$checked    = '';
		}

		$posticonlist .= "<input type=radio class=radio name=iconid value=$i $checked>&nbsp;<img src='{$posticons[$i]}' HEIGHT=15 WIDTH=15>&nbsp; &nbsp;";

		++$i;
		if($i % 10 == 0) $posticonlist .= '<br>';
	}

	// Blank or set to None?
	if (!$selected || $iconid == -1) $checked = 'checked=1';
	
	$posticonlist .= "<br>".
	                 "<input type=radio class='radio' name=iconid value=-1 $checked>&nbsp; None &nbsp; &nbsp;".
	                 "Custom: <input type='text' name=custposticon VALUE=\"".htmlspecialchars($customicon)."\" SIZE=40 MAXLENGTH=100>";
	
	return $posticonlist;
}

// moodlist(return -> false)
function avatarlist($user, $sel = 0) {
	$moods = getavatars($user);

	$c[$sel] = "selected";
	
	$txt = "";
	foreach ($moods as $file => $data) {
		$txt .= "<option value='{$file}' ".filter_string($c[$file]).">".htmlspecialchars($data['title'])."</option>\n";
	}
	
	return "Avatar: <select name='moodid'><option value='0'>-Normal avatar-</option>\n{$txt}</select>".include_js('avatars.js');	
}

// Forum attachments; have low size limit (1MB); do not count for user storage limit (only show on newreply.php)
function attachmentslist($attachments) {
	//@TODO: add this
	// attachments => [<id>, <name>, <size>, <downloads>, <image_data> = NULL]
	// where if image_data is set it contains [<x>, <y>]
	
	// Mostly cloning off the quik-attach for this
	$out_img = $out_file = "";
	foreach ($attachments as $x) {
		$filename = htmlspecialchars($x['name']);
		$size     = sizeunits($x['size']);
		if ($x['image_data'] !== NULL) {
			$out_img .= 
			"<a href='download.php?id={$x['id']}'>".
				"<img src='attachments/t/{$x['id']}.png' title=\"{$filename} - {$size}, downloads: {$x['downloads']}\">".
			"</a> ";
		} else {
			$out_file .= "<a href='download.php?id={$x['id']}'>{$filename}</a> ({$size}) - downloads: {$x['downloads']}<br>";	
		}
	}
	return "<fieldset><legend>Attachments</legend>{$out_img}<br><br>{$out_file}</fieldset>";
}

function dopagelist($url, $elements, $div){
	global $loguser;
	$pagelinks = '';
	$page = filter_int($_GET['page']);
	$maxfromstart = (($loguser['pagestyle']) ?  9 :  4);
	$maxfromend   = (($loguser['pagestyle']) ? 20 : 10);
	$totalpages	= ceil($elements / $div);
	for($k = 0; $k < $totalpages; ++$k) {
		if ($totalpages >= ($maxfromstart+$maxfromend+1) && $k > $maxfromstart && $k < ($totalpages - $maxfromend)) {
		  $k = ($totalpages - $maxfromend);
			$pagelinks .= " ...";
		}
		$w = ($_GET['page'] == $k ? 'x' : 'a');
		$pagelinks.=" <$w href='$url&page=$k'>".($k+1)."</$w>";
	}
	return $pagelinks;
}

/* WIP
$jspcount = 0;
function jspageexpand($start, $end) {
	global $jspcount;

	if (!$jspcount) {
		echo '
			<script type="text/javascript">
				function pageexpand(uid,st,en)
				{
					var elem = document.getElementById(uid);
					var res = "";
				}
			</script>
		';
	}

	$entityid = "expand" . ++$jspcount;

	$js = "#todo";
	return $js;
}
*/

function errorpage($text, $redirurl = '', $redir = '', $redirtimer = 4) {
	if (!defined('HEADER_PRINTED')) pageheader();

	print "<table class='table'><tr><td class='tdbg1 center'>$text";
	if ($redir)
		print '<br>'.redirect($redirurl, $redir, $redirtimer);
	print "</table>";

	pagefooter();
}

function redirect($url, $msg, $delay){
	if($delay < 1) $delay = 1;
	return "You will now be redirected to <a href='$url'>$msg</a>...<META HTTP-EQUIV=REFRESH CONTENT=$delay;URL=$url>";
}

/*
function sizelimitjs(){
	// where the fuck is this used?!
	return "";
  return '
	<script>
	  function sizelimit(n,x,y){
		rx=n.width/x;
		ry=n.height/y;
		if(rx>1 && ry>1){
		if(rx>=ry) n.width=x;
		else n.height=y;
		}else if(rx>1) n.width=x;
		else if(ry>1) n.height=y;
	  }
	</script>
  '; 
}*/

function adminlinkbar($sel = NULL) {
	
	if (!has_perm('admin-actions')) return;
	
	if (!$sel) {
		// If no selection is passed, default to the current script
		global $scriptname;
		$sel = $scriptname;
	}

	$links	= array(
		array(
			'admin.php'	=> "Admin Control Panel",
		),
		array(
//			'admin-todo.php'        => "To-do list",
			'announcement.php'      => "Go to Announcements",
			'admin-editfilters.php' => "Edit Filters",
			'admin-editforums.php'  => "Edit Forum List",
			'admin-editmods.php'    => "Edit Forum Moderators",
			'admin-editperms.php'   => "Edit Permissions",
		),
		array(
			//'admin-threads.php'     => "ThreadFix",
			//'admin-threads2.php'    => "ThreadFix 2",
			//'admin-forums.php'      => "ForumFix",
			'admin-counters.php'    => "Fix Forum Counters",
			'admin-backup.php'      => "Board Backups",
			
		),
		array(
			'admin-ipsearch.php'    => "IP Search",
			'admin-ipbans.php'      => "IP Bans",
			'admin-pendingusers.php'=> "Pending Users",
		//	'admin-slammer.php'     => "EZ Ban Button",
			'admin-deluser.php'     => "Delete User",
		)
	);

	$r = "<div style='padding:0px;margins:0px;'>
			<table class='table'>
				<tr>
					<td class='tdbgh center b' style='border-bottom: 0'>
						Admin Functions
					</td>
				</tr>
			</table>";

	$total = count($links) - 1;
    foreach ($links as $rownum => $linkrow) {
		$c	= count($linkrow);
		$w	= floor(1 / $c * 100);

		$r .= "<table class='table'><tr>";
		$nb = ($rownum != $total) ? ";border-bottom: 0" : "";

		foreach($linkrow as $link => $name) {
			$cell = '1';
			if ($link == $sel) $cell = 'c';
			$r .= "<td class='tdbg{$cell} center nobr' style='padding: 1px 10px{$nb}' width=\"{$w}%\"><a href=\"{$link}\">{$name}</a></td>";
		}

		$r .= "</tr></table>";
	}
	$r .= "</div><br>";

	return $r;
}

function include_js($fn, $as_tag = false) {
	// HANDY JAVASCRIPT INCLUSION FUNCTION
	if ($as_tag) {
		// include as a <script src="..."></script> tag
		return "<script src='$fn' type='text/javascript'></script>";
	} else {
		return '<script type="text/javascript">'.file_get_contents("js/$fn").'</script>';
	}
}

// JS "Help windows"
function quick_help($message, $title = "Help Window") {
	static $i = 0;
	if ($message) {
		$style = $i ? "" : "<style type='text/css'>.qhclose{float: right; padding: 0px 7px}</style>" . include_js("qhelp.js");
		++$i;
		
		return $style .
		"<table class='table' id='qhmain{$i}'>".
			"<tr><td class='tdbgh center b'>".
				"{$title}".
				"<a href='#' id='qhclose{$i}' class='qhclose' style='display:none' onmousedown='closeHelp({$i})'>X</a>".
			"</td></tr>".
			"<tr><td class='tdbg1 center'>{$message}</td></tr>".
		"</table><script>setCloseButton({$i})</script>";
	} else {
		return "";
	}
	
}

// @TODO: Implement a toolbar replacement without copying the bad old code
function replytoolbar() { return; }

function adbox() {

	// no longer needed. RIP
	return "";

	global $loguser, $bgcolor, $linkcolor;

/*
	$tagline	= array();
	$tagline[]	= "Viewing this ad requires<br>ZSNES 1.42 or older!";
	$tagline[]	= "Celebrating 5 years of<br>ripping off SMAS!";
	$tagline[]	= "Now with 100% more<br>buggy custom sprites!";
	$tagline[]	= "Try using AddMusic to give your hack<br>that 1999 homepage feel!";
	$tagline[]	= "Pipe cutoff? In my SMW hack?<br>It's more likely than you think!";
	$tagline[]	= "Just keep giving us your money!";
	$tagline[]	= "Now with 97% more floating munchers!";
	$tagline[]	= "Tip: If you can beat your level without<br>savestates, it's too easy!";
	$tagline[]	= "Tip: Leave exits to level 0 for<br>easy access to that fun bonus game!";
	$tagline[]	= "Now with 100% more Touhou fads!<br>It's like Jul, but three years behind!";
	$tagline[]	= "Isn't as cool as this<br>witty subtitle!";
	$tagline[]	= "Finally beta!";
	$tagline[]	= "If this is blocking other text<br>try disabling AdBlock next time!";
	$tagline[]	= "bsnes sucks!";
	$tagline[]	= "Now in raspberry, papaya,<br>and roast beef flavors!";
	$tagline[]	= "We &lt;3 terrible Japanese hacks!";
	$tagline[]	= "573 crappy joke hacks and counting!";
	$tagline[]	= "Don't forget your RATS tag!";
	$tagline[]	= "Now with exclusive support for<br>127&frac12;Mbit SuperUltraFastHiDereROM!";
	$tagline[]	= "More SMW sequels than you can<br>shake a dead horse at!";
	$tagline[]	= "xkas v0.06 or bust!";
	$tagline[]	= "SMWC is calling for your blood!";
	$tagline[]	= "You can run,<br>but you can't hide!";
	$tagline[]	= "Now with 157% more CSS3!";
	$tagline[]	= "Stickers and cake don't mix!";
	$tagline[]	= "Better than a 4-star crap cake<br>with garlic topping!";
	$tagline[]	= "We need some IRC COPS!";

	if (isset($_GET['lolol'])) {
		$taglinec	= $_GET['lolol'] % count($tagline);
		$taglinec	= $tagline[$taglinec];
	}
	else
		$taglinec	= pick_any($tagline);
*/

	return "
<center>
<!-- Beginning of Project Wonderful ad code: -->
<!-- Ad box ID: 48901 -->
<script type=\"text/javascript\">
<!--
var pw_d=document;
pw_d.projectwonderful_adbox_id = \"48901\";
pw_d.projectwonderful_adbox_type = \"5\";
pw_d.projectwonderful_foreground_color = \"#$linkcolor\";
pw_d.projectwonderful_background_color = \"#$bgcolor\";
//-->
</script>
<script type=\"text/javascript\" src=\"http://www.projectwonderful.com/ad_display.js\"></script>
<noscript><map name=\"admap48901\" id=\"admap48901\"><area href=\"http://www.projectwonderful.com/out_nojs.php?r=0&amp;c=0&amp;id=48901&amp;type=5\" shape=\"rect\" coords=\"0,0,728,90\" title=\"\" alt=\"\" target=\"_blank\" /></map>
<table cellpadding=\"0\" border=\"0\" cellspacing=\"0\" width=\"728\" bgcolor=\"#$bgcolor\"><tr><td><img src=\"http://www.projectwonderful.com/nojs.php?id=48901&amp;type=5\" width=\"728\" height=\"90\" usemap=\"#admap48901\" border=\"0\" alt=\"\" /></td></tr><tr><td bgcolor=\"\" colspan=\"1\"><center><a style=\"font-size:10px;color:#$linkcolor;text-decoration:none;line-height:1.2;font-weight:bold;font-family:Tahoma, verdana,arial,helvetica,sans-serif;text-transform: none;letter-spacing:normal;text-shadow:none;white-space:normal;word-spacing:normal;\" href=\"http://www.projectwonderful.com/advertisehere.php?id=48901&amp;type=5\" target=\"_blank\">Ads by Project Wonderful! Your ad could be right here, right now.</a></center></td></tr></table>
</noscript>
<!-- End of Project Wonderful ad code. -->
</center>";
}

class msg_holder {
	private static $message = "";
	// A cookie message can be set here
	public static function get_cookie() {return filter_string($_COOKIE['msg']);}
	public static function set_cookie($msg) {setcookie('msg', $msg);}
	public static function del_cookie() {setcookie('msg', NULL);}
	// but we can also just use it to keep a message for the current page
	public static function set_message($msg) {self::$message = xssfilters(filter_string($msg, true));}
	public static function get_message() {return quick_help(self::$message, "Message");}
}

class infobar {
	private static $msglist = []; // message, color
	// Add a new infobar message
	public static function add($message, $color) {
		self::$msglist[] = [$message, $color];
	}
	public static function showAll() {
		$out = "";
		foreach (self::$msglist as $x) {
			$out .= "<div class='center b' style='color: {$x[1]}; padding: 3px; border: 5px dotted {$x[1]}; background: #000;'>{$x[0]}</div>\n";
		}
		return $out;
	}

}