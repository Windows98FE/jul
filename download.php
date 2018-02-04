<?php

	require "lib/function.php";

	$_GET['id']     = filter_int($_GET['id']);
	$_GET['info']   = filter_bool($_GET['info']);

	if (!$_GET['id']) {
		errorpage("No attachment specified.");
	}
	
	$attachment = $sql->fetchq("SELECT * FROM attachments WHERE id = {$_GET['id']}");
	
	if (!$attachment) {
		errorpage("Cannot download the attachment.<br>Either it doesn't exist or you're not allowed to download it.");
	}
	
	$post = $sql->fetchq("
		SELECT p.id pid, t.id tid, f.id fid, f.minpower
		FROM posts p
		LEFT JOIN threads t ON p.thread = t.id
		LEFT JOIN forums  f ON t.forum  = f.id
		WHERE p.id = {$attachment['post']}
	");
	
	if (
		   !$post // Post doesn't exist
		|| (!$ismod && !$post['tid']) // Post in invalid thread 
		|| (!$ismod && !$post['fid']) // Thread in invalid forum
		|| $loguser['powerlevel'] < $post['minpower'] // Can't view forum
		|| !file_exists(attachment_name($id)) // File missing
	) {
		errorpage("Cannot download the attachment.<br>Either it doesn't exist or you're not allowed to download it.");
	}
	
	// All OK!
	
	if ($_GET['info']) {
		echo "<pre>Attachment display:\n\n";
		print_r($attachment);
		die;
	}
	
	$sql->query("UPDATE attachments SET views = views + 1 WHERE id = {$_GET['id']}");
	
	// Clear out any previous state
	if (ob_get_level()) ob_end_clean();
	
	// Set the correct headers to make this file downloadable
	header("Pragma: public");
	//header("Expires: 0");
	//header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
	
	header("Cache-Control: public");
	header('Connection: Keep-Alive');
	if (!$attachment['is_image']) {
		// Display download box if it isn't an image
		header("Content-Disposition: attachment");
	}
	header("Content-Description: File Transfer");
	header("Content-Disposition: filename=\"{$attachment['filename']}\"");
	header("Content-Transfer-Encoding: binary");
	header("Content-Length: {$attachment['size']}");
	header("Content-type: {$attachment['mime']}");

	readfile(attachment_name($_GET['id']));

	die;