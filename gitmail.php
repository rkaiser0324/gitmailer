<?php
// gitmail.php <path-to-git> <path-to-repo>
// E.g.,  php gitmail.php 'H:/Programs/Git/bin/git.exe' 'H:\\path\\to\\reponame\\.git'

require "Git.php";
require "class.phpmailer-lite.php";

if ($argc != 3)
    die("Wrong usage.\n");

$git = new Git($argv[2], $argv[1]);

// Find all the branches, and then select the one with the latest commit date
$commit = null;
$branches = $git->getBranches();
foreach (array_keys($branches) as $b)
{
    $head_commit = $git->getCommit($b, true);
    if (empty($commit) || $commit->date < $head_commit->date)
        $commit = $git->getCommit($b, true); 
}

$html = '';

$commit->modified = array();
$commit->added = array();
$commit->deleted = array();

$matches = preg_split('/^diff --git /ms', $commit->diff);
foreach ($matches as $el)
{
    $arr = array();
    if (!empty($el))
    {
	$type = 'modified';
	if (preg_match('/^a\/(.*) /', $el, $tokens))
		$arr['filename'] = $tokens[1];
	if (preg_match('/^index ([^\n]*)\n([^\n]*)\n([^\n]*)\n(.*)/ms', $el, $tokens))
	{
		$arr['diff'] = $tokens[4];
		if (preg_match('/^0000000/', $tokens[1]))
			$commit->added[] = $arr;
		elseif (preg_match('/\.\.0000000/', $tokens[1]))
			$commit->deleted[] = $arr;
		else 
			$commit->modified[] = $arr;
	}
    }
}

$font = 'font-family:verdana,arial,helvetica,sans-serif;font-size:10pt;';

for ($i = 0; $i < count($commit->modified); $i++)
{
	$file = $commit->modified[$i];
	$html .= '<a name="file-' . $i . '"></a>' .
			'<h4 style="' . $font . 'background:#369;color:#fff;margin:0">Modified: ' . $file['filename'] . '</h4>' .
			'<div style="border:1px solid #ccc;margin:0 0 10px 0;background-color:#eee;">' .
			'<pre style="color:black;overflow:auto;font-family:monospace">';

	$lines = explode("\n", $file['diff']);

	foreach ($lines as $el)
	{
		$style = 'padding:0 10px;';
		if (preg_match('/^(---|\+\+\+|@@) (.*)$/', $el)) {
			$style .= 'background:#fff';
		} elseif (preg_match('/^-(.*)$/', $el)) {
			$style .= 'background:#fdd;text-decoration:line-through';
		} elseif (preg_match("/^\+(.*)$/", $el)) {
			$style .= 'background:#dfd';
		}
		$html .= '<div style="' . $style . '">' . htmlspecialchars($el) . "</div\n>";
	}
	$html .= '</pre></div><br/>';
}

ob_start();
?>
<html>
    <head><title><?php echo $commit->title ?></title></head>
<body>
    
	<table width="100%" cellpadding="4" cellspacing="0" border="0">
		<tr>
			<td style="font-weight:bold;background: #369;color: #fff;<?php echo $font ?>">Committer</td>
			<td style="background: #369;color: #fff;<?php echo $font ?>"><?php echo $commit->author ?></td>
		</tr>
		<tr>
			<td style="font-weight:bold;background: #369;color: #fff;<?php echo $font ?>">Authored Date</td>
			<td style="background: #369;color: #fff;<?php echo $font ?>"><?php 
			echo strftime('%A, %B %d %Y %I:%M %p', strtotime($commit->date));
			?></td>
		</tr>
		<tr>
			<td style="font-weight:bold;background: #369;color: #fff;<?php echo $font ?>">Commit</td>
			<td style="background: #369;color: #fff;<?php echo $font ?>"><?php echo substr($commit->commit, 0, 7) ?>... (<?php echo $commit->branch ?>)</td>
		</tr>	
	</table>

	<h3 style="<?php echo $font ?>font-weight: bold;">Log Message</h3>
	<pre style="<?php echo $font ?>background: #ffc; border: 1px #fa0 solid; padding: 6px"><?php echo htmlspecialchars($commit->title) ?></pre>

	<?php if (count($commit->added) > 0) { ?>
	<h3 style="<?php echo $font ?>font-weight: bold;">Added Paths</h3>
	<ul>
		<?php for ($i = 0; $i < count($commit->added); $i++) { ?>
		<li style="<?php echo $font ?>"><?php echo $commit->added[$i]['filename'] ?></li>
		<?php } ?>
	</ul>
	<?php } ?>

	<?php if (count($commit->deleted) > 0) { ?>
	<h3 style="<?php echo $font ?>font-weight: bold;">Removed Paths</h3>
	<ul>
		<?php for ($i = 0; $i < count($commit->deleted); $i++) { ?>
		<li style="<?php echo $font ?>"><?php echo $commit->deleted[$i]['filename'] ?></li>
		<?php } ?>
	</ul>
	<?php } ?>

	<?php if (count($commit->modified) > 0) { ?>
	<h3 style="<?php echo $font ?>font-weight: bold;">Modified Paths</h3>
	<ul>
		<?php for ($i = 0; $i < count($commit->modified); $i++) { ?>
		<li style="<?php echo $font ?>"><a href="#file-<?php echo $i ?>"><?php echo $commit->modified[$i]['filename'] ?></a></li>
		<?php } ?>
	</ul>
	<?php } ?>

<?php echo $html ?>

</body>
</html>
<?php
$output = ob_get_contents();
ob_end_clean();

$mail = new PHPMailerLite();
$mail->SetFrom($git->getConfigValue('hooks.envelopesender'));
$mailinglist = $git->getConfigValue('hooks.mailinglist');
$recipients = explode(',', $mailinglist);
foreach ($recipients as $el)
{
    $mail->AddAddress($el);
}
$prefix = $git->getConfigValue('hooks.emailprefix');

$mail->Subject = $prefix . ' ' . substr($commit->title, 0, 100);
$mail->AltBody = $prefix . ' ' . $commit->title; 
$mail->MsgHTML($output);

if ($mail->Send())
	echo "Mail sent\n";
else 
	echo "Mail not sent\n";
