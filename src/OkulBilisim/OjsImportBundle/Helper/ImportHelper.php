<?php

namespace OkulBilisim\OjsImportBundle\Helper;

class ImportHelper
{
    public static function spamUsersFilterSql()
    {
        // Those are some patterns we came across when importing data
        $spamFilters = [
            "AND users.phone != '123456'",
            "AND users.email NOT LIKE '%itregi.com'",
            "AND users.email NOT LIKE '%drupaler.org'",
            "AND users.email NOT LIKE '%brainhard.net'",
        ];

        return implode(" ", $spamFilters);
    }

}