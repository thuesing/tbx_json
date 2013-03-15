#!/bin/sh
# git add and commit if index is dirty

DATUM=`date '+%m-%d-%Y-%T'`

if [[ $(git diff --shortstat 2> /dev/null | tail -n1) != "" ]]
then
	#echo "index is dirty" 
	git add -u
    echo "index up" 
	git commit -m "$DATUM" 
	echo "commit done" 
else
	echo "index is clean" 
fi