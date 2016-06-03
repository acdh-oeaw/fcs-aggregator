# fcs-aggregator

A FCS/SRU aggregator that uses XSL 1.0 stylesheets to present a browseable view or html snippets depending on some parameter.
It's aggregation capabilities are limited at the moment to the switching idea: It serves as a single endpoint that then fetches
results from backends depending on some parameter (x-context).

## Setting up an FCS/SRU aggregator/switch to render XML to HTML

* This project has to be in the same directory as [utils-php](https://github.com/acdh-oeaw/utils-php).
This component also has dependencies.
* This project needs our [XSL library](https://github.com/simar0at/cs-xsl) (right now that special variety of it but it should be merged with [this one](https://github.com/acdh-oeaw/cs-xsl))
* For the [common configuration file](https://github.com/acdh-oeaw/utils-php/blob/master/config.php.dist)
see that project. A template for specifying the DB credentials is part of that file.
* There is also a specific [XML configuration file](https://github.com/acdh-oeaw/fcs-aggregator/blob/master/switch.config.dist). It contains all the backend endpoints and their specific configuratiion.

## Classes

[One giant class](https://github.com/acdh-oeaw/fcs-aggregator/blob/master/switch.php) that does it all:
* Either just return (print) the result and send the header or
* return the result as an object that can be further manipulated
* Handles all formats like html (snippets), htmlbootstrap, htmlpagetable, json and xml

## Helper scripts

The other scripts in here are mostly only run once and provide very little functionality. Most of it is obsolete.

## Part of corpus_shell

Depends on [vLIB](https://github.com/acdh-oeaw/vLIB) and [utils-php](https://github.com/acdh-oeaw/utils-php). See the umbrella project [corpus_shell](https://github.com/acdh-oeaw/corpus_shell). In the default setup communicates with the [mysqlonsru scripts](https://github.com/acdh-oeaw/mysqlonsru). Can also talk to other endpoints like our customized NoSkE or [cr-xq-mets](https://github.com/acdh-oeaw/cr-xq-mets) fcs module.

## More docs

* [TODO](https://github.com/acdh-oeaw/mysqlonsru/blob/master/docs/TODO.md)
* [Design](https://github.com/acdh-oeaw/mysqlonsru/blob/master/docs/Design.md): A document about design decissions.
