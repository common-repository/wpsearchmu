
rm -rf wp-search-mu
rm wp-search-mu.zip

mkdir wp-search-mu
cp -r StandardAnalyzer wp-search-mu/
cp -r Zend wp-search-mu/
cp -r debug wp-search-mu/
cp -r libs wp-search-mu/
cp -r readme.txt wp-search-mu/
cp -r wpSearch.php wp-search-mu/
cp -r wpSearchService.php wp-search-mu/

cd wp-search-mu
find ./ -name ".svn" | xargs rm -Rf
cd ..

zip -r wp-search-mu wp-search-mu/* wp-search-mu.php

rm -rf wp-search-mu
