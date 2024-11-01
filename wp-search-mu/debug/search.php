<?php 
	require_once 'Zend/Search/Lucene.php'; 
	require_once 'libs/Result.php'; 
?>
<html>
	<head>
		<title>Search Index</title>
	</head>
	<body>

			<form action="search.php" method="GET">
				<input type="text" name="q" />
				<input type="submit" value="Search" />
			</form>
		<hr />
		<p />
		
		<?php
			
			$q = $_GET['q'];
			
			if($q != '')
			{	
				$results = array();
				
				$index = new Zend_Search_Lucene('data/', false);
				$query = Zend_Search_Lucene_Search_QueryParser::parse($q);
				$hits = $index->find($query);
				
				foreach($hits as $hit)
				{
				/*	echo '<p />';
						echo $hit->docId 	. '<br />';
						echo $hit->title 	. '<br />';
						echo $hit->content 	. '<br />';
						echo $hit->tags 	. '<br />';
					echo '<p /><br />';
				*/
				
				$results[] = new Result($hit);
				}
				
				echo json_encode($results);
			}
		?>

		
	</body>
</html>