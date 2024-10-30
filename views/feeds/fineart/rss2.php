<?php
global $posts;
global $post;
header('Content-Type: ' . feed_content_type('rss-http') . '; charset=' . get_option('blog_charset'), true);
$more = 1;

echo '<?xml version="1.0" encoding="'.get_option('blog_charset').'"?'.'>'; ?>

<rss version="2.0"
	xmlns:content="http://purl.org/rss/1.0/modules/content/"
	xmlns:wfw="http://wellformedweb.org/CommentAPI/"
	xmlns:dc="http://purl.org/dc/elements/1.1/"
	xmlns:atom="http://www.w3.org/2005/Atom"
	xmlns:sy="http://purl.org/rss/1.0/modules/syndication/"
	xmlns:slash="http://purl.org/rss/1.0/modules/slash/"
	<?php do_action('rss2_ns'); ?>
>

<channel>
	<title><?php bloginfo_rss('name'); wp_title_rss(); ?></title>
	<atom:link href="<?php self_link(); ?>" rel="self" type="application/rss+xml" />
	<link><?php bloginfo_rss('url') ?></link>
	<description><?php bloginfo_rss("description") ?></description>
	<copyright><?php print get_option('fineart_copyright'); ?></copyright>
	<lastBuildDate><?php echo mysql2date('D, d M Y H:i:s +0000', get_lastpostmodified('GMT'), false); ?></lastBuildDate>
	<language><?php echo get_option('rss_language'); ?></language>
	<sy:updatePeriod><?php echo apply_filters( 'rss_update_period', 'hourly' ); ?></sy:updatePeriod>
	<sy:updateFrequency><?php echo apply_filters( 'rss_update_frequency', '1' ); ?></sy:updateFrequency>
	<?php do_action('rss2_head'); ?>
	<?php foreach ( $items as $item ) { ?>
	<item>
		<title><?php print $item['title']; ?></title>
		<link><?php print $item['permalink']; ?></link>
		<pubDate><?php print $item['pubdate']; ?></pubDate>
		<dc:creator><?php print $item['author']; ?></dc:creator>
		<guid isPermaLink="false"><?php print $item['guid']; ?></guid>
		<content:encoded><![CDATA[<?php print Cairn_Fineart_Post::body( $item, false ); ?>]]></content:encoded>
<?php foreach ( $item['downloads'] as $enclosure ) { 
	print '<enclosure url="' . trim(htmlspecialchars($enclosure['url'])) . '" length="' . trim($enclosure['size']) . '" type="'.trim($enclosure['format']).'" />'; // add type
} ?>
	</item>
	<?php } ?>
</channel>
</rss>
