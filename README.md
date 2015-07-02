OJS Tools Bundle!
======

This bundle for import PKPOjs datas to OkulBilisim/Ojs database. 

## Scope

 - Journals
 - Issues
 - Articles
 - Contacts
 - Subjects
 - Sections
 - Users
 - Authors
 - Roles
 - Pages
 - Article Statistics
 
Installation
------------

 1 - Download ToolsBundle using composer
 
    ```php composer.phar require okulbilisim/ojs-tools-bundle "*"```
 
 2 - Enable the bundle
 
    ```php
    <?php
    // app/AppKernel.php
    
    public function registerBundles()
    {
        $bundles = array(
            // ...
            new Okulbilisim\OjsToolsBundle\OkulbilisimOjsToolsBundle(),
        );
    }
    
    ```
 3 - Enjoy !
 
 
Usage
======

It has 2 console command for import. ```ojs:import:journal journalId database_connection_string``` and ```ojs:import:article_stats database_connection_string``` .

Database connection string example: ```user:password@host/database``` 
 
#### Import Journal

 This command import journal data defined as parameter. 
 
 Usage: 
 
 ```php app/console ojs:import:journal 1071 root:123456@localhost/dergipark```
 
#### Import Article Stats
 
 This command scan all imported articles and import statistic data. 
 
 Usage: 
 
 ```php app/console ojs:import:article_stats root:123456@localhost/dbstats```


Developing and Contributing
------

We'd love to get contributions from you! Please read [contributing to Symfony](https://symfony.com/doc/current/contributing/code/index.html) document

Support
-------

Please log tickets and issues at  [ISSUES](https://github.com/okulbilisim/ojs-tools-bundle/issues) section.
