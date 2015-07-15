Description
============
That is a tool for importing database in PKPOJS to OJS which developed by OKUL BİLİŞİM.

Installation
============

Step 1: Download the Bundle
---------------------------

Open a command console, enter your project directory and execute the
following command to download the latest stable version of this bundle:

```bash
$ composer require okulbilisim/ojs-tools-bundle "dev-master"
```

This command requires you to have Composer installed globally, as explained
in the [installation chapter](https://getcomposer.org/doc/00-intro.md)
of the Composer documentation.

Step 2: Enable the Bundle
-------------------------

Then, enable the bundle by adding it to the list of registered bundles
in the `app/AppKernel.php` file of your project:

```php
<?php
// app/AppKernel.php

// ...
class AppKernel extends Kernel
{
    public function registerBundles()
    {
        $bundles = array(
            // ...

            new Okulbilisim\OjsToolsBundle\OkulbilisimOjsToolsBundle(),
        );

        // ...
    }

    // ...
}
```

Usage
======

You have 4 different command for usage.

#### 1- Install data command

This command is used for importing specific institution data to the database.For example, you can import institution data and 
subjects, that prepared for OJS in Yaml format, to the database.This data are in DataFixtures/ojs folder.


```bash

php app/console ojs:install:data ojs

```

Above command, save Ojs data to database.


#### 2- Import journal command

This command is transfer a journal specified by id, from old database to new ojs database. Its include all journal 
related contents like articles, issues, users and others. It is required 3 parameter. First one is old journal id, 
second one is mysql connection string for old database. Third one is pkp/ojs base_domain for download file url.

```bash

php app/console ojs:import:journal 1007 root:root@localhost/pkpojs http://journal.pkp-ojs.org

```

Above command, imports 1007 ID journal from PKP database to Ojs database.

#### 3- Download waiting files

Import journal command cant download any files. Instead of downloading files, write a mongo documents named as waiting_files.
You must run this command for saving all journal related files (like images, issue files, article fulltexts). 
It is need  any parameter. Just run and wait for downloading.

```bash

php app/console ojs:waiting_files:download

```

Above command saves all files under web/uploads directory.

#### 4- Import article statistics

2 command for this action.
 
First one is ojs:import:article_stats as SymfonyConsoleCommand. It is run slowly. Only needs
old database connection string parameter like root:root@localhost/dpstats .

Second one is written as pure php. It is most performed than SymfonyConsoleCommand. It is need 5 parameter. 

 - Journal ID
 - New database connection string for search journal and articles
 - Old database connection string for get statistics
 - MongoDB document name for write stats data
 - MongoDB host address for access new document

```bash

php dpstastImporter.php $id root:root@127.0.0.1/ojs root:root@127.0.0.1/statistics ojs 127.0.0.1

```

Above command find old statistics and write new mongoDb documents for all articles. Its included all single and total 
counts.
