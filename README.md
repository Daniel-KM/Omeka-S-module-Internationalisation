Internationalisation (module for Omeka S)
=========================================

[Internationalisation] is a module for [Omeka S] that allows visitors to switch
between sites pages, when the sites are managed by language.

This module provides the same features than the [Omeka Classic] plugins [MultiLanguage]
and [Locale Switcher], adapted for the multi-sites capabilities of Omeka S.


Installation
------------

This module requires the module [`Generic`] installed first.

The module uses external libraries, so use the release zip to install it, or use
and init the source.

See general end user documentation for [installing a module].

* From the zip

Download the last release [`Internationalisation.zip`] from the list of releases
(the master does not contain the dependency), and uncompress it in the `modules`
directory.

* From the source and for development

If the module was installed from the source, rename the name of the folder of
the module to `Internationalisation`, go to the root of the module, and run:

```
composer install
```


Usage
-----

In Omeka, each site can have one language and only one. The idea of this module
is to manage sites by group, each of them (sites) with a specific language. So
even if you have multiple sites, you can translate them all and keep them
separately by group of sites.

1. In site admin board

Set the locale setting for all sites you want to translate. It allows to set the
language of all pages.

2. In main settings

Set the groups of all sites that will be translated. For example if you have a
main site and three exhibits, or four sites for different libraries:

```
my-site-fra, my-site-way, my-site-rus
my-exhibit-fra, my-exhibit-rus
other-exhibit-fra, other-exhibit-rus
fourth-site
```
Here, the first site is available in three languages, the second and third ones
in two languages and the last is not translated (and can be omitted).

3. In site pages

Make the relations between translated pages in each group of sites. For that
purpose, there is a new field to fill in the site page: the pages that are a
translation of the current page. So select the related pages and translate them.

It’s important to set relations for all pages, else the language switcher will
display a "page doesn’t exist" error if the user browse to it. Furthemore, it is
recommended to use all the same settings, item pools, themes, rights, etc. for
all related sites so the visitor can browse smoothly. A new feature will allow
to process that automatically, but it is not available yet.

In the case where the page is not yet translated, and you want to avoid an
error, you can create a page with the block "Simple Page", and that display the
same content than the specified page. It is useful for pages that are common in
all the sites too (about, terms and conditions…).

Then, in public front-end, the visitor can switch between sites via a flag.

**Important**: the language switcher is not added automatically to the theme. So
add the view helper somewhere in the file `layout.phtml`, generally in the
header:

```php
<?php echo $this->languageSwitcher(); ?>
<?php // Or better, to make the theme more generic and resilient in case of an upgrade: ?>
<?php if ($this->getHelperPluginManager()->has('languageSwitcher')) echo $this->languageSwitcher(); ?>
```

The partial `common/language-switcher.phtml` view can be themed: simply copy it
in your theme and customize it.


TODO
----

- Return original page when it is not translated in a site, instead of an error.
- Add options to manage multilanguage property values (various ways).
- Add links for easier browsing between translated pages.
- Add a button to duplicate a site (item pool, pages and navigation, relations).
- Add a button to apply settings of another site (except language).
- Add automatic setection of the site with the browser language.


Warning
-------

Use it at your own risk.

It’s always recommended to backup your files and your databases and to check
your archives regularly so you can roll back if needed.


Troubleshooting
---------------

See online issues on the [module issues] page on GitHub.


License
-------

### Module

This module is published under the [CeCILL v2.1] licence, compatible with
[GNU/GPL] and approved by [FSF] and [OSI].

This software is governed by the CeCILL license under French law and abiding by
the rules of distribution of free software. You can use, modify and/ or
redistribute the software under the terms of the CeCILL license as circulated by
CEA, CNRS and INRIA at the following URL "http://www.cecill.info".

As a counterpart to the access to the source code and rights to copy, modify and
redistribute granted by the license, users are provided only with a limited
warranty and the software’s author, the holder of the economic rights, and the
successive licensors have only limited liability.

In this respect, the user’s attention is drawn to the risks associated with
loading, using, modifying and/or developing or reproducing the software by the
user in light of its specific status of free software, that may mean that it is
complicated to manipulate, and that also therefore means that it is reserved for
developers and experienced professionals having in-depth computer knowledge.
Users are therefore encouraged to load and test the software’s suitability as
regards their requirements in conditions enabling the security of their systems
and/or data to be ensured and, more generally, to use and operate it in the same
conditions as regards security.

The fact that you are presently reading this means that you have had knowledge
of the CeCILL license and that you accept its terms.

### Libraries

The [flag icons] are released under the MIT license.


Copyright
---------

This module was built for the site [Watau], that will be released soon.

* Copyright Daniel Berthereau, 2019 (see [Daniel-KM] on GitHub)
* Copyright BibLibre, 2017 (see [BibLibre] on Github), for the switcher


[Internationalisation]: https://github.com/Daniel-KM/Omeka-S-module-Internationalisation
[Omeka S]: https://omeka.org/s
[MultiLanguage]: https://github.com/patrickmj/multilanguage
[Locale Switcher]: https://github.com/Daniel-KM/Omeka-plugin-LocaleSwitcher
[Omeka Classic]: https://omeka.org/classic
[`Generic`]: https://github.com/Daniel-KM/Omeka-S-module-Generic
[`Internationalisation.zip`]: https://github.com/Daniel-KM/Omeka-S-module-Internationalisation/releases
[Installing a module]: http://dev.omeka.org/docs/s/user-manual/modules/#installing-modules
[Next]: https://github.com/Daniel-KM/Omeka-S-module-Next
[module issues]: https://github.com/Daniel-KM/Omeka-S-module-Internationalisation/issues
[CeCILL v2.1]: https://www.cecill.info/licences/Licence_CeCILL_V2.1-en.html
[GNU/GPL]: https://www.gnu.org/licenses/gpl-3.0.html
[FSF]: https://www.fsf.org
[OSI]: http://opensource.org
[flag icons]: https://github.com/lipis/flag-icon-css
[Watau]: https://watau.org
[BibLibre]: https://github.com/BibLibre
[Daniel-KM]: https://github.com/Daniel-KM "Daniel Berthereau"
