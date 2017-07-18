<?php
	// This file is already fixed. Just remove this comment and go on.
	require 'lib/function.php';
	pageheader();
	
	$id = filter_int($_GET['id']);
	
	if ($id) {
		admincheck();
		$edituser 	= true;
		$titleopt	= 1;
		$id_q		= "?id=$id";
		$userdata	= $sql->fetchq("SELECT u.*,r.gcoins FROM users u LEFT JOIN users_rpg r ON u.id = r.uid WHERE u.id = $id");
		if (!has_perm('sysadmin-actions') && check_perm('sysadmin-actions', $userdata['group'])) {
			errorpage("You cannot edit a root admin's profile.");
		}
	} else {
		
		if(!$loguser['id'])
			errorpage('You must be logged in to edit your profile.');
		if($banned)
			errorpage("Sorry, but banned users aren't allowed to edit their profile.");
		if(!has_perm('edit-own-profile'))
			errorpage("You are not allowed to edit your profile.");
		
		// Custom title requirements
		if		(has_perm('has-always-title')) 	$titleopt = 1;
		else if	(!has_perm('has-title')) 		$titleopt = 0;
		else $titleopt=($loguser['posts']>=500 || ($loguser['posts']>=250 && (ctime()-$loguser['regdate'])>=100*86400));
		
		$id 		= $loguser['id'];
		$id_q		= "";
		$userdata 	= $loguser;
		$edituser 	= false;
	}
	
	//if($_GET['lol'] || ($loguserid == 1420)) errorpage('<div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%;"><object width="100%" height="100%"><param name="movie" value="http://www.youtube.com/v/lSNeL0QYfqo&hl=en_US&fs=1&color1=0x2b405b&color2=0x6b8ab6&autoplay=1"></param><param name="allowFullScreen" value="true"></param><param name="allowscriptaccess" value="always"></param><embed src="http://www.youtube.com/v/lSNeL0QYfqo&hl=en_US&fs=1&color1=0x2b405b&color2=0x6b8ab6&autoplay=1" type="application/x-shockwave-flash" allowscriptaccess="always" allowfullscreen="true" width="100%" height="100%"></embed></object></div>');
	

	if (isset($_POST['submit'])) {
		check_token($_POST['auth']);
		
		// Reinforce "Force male / female" gender item effects
		$itemdb = getuseritems($id);
		foreach ($itemdb as $item){
			if 		($item['effect'] == 1) $_POST['sex'] = 1;	// Force female
			else if ($item['effect'] == 2) $_POST['sex'] = 0;	// Force male
		}

		// Reset the date settings in case they match with the default
		if ($_POST['presetdate']) {
			$eddateformat 	= filter_string($_POST['presetdate'], true);
		} else {
			$eddateformat 	= filter_string($_POST['dateformat'], true);
		}
		if ($_POST['presettime']) {
			$edtimeformat 	= filter_string($_POST['presettime'], true);
		} else {
			$edtimeformat 	= filter_string($_POST['timeformat'], true);
		}
		
		if (!$eddateformat || $eddateformat == $config['default-dateformat']) $eddateformat = '';
		if (!$edtimeformat || $edtimeformat == $config['default-timeformat']) $edtimeformat  = '';
		
		
		// \n -> <br> conversion
		$postheader = filter_string($_POST['postheader'], true);
		$signature 	= filter_string($_POST['signature'], true);
		$bio 		= filter_string($_POST['bio'], true);
		sbr(0,$postheader);
		sbr(0,$signature);
		sbr(0,$bio);
		
		// Make sure the thread layout does exist to prevent "funny" shit
		$tlayout = filter_int($_POST['layout']);
		$valid = $sql->resultq("SELECT id FROM tlayouts WHERE id = $tlayout");
		if (!$valid) $tlayout = 1;	// Regular (no numgfx)
			
		
		/*
			$oldtitle	= "";
			$title		= filter_string($_POST['title'], true);
		while ($oldtitle != $title) {
			$oldtitle = $title;
			$title=preg_replace("'<(b|i|u|s|small|br)>'si", '[\\1]', $title);
			$title=preg_replace("'</(b|i|u|s|small|font)>'si", '[/\\1]', $title);
			$title=preg_replace("'<img ([^>].*?)>'si", '[img \\1]', $title);
			$title=preg_replace("'<font ([^>].*?)>'si", '[font \\1]', $title);
		//   $title=preg_replace("'<[\/\!]*?[^<>]*?>'si", '&lt;\\1&gt;', $title); 
			$title=strip_tags($title);
		//    $title=preg_replace("'<[\/\!]*?[^<>]*?>'si", '&lt;\\1&gt;', $title); 
			$title=preg_replace("'\[font ([^>].*?)\]'si", '<font \\1>', $title);
			$title=preg_replace("'\[img ([^>].*?)\]'si", '<img \\1>', $title);
			$title=preg_replace("'\[(b|i|u|s|small|br)\]'si", '<\\1>', $title);
			$title=preg_replace("'\[/(b|i|u|s|small|font)\]'si", '</\\1>', $title);
			$title=preg_replace("'(face|style|class|size|id)=\"([^ ].*?)\"'si", '', $title);
			$title=preg_replace("'(face|style|class|size|id)=\'([^ ].*?)\''si", '', $title);
			$title=preg_replace("'(face|style|class|size|id)=([^ ].*?)'si", '', $title);
		}*/

		// Changing the password?
		$password 	= filter_string($_POST['pass1']);
		$passchk 	= filter_string($_POST['pass2']);
		if ($password && ($edituser || $password == $passchk)) {	// Make sure we enter the correct password
			$passwordenc = getpwhash($password, $id);
			if ($loguser['id'] == $id) {
				$verifyid = intval(substr($_COOKIE['logverify'], 0, 1));
				$verify = create_verification_hash($verifyid, $hash);
				setcookie('logverify',$verify,2147483647, "/", $_SERVER['SERVER_NAME'], false, true);
			}
		} else { // Sneaky!  But no.
			$passwordenc = $userdata['password'];
		}
		
		if (has_perm('change-namecolor')) {
			$namecolor = $_POST['colorspec'] ? $_POST['colorspec'] : $_POST['namecolor'];
		} else {
			$namecolor = $userdata['namecolor'];
		}
		
		
		$sql->beginTransaction();
		$querycheck = array();
		
		// Using extra schemes is locked behind a permission now
		$scheme = filter_int($_POST['scheme']);
		$secret = $sql->resultq("SELECT special FROM schemes WHERE id = $scheme");
		if ($secret && !has_perm('select-secret-themes')) {
			$scheme = 0; // Night theme
		}
		
		// Generally, anything that is allowed to contain HTML goes through xssfilters() here
		// Things that don't will be htmlspecialchars'd when they need to be displayed, so we don't bother 
		
		// Editprofile fields
		$mainval = array(
			// Login info
			'password'			=> $passwordenc,	
			// Appareance
			'title'				=> $titleopt ? xssfilters(filter_string($_POST['title'], true)) : $userdata['title'],
			'namecolor'			=> htmlspecialchars($namecolor, ENT_QUOTES),
			'useranks' 			=> isset($_POST['useranks']) ? filter_int($_POST['useranks']) : $userdata['useranks'],
			'picture' 			=> filter_string($_POST['picture'], true),
			'minipic' 			=> filter_string($_POST['minipic'], true),
			'moodurl' 			=> filter_string($_POST['moodurl'], true),
			'postheader' 		=> xssfilters($postheader),
			'signature' 		=> xssfilters($signature),
			// Personal information
			'sex' 				=> forcerange($_POST['sex'], 0, 2, 0),	// x >= 0 && x <= 2, default to 0 otherwise 
			'aka' 				=> filter_string($_POST['aka'], true),
			'realname' 			=> filter_string($_POST['realname'], true),
			'location' 			=> xssfilters(filter_string($_POST['location'], true)),
			'birthday'			=> fieldstotimestamp('birth', '_POST'),
			'bio' 				=> xssfilters($bio),
			// Online services
			'email' 			=> filter_string($_POST['email'], true),
			'privateemail' 		=> filter_int($_POST['privateemail']),
			'icq' 				=> filter_int($_POST['icq']),
			'aim' 				=> filter_string($_POST['aim'], true),
			'imood' 			=> filter_string($_POST['imood'], true),
			'homepageurl' 		=> filter_string($_POST['homepageurl'], true),
			'homepagename'	 	=> filter_string($_POST['homepagename'], true),
			// Options
			'dateformat' 		=> $eddateformat,
			'timeformat' 		=> $edtimeformat,
			'timezone' 			=> filter_int($_POST['timezone']),
			'postsperpage' 		=> filter_int($_POST['postsperpage']),
			'threadsperpage'	=> filter_int($_POST['threadsperpage']),
			'viewsig'			=> forcerange($_POST['viewsig'], 0, 2, 0),
			'pagestyle' 		=> forcerange($_POST['pagestyle'], 0, 1, 0),
			'pollstyle' 		=> forcerange($_POST['pollstyle'], 0, 1, 0),
			'layout' 			=> $tlayout,
			'signsep' 			=> forcerange($_POST['signsep'], 0, 3, 0),
			'scheme' 			=> $scheme,
			'hideactivity' 		=> filter_int($_POST['hideactivity']),
			// What user?
			'id'				=> $id,
		);
		
		
		if ($edituser) {
			/*
			if ($sex == -378) {
			$sex = $sexn;
			}
*/
			if ($id == 1 && $loguser['id'] != 1) {
				xk_ircsend("1|". xk(7) ."Someone (*cough* {$loguser['id']} *cough*) is trying to be funny...");
			}
		
			 //$sql->query("INSERT logs SET useraction ='Edit User ".$user[nick]."(".$user[id]."'");
			 
			 // Do the double name check here
			$users = $sql->query('SELECT name FROM users');
			
			$username  = substr(xssfilters(filter_string($_POST['name'], true)),0,25);
			$username2 = str_replace(' ','',$username);
			$username2 = preg_replace("'&nbsp;?'si",'',$username2);
			
			$samename = NULL;
			while ($user = $sql->fetch($users)) {
				$user['name'] = str_replace(' ','',$user['name']);
				if (strcasecmp($user['name'], $username2) == 0) $samename = $user['name'];
			}
			 
			// No "Imma become a root admin" bullshit
			$group = filter_int($_POST['group']);
			if (check_perm('sysadmin-actions', $group) && !has_perm('sysadmin-actions')) {
				$group = GROUP_NORMAL;
			}				
			 
			
			// Extra edituser fields
			$adminval = array(
				
				'name'				=> ($samename || !$username) ? $username : $userdata['name'],
				'group'		 		=> $group,
				'regdate'			=> fieldstotimestamp('reg', '_POST'),
				'posts'				=> filter_int($_POST['posts']),
				'ban_expire'		=> ($group == GROUP_BANNED && filter_int($_POST['ban_hours']) > 0) ? (ctime() + filter_int($_POST['ban_hours']) * 3600) : 0,
				
			);
	
			$adminset = $sql->setplaceholders("`group`","name","regdate","posts","ban_expire").",";
			
			$gcoins = filter_int($_POST['gcoins']);
			$sql->query("UPDATE users_rpg SET gcoins = $gcoins WHERE uid = $id", false, $querycheck);
		} else {
			$adminval = array();
			$adminset = "";
		}
		
		// You are not supposed to look at this.
		// No, really. These are the same placeholder from the arrays above.
		$userset = $sql->setplaceholders("password","namecolor","picture","minipic","signature","bio","email","icq","title","useranks","aim","sex",
		"homepageurl","homepagename","privateemail","timezone","dateformat","timeformat","postsperpage","aka","realname","location","postheader","scheme",
		"threadsperpage","birthday","viewsig","layout","moodurl","imood","signsep","pagestyle","pollstyle","hideactivity");
		
		$sql->queryp("UPDATE users SET {$adminset}{$userset} WHERE id = :id", array_merge($adminval, $mainval), $querycheck);
		
		if ($sql->checkTransaction($querycheck)) {
			if (!$edituser)	errorpage("Thank you, {$loguser['name']}, for editing your profile.","profile.php?id=$id",'view your profile',0);
			else errorpage("Thank you, {$loguser['name']}, for editing this user.","profile.php?id=$id","view {$userdata['name']}'s profile",0);
		} else {
			errorpage("Could not edit the profile due to an error.");
		}
		
	}
	else {
		
		sbr(1,$userdata['postheader']);
		sbr(1,$userdata['signature']);
		sbr(1,$userdata['bio']);
		
		/*
			A ""slightly updated"" version of the table system from boardc
			(You can now set a maxlength for input fields)
			
			----
			
			Format of the tables:
			
			table_format(<name of section>, <array>);
			
			<array> itself is a "list" with multiple arrays. each has this format:
			<title of field> => [<type>, <input name>, <description>, <extra>, <extra2>]
			
			<title of field> - Text on the left
			
			<type> ID of input field
			Types: 
				0 - Input text. Uses <extra> and <extra2> for SIZE and MAXLENGTH respectively.
				1 - Wide Textarea
				2 - Radio buttons. Uses <extra> for the choices, ie: (No|Yes). The value IDs start from 0.
				3 - Listbox. Uses <extra> to get choices. See above.
				4 - Custom. It prints a variable with the same name of <input name>. It's up to you to create this variable.
				
			<input name> Name of the input field.
			<description> Small text shown below the title of the field. This is only shown when editing your own profile.
			<extra> & <extra2> What these do depends on <type>.
				
			table_format automatically appends array elements to the existing group
		*/

		table_format("Login information", array(
			"User name" 	=> [4, "name", "", 25, 25], // static
			"Password"		=> [4, "password", "You can change your password by entering a new one here."], // password field
		));
		
		if ($edituser) {
			// Set type from static to input, as an admin should be able to do that.
			$fields["Login information"]["User name"][0] = 0;
			
			// ... and also gets the extra "Administrative bells and whistles"
			table_format("Administrative bells and whistles", array(
				"Group"		 				=> [4, "group", ""], // Custom listbox with negative values.
				"Ban duration"				=> [4, "ban_hours", ""],
				"Number of posts"			=> [0, "posts", "", 6, 10],
				"Registration time"			=> [4, "regdate", ""],
				"User permissions"			=> [4, "permlink", ""],
			));
		}
		
		if ($titleopt) {
			table_format("Appareance", array(
				"Custom title" => [0, "title", "This title will be shown below your rank.", 65, 255],
			));
		}
		if (has_perm('change-namecolor')) {
			table_format("Appareance", array(
				"Name color" 	=> [4, "namecolor", "Your username will be shown using this color (leave this blank to return to the default color). This is an hexadecimal number.<br>You can use the <a href='hex.php' target='_blank'>Color Chart</a> to select a color to enter here."],
			));
		}
		table_format("Appareance", array(
			"User rank"		=> [4, "useranks", "You can hide your rank, or choose from different sets."],
			"User picture" 	=> [0, "picture", "The full URL of the image showing up below your username in posts. Leave it blank if you don't want to use a picture. The limits are 200x200 pixels, and about 100KB; anything over this will be removed.", 65, 100],
			"Mood avatar" 	=> [0, "moodurl", "The URL of a mood avatar set. '\$' in the URL will be replaced with the mood, e.g. <b>http://your.page/here/\$.png</b>!", 65, 100],
			"Minipic" 		=> [0, "minipic", "The full URL of a small picture showing up next to your username on some pages. Leave it blank if you don't want to use a picture. The picture is resized to 16x16.", 65, 100],
			"Post header" 	=> [1, "postheader", "This will get added before the start of each post you make. This can be used to give a default font color and face to your posts (by putting a &lt;font&gt; tag). This should preferably be kept small, and not contain too much text or images."],
			"Signature" 	=> [1, "signature", "This will get added at the end of each post you make, below an horizontal line. This should preferably be kept to a small enough size."],
		));		
		
		table_format("Personal information", array(
			"Sex" 		=> [2, "sex", "Male or female. (or N/A if you don't want to tell it).", "Male|Female|N/A"],
			"Also known as" => [0, "aka", "If you go by an alternate alias (or are constantly subjected to name changes), enter it here.  It will be displayed in your profile if it doesn't match your current username.", 25, 25],
			"Real name" => [0, "realname", "Your real name (you can leave this blank).", 40],
			"Location" 	=> [0, "location", "Where you live (city, country, etc.).", 40],
			"Birthday"	=> [4, "birthday", "Your date of birth."],
			"Bio"		=> [1, "bio", " Some information about yourself, showing up in your profile."],
		));

		table_format("Online services", array(
			"Email address" 	=> [0, "email", "This is only shown in your profile; you don't have to enter it if you don't want to.", 65, 60],
			"Email privacy" 	=> [2, "privateemail", "You can select a few privacy options for the email field.", "Public|Hide to guests|Staff only"],
			"AIM screen name" 	=> [0, "aim", "Your AIM screen name, if you have one.", 30, 30],
			"ICQ number" 		=> [0, "icq", "Your ICQ number, if you have one.", 10, 10],
			"imood" 			=> [0, "imood", "If you have a imood account, you can enter the account name (email) for it here.", 65, 100],
			"Homepage URL" 		=> [0, "homepageurl", "Your homepage URL (must start with the \"http://\") if you have one.", 65, 80],
			"Homepage Name" 	=> [0, "homepagename", "Your homepage name, if you have a homepage.", 65, 100],
		));
		
		table_format("Options", array(
			"Custom date format" 			=> [4, "dateformat", "Edit the date format here to affect how dates are displayed. Leave it blank to return to the default format ({$config['default-dateformat']})<br>See the <a href='http://php.net/manual/en/function.date.php'>date() function in the PHP manual</a> for more information."],
			"Custom time format" 			=> [4, "timeformat", "Edit the time format here to affect how time is displayed. Leave it blank to return to the default format (<b>{$config['default-timeformat']}</b>)."],
			"Timezone offset"	 			=> [0, "timezone", "How many hours you're offset from the time on the board (".date($loguser['dateformat'],ctime()).").", 5, 5],
			"Posts per page"				=> [0, "postsperpage", "The maximum number of posts you want to be shown in a page in threads.", 3, 3],
			"Threads per page"	 			=> [0, "threadsperpage", "The maximum number of threads you want to be shown in a page in forums.", 3, 3],
			"Signatures and post headers"	=> [2, "viewsig", "You can disable them here, which can make thread pages smaller and load faster.", "Disabled|Enabled|Auto-updating"],
			"Forum page list style"			=> [2, "pagestyle", "Inline (Title - Pages ...) or Seperate Line (shows more pages)", "Inline|Seperate line"],
			"Poll vote system"				=> [2, "pollstyle", "Normal (based on users) or Influence (based on levels)", "Normal|Influence"],
			"Thread layout"					=> [4, "layout", "You can choose from a few thread layouts here."],
			"Signature separator"			=> [4, "signsep", "You can choose from a few signature separators here."],
			"Color scheme / layout"	 		=> [4, "scheme", "You can select from a few color schemes here."],
			"Hide activity"			 		=> [2, "hideactivity", "You can choose to hide your online status.", "Show|Hide"],
		));
		if ($edituser){
			table_format("Options", array(
				"Green coins" 	=> [0, "gcoins", "", 10, 10],
			));
		}		
		
		/*
			Custom values (used when first value in array is set to 4)
		*/
		
		// Static text for the username (shown when editing your own profile)
		$name = $userdata['name'];
		
		// Password field + confirmation (unless you're editing another user)
		$password = "<input type='password' size=24 name='pass1'>";
		if (!$edituser)	$password .= " Retype: <input type='password' size=24 name='pass2'>";
	
	
		$birthday = datetofields($userdata['birthday'], 'birth');
		
		// The namecolor field is special
		// Usually it contains an hexadecimal number, but it can take extra text values for special effects
		// When a special effect is defined, we make sure to blank the main textbox
		// otherwise we select the "Defined" option
		if ($userdata['namecolor'] && !ctype_xdigit($userdata['namecolor'])) {	// Special effect?
			$userdata['namecolor'] = "";
			$sel_color[$userdata['namecolor']] = 'checked=1';
		} else {
			$sel_color[0] = 'checked=1';
		}

		$colorspec="
		<input type=radio class='radio' name=colorspec value='' ".filter_string($sel_color[0]).">Defined 
		<input type=radio class='radio' name=colorspec value='random' ".filter_string($sel_color[1]).">Random 
		<input type=radio class='radio' name=colorspec value='time' ".filter_string($sel_color[2]).">Time-dependent 
		<input type=radio class='radio' name=colorspec value='rnbow' ".filter_string($sel_color[3]).">Rainbow";
	
		$namecolor = "<input type='text' name=namecolor VALUE=\"{$userdata['namecolor']}\" SIZE=6 MAXLENGTH=6> $colorspec";
		
		if (!$userdata['dateformat']) {
			$userdata['dateformat'] = $config['default-dateformat'];
		}
		$dateformat = 	"<span class='nobr'><input type='text' name='dateformat' size=16 maxlength=32 value='{$userdata['dateformat']}'> or preset: ".
						"<select name='presetdate'><option value=''></option>\n\r";
		$dateformatlist = array("m-d-y", "d-m-y", "y-m-d", "Y-m-d", "m/d/Y", "d.m.y", "M j Y", "D jS M Y");
		foreach ($dateformatlist as $fmt) {
			$dateformat .= "<option value='$fmt'>$fmt (".date($fmt, ctime()).")</option>";
		}
		$dateformat .= "</select></span>";
		
		if (!$userdata['timeformat']) {
			$userdata['timeformat'] = $config['default-timeformat'];
		}		
		$timeformat = 	"<span class='nobr'><input type='text' name='timeformat' size=16 maxlength=32 value='{$userdata['timeformat']}'> or preset: ".
						"<select name='presettime'><option value=''></option>\n\r";
		$timeformatlist = array("h:i A", "h:i:s A", "H:i", "H:i:s");
		foreach ($timeformatlist as $fmt) {
			$timeformat .= "<option value='$fmt'>$fmt (".date($fmt, ctime()).")</option>";
		}
		$timeformat .= "</select></span>";
		
		

		
		if ($edituser) {
			// Group selection
			$group = "";
			$check1[$userdata['group']] = 'selected';
			$sysadmin = has_perm('sysadmin-actions');
			$checkcache = $sql->fetchq("SELECT id, ".perm_fields()." FROM perm_groups", PDO::FETCH_UNIQUE, MYSQL::FETCH_ALL);
			foreach ($grouplist as $groupid => $groupval) {
				if (check_perm('sysadmin-actions', $groupid, $checkcache[$groupid]) && !$sysadmin) {
					continue; // Hide groups that would give normal admins sysadmin actions
				}
				$group .= "<option value={$groupid} ".filter_string($check1[$groupid]).">{$groupval['name']}</option>";
			}
			$group = "<select name=group>{$group}</select>";
			
			// Registration time
			$regdate = datetofields($userdata['regdate'], 'reg', DTF_DATE | DTF_TIME);
			
			// Hours left before the user is unbanned
			$ban_val 	= 
				($userdata['group'] == GROUP_BANNED && $userdata['ban_expire']) ? 
				 ceil(($userdata['ban_expire']-ctime())/3600) : 
				 0;
			
			$ban_select = array(
				$ban_val => timeunits2($ban_val*3600),
				0		 => "*Permanent",
				1		 => "1 hour",
				3		 => "3 hours",
				6		 => "6 hours",
				24		 => "1 day",
				72		 => "3 days",
				168		 => "1 week",
				336		 => "2 weeks",
				774		 => "1 month",
				1488	 => "2 months"
			);
			ksort($ban_select);
			
			$sel_b[$ban_val] = "selected";
			
			$ban_hours = "<select name='ban_hours'>";
			foreach($ban_select as $i => $x){
				$ban_hours .= "<option value=$i ".filter_string($sel_b[$i]).">$x</option>";
			}
			$ban_hours .= "</select> (has effect only for '".$grouplist[GROUP_BANNED]['name']."' users)";
			
			// Link to edit user permissions
			$permlink = "You can edit them <a href='admin-editperms.php?mode=1&id=$id'>here</a>. Make sure to save the settings on this page if you don't want to lose them.";
		}

		
		// listbox with <name> <used>
		if (!$edituser && !has_perm('select-secret-themes'))
			$scheme   = queryselectbox('scheme',   "SELECT s.id as id, s.name, COUNT(u.scheme) as used FROM schemes s LEFT JOIN users u ON (u.scheme = s.id) WHERE s.ord > 0 AND (!s.special OR s.id = {$userdata['scheme']}) GROUP BY s.id ORDER BY s.ord");
		else
			$scheme = doschemelist(true, $userdata['scheme'], 'scheme');
		$layout   = queryselectbox('layout',   'SELECT tl.id as id, tl.name, COUNT(u.layout) as used FROM tlayouts tl LEFT JOIN users u ON (u.layout = tl.id) GROUP BY tl.id ORDER BY tl.ord');
		$useranks = queryselectbox('useranks', 'SELECT rs.id as id, rs.name, COUNT(u.useranks) as used FROM ranksets rs LEFT JOIN users u ON (u.useranks = rs.id) GROUP BY rs.id ORDER BY rs.id');
		
		$used = $sql->getresultsbykey('SELECT signsep, count(*) as cnt FROM users GROUP BY signsep');
		$signsep = "";
		for($i = 0; isset($sepn[$i]); ++$i){
				$sel = ($i==$userdata['signsep'] ? ' selected' : '');
				$signsep .= "<option value={$i}{$sel}>{$sepn[$i]} (".filter_int($used[$i]).")";
		}
		$signsep="<select name='signsep'>$signsep</select>";
		
		/*
			Table field generator
		*/
		$t = "";
		foreach($fields as $i => $field){
			$t .= "<tr><td class='tdbgh center'>$i</td><td class='tdbgh center'>&nbsp;</td></tr>";
			foreach($field as $j => $data){
				$desc = $edituser ? "" : "<br><small>$data[2]</small>";
				if (!$data[0]) { // text box
					if (!isset($data[3])) $data[3] = 65;
					if (!isset($data[4])) $data[4] = 100;
					$input = "<input type='text' name='$data[1]' size={$data[3]} maxlength={$data[4]} value=\"".htmlspecialchars($userdata[$data[1]])."\">";
				}
				else if ($data[0] == 1) // large
					$input = "<textarea name='$data[1]' rows=8 cols=65 style='resize:vertical;' wrap='virtual'>".htmlspecialchars($userdata[$data[1]])."</textarea>";
				else if ($data[0] == 2){ // radio
					$ch[$userdata[$data[1]]] = "checked"; //example $sex[$user['sex']]
					$choices = explode("|", $data[3]);
					$input = "";
					foreach($choices as $i => $x)
						$input .= "<input name='$data[1]' type='radio' value=$i ".filter_string($ch[$i]).">&nbsp;$x&nbsp;&nbsp;&nbsp; ";
					unset($ch);
				}
				else if ($data[0] == 3){ // listbox
					$ch[$userdata[$data[1]]] = "selected";
					$choices = explode("|", $data[3]);
					$input = "";
					foreach($choices as $i => $x)
						$input .= "<option value=$i ".filter_string($ch[$i]).">$x</option>";
					$input = "<select name='$data[1]'>$input</select>";
					unset($ch);
				}
				else
					$input = ${$data[1]};
					
				$t .= "<tr><td class='tdbg1 center'><b>$j:</b>$desc</td><td class='tdbg2'>$input</td></tr>";
			}
		}
	}
	
	
	// Hack around autocomplete, fake inputs (don't use these in the file) 
	// Web browsers think they're smarter than the web designer, so they ignore demands to not use autocomplete.
	// This is STUPID AS FUCK when you're working on another user, and not YOURSELF.
	$finput = $edituser ? '<input style="display:none" type="text" name="__f__usernm__"><input style="display:none" type="password" name="__f__passwd__">' : "";

	?>
	
	<FORM ACTION="editprofile.php<?=$id_q?>" NAME=REPLIER METHOD=POST autocomplete=off>
	<table class='table'>
		<?=$finput?>
		<?=$t?>
		<tr>
			<td class='tdbgh center'>&nbsp;</td>
			<td class='tdbgh center'>&nbsp;</td>
		</tr>
		<tr>
			<td class='tdbg1 center'>&nbsp;</td>
			<td class='tdbg2'>
		<input type='hidden' name=auth VALUE="<?=generate_token()?>">
		<input type='submit' class=submit name=submit VALUE="Edit <?=($edituser ? "user" : "profile")?>">
		</td>
	</table>
	</FORM>
	<?php
	
	pagefooter();
	
	function forcerange(&$var, $low, $high, $default) {
		$var = (int) $var;
		return ($var < $low || $var > $high) ? $default: $var;
	}
	
	function table_format($name, $array){
		global $fields;
		
		if (isset($fields[$name])){ // Already exists: merge arrays
			$fields[$name] = array_merge($fields[$name], $array);
		} else { // It doesn't: Create a new one.
			$fields[$name] = $array;
		}
	}
	
	// When it comes to copy / pasted code...
	function queryselectbox($val, $query) {
		global $sql, $userdata;
		$txt = "";
		$q = $sql->query($query);
		while ($x = $sql->fetch($q, PDO::FETCH_ASSOC)) {
			$sel = ($x['id'] == $userdata[$val] ? ' selected' : '');
			$txt .=" <option value={$x['id']}{$sel}>{$x['name']} ({$x['used']})</option>\n\r";			
		}
		return "<select name='$val'>$txt</select>";
	}
?>