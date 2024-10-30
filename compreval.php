<?php
/*
Plugin Name: ComPreVal
Plugin URI: http://dev.d10e.net/wordpress/
Description: Adds serverside comment preview and validation to WordPress
Version: 1.2
Author: Mark Wubben & Ben de Groot
Author URI: http://d10e.net/
*/

/*
	Original code by Mark, editing and updates by Ben.
	Published under the GNU General Public License:
	http://www.opensource.org/licenses/gpl-license.php
*/

// load possible translations
load_plugin_textdomain('compreval');

// requires SafeHtml
$comment_preview_validate = true;

// validating comment if posted (to prevent bypassing of validation)
if( $comment_preview_validate &&  preg_match("/wp-comments-post\.php/", $_SERVER['REQUEST_URI']) ) {

	if( !class_exists("SafeHtmlChecker") ) {
		include( ABSPATH . 'wp-content/plugins/safehtml.php' );
	}

	$comment = trim($_POST['comment']);
	$author = trim(strip_tags($_POST['author']));
	$email = trim(strip_tags($_POST['email']));

	if( $comment == "" || preg_match("/(<p>\s*\n*<\/p>|<br \/>)/", $comment) ) {
		die( __('Error: please type a comment.','compreval'));
	}

	if( get_settings('require_name_email') ) {
		if( false == preg_match('/^[^@\s]+@([-a-z0-9]+\.)+[a-z]{2,}$/i', $email) || $author == "" ) {
			die( __('Please fill in the name, email and comment fields.','compreval'));
		}
	}

	$comment = wpautop($comment, 0);
	$comment = balanceTags($comment, 1);
	$comment = format_to_post($comment);
	$comment = apply_filters('comment_text', $comment);
	$comment = stripslashes($comment);
	$checker = new SafeHtmlChecker;
	$checker->check('<all>'.$comment.'</all>');
	if( !$checker->isOK() ) {
		die( __('You have tried to bypass the preview and submit an invalid comment. Needless to say, you have failed.','compreval'));
	}

}

// remove WP validator
remove_filter('comment_text', 'wp_filter_kses');
remove_filter('pre_comment_content', 'wp_filter_kses');
// you can comment the following line if you want the (slightly problematic) automatic linking of URIs:
remove_filter('comment_text', 'make_clickable');

// actual code
function bnCommentPreviewValidate() {

	if( isset($_POST["preview_submit"]) ) {
		bn_comment_preview_show();
	} else {
		bn_comment_preview_new();
	}

}

function bn_comment_preview_show() {

	global $require_name_email, $comment_preview_validate, $user_ID, $user_identity;

	if( $comment_preview_validate && !class_exists("SafeHtmlChecker") ) {
		die( __('Unable to validate comment, SafeHtmlChecker is not loaded.','compreval'));
	} else {
		$bValidate = true;
	}

	$author = trim(htmlspecialchars($_POST["preview_author"]));
	$email = trim(htmlspecialchars($_POST["preview_email"]));
	$url = trim(htmlspecialchars($_POST["preview_url"]));
		/* check url for http, in case base url is in use */
	if ( $_POST["preview_url"] !== "" ) {
		$urlstart = substr($url,0,7);
		if ("http://" !== $urlstart) {
			$url = "http://$url";
		}
	}
	$comment = trim($_POST["preview_comment"]);
	$id = intval($_POST['preview_comment_post_ID']);

	$redirect = trim($_POST["preview_redirect_to"]);

	$comment = wpautop($comment, 0);
	$comment = balanceTags($comment, 1);
	$comment = format_to_post($comment);
	$comment = apply_filters('comment_text', $comment);
	$comment = stripslashes($comment);
	$comment = trim($comment);
	if( preg_match("/(<p>\s*\n*<\/p>|<br \/>)/", $comment) ) {
		$comment = "";
	}

	if($bValidate) {
		$checker = new SafeHtmlChecker;
		$checker->check('<all>'.$comment.'</all>');
		if( $checker->isOK() ) {
			$bIsValid = true;
		} else {
			$bIsValid = false;
		}
	} else {
		$bIsValid = true;
	}

	if( get_settings('require_name_email') ) {
		if( preg_match( '/^[^@\s]+@([-a-z0-9]+\.)+[a-z]{2,}$/i' , $email) != false && $author != "" ) {
			$details = true;
		} else {
			$details = false;
		}
	} else {
		$details = true;
	}

	/* Outputted XHTML */
?>
<h3 id="preview"><?php _e('Your comment preview','compreval'); ?></h3>
<?php
	if( $bIsValid && $details && $comment != "comment" && $comment != "" ) {
		if( $url != "" ) {
			echo "<p><a href=\"$url\">$author</a> ";
		} else {
			echo "<p>$author ";
		}
		_e('says','compreval');
		echo ":</p>\n<div id=\"previewedcomment\">\n";
		echo $comment;
		echo "\n</div>\n<form action=\"".get_settings("siteurl")."/wp-comments-post.php\" method=\"post\">\n<div>";
		echo "<input type=\"hidden\" name=\"comment_post_ID\" value=\"$id\" />";
		echo "<input type=\"hidden\" name=\"redirect_to\" value=\"$redirect\" />";
		echo "<input type=\"hidden\" name=\"author\" value=\"$author\" />";
		echo "<input type=\"hidden\" name=\"email\" value=\"$email\" />";
		echo "<input type=\"hidden\" name=\"url\" value=\"$url\" />";
		echo "<input type=\"hidden\" name=\"comment\" value=\"".htmlspecialchars($comment)."\" />";
		echo "\n<p><input type=\"submit\" name=\"submit\" id=\"submitComment\" value=\"";
		_e('Submit','compreval');
		echo "\" /></p>";
		echo "</div>\n</form>\n";
	} elseif(!$bIsValid) {
		_e('<p>Unfortunately your comment is not well-formed according to the XML rules or contains illegal tags and/or attributes. Here is a list of the errors found while validating your comment:</p>','compreval');
		echo "<ul>\n";
		foreach ($checker->getErrors() as $error) {
			echo '<li>'.$error.'</li>'."\n";
		}
		echo "</ul>\n";
	} else {
		_e('<p>Please fill in the name, email and comment fields.</p>','compreval');
	}

	echo "<h3 id=\"editcomment\">";
	_e('Edit your comment','compreval');
	echo "</h3>\n";
	echo "<form action=\"".$_SERVER['REQUEST_URI']."#preview\" method=\"post\">\n";
	echo "<div>\n";
	echo "<p><label for=\"comment\">";
	_e('Your Comment','compreval');
	echo "</label><br />\n";
	echo "<textarea name=\"preview_comment\" id=\"comment\" cols=\"50\" rows=\"10\">".htmlspecialchars($comment)."</textarea></p>\n";

	if ( $user_ID ) {
	?>
	<p><?php _e('Logged in as'); ?> <a href="<?php echo get_option('siteurl'); ?>/wp-admin/profile.php"><?php
		echo $user_identity; ?></a>. <a href="<?php echo get_option('siteurl'); ?>/wp-login.php?action=logout" title="Log out of this account"><?php _e('Logout'); ?> &#187;</a></p><?php
		echo "\n<input type=\"hidden\" name=\"preview_author\" id=\"author\" value=\"$author\" />\n";
		echo "<input type=\"hidden\" name=\"preview_comment_post_ID\" value=\"$id\" />\n";
		echo "<input type=\"hidden\" name=\"preview_redirect_to\" value=\"$redirect\" /></p>\n";
		echo "<input type=\"hidden\" name=\"preview_email\" id=\"email\" value=\"$email\" />\n";
		echo "<input type=\"hidden\" name=\"preview_url\" id=\"url\" value=\"$url\" />\n";

	} else {

		echo "\n<p><input type=\"text\" name=\"preview_author\" id=\"author\" value=\"$author\" />\n";
		echo "<label for=\"author\">";
		_e('Name','compreval');
		echo "</label>\n";
		echo "<input type=\"hidden\" name=\"preview_comment_post_ID\" value=\"$id\" />\n";
		echo "<input type=\"hidden\" name=\"preview_redirect_to\" value=\"$redirect\" /></p>\n";
		echo "<p><input type=\"text\" name=\"preview_email\" id=\"email\" value=\"$email\" />\n";
		echo "<label for=\"email\">";
		_e('Email','compreval');
		echo "</label></p>\n";
		echo "<p><input type=\"text\" name=\"preview_url\" id=\"url\" value=\"$url\" />\n";
		echo "<label for=\"url\">";
		_e('Website','compreval');
		echo "</label></p>\n";
	}

	echo "<p><input name=\"preview_submit\" type=\"submit\" id=\"submitComment\" value=\"";
	_e('Preview','compreval');
	echo "\" /></p>\n";
	echo "</div>\n";
	echo "</form>\n";

}

function bn_comment_preview_new() {

	global $post, $user_ID, $user_identity, $user_email, $user_url;

	if ( $user_ID ) {
		$comment_author = $user_identity;
		$comment_author_email = $user_email;
		$comment_author_url = $user_url;
	} else {
		$comment_author = isset($_COOKIE['comment_author_'.COOKIEHASH]) ? trim(stripslashes($_COOKIE['comment_author_'.COOKIEHASH])) : '';
		$comment_author_email = isset($_COOKIE['comment_author_email_'.COOKIEHASH]) ? trim(stripslashes($_COOKIE['comment_author_email_'.COOKIEHASH])) : '';
		$comment_author_url = isset($_COOKIE['comment_author_url_'.COOKIEHASH]) ? trim(stripslashes($_COOKIE['comment_author_url_'.COOKIEHASH])) : '';
	}

	echo "<div id=\"postcomment\">\n<h3 id=\"respond\">";
	_e('Leave a Reply','compreval');
	echo "</h3>\n";
	_e('<p>Common XHTML tags allowed, which will be validated. Spam and trolling will not be tolerated.</p>','compreval');
	echo "\n<form action=\"".$_SERVER['REQUEST_URI']."#preview\" method=\"post\">\n";
	echo "<div>\n";
	echo "<p><label for=\"comment\">";
	_e('Your Comment','compreval');
	echo "</label><br />\n";
	echo "<textarea name=\"preview_comment\" id=\"comment\" cols=\"50\" rows=\"10\"></textarea></p>\n";

	if ( $user_ID ) {
	?>
	<p><?php _e('Logged in as'); ?> <a href="<?php echo get_option('siteurl'); ?>/wp-admin/profile.php"><?php
		echo $user_identity; ?></a>. <a href="<?php echo get_option('siteurl'); ?>/wp-login.php?action=logout" title="Log out of this account"><?php _e('Logout'); ?> &#187;</a></p><?php
		echo "\n<input type=\"hidden\" name=\"preview_author\" id=\"author\" value=\"$comment_author\" />\n";
		echo "<input type=\"hidden\" name=\"preview_comment_post_ID\" value=\"".$post->ID."\" />\n";
		echo "<input type=\"hidden\" name=\"preview_redirect_to\" value=\"".htmlspecialchars($_SERVER["REQUEST_URI"])."#comments\" /></p>\n";
		echo "<input type=\"hidden\" name=\"preview_email\" id=\"email\" value=\"$comment_author_email\" />\n";
		echo "<input type=\"hidden\" name=\"preview_url\" id=\"url\" value=\"$comment_author_url\" />\n";

	} else {

		echo "\n<p><input type=\"text\" name=\"preview_author\" id=\"author\" value=\"$comment_author\" />\n";
		echo "<label for=\"author\">";
		_e('Name','compreval');
		echo "</label>\n";
		echo "<input type=\"hidden\" name=\"preview_comment_post_ID\" value=\"".$post->ID."\" />\n";
		echo "<input type=\"hidden\" name=\"preview_redirect_to\" value=\"".htmlspecialchars($_SERVER["REQUEST_URI"])."#comments\" /></p>\n";
		echo "<p><input type=\"text\" name=\"preview_email\" id=\"email\" value=\"$comment_author_email\" />\n";
		echo "<label for=\"email\">";
		_e('Email (will not be published)','compreval');
		echo "</label></p>\n";
		echo "<p><input type=\"text\" name=\"preview_url\" id=\"url\" value=\"$comment_author_url\" />\n";
		echo "<label for=\"url\">";
		_e('Website','compreval');
		echo "</label></p>\n";
	}

	echo "<p><input name=\"preview_submit\" type=\"submit\" id=\"submitComment\" value=\"";
	_e('Preview','compreval');
	echo "\" /></p>\n";
	echo "</div>\n";
	echo "</form>\n</div>";

}
?>