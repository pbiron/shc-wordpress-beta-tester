# SHC Wordpress Beta/RC Tester #

**Contributors:** [pbiron](https://profiles.wordpress.org/pbiron)  
**Tags:** beta, advanced, testing  
**Requires at least:** 5.2.4  
**Tested up to:** 5.3-RC1  
**Stable tag:** 0.1.0  
**License:** GPL-2.0-or-later  
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html  

Limit core updates to next Beta/RC release if currently running a Beta/RC release

## Description ##

The WP Core Update API allows a site to update to nightlies from either
trunk or the latest branch (i.e., minor version).  The
[WordPress Beta Tester](https://wordpress.org/plugins/wordpress-beta-tester/)
plugin allows a site admin to control which of those nightlies will be updated
to.

However, that API does not allow updating to, what I call, "named" beta/RC releases
(e.g., 5.3-beta2, 5.3-RC1, etc; as opposed to a nightly build, e.g., 5.3-beta2-[commit #]).
Once I install a beta/RC release on a site, I would rather that site not be updated
(either via auto-updates or manually) to nightlies.  This plugin accomplishes that.

It would be great if the WP Core Update API had 'beta/RC' offers so that one
could use an updated version of the `WordPress Beta Tester` plugin to acheive this,
but until that happens (which isn't likely), this plugin is useful to me.

This plugin **can** be used in conjuction with `WordPress Beta Tester`.  If both
plugins are active and the site is running a "named" beta/RC version, `WordPress Beta Tester`
will **not** automatically update it to the nightlies;  if both are active and
the site is running one of the nightlies, then `WordPress Beta Tester` will continue
to update the site to the nightlies.

## Developer Notes ##

Here are some initial thoughts about: 1) how the Core Update API could be modified
to support this functionality natively; and 2) how this functionality could be incorporated
in the `WordPress Beta Tester` plugin.

### Core Update API ###

Ideally, the Core Update API should include beta/RC packages in its offers, in addition to the
current `development` offer, or possibly to accept an argument to its queries to tell it to
send the beta/RC package URL as the `development` offer.  Since that API is not open sourced,
it's impossible to say how easy that would be.

### WordPress Beta Tester Plugin ###

My first thought is that `WordPress Beta Tester` could add a "Beta/RC" setting in addition to
the existing `Point release nightlies` and `Bleeding edge nightlies`.  When that setting were selected
then functionality similar to that provided by this plugin would kick in and updates to core would
only happen when the next beta/RC package becomes available.  I'm not sure how many others have
the same needs as I do and would want that.

Another idea is that `WordPress Beta Tester` could always include the relevant beta/RC packages
(if they exist) as the `development` offer.  This _might_ help make it easier for more people
to test these packages as they are built but before they are officially announced.  For example,
on the day that beta/RC packages are built, folks gather in the [#core](https://wordpress.slack.com/messages/C02RQBWTW)
Slack channel and once the packages are built those present are asked to test the packages to make
sure they were built correctly.  The vast majority of that testing happens by those present using
[wp-cli](https://wp-cli.org/) and not everyone who potentially might want to help test the packages
has easy access to `wp-cli`.  If folks could just go to the "WordPress Updates" (`/wp-admin/update-core.php`)
screen and click the "Update Now" and get the newly built beta/RC package that _might_ increase the
number of people who are able to test the packages before they are announced.  Demonstrating that
less technical people could help test the beta/RC builds might just be the kicker that is needed
to get the folks who control the Core Update API to add beta/RC packages to its responses.

For that idea to be incorporated into `WordPress Beta Tester` a few changes to the logic of when
the beta/RC packages are looked for would have to be made.  I think the main thing would be that the
regex used to determine what the "next" beta/RC number is would have to be changed to incorporate
`alpha` versions as well. See the DocBlock for the `SHC\WordPress_Beta_Tester\Plugin` class for
more info on this.
 
## Installation ##

From your WordPress dashboard

1. Get the latest zip from the [GitHub repo](https://github.com/pbiron/shc-wordpress-beta-tester/releases)
2. Go to _Plugins > Add New_ and click on _Upload Plugin_
3. Upload the zip file
4. Activate the plugin (if on multisite, must be network activated)

## Changelog ##

### 0.1.0 ###

* init commit
