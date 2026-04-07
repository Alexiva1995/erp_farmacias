@echo off
echo SET FOREIGN_KEY_CHECKS=0; > temp_farma.sql
type farma.sql >> temp_farma.sql
echo SET FOREIGN_KEY_CHECKS=1; >> temp_farma.sql
mysql -u root farmaciabs < temp_farma.sql
del temp_farma.sql
