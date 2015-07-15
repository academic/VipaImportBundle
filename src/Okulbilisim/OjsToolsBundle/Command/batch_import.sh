#!/bin/bash
IDS=journal_ids.csv

contents=()
i=0
while read id
do
    echo "Adding $id"
    php ../app/console ojs:import:journal $id root:root@127.0.0.1/pkpojs
    i=$((i+1))
    contents[$i]=$id
    res=$[$i%10]
    if [ $res  -eq "0" ]; then
        echo "\nwaiting...\n"
        sleep 10
    fi
done < $IDS
