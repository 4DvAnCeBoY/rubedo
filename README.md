Rubedo
======

*Warning* : This branch is not suitable for production or test and may content some problems, like authentication broken...

An open source PHP CMS, based on Zend Framework &amp; MongoDB : http://www.rubedo-project.org/

Copyright (c) 2013, WebTales (http://www.webtales.fr/).
All rights reserved.
licensing@webtales.fr

Open Source License
------------------------------------------------------------------------------------------
Rubedo is licensed under the terms of the Open Source GPL 3.0 license. 

http://www.gnu.org/licenses/gpl.html


Installation
------------------------------------------------------------------------------------------
### PreRequisites
* A full PHP 5.3.6+ stack (i.e. http://www.zend.com/products/server/)
* MongoDB (http://www.mongodb.org) 2.4.x (issues reported on 2.6, will be fixed in the next version)
* PHP MongoDB Driver >= 1.3.0
* intl PHP extension (http://www.php.net/manual/intro.intl.php) which you should use anyway
* ElasticSearch (http://www.elasticsearch.org), latest version compatible with Elastica PHP client, 1.2.1 at this moment (https://github.com/ruflin/Elastica/)
* Mapper Attachments Type for ElasticSearch (https://github.com/elasticsearch/elasticsearch-mapper-attachments) 
* ICU Analysis plugin for ElasticSearch (https://github.com/elasticsearch/elasticsearch-analysis-icu)

### Already packaged Rubedo
* Prebuilt releases of Rubedo are available on releases page (https://github.com/WebTales/rubedo/releases)
* Install preRequisites (Apache,PHP,DB,Search Engine)
* Define a simple vHost with the *public* directory as documentRoot
* Add an AllowOverride All on this documentRoot
* Access the documentRoot URL automatically run the config wizard

### From Source Install Steps
* Download Source from gitHub (https://github.com/WebTales/rubedo/tags)
* Extract them on your server
* Define a simple vHost with the *public* directory as documentRoot
* Add an AllowOverride All on this documentRoot
* If on Unix server : Inside project root, run `./rubedo.sh`
* If on Windows server : Inside project root, run `rubedo`
* Access the documentRoot URL automatically run the config wizard

### For Developpers
* You'll need Git!
* Clone form gitHub to your server `git clone git://github.com/WebTales/rubedo.git`
* Inside project root, choose the branch you want to use (current or next) : `git checkout next`
* Do as in normal install process



Setting Up Your VHOST
------------------------------------------------------------------------------------------
The following is a sample VHOST you might want to consider for your project.

	<VirtualHost *:80>
	   DocumentRoot "path_to_project/rubedo/public"
	   ServerName rubedo.local
	
	   <Directory "path_to_project/rubedo/public">
	       Options -Indexes FollowSymLinks
	       AllowOverride All
	       Order allow,deny
	       Allow from all
	   </Directory>
	
	</VirtualHost>

