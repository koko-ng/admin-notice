#!/usr/bin/env bash

# Use -vv otherwise it never completes
vendor/bin/phpcbf -vv $@ > /dev/null

status=$?

[ $status -eq 1 ] && exit 0 || exit $status
