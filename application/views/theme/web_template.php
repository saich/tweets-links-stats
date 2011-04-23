<?php defined('SYSPATH') OR die('No direct access allowed.'); ?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html>
<head>

<title><?php if(!empty($title)) echo $title, " :: "; echo "Your tweet-links Analyzr" ?></title>

<?php echo "\t", html::meta("Content-Type", "text/html; charset=utf-8");?>

<?php
if(!empty($metas) && is_array($metas))
{
		echo "\t", html::meta($metas), "\n";
}
?>
<link rel="shortcut icon" href="favicon.ico" type="image/x-icon"/>

<?php
if(!empty($styles) && is_array($styles))
{
	foreach($styles as $style)
		echo "\t", is_array($style) ? html::stylesheet("css/".$style[0], $style[1]) : html::stylesheet("css/".$style);
}
?>

</head>
<body>
<?if(!empty($content)) echo $content;?>
</body>
<script type="text/javascript" src="/scripts/jquery-1.5.2.min.js"></script>
<script type="text/javascript" src="/scripts/complete.js"></script>
</html>
