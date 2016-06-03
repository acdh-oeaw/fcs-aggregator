# Things that need to be done to make this project future proof

* Split up the one giant class
* This component should be described using [composer](https://getcomposer.org) and then imported using it.
* This component should use [composer](https://getcomposer.org) to fetch its dependencies
* The JavaScript and CSS dependencies should be specified using [bower](http://bower.io/) and
a JS and/or CSS bundle build using [grunt](http://gruntjs.com/)
* This component relies on global variables for configuration. They should be replaced with something better suited
* There should be PHPUnit tests
* The switch should actually understand CQL (Context Query Language). There was a
[PHP parser for CQL](https://github.com/simar0at/sru-cql-parser) this which could be modernized. That parser is GPL licensed
* The people that worked on a particular entry or on entries displayed should be visible in the result by full name.
