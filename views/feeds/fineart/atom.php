<?php
global $posts;
global $post;
header('Content-Type: ' . feed_content_type('atom') . '; charset=' . get_option('blog_charset'), true);
$more = 1;

echo '<?xml version="1.0" encoding="'.get_option('blog_charset').'"?'.'>'; ?>
<feed
  xmlns="http://www.w3.org/2005/Atom"
  xmlns:thr="http://purl.org/syndication/thread/1.0"
  xml:lang="<?php echo get_option('rss_language'); ?>"
  xml:base="<?php bloginfo_rss('url') ?>/wp-atom.php"
  <?php do_action('atom_ns'); ?>
 >
	<title type="text"><?php bloginfo_rss('name'); wp_title_rss(); ?></title>
	<subtitle type="text"><?php bloginfo_rss("description") ?></subtitle>

	<updated><?php echo mysql2date('Y-m-d\TH:i:s\Z', get_lastpostmodified('GMT'), false); ?></updated>

	<link rel="alternate" type="text/html" href="<?php bloginfo_rss('url') ?>" />
	<id><?php bloginfo('atom_url'); ?></id>
	<link rel="self" type="application/atom+xml" href="<?php self_link(); ?>" />

	<?php do_action('atom_head'); ?>
	<?php foreach( $items as $item ) { ?>
	<entry>
		<author>
			<name><?php print $item['license']['author']; ?></name>
			<uri><?php print $item['license']['author_url']; ?></uri>
		</author>
		<rights><?php print Cairn_Copyright::text( $item['license'] ); ?></rights>
		<title type="<?php html_type_rss(); ?>"><![CDATA[<?php print $item['title']; ?>]]></title>
		<link rel="alternate" type="text/html" href="<?php print $item['permalink']; ?>" />
		<id><?php print $item['guid']; ?></id>
		<updated><?php echo get_post_modified_time('Y-m-d\TH:i:s\Z', true); ?></updated>
		<published><?php echo get_post_time('Y-m-d\TH:i:s\Z', true); ?></published>
		<summary type="<?php html_type_rss(); ?>"><![CDATA[<?php print $item['excerpt'] ?>]]></summary>
		<content type="<?php html_type_rss(); ?>" xml:base="<?php print $item['permalink']; ?>"><![CDATA[<?php print Cairn_Fineart_Post::body( $item, false ); ?>]]></content>

<?php foreach ( $item['downloads'] as $enclosure ) { 
	print '<enclosure url="' . trim(htmlspecialchars($enclosure['url'])) . '" length="' . trim($enclosure['size']) . '" type="'.trim($enclosure['format']).'" />'; // add type
} ?>
	</entry>
	<?php } ?>
</feed>

