<?php
	
	/* This page acts like a web service. It takes in several paramters, and queries the lucene index independent of Wordpress.
	* This is good for index testing, or extending the functionality of wpSearch. Remote calls could be made to this service from 
	* another web site. If you would rather not have this functionality available, you can simple delete this file.
	* Read more about the functionality of this page in the wpSearch manual @:
	* http://codefury.net/wp-content/uploads/2008/06/wpSearch_Manual.pdf
	*/
	
	/*  Copyright 2008 Kenneth Katzgrau  (email : kjk34@njit.edu)

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
	
	require_once 'Zend/Search/Lucene.php'; 
	require_once 'libs/WPSearchResult.php';

	$q 					= wpSearch_coalesce($_POST['q'], 					$_GET['q']);
	$page_number 		= wpSearch_coalesce($_POST['p'], 					$_GET['p'], 		'0');
	$results_per_page 	= wpSearch_coalesce($_POST['n'], 					$_GET['n'], 		'10');
			
	/* If there is no query q to search on */
	if($q == '') die("No Query Supplied. q = '$q'");
	
	$results = array();
				
	// Set the default Search operator to AND ( like Google )
	// Zend_Search_Lucene_Search_QueryParser::setDefaultOperator( Zend_Search_Lucene_Search_QueryParser::B_AND );
	
	/* Open the index. Must exist at /data */
	$index = new Zend_Search_Lucene(dirname(__FILE__) . '/data/', false);
	
	/* Create the parts of the query */
	$title_query 	= "post_title:($q)";
	$content_query 	= "post_content:($q)";
	$tags_query 	= "post_name:($q)";
	
	/* Use Zend_Lucene to parse it into a query object*/
	$query = Zend_Search_Lucene_Search_QueryParser::parse("$title_query $content_query $tags_query");
	
	/* Get the results into a hits object */
	$hits = $index->find($query);
	
	$low = $page_number * $results_per_page;
	$high = $low + $results_per_page;
	
	if ( sizeof ($hits) < $low ) $low = sizeof ($hits);
	if ( sizeof ($hits) < $high ) $high = sizeof ($hits);
	
	switch($format)
				{
					default:
						$jsonResult = array();
						
						for($i = $low; $i < $high; $i++)
						{
							$hit = $hits[intval($i)];
							$jsonResult[] = WPSearchResult::BuildWPResultFromHit($hit);
						}
						
						die( json_encode ( $jsonResult ) );
					
					
						die ("Request Error: Format wasn't specified.");
				}
			
	function wpSearch_coalesce() 
	{
	    // Loop through all arguments given
	    foreach (func_get_args() as $value) {
	        // If this argument doesn't equal false, return it
	        if ($value || $value === '0') { return $value; }
	    }
	}
	
?>
