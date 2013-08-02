#!/bin/bash

TSLOT=$((`date +\%s`/1800))

for FNAME in zidstuff.csv pdt.json auth_hash.csv zidmem.csv
do
	/bin/mv /var/log/zperfmon/${FNAME} /var/log/zperfmon/${TSLOT}.${FNAME}
done

for FNAME in zidstuff.csv pdt.json auth_hash.csv zidmem.csv
do
	/usr/bin/bzip2 /var/log/zperfmon/${TSLOT}.${FNAME}
done
