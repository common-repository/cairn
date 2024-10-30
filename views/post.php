<article>
	<?php if ( $title ) { ?>
	<header>
		<h1><a href="<?php print $item['permalink']; ?>"><?php print $item['title']; ?></a></h1>
		<address class="author">By <a rel="author" href="<?php bloginfo('url'); ?>"><?php print $item['author']; ?></a></address> 
		<time datetime="<?php print $item['date']; ?>" pubdate="pubdate"><?php print $item['pretty_date']; ?></time>
	</header>
	<?php } ?>
	<?php print $item['body']; ?>
	<footer>
		<?php print Cairn_Copyright::html( $item['license'] ); ?>
	</footer>
</article>


