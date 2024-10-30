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
	$height = round( $width * $item['image'][2] / $item['image'][1] );
	print '<a href="'.$item['permalink'].'"><img src="'.$item['image'][0].'?w='.$width.'&h='.$height.'" width="'.$width.'" height="'.$height.'" /></a>';

?>
	<p><?php print $item['details']['width'].'″ x '.$item['details']['height'].'″<br/>'; ?></p>
	<p><?php print $item['details']['description']; ?></p>

<?php
	if ( isset($item['options']) ) {
		print '<p><a href="'.$item['permalink'].'">Buy Now</a></p>';
	}
?>	

	<footer>
<?php 
	if ( isset( $item['downloads'] ) && $item['downloads'] ) { 
		print '<p>Downloads:</p>';
		print '<ol>';
		foreach ( $item['downloads'] as $enclosure ) { 
			print '<li><a href="' . $enclosure['url'].'">'.$enclosure['name'].' ( '.$enclosure['size'].'  bytes / '.$enclosure['format'].')</a></li>';
		}
		print '</ol>';
	} 

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