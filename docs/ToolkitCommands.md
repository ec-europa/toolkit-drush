# Toolkit Commands

These commands are toolkit commands that evaluate the modules on your site
installation.

## Requirements
- Drush 9 >=
- Drupal 8 >=

## Commands

### toolkit:check-modules-authorized-security

This command checks if your modules have been authorised by QA. In case you have
a module with an exception for your project you should provide the command with
your project ID as an argument. This command also checks if any of your modules
have security updates available.

`./vendor/bin/drush toolkit:check-modules-authorized-security myproject-id`

### toolkit:check-modules-minimum-version

This command checks if your modules meet the minimum required version of QA'security
module reviews. In case you have a module with an exception for your project you
should provide the command with your project ID as an argument.

`./vendor/bin/drush toolkit:check-modules-minimum-version myproject-id`

### toolkit:toolkit-check-modules-unused

This command checks your site installation for modules that have been required
by the composer.lock file and have not been installed. It takes two options:
- path: the path in which to check for unused modules
- lockfile: the composer.lock file which to crosreference

`./vendor/bin/drush toolkit:toolkit-check-modules-unused --path=modules/contrib --lockfile=../composer.lock`