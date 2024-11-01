<?php
/*
Plugin Name: wpSearchMu
Plugin URI: http://welcome.totheinter.net/wpsearchmu/
Description: This plugin uses Lucene to power a blog search. Based extensively on <a href='http://codefury.net'>Kenny Katzgrau</a>'s WordPress SU plugin <a href='http://codefury.net/projects/wpSearch/'>wpSearch</a>
Version: 2.1.2
Author: Adam Wulf
Author URI: http://welcome.totheinter.net/
*/

/*  Copyright 2009 Adam Wulf  (email : adam.wulf@gmail.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/


/*
 Get permalink from correct blog 
function onesearch_get_blog_permalink () {
	global $post;
	return get_blog_permalink( $post->blog_id, $post->ID );
}

add_filter ('the_permalink', 'onesearch_get_blog_permalink');
*/
/*********************************************************************************************************************************/
/*											WPSearch Default Configuration								  */
/*********************************************************************************************************************************/
$wpSearch_showFooter	 		= wpSearch_getOption('wpSearch_showFooter'			, 'false');
$wpSearch_blogIds		 		= wpSearch_getOption('wpSearch_blogIds'				, "");
$wpSearch_notBlogIds		 	= wpSearch_getOption('wpSearch_notBlogIds'			, "");
$wpSearch_indexTitleBoost 		= wpSearch_getOption('wpSearch_indexTitleBoost'		, 1.8);
$wpSearch_indexCategoryBoost	= wpSearch_getOption('wpSearch_indexCategoryBoost'	, 1);
$wpSearch_indexAuthorBoost		= wpSearch_getOption('wpSearch_indexAuthorBoost'	, 1);
$wpSearch_indexContentBoost 	= wpSearch_getOption('wpSearch_indexContentBoost'	, 1.3);
$wpSearch_indexTagBoost 		= wpSearch_getOption('wpSearch_indexTagBoost'		, 1);
$wpSearch_indexCommentBoost 	= wpSearch_getOption('wpSearch_indexCommentBoost'	, 1);
$wpSearch_indexComments 		= wpSearch_getOption('wpSearch_indexComments'		, 'false');

/*********************************************************************************************************************************/
/*********************************************************************************************************************************/
/******************************|                DO NOT EDIT BELOW                      			      |*****************************************/
/*********************************************************************************************************************************/
/*********************************************************************************************************************************/

require_once 'Zend/Search/Lucene.php';
require_once 'libs/WPSearchResult.php';
require_once 'libs/TimeCounter.php';
require_once 'libs/KLogger.php';

// Globals
$wpSearch_index_location 		= dirname(__FILE__) . '/data/';
$wpSearch_db_prefix 			= $wpdb->prefix;
$wpSearch_blogURL				= get_bloginfo( 'wpurl' );
$wpSearch_defaultEncoding		= 'UTF-8';
$wpSearch_low					= 0;
$wpSearch_high					= 0;
$wpSearch_log					= new KLogger( dirname(__FILE__) . "/log.txt" , KLogger::OFF );
// the file that caches what blogs index what other blogs
$wpSearch_indexFile				= dirname(__FILE__) . "/indexes.ser";
/*********************************************************************************************************************************/
/* START Register Wordpress Hooks */
/*********************************************************************************************************************************/

// Installation hook

/* This thing never seems to work. It throws an error within wordpress upon activation. */
// register_activation_hook(__FILE__, 'wpSearch_install');

// Hooks for actions
add_action('edit_post', 	'wpSearch_editPost');
add_action('delete_post', 	'wpSearch_deletePost');
add_action('publish_post', 	'wpSearch_publishPost');
add_action('publish_page', 	'wpSearch_publishPost');

add_action('admin_menu', 	'wpSearch_addOptionsPage');
add_filter('posts_where', 	'wpSearch_killSearch');
add_filter('post_limits', 	'wpSearch_getLimit');
add_filter('the_posts', 	'wpSearch_Query');

add_action('wp_footer', 'wpSearch_footer');
function wpSearch_footer()
{
    global $wpSearch_showFooter;
    
	if ( is_search() && wpSearchIsIndexBuilt() && $wpSearch_showFooter == "true"){
		echo "<p>Results provided by <a href='http://welcome.totheinter.net/wpsearchmu/'>wpSearchMu</a>.</p>";
	}
}


/* Not needed */
//add_action('comment_post', 	'wpSearch_commentPost');
//add_action('delete_comment',	'wpSearch_deleteComment');
//add_action('edit_comment', 	'wpSearch_editComment');

/*********************************************************************************************************************************/
/* START Functions used to interface with Wordpress */
/*********************************************************************************************************************************/

function wpSearch_isMultiSite(){
	global $wpdb;
	// get a list of blogs in order of most recent update. show only public and nonarchived/spam/mature/deleted
	$sql = "SELECT blog_id FROM $wpdb->blogs";
	$blogs = $wpdb->get_col($sql);
	if(!$blogs) return false;
	return true;
}

function wpSearch_editPost($post_id)
{
	global $wpSearch_log, $wpdb;
	
	$wpSearch_log->LogDebug("Enter: wpSearch_editPost (blog_id = " . $wpdb->blogid . ", post_id = $post_id)");
	$wpSearch_log->LogDebug("Enter: wpSearch_editPost (blog_ids = " . print_r(blogsIndexedFor($wpdb->blogid), true) . ")");
	
	wpSearch_addPostToIndex($wpdb->blogid, $post_id);
	
	$wpSearch_log->LogDebug("Exit : wpSearch_editPost");
}

function wpSearch_deletePost($post_id)
{
	global $wpSearch_log, $wpdb;
	$wpSearch_log->LogDebug("Enter: wpSearch_deletePost (blog_id = " . $wpdb->blogid . ", post_id = $post_id)");
	$wpSearch_log->LogDebug("Enter: wpSearch_deletePost (blog_ids = " . print_r(blogsIndexedFor($wpdb->blogid), true) . ")");
	
	wpSearch_removePostFromIndex($wpdb->blogid, $post_id);
	
	$wpSearch_log->LogDebug("Exit : wpSearch_deletePost");
}

function wpSearch_publishPost($post_id)
{
	global $wpSearch_log, $wpdb;

	$wpSearch_log->LogDebug("Enter: wpSearch_publishPost (blog_id = " . $wpdb->blogid . ", post_id = $post_id)");
	$wpSearch_log->LogDebug("Enter: wpSearch_publishPost (blog_ids = " . print_r(blogsIndexedFor($wpdb->blogid), true) . ")");
	
	wpSearch_addPostToIndex($wpdb->blogid, $post_id);
	$wpSearch_log->LogDebug("Added post to index.");
	$wpSearch_log->LogDebug("Exit : wpSearch_publishPost");
}

function wpSearch_addOptionsPage()
{
	add_options_page('WP Search Mu', 'WP Search Mu', 8, __FILE__, 'wpSearch_printAdminScreen');
}

// return true if our directory is writeable directory
function wpSearchIsWriteable(){
	return is_writable(dirname(__FILE__));
}

function wpSearchIsIndexBuilt(){
	global $wpSearch_index_location, $wpdb;
	$indexloc = $wpSearch_index_location . $wpdb->blogid . "/";
	if(file_exists($indexloc) && is_dir($indexloc)){
		return true;
	}
	return false;
}

/* END Functions used to interface with Wordpress */

/* START Library Functions */


function blogsIndexedFor($blog_id){
	global $wpdb, $wpSearch_indexFile;
	$out = array();
	
	if(file_exists($wpSearch_indexFile)){
		$contents = unserialize(file_get_contents($wpSearch_indexFile));
		foreach($contents as $blog => $blogs_indexed){
			if(in_array($blog_id, $blogs_indexed[0])){
				// the blog is indexing $blog_id
				$out[] = $blog;
			}else if(count($blogs_indexed[0]) == 0 && !in_array($blog_id, $blogs_indexed[1])){
				// the blog is indexing everything
				// and not explicitly *not* indexing $blog_id
				$out[] = $blog;
			}
		}
	}
	
	return array_unique($out);
}


/*
*	wpSearch_Query: This is the heart of wpSearch. It grabs the query 
*	parameters from the global $wpSearch object, and uses the parsed out
*	upper/lower results bounds found in wpSearch_getLimit(). It then opens the index,
*	queries, and hijacks the passd-in $posts object. It loads lucene hits into
*	pseudo DB rows (w00t for duck-typing), changes a few variables for appropriate
*	hit count reporting and paging in wordpress, and returns.
*/
function wpSearch_Query($posts)
{
	global $wpSearch_log;
	
	/* If its query time, hijack the results */
	if ( is_search() && wpSearchIsIndexBuilt() )
	{
		global $wp_query;
		global $wp;
		global $wpSearch_low;
		global $wpSearch_high;
		global $wpSearch_log;
		global $wpSearch_defaultEncoding;
		global $wpSearch_indexComments;
		
		$timer = new TimeCounter(true);

		$posts = array(); /* Kill The Posts. *whoops* */

			/* Grab the query variable s from the global $wp object */
			$q = $wp->query_vars["s"];
			
			$wpSearch_log->LogInfo("Query for term: '$q'");

			/* Open the index */
			$index = wpSearch_createIndexObject(false);
			
			$comment_query = "";
			/* Create the parts of the query */
			$title_query 		= "post_title:($q)";
			$content_query 		= "post_content:($q)";
			$name_query 		= "post_name:($q)";
			$tags_query 		= "tags:($q)";
			$author_query 		= "author:($q)";
			$categories_query 	= "categories:($q)";
			$withComments = $wpSearch_indexComments == 'true' ? true : false;
			$comment_query = "";
			if( $withComments ) $comment_query 	= "post_comments:($q)";
			
			/* Log the query as it went into the query parser */
			$wpSearch_log->LogInfo("$title_query $content_query $tags_query $comment_query");
			
			/* Use Zend_Lucene to parse it into a query object. Don't forget the encoding. */
			$query = Zend_Search_Lucene_Search_QueryParser::parse("$title_query $content_query $name_query $tags_query $categories_query $author_query $comment_query", $wpSearch_defaultEncoding);
			
			/* Get internal Lucene timing */
			$search_time = new TimeCounter(true);
			
			/* Get the results into a hits object */
			$hits = $index->find($query);
			
			/* Get/Print Internal Search Time */
			$t = $search_time->Stop();
			$wpSearch_log->LogInfo( "Internal Lucene Search: $t ms" );
			
			$wp_query->found_posts = sizeof($hits);
			$wp_query->max_num_pages = ceil(sizeof($hits) / $wp_query->query_vars["posts_per_page"]);
			
			$wpSearch_log->LogInfo ( "Changed found_posts to $wp_query->found_posts, max_num_pages to $wp_query->max_num_pages." );
			
			if ( $wpSearch_high > sizeof( $hits ) - 1) $wpSearch_high = sizeof ($hits) - 1;
			
			$num_hits = sizeof($hits);
			$wpSearch_log->LogInfo("Paging - High: $wpSearch_high ; Low: $wpSearch_low ; Number of hits: $num_hits");
			
			for($i = $wpSearch_low; $i <= $wpSearch_high; $i++)
			{
				$hit = $hits[intval($i)];
				$posts[] = WPSearchResult::BuildWPResultFromHit($hit);
			}
		
			$tot = $timer->Stop();
			$wpSearch_log->LogInfo ( "Total Search/Result Load Time: $tot ms" );
	}

	return $posts;
}

/* When a search is executed, WP is going to try and perform a SQL LIKE query
* whether you like it or not. This wpSearch_killSearch hook will appends 0 = 1 to the WHERE clause.
* Hopefully the MYSQL query optimizer comes into play and returns an instant
* empty result set.
*/
function wpSearch_killSearch($where)
{
//echo "Before: $where \n";
	// Essentially make the WP Search as fast as possible.. 
	if ( is_search() && wpSearchIsIndexBuilt() )
	{	
		$where = "AND (0 = 1)";
	}
//echo "After: $where \n";
	return $where;
}

/* This function parses out the upper and lower bound of search results from the internal WP SQL query */
function wpSearch_getLimit($limit)
{
	global $wpSearch_low;
	global $wpSearch_high;

	if( is_search() && wpSearchIsIndexBuilt() )
	{
		$temp 			= str_replace("LIMIT", "", $limit);
		$temp 			= split(",", $temp);
		$wpSearch_low 	= intval($temp[0]);
		$wpSearch_high 	= intval($wpSearch_low + intval($temp[1]) - 1);
	}
	
	return $limit;
}

// Gets a Wordpress option using the Wordpress API
function wpSearch_getOption($optionName, $defaultValue)
{
	$value = get_option($optionName);
	
	if( $value ) return $value;
	
	return $defaultValue;
}

// Sets a Wordpress option using the Wordpress API
function wpSearch_setOption($optionName, $value)
{
	if(count($_POST) > 0)
	{
		if ( get_option($optionName) !== false) 
		{
			update_option($optionName, $value);
		} 
		else 
		{
			$deprecated=' ';
			$autoload='no';
			add_option($optionName, $value, $deprecated, $autoload);
		}
	}
}


function parseIdsFor($str){
	$ids = explode(",", $str);
	$out = array();
	foreach($ids as $id){
		$id = (int) $id;
		if($id){
			$out[] = $id;
		}
	};
	return $out;
}

/*
*	wpSearch_buildFullIndex: Builds a full index based on what is already in the blog.
*		Used primarily on plugin activation (first startup) and can be used to
*		rebuild an index.
*/
function wpSearch_buildFullIndex($withComments = false)
{	
	global $wpdb;
	global $wpSearch_db_prefix;
	global $wpSearch_blogIds;
	global $wpSearch_notBlogIds;


	$ids = parseIdsFor($wpSearch_blogIds);
	$nids = "";
	foreach($ids as $id){
		if(strlen($nids)) $nids .= ",";
		$nids .= "'" . $id . "'";
	};
	
	$ids = parseIdsFor($wpSearch_notBlogIds);
	$nnids = "";
	foreach($ids as $id){
		if(strlen($nnids)) $nids .= ",";
		$nnids .= "'" . $id . "'";
	};
	
	/* Kill the time limit. This will prevent large index builds from being interrupted.  Thanks to 'olivier' */
	set_time_limit  (0);
	
	/* Open the index */
	$index_blog_id = $wpdb->blogid;
	$create_index = true;
	
	/* Configure index for optimal indexing 		*/
	/* Try not to make assumptions about the machine 	*/
	//$index->setMaxBufferedDocs(15);
	//$index->setMergeFactor(15);
	
	$postTable = $wpSearch_db_prefix . "posts";
	
	
	// get a list of blogs in order of most recent update. show only public and nonarchived/spam/mature/deleted
	$sql = "SELECT blog_id FROM $wpdb->blogs WHERE
		public = '1' AND archived = '0' AND mature = '0' AND spam = '0' AND deleted = '0' " .
		(strlen($nids) ? "AND blog_id IN ($nids) " : " ") .
		(strlen($nnids) ? "AND blog_id NOT IN ($nnids) " : " ") .
		"ORDER BY last_updated DESC";
	$blogs = $wpdb->get_col($sql);
	if(!$blogs) $blogs = array();
	
	foreach ($blogs as $blog) {
		if($blog !== false) switch_to_blog($blog);

		/* This is to keep the system from running out of memory 	  */
		/* Process posts from the database in batches of $postsPerBatch */
		$postsPerBatch = 20;
		$cursor = 0;
		
		//
		// if $blog === false, then we're in normal
		// wordpress, otherwise we're in wordpress mu
		//
		// we need _posts and _options tables for this to work
		$blogPostsTable = $wpdb->prefix . "posts";
		
		do
		{
			$index = wpSearch_createIndexObject($create_index,$index_blog_id);
			$sql = "SELECT *
					FROM $blogPostsTable
					WHERE post_status = 'publish'
					LIMIT $cursor, $postsPerBatch";
					
			$posts = $wpdb->get_results($sql);
			
			if (sizeof( $posts ) == 0) break;
			
			foreach($posts as $post)
			{
				$index->addDocument( wpSearch_createDocument($wpdb->blogid, $post, $withComments));
			}
			$cursor += $postsPerBatch;

			$create_index = false;
			$index->commit();
		}
		while(true); // Exiting the loop is handled with a 'break' if the posts array is empty

		if($blog !== false) restore_current_blog();
	}

	if($index) $index->optimize();
	
}

/* This function is for a feature still in devlopment: Comment searching. */

/*
*	wpSearch_getPostComments: This function grabs all the comment content of a post
*		with the specified $postID.
*/
function & wpSearch_getPostComments( $bid, $postID )
{	
	global $wpdb;
	global $wpSearch_db_prefix;
	
	/* This is to keep the system from running out of memory 	  */
	/* Process posts from the database in batches of $postsPerBatch */
	$postsPerBatch = 40;
	$cursor = 0;

	$commentTable = $wpdb->prefix . "comments";

	do
	{
		/*P rocess posts in batches of 20 from the db */
		$sql = "SELECT comment_author, comment_content
				FROM $commentTable
				WHERE comment_approved = '1' 
				AND comment_post_ID = '$postID'
				LIMIT $cursor, $postsPerBatch";
				
		$comments = $wpdb->get_results($sql);
		
		if ( sizeof ( $comments ) == 0 ) break;
		
		foreach($comments as $comment)
		{
			$comment_blob .= $comment->comment_author . ' ' . $comment->comment_content . ' ';
		}
		
		$cursor += $postsPerBatch;
	
	}while( true );
	
	return $comment_blob;
}

/*
*	: Cleans a whole mess of non-alphanumeric and other non
*	index-friendly characters from a string.
*/
function wpSearch_cleanNonAlphaNum($string)
{
	$string = strip_tags( $string );
	$string = str_replace ( "\n\r" , " " , $string );
	$string = str_replace ( "\n" , " " , $string );
	$string = str_replace ( "\r" , " " , $string );
	return $string;
}

/*
*	wpSearch_createDocument: Takes a wordpress database post object and turns it
*		into a lucene document. This takes care fo field types, fields weights, etc.
*/
function & wpSearch_createDocument($bid, $post, $withComments = false)
{
	global $wpSearch_indexTitleBoost;
	global $wpSearch_indexCategoryBoost;
	global $wpSearch_indexContentBoost;
	global $wpSearch_indexAuthorBoost;
	global $wpSearch_indexTagBoost;
	global $wpSearch_indexCommentBoost;
	global $wpSearch_defaultEncoding;
	global $wpSearch_log;
	
	$doc = new Zend_Search_Lucene_Document();

	$comments = "";
	$comments = wpSearch_getPostComments($bid, $post->ID);

	/* If you happen to know about Lucene, and are wondering wy I'm indexing / analyzing everything -- its because using the Unstored option
	*   causes index corruption for an unknown reason. It also caused the most furious bug search in my programming lifetime. Over 20 hours.
	*/
	
	$categories = get_the_category($post->ID);
	$categories_str = "";
	foreach($categories as $category) {
		if(strlen($categories_str)) $categories_str .= " ";
		$categories_str .= $category->cat_name;
	}
	
	$tags = get_the_tags($post->ID);
	$tags_str = "";
	if($tags){
		foreach($tags as $tag) {
			if(strlen($tags_str)) $tags_str .= " ";
			$tags_str .= $tag->name;
		}
	}
	
	$curauth = get_userdata(intval($post->post_author));
	$author = "";
	if($curauth){
		$author = $curauth->display_name;
	}
	
	// Create Fields
	$docId                = Zend_Search_Lucene_Field::Text('docId'                ,  ($post->ID                                   )   , $wpSearch_defaultEncoding);
	$blogId               = Zend_Search_Lucene_Field::Text('blogId'               ,  ($bid                                        )   , $wpSearch_defaultEncoding);
	$post_author          = Zend_Search_Lucene_Field::Text('post_author'          ,  ($post->post_author                          )   , $wpSearch_defaultEncoding);
	$author				  = Zend_Search_Lucene_Field::Text('author'				  ,  (wpSearch_cleanNonAlphaNum($author)          )   , $wpSearch_defaultEncoding);
	$categories			  = Zend_Search_Lucene_Field::Text('categories'			  ,  (wpSearch_cleanNonAlphaNum($categories_str)  )   , $wpSearch_defaultEncoding);
	$tags		          = Zend_Search_Lucene_Field::Text('tags'                 ,  (wpSearch_cleanNonAlphaNum($tags_str)        )   , $wpSearch_defaultEncoding);
	$post_date            = Zend_Search_Lucene_Field::Text('post_date'            ,  ($post->post_date                            )   , $wpSearch_defaultEncoding);
	$post_date_gmt        = Zend_Search_Lucene_Field::Text('post_date_gmt'        ,  ($post->post_date_gmt                        )   , $wpSearch_defaultEncoding);
	$post_content         = Zend_Search_Lucene_Field::Text('post_content'         ,  wpSearch_cleanNonAlphaNum($post->post_content)   , $wpSearch_defaultEncoding);
	$post_comments        = Zend_Search_Lucene_Field::Text('post_comments'        ,  wpSearch_cleanNonAlphaNum($comments     )        , $wpSearch_defaultEncoding);
	$post_title           = Zend_Search_Lucene_Field::Text('post_title'           ,  wpSearch_cleanNonAlphaNum($post->post_title  )   , $wpSearch_defaultEncoding);
	$post_category        = Zend_Search_Lucene_Field::Text('post_category'        ,  ($post->post_category                        )   , $wpSearch_defaultEncoding);
	$post_excerpt         = Zend_Search_Lucene_Field::Text('post_excerpt'         ,  ($post->post_excerpt                         )   , $wpSearch_defaultEncoding);
	$post_status          = Zend_Search_Lucene_Field::Text('post_status'          ,  ($post->post_status                          )   , $wpSearch_defaultEncoding);
	$comment_status       = Zend_Search_Lucene_Field::Text('comment_status'       ,  ($post->comment_status                       )   , $wpSearch_defaultEncoding);
	$ping_status          = Zend_Search_Lucene_Field::Text('ping_status'          ,  ($post->ping_status                          )   , $wpSearch_defaultEncoding);
	$post_name            = Zend_Search_Lucene_Field::Text('post_name'            ,  wpSearch_cleanNonAlphaNum($post->post_name   )   , $wpSearch_defaultEncoding);
	$to_ping              = Zend_Search_Lucene_Field::Text('to_ping'              ,  ($post->to_ping                              )   , $wpSearch_defaultEncoding);
	$pinged               = Zend_Search_Lucene_Field::Text('pinged'               ,   ($post->pinged                              )   , $wpSearch_defaultEncoding);
	$post_modified        = Zend_Search_Lucene_Field::Text('post_modified'        ,  ($post->post_modified                        )   , $wpSearch_defaultEncoding);
	$post_modified_gmt    = Zend_Search_Lucene_Field::Text('post_modified_gmt'    ,  ($post->post_modified_gmt                    )   , $wpSearch_defaultEncoding);
	$post_content_filtered= Zend_Search_Lucene_Field::Text('post_content_filtered',  ($post->post_content_filtered                )   , $wpSearch_defaultEncoding);
	$post_parent          = Zend_Search_Lucene_Field::Text('post_parent'          ,  ($post->post_parent                          )   , $wpSearch_defaultEncoding);
	$guid                 = Zend_Search_Lucene_Field::Text('guid'                 ,  ($post->guid                                 )   , $wpSearch_defaultEncoding);
	$menu_order           = Zend_Search_Lucene_Field::Text('menu_order'           ,  ($post->menu_order                           )   , $wpSearch_defaultEncoding);
	$post_type            = Zend_Search_Lucene_Field::Text('post_type'            ,  ($post->post_type                            )   , $wpSearch_defaultEncoding);
	$post_mime_type       = Zend_Search_Lucene_Field::Text('post_mime_type'       ,  ($post->post_mime_type                       )   , $wpSearch_defaultEncoding);
	$comment_count        = Zend_Search_Lucene_Field::Text('comment_count'        ,  ($post->comment_count                        )   , $wpSearch_defaultEncoding);


	//Boost fields (customizable in the future?)
	$post_title->boost		= $wpSearch_indexTitleBoost;
	$post_name->boost		= $wpSearch_indexTagBoost;
	$tags->boost			= $wpSearch_indexTagBoost;
	$author->boost			= $wpSearch_indexAuthorBoost;
	$categories->boost		= $wpSearch_indexCategoryBoost;
	$post_content->boost	= $wpSearch_indexContentBoost;
	$post_comments->boost	= $wpSearch_indexCommentBoost;
	
	// Add to doc
	$doc->addField($docId);
	$doc->addField($blogId);
	$doc->addField($post_author);
	$doc->addField($author);
	$doc->addField($categories);
	$doc->addField($tags);
	$doc->addField($post_date);
	$doc->addField($post_date_gmt);
	$doc->addField($post_content);
	$doc->addField($post_comments);
	$doc->addField($post_title);
	$doc->addField($post_category);
	$doc->addField($post_excerpt);
	$doc->addField($post_status);
	$doc->addField($comment_status);
	$doc->addField($ping_status);
	$doc->addField($post_name);
	$doc->addField($to_ping);
	$doc->addField($pinged);
	$doc->addField($post_modified);
	$doc->addField($post_modified_gmt);
	$doc->addField($post_content_filtered);
	$doc->addField($post_parent);
	$doc->addField($guid);
	$doc->addField($menu_order);
	$doc->addField($post_type);
	$doc->addField($post_mime_type);
	$doc->addField($comment_count);

	return $doc;
}

/*
*	wpSearch_addPostToIndex: Takes a single wordpress post or page Id and
*		adds it to the index.
*/
function wpSearch_addPostToIndex($bid, $postId)
{
	global $wpdb;
	global $wpSearch_db_prefix;
	global $wpSearch_indexComments;
	global $wpSearch_log;
	
	/* Get rid of any posts with a matching id */
	wpSearch_removePostFromIndex($bid, $postId);
	
	$indexes = blogsIndexedFor($bid);
	
	foreach($indexes as $id){
		$index = wpSearch_createIndexObject(false, $id);
		if($index === false) continue;

		//$index->setMaxBufferedDocs(10);
		//$index->setMergeFactor(10);
		
		$postTable = $wpSearch_db_prefix . "posts";

		$sql = "SELECT * 
				FROM $postTable
				WHERE ID = '$postId'
				AND post_status = 'publish'";
				
		$posts = $wpdb->get_results($sql);
		
		$withComments = $wpSearch_indexComments == 'true' ? true : false;
		
		$wpSearch_log->LogDebug("Enter: wpSearch_addPostToIndex (blog_id = $bid, post_id = $postId)");
		
		
		$index->addDocument(wpSearch_createDocument($bid, $posts[0], $withComments));
		$index->commit();
	}
	
}

/*
*	wpSearch_removePostFromIndex: Takes a wordpress database page or post id
*		and removes it from the index, if it can find it.
*/
function wpSearch_removePostFromIndex($bid, $postId)
{
	global $wpSearch_log;
	
	$indexes = blogsIndexedFor($bid);
	foreach($indexes as $id){
		$index = wpSearch_createIndexObject(false, $id);
		if($index === false) continue;

		$query = Zend_Search_Lucene_Search_QueryParser::parse('docId:(' . $postId . ') AND blogId:(' . $bid . ')');
		$hits = $index->find($query);
		
		$n = sizeof( $hits );
		
		$wpSearch_log->LogInfo("Searching index for docId = $postId for deletion. Delete query: $query");
		$wpSearch_log->LogInfo("Found $n match(es).");
		
		foreach($hits as $hit)
		{
			$wpSearch_log->LogDebug("Delete Document: internal id: $hit->id ; docId: $hit->docId, '$hit->post_title'");
			$index->delete($hit->id);
		}
		
		$index->commit();
	}
}

/*
*	wpSearch_createIndexObject: Creates a lucene index object. A boolean parameter
*		$create can be supplied to create the physical index if needed.
*/
function & wpSearch_createIndexObject($create = false, $blogid = false)
{
	global $wpSearch_index_location, $wpdb;
	
	if($blogid === false) $blogid = $wpdb->blogid;
	
	$indexloc = $wpSearch_index_location . $blogid;
	
	if ( $create )
		return Zend_Search_Lucene::create( $indexloc );
	else if(file_exists($indexloc))
		return Zend_Search_Lucene::open( $indexloc );
	else
		return false;
}

function wpSearch_removeDir($path) {
    $dh = opendir($path);
    while ($file = readdir($dh)) {
        if($file != '.' && $file != '..') {
            $fullpath = $path.'/'.$file;
            if(!is_dir($fullpath)) {
        		@unlink($fullpath);
            } else {
        		wpSearch_removeDir($fullpath, true);
        		@rmdir($fullpath);
            }
        }
    }
	@rmdir($path);
 
    closedir($dh);
}

/* END Library Functions */

/* Having HTML so intertwined with PHP is gross, but there isn't much of a choice here. This prints the admin panel. */
function wpSearch_printAdminScreen()
{
	global $wpSearch_blogIds;
	global $wpSearch_notBlogIds;
	global $wpSearch_indexTitleBoost;
	global $wpSearch_indexAuthorBoost;
	global $wpSearch_indexCategoryBoost;
	global $wpSearch_indexContentBoost;
	global $wpSearch_indexTagBoost;
	global $wpSearch_indexCommentBoost;
	global $wpSearch_indexComments;
	global $wpSearch_blogSearchType;
	global $wpSearch_indexFile;
	global $wpSearch_index_location;
	global $wpdb;
	
//	error_reporting(E_ALL);


	if(isset($_POST["deleteIndex"])){
		wpSearch_removeDir($wpSearch_index_location . $wpdb->blogid);
	}else{
		wpSearch_setOption('wpSearch_showFooter', 			isset($_POST['wpSearch_footer']) ? "true" : "false");
		wpSearch_setOption('wpSearch_blogIds', 				$_POST['wpSearch_BIDS']);
		wpSearch_setOption('wpSearch_notBlogIds', 			$_POST['wpSearch_NBIDS']);
		wpSearch_setOption('wpSearch_indexTitleBoost', 		$_POST['wpSearch_RCTB']);
		wpSearch_setOption('wpSearch_indexContentBoost', 	$_POST['wpSearch_TCCB']);
		wpSearch_setOption('wpSearch_indexCategoryBoost', 	$_POST['wpSearch_TCatB']);
		wpSearch_setOption('wpSearch_indexAuthorBoost', 	$_POST['wpSearch_TAuthB']);
		wpSearch_setOption('wpSearch_indexTagBoost', 		$_POST['wpSearch_RCTagB']);
		wpSearch_setOption('wpSearch_indexCommentBoost', 	$_POST['wpSearch_CB']);
	}
	

	$wpSearch_showFooter	 		= wpSearch_getOption('wpSearch_showFooter'			, 'true');
	$wpSearch_blogIds		 		= wpSearch_getOption('wpSearch_blogIds'				, "");
	$wpSearch_notBlogIds		 	= wpSearch_getOption('wpSearch_notBlogIds'			, "");
	$wpSearch_indexTitleBoost 		= wpSearch_getOption('wpSearch_indexTitleBoost'		, 1.8);
	$wpSearch_indexContentBoost 	= wpSearch_getOption('wpSearch_indexContentBoost'	, 1.3);
	$wpSearch_indexAuthorBoost	 	= wpSearch_getOption('wpSearch_indexAuthorBoost'	, 1);
	$wpSearch_indexCategoryBoost 	= wpSearch_getOption('wpSearch_indexCategoryBoost'	, 1);
	$wpSearch_indexTagBoost 		= wpSearch_getOption('wpSearch_indexTagBoost'		, 1);
	$wpSearch_indexCommentBoost 	= wpSearch_getOption('wpSearch_indexCommentBoost'	, 1);
	$wpSearch_indexComments 		= wpSearch_getOption('wpSearch_indexComments'		, 'false');

	//
	// update our cache of what blogs are indexing which other blogs
	if(file_exists($wpSearch_indexFile)){
		$cache = unserialize(file_get_contents($wpSearch_indexFile));
	}else{
		$cache = array();
	}
	$nids = parseIdsFor($wpSearch_blogIds);
	$nnids = parseIdsFor($wpSearch_notBlogIds);
	$cache[$wpdb->blogid] = array($nids, $nnids);
	$ret = @file_put_contents($wpSearch_indexFile, serialize($cache));
	
	$writeable = true;
	if($ret === false){
		// we can't write to our temp folder :(
		$writeable = false;
	}
	
	
	?>
	
	<div class="wrap">
		<h2>wpSearchMu Control Panel</h2>
		<?php 
		
			/* Check to see if we should rebuild the index */
			if($_POST['rebuild'] == 'rebuild') 
			{ 
				/* Check to see if we should include commments */
				if( $_POST['comments'] != 'comments' )
				{
					echo 'Rebuilding index ... ';
					
					/* Disable Comment searching */
					wpSearch_setOption('wpSearch_indexComments', 'false');
					
					wpSearch_buildFullIndex();
				}
				else
				{
					echo 'Rebuilding index with comments ... ';
					
					/* Set the option for comment searching to be enabled */
					wpSearch_setOption('wpSearch_indexComments', 'true');
					
					wpSearch_buildFullIndex( true );
				}

				echo 'Complete<br/>';
			}
			
		?>
		<script language="javascript">
			function checkIndexBox()
			{
				if( document.getElementById('chkComments').checked == true )
				{
					document.getElementById('chkRebuild').checked = true;
				}
			}
			function uncheckCommentBox()
			{
				if( document.getElementById('chkRebuild').checked == false )
				{
					document.getElementById('chkComments').checked = false;
				}
			}
		</script>
		<?
		
		$ok = true;
		
		$dir1 = realpath(get_template_directory() . "/../../plugins/") . "/";
		$dir2 = dirname(__FILE__);
		
			if(!wpSearch_isMultiSite()){
				$ok = false;
		?>
			<div id="message" class="updated fade below-h2" style="background-color: rgb(255, 251, 204);">
			<p>WPSearchMu requires that Wordpress is running in Network mode.</p>
			<p>More information is available here: <a href='http://codex.wordpress.org/User:Andrea/Create_A_Network'>Wordpress 3.0 Network</a>.</p>
			</div>
		<?
			}else if(strpos($dir2, "mu-plugin") === false || strpos($dir2, $dir1) !== false){
				$ok = false;
		?>
			<div id="message" class="updated fade below-h2" style="background-color: rgb(255, 251, 204);">
			<p>wpSearchMu needs to be installed to the mu-plugins folder. Please move the wp-search-mu folder and the wp-search-mu.php file to the following directory:</p>
			<p><?=$dir1?></p>
			</div>
		<? }else if(!wpSearchIsWriteable()){ ?>
			<div id="message" class="updated fade below-h2" style="background-color: rgb(255, 251, 204);">
			<p>The wpSearchMu directory is not writeable. From the command line, please type:</p>
			<p>chmod 777 <?=dirname(__FILE__)?></p>
			</div>
		<? }else if(!wpSearchIsIndexBuilt()){ ?>
			<div id="message" class="updated fade below-h2" style="background-color: rgb(255, 251, 204);">
			<p>Since there is no index built for this blog, the default WordPress search will be used.</p>
			<p>To use wpSearchMu for this blog, check the "Build Search Index" option below and then Save Changes.</p>
			</div>
		<? }else{ ?>
			<div id="message" class="updated fade below-h2" style="background-color: rgb(255, 251, 204);">
			<p>wpSearchMu is properly configured and indexed. All searches on this blog use wpSearchMu instead of the regular WordPress search.</p>
			<p><b>Theme Integration</b>: Remember to integrate wpSearchMu with your theme. More info at <a href='http://welcome.totheinter.net/wpsearchmu/'>http://welcome.totheinter.net/wpsearchmu/</a>.</p>
			</div>
		<? } ?>
		
		<? if($ok){ ?>
			<h3>wpSearchMu Options</h3>
			<form action="<?php echo $PHP_SELF; ?>" method="post">
				<table class="form-table">
					<tbody>
						<tr valign="top">
							<th scope="row">Show link in footer</th>
							<td>Show link to <a href='http://welcome.totheinter.net/wpsearchmu/'>wpSearchMu homepage</a> in footer: <input type="checkbox" value="true" <?php echo wpSearch_getOption('wpSearch_showFooter', $wpSearch_showFooter) == "true" ? "CHECKED" : ""; ?> name="wpSearch_footer"/> 
								<br />If you love wpSearchMu, then add a link back to my site in the footer of your search pages. <b>Thanks!!</b>
							</td>
						</tr>				
						<tr valign="top">
							<th scope="row">Blogs to Index</th>
							<td>Blog Ids: <input type="text" size="10" value="<?php echo wpSearch_getOption('wpSearch_blogIds', $wpSearch_blogIds); ?>" name="wpSearch_BIDS"/> 
								<br /><b>Leave blank for all blogs</b> OR enter the blog ids that you want to index separated by a comma.
								<br /><b>If you change this value, you will need to rebuild your index.</b>
							</td>
						</tr>				
						<tr valign="top">
							<th scope="row">Blogs NOT to Index</th>
							<td>Blog Ids: <input type="text" size="10" value="<?php echo wpSearch_getOption('wpSearch_notBlogIds', $wpSearch_notBlogIds); ?>" name="wpSearch_NBIDS"/> 
								<br />Enter the blog ids that you DO NOT want to index separated by a comma. This is really only helpful if you
								leave the first option blank.
								<br /><b>If you change this value, you will need to rebuild your index.</b>
							</td>
						</tr>				
						<tr valign="top">
							<th scope="row">Relevancy Customization (Field 'boosting')</th>
							<td>Title: <input type="text" size="10" value="<?php echo wpSearch_getOption('wpSearch_indexTitleBoost', $wpSearch_indexTitleBoost); ?>" name="wpSearch_RCTB"/> 
							    Content:<input type="text" size="10" value="<?php echo wpSearch_getOption('wpSearch_indexContentBoost', $wpSearch_indexContentBoost); ?>" name="wpSearch_TCCB"/> 
							    Author:<input type="text" size="10" value="<?php echo wpSearch_getOption('wpSearch_indexAuthorBoost', $wpSearch_indexAuthorBoost); ?>" name="wpSearch_TCCB"/> 
							    <br />
							    Tag:<input type="text" size="10" value="<?php echo wpSearch_getOption('wpSearch_indexTagBoost', $wpSearch_indexTagBoost); ?>" name="wpSearch_RCTagB"/>
							    Categories:<input type="text" size="10" value="<?php echo wpSearch_getOption('wpSearch_indexCategoryBoost', $wpSearch_indexCategoryBoost); ?>" name="wpSearch_TCatB"/>
							    Comments:<input type="text" size="10" value="<?php echo wpSearch_getOption('wpSearch_indexCommentBoost', $wpSearch_indexCommentBoost); ?>" name="wpSearch_CB"/><br />
								<br />'Boosting' one field higher than another will make that field more important in searches.
								<br />Usually, the title is the most important thing to match in a search, but that's up to you.
								<br />For most users, these values won't need to be changed. (Typical values range from 1 to 2).
								<br />Modifying these values will require an index rebuild (below).
							</td>
						</tr>				
						<tr valign="top">
							<th scope="row">Advanced Options</th>
							<td><b>Build Search Index:</b> <input id="chkRebuild" type="checkbox" name="rebuild" value="rebuild" onClick="uncheckCommentBox()">
								<b>Enable Comment Searching on build:</b><input id="chkComments" type="checkbox" name="comments" value="comments" onClick="checkIndexBox()">
								<br />Checking this option and clicking 'Save' will build or rebuild the search database for your blog.
								<br />This should be done immediately after installation and almost never after.
								<br />If comment searching is enabled, it will always be enabled for the life of the index.
								<br />Enabling comments is only possible when also building the index,
								<br />and can greatly increase indexing time, depending on the number of comments.
								<br />If you suspect the index is corrupted (very rare), this will be the fix.
								<br />Index building runs at ~5 posts / second for a moderately sized page/post.
							</td>
						</tr>
					</tbody>
				</table>
				<p class="submit">
					<input type="submit" value="Save Changes" name="Submit"/>
				</p>
			</form>
			<? if(wpSearchIsIndexBuilt()){ ?>
			<h3>Stop Using wpSearchMu</h3>
			<form action="<?php echo $PHP_SELF; ?>" method="post" onsubmit='return confirm("Are you sure you want to delete the wpSearchMu index and use the default WordPress search instead?")'>
				<p>
					Remove the wpSearchMu index and use the default WordPress search instead.
				</p>
				<p class="submit">
					<input type="hidden" value="1" name="deleteIndex"/>
					<input type="submit" value="Stop using wpSearchMu" name="Submit"/>
				</p>
			</form>
			<? } ?>
			
		<? } ?>
	</div>
	
	<?php
}
?>
