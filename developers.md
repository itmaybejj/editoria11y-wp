
1. Run composer install
2. Add Wordpress wpcs to your phpcs path to add WordPress code validation:
   First get the existing list: 
   `~/.composer/vendor/bin/phpcs --config-show`
   Then add your shiny new path to the list.
   `~/.composer/vendor/bin/phpcs --config-set installed_paths 'THE-OLD-LIST,~/PATH/TO/YOUR/wpcs'`
   Check to see Wordpress standard is available using `~/.composer/vendor/bin/phpcs -i--`
3. Set VSCode to use the WordPress or WordPress-Core standard (toggle between them to check if it's working...):
   `    "phpsab.standard": "WordPress",`

