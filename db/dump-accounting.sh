#!/bin/bash
# Simple command to dump the database structure to a file
mysqldump --no-data accounting -u root -p -r accounting-structure.sql

