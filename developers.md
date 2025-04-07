# Push
Don't forget to pull the latest library, using scripts/get.sh
Don't forget to do a global search for the old version number.

# Setup
1. Run composer install
2. Add WordPress wpcs to your phpcs path to add WordPress code validation:
   First get the existing list: 
   `~/.composer/vendor/bin/phpcs --config-show`
   Then add your shiny new path to the list.
   `~/.composer/vendor/bin/phpcs --config-set installed_paths 'THE-OLD-LIST,~/PATH/TO/YOUR/wpcs'`
   Check to see WordPress standard is available using `~/.composer/vendor/bin/phpcs -i--`
3. Set VSCode to use the WordPress or WordPress-Core standard (toggle between them to check if it's working...):
   `    "phpsab.standard": "WordPress",`
4. Enable logging to file -- add to wp-config `define( 'WP_DEBUG_LOG', ABSPATH . 'wp-errors.log' );`


For reference, My .vscode/settings.json:
{
    "phpsab.executablePathCBF": "/Users/jjameson/Sites/tooling/wpcs/vendor/bin/phpcbf",
    "phpsab.executablePathCS": "/Users/jjameson/Sites/tooling/wpcs/vendor/bin/phpcs",
    "phpcbf.standard": "WordPress-Core",
    "phpsab.standard": "WordPress-Core",
    "phpsab.allowedAutoRulesets": [
        ".phpcs.xml",
        ".phpcs.xml.dist",
        "phpcs.xml",
        "phpcs.xml.dist",
        "phpcs.ruleset.xml",
        "ruleset.xml"
    ],
    "editor.tabSize": 4,
    "editor.insertSpaces": false,
    "editor.detectIndentation": false,
	"files.eol": "\n"
}
