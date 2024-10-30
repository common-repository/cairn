<article>
	<?php if ( $title ) { ?>
	<header>
		<h1>
			<a href="<?php print $item['permalink']; ?>"><?php print $item['title']; ?></a>
		</h1>
		<address class="author">By <a rel="author" href="<?php bloginfo('url'); ?>"><?php print $item['author']; ?></a></address> 
		<time datetime="<?php print $item['date']; ?>" pubdate="pubdate"><?php print $item['pretty_date']; ?></time>
	</header>
	<?php } ?>

	<?php 
	$width = 500;
	foreach ( $item['videos'] as $enclosure ) { 
		if ( $enclosure['feed'] ) {
			$height = round( $enclosure['meta']['height'] / $enclosure['meta']['width'] * $width );
			print '<video controls="controls" poster="'.$item['image'][0].'" width="'.$width.'" height="'.$height.'"><source src="'.$enclosure['url'].'" type="'.$enclosure['type'].'"></source></video>';

		}	
	} ?>

<?php 





?>
	<p><?php print $item['details']['description']; ?></p>

	<footer>
<?php 
	if ( isset( $item['attributions'] ) && $item['attributions'] ) { 
		print '<p>References:</p>';
		print '<ol>';
		foreach ( $item['attributions'] as $attr ) { 
			print '<li>'.$attr['html'].'</li>';
		}
		print '</ol>';
	} 
?>
	<?php print Cairn_Copyright::html( $item['license'] ); ?>

	</footer>

</article>