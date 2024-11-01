=== Plugin Name ===
Contributors: Adam Wulf, Kenny Katzgrau
Developer Links: 
	http://welcome.totheinter.net/wpsearchmu/
	http://codefury.net/projects/wpsearch/donate-to-wpsearch/
Donate to Kenny link: http://codefury.net/projects/wpsearch/donate-to-wpsearch/
Tags: wpmu, sitewide, wordpress, search, blog, lucene, plugin, boolean, wildcard, google, unleashed
Requires at least: 3.0
Tested up to: 3.0
Stable tag: 2.1.2

wpSearchMu is a very powerful search plugin for Wordpress Mu. It is based on the open source search engine "Lucene" which means its fast and relevant.

== Description ==

wpSearchMu is a very powerful search plugin for Wordpress Mu. It is based on the open source search engine "Lucene" which means its fast and relevant. If you need an awesome blog search, you've come to the right place.

The major features of wpSearchMu are:

    * Each blog can setup wpSearchMu for /just/ their blog's search
    * Index and search all/some/one of the WordPress Mu blogs
    * Unmatched and customizable search relevancy (thats the power of Lucene!)
    * Very fast search speed
    * Wildcard and Boolean operator support
    * Easy installation
    * Instantly updated searching after a post has been written
    * Searching of Posts and Pages
	* Integration into any blog
	* No javascript / css includes
	* Optimized integration into the Wordpress search mechanism
	* Searching of comments
	* Foreign character support

Features for advanced users:

    * Access to an internal search service for extendability
	
Requirements:

	* Wordpress Mu version 2.2.x or higher.
	* PHP 5 or higher (PHP 4.4.8 will throw parsing errors)
	* The iconv library (Usually installed/enabled on a server by default)

== Installation ==

If you are upgrading from a previous version, it is recommended that you rebuild your index after the upgrade.

	* Copy the wp-search-mu folder to the Wordpress mu-plugins directory (if one does not exist, create a directory called "mu-plugins" in the wp-content directory)
	* Copy the wp-search-mu.php file to the mu-plugins directory
	* Set permissions of the wp-search-mu directory to 777 (very important!)
	* Go to Settings-->wpSearchMu Options, and check "Build Search Index" and optionally "Enable Comment Searching"
	* Click "Save Changes" and wait until the page reloads (this can take a while depending on the number of posts in your blog)
	* Go to your blog's search box and search. Do the results look better? Cool!
        * Small edits are needed for your theme's search.php file. read to http://welcome.totheinter.net/wpsearchmu/ for details

Only public blogs are searchable. private, spam, mature, archived, and deleted
blogs do not show up in the search results.
	
	Did you have trouble installing? Let me know at http://welcome.totheinter.net/wpsearchmu/
	
See http://welcome.totheinter.net/wpsearchmu/ for the latest release information and documentation.

Note: Don't forget the bit about changing folder permissions and theme changes!

== Screenshots ==

For "Before wpSearchMu" / "After wpSearchMu" Screenshots,
Visit http://welcome.totheinter.net/wpsearchmu/

== Frequently Asked Questions ==

1. Q: I get an error that says something like:

Parse error: syntax error, unexpected T_STRING, expecting '{' in /homepages/18/d179583305/htdocs/MLL-site/book/wp-content/plugins/wpsearch/Zend/Search/Lucene.php on line 82

What's going on?

1. A: This means you are using PHP 4.x.x or lower. The search library underneath wpSearch needs PHP 5 or higher to run.

2. Q: Where do I send bug reports to?

2. A: wpSearch is still young, so there may be a few unforeseen issues when wpSearch makes its appearance in a world of different WP versions, PHP versions, etc.
Send all bug reports to katzgrau@gmail.com .

== Known Conflictions ==

wpSearchMu is known to react with certain plugins which manipulate search results. 
wpSearchMu must be installed /instead of/ wpSearch. Both plugins cannot be installed on the same blog.
These plugins most likely expect the default Wordpress search to in use, which it is not. 
The following plugins are known to cause issues:

* Custom Query String ( CQS )
* Headspace2

Send all bug reports to katzgrau@gmail.com .
