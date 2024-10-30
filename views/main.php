<?php

/**
 * The main template used to return HTML output
 *
 * Long description first sentence starts here
 * and continues on this line for a while
 * finally concluding here at the end of
 * this paragraph
 *
 * The blank line above denotes a paragraph break
 */

global $wp_query;
if ( count( $items ) <= 0 ) {
	header("HTTP/1.0 404 Not Found");
	$item = array(
		'title' => __('Not Found'),
		'permalink' => '',
		'body' => __('<p class="sorry">Sorry, no posts matched your criteria.</p>'),
		'date' => '2007-12-31 23:59:59',
		'pretty_date' => 'Saturday 32nd, 2041',
		'excerpt' => __('<p class="sorry">Sorry, no posts matched your criteria.</p>')
	);
	$items[] = $item;
}
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
<meta charset="utf-8">
<title><?php

	wp_title( '|', true, 'right' );

	// Add the blog name.
	bloginfo( 'name' );

	// Add the blog description for the home/front page.
	$site_description = get_bloginfo( 'description', 'display' );
	if ( $site_description && ( is_home() || is_front_page() ) )
		echo " | $site_description";

	?></title>
<?php 
	global $wp_query; 
	if ( isset( $wp_query->query_vars['robots'] ) ) {
		print '<meta name="robots" content="noindex, nofollow">'.PHP_EOL;
	}
	if ( isset( $wp_query->query_vars['post_type'] ) &&
		$wp_query->query_vars['post_type'] == 'post' ) {
		print '<link href="'.get_home_url().'/news/feed/'.'" rel="alternate" type="application/rss+xml" title="" />'.PHP_EOL;
		print '<link href="'.get_home_url().'/news/feed/atom/'.'" rel="alternate" type="application/atom+xml" title="" />'.PHP_EOL;
		print '<link rel="copyright" href="'.get_option('cairn_posts_copyright_url').'" />'.PHP_EOL;
		print '<meta name="copyright" content="'.get_option('cairn_posts_copyright').'" />'.PHP_EOL;
	} else if ( isset( $wp_query->query_vars['post_type'] ) &&
		$wp_query->query_vars['post_type'] == 'fineart' ) {
		print '<link href="'.get_home_url().'/shop/feed/'.'" rel="alternate" type="application/rss+xml" title="" />'.PHP_EOL;
		print '<link href="'.get_home_url().'/shop/feed/atom/'.'" rel="alternate" type="application/atom+xml" title="" />'.PHP_EOL;
		print '<link rel="copyright" href="'.get_option('cairn_fineart_copyright_url').'" />'.PHP_EOL;
		print '<meta name="copyright" content="'.get_option('cairn_fineart_copyright').'" />'.PHP_EOL;
	} else if ( isset( $wp_query->query_vars['post_type'] ) &&
		$wp_query->query_vars['post_type'] == 'portfolio' ) {
		print '<link href="'.get_home_url().'/portfolio/feed/'.'" rel="alternate" type="application/rss+xml" title="" />'.PHP_EOL;
		print '<link href="'.get_home_url().'/portfolio/feed/atom/'.'" rel="alternate" type="application/atom+xml" title="" />'.PHP_EOL;
		print '<link rel="copyright" href="'.get_option('cairn_portfolio_copyright_url').'" />'.PHP_EOL;
		print '<meta name="copyright" content="'.get_option('cairn_portfolio_copyright').'" />'.PHP_EOL;
	}
?>
<meta http-equiv="X-UA-Compatible" content="chrome=1" />
<meta name="description" content="<?php print get_bloginfo('description'); ?>" />
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1"/>
<link rel="stylesheet" href="<?php print Cairn::static_url( '/style.css' ); ?>" type="text/css" charset="utf-8" />
<script type="text/javascript" src="<?php print Cairn::static_url('/includes/json/json2.js'); ?>"></script>
<script type="text/javascript" src="<?php print Cairn::static_url('/includes/jquery-1.7.2.min.js'); ?>"></script>
<script type="text/javascript" src="<?php print Cairn::static_url('/includes/ejs.js'); ?>"></script>
<script type="text/javascript" src="<?php print Cairn::static_url('/jquery.cairn.js'); ?>"></script>
<script type="text/javascript" src="<?php print Cairn::static_url('/includes/jquery.cj-swipe.js'); ?>"></script>
<script type="text/javascript" src="<?php print Cairn::static_url('/includes/jquery.placeholder.js'); ?>"></script>
<script type="text/javascript" src="<?php print Cairn::static_url('/callbacks.js'); ?>"></script>
<script type="text/javascript">
var ajaxurl = "<?php echo admin_url('admin-ajax.php'); ?>";
var version = "<?php echo Cairn::$version; ?>";
var nonce = "<?php echo wp_create_nonce( 'cairn_nonce' ); ?>";
var templatesurl = "<?php echo plugins_url('cairn/static/templates/'); ?>";
var homeurl = "<?php echo get_home_url('/'); ?>";
<?php 
	$cairn_large_logo = get_option('cairn_large_logo', true);
	$cairn_small_logo = get_option('cairn_small_logo', true);
	$cairn_large_logo_image = false;
	if ( $cairn_large_logo ) {
		$cairn_large_logo_image = wp_get_attachment_image_src( $cairn_large_logo['id'], 'full' );
	}
	$cairn_small_logo_image = false;
	if ( $cairn_small_logo ) {
		$cairn_small_logo_image = wp_get_attachment_image_src( $cairn_small_logo['id'], 'full' );
	}
	$cairn_video_poster = get_option('cairn_video_poster', false);
	if ( $cairn_video_poster ) {
		$cairn_video_poster_image = wp_get_attachment_image_src( $cairn_video_poster['id'], 'full' );
	}	
	$cairn_video_sources = get_option('cairn_video_sources', false);
	if ( $cairn_video_sources ) {
		foreach ( $cairn_video_sources as &$source ) {
			$source['url'] = wp_get_attachment_url( $source['id'] );
			$source['meta'] = wp_get_attachment_metadata( $source['id'] );
			$source['type'] = $source['type'];//$video['meta']['mime_type'];
		}
	}
?>
var cover_videos = <?php print json_encode( $cairn_video_sources ); ?>;
var cover_video_poster = <?php print json_encode( $cairn_video_poster_image ); ?>;
var cover_display_text = "<?php print get_option('cairn_display_text', false); ?>";
var cover_button_text = "<?php print get_option('cairn_button_text', false); ?>";
var cover_button_link = "<?php print get_option('cairn_button_link', false); ?>";
var largelogo = <?php echo json_encode($cairn_large_logo_image); ?>;
var smalllogo = <?php echo json_encode($cairn_small_logo_image); ?>;
var coverimagehorizontal = "<?php echo get_option('cairn_cover_image_horizontal'); ?>";
var coverimagevertical = "<?php echo get_option('cairn_cover_image_vertical'); ?>";
var covertitle = "<?php echo get_option('cairn_cover_title'); ?>";
var coverdate = "<?php echo get_option('cairn_cover_date'); ?>";
var coverauthor = "<?php echo get_option('cairn_cover_author'); ?>";
var aboutpage = "<?php echo get_permalink(get_option('cairn_about_page')); ?>";
var privacypage = "<?php echo get_permalink(get_option('cairn_privacy_page')); ?>";
var shippingpage = "<?php echo get_permalink(get_option('cairn_shipping_page')); ?>";
var title = "<?php echo get_bloginfo('name'); ?>";
var description = "<?php echo get_bloginfo('description'); ?>";
var mediaurl ="<?php echo plugins_url('cairn/static'); ?>";
var copyright ="<?php echo get_option('cairn_posts_copyright'); ?>";
var copyright_url ="<?php echo get_option('cairn_posts_copyright_url'); ?>";
<?php 
	$wp_response = array( 'items' => $items, 'contact' => $contact );
	if ( isset( $cart ) ) {
		$wp_response['cart'] = $cart;
	}
	$home_url_parsed = parse_url( get_home_url('/') );
	if ( isset($home_url_parsed['path']) ) {
		$goto_url = str_replace( $home_url_parsed['path'], '', $_SERVER['REQUEST_URI'] );
	} else {
		$goto_url = $_SERVER['REQUEST_URI'];
	}
?>
var wp_response = <?php print json_encode( $wp_response  ); ?>;
jQuery(document).ready(function($){
$.getJSON('<?php print Cairn::static_url( "/urls.json" ); ?>', function(data){
data['mediaurl'] = mediaurl;
data['description'] = description;
data['title'] = title;
data['version'] = version;
data['homeurl'] = homeurl;
// setup init
$().cairn('init', data, 'stage_loaded_callback', 'stage_leaving_callback');
// goto first url
$().cairn('request', '<?php print $goto_url; ?>');
})
})
</script>
</head>
<body>
<noscript>
<?php 
if ( $items ) {
	foreach ( $items as $item ) {
		if ( isset( $item['type'] ) ) {
			if ( $item['type'] == 'fineart' ) {
				print Cairn_Fineart_Post::body( $item, true ).PHP_EOL;
			} else if ( $item['type'] == 'portfolio' ) {
				print Cairn_Portfolio_Post::body( $item ).PHP_EOL;
			} else if ( $item['type'] == 'post' ) {
				print Cairn_Post::body( $item ).PHP_EOL;
			}
		}
	}	
} else {
?>
	<h1>404 Not Found</h1>
	<?php _e('Sorry, no posts matched your criteria.'); ?>
	<?php
};
?>
</noscript>
</body>
</html>
