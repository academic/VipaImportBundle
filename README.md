# OjsImportBundle
This is a bundle which helps with importing databases of other journal softwares to a new instance of OJS.

## PKP/OJS

### Importing an user
```
php app/console ojs:import:pkp:user <user_id_from_pkpojs> <db_host> <db_user> <db_pass> <db_name>
```

### Importing a journal
This command will import the given journal and its sections, issues, articles and users who submitted an article. 
```
php app/console ojs:import:pkp:journal <journal_id_from_pkpojs> <db_host> <db_user> <db_pass> <db_name>
```

### Downloading files
After importing your journals, run this to make your files available on OJS.
```
php app/console ojs:import:download <pkpojs_domain_name>
```
