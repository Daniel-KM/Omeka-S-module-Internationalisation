Internationalisation (module for Omeka S)
=========================================

> __New versions of this module and support for Omeka S version 3.0 and above
> are available on [GitLab], which seems to respect users and privacy better
> than the previous repository.__

[Internationalisation] is a module for [Omeka S] that allows visitors to switch
between sites pages, when the sites are managed by language.

A language switcher is available as a page or resource block, so the user has
only one click to see the translated page.

Any string in any language can be added for translation in the admin board via
the module [Table].


Installation
------------

It is recommended to install the php extension `intl` to localize some strings,
in particular dates. It may or may not be installed by default on your server,
so check the system information in the bottom of the admin board of Omeka.

See general end user documentation for [installing a module].

This module requires the module [Common], that should be installed first.

The module [Table] can be used to add translations in admin board.

The module uses external libraries, so use the release zip to install it, or use
and init the source.

* From the zip

Download the last release [Internationalisation.zip] from the list of releases (the master
does not contain the dependency), and uncompress it in the `modules` directory.

* From the source and for development

If the module was installed from the source, rename the name of the folder of
the module to `Internationalisation`, and go to the root module, and run:

```sh
composer install --no-dev
```

Then install it like any other Omeka module and follow the config instructions.

**Important**: Read below to display all translations for interface and metadata.


Usage
-----

### Tables of translations

To add specific strings to the translator, you need to install the module [Table].

Create a table for each language, setting them as "associative". The locale set
for the table should be a managed one, for example "fr" or "el_GR". Then fill
the text area with translations from American to the locale, for example for a
site in international British English:

```ini
Color = Colour
Internationalization = Internationalisation
License = Licence
Movie = Film
```

The name of the table can be anything. The tables whose name is "translation-xx"
or "translation-xx-yy", for example "translation-fr" or "translation-el-gr", are
automatically included, else include them in main settings and in site settings.

Of course, only strings passed to the function `$translate()` will be translated,
so you need to check all hard coded strings in themes and to translate them to
American inside the theme, then into a specific language in the module part.

Note that the specific translations of the tables override the default
translations of Omeka, for example for vocabularies.

### Translations by site

In Omeka, each site can have one language and only one. The idea of this module
is to manage sites by group, each of them (sites) with a specific language. So
even if you have multiple sites, you can translate them all and keep them
separately by group of sites.

This feature is useful only if the language switcher is added to the theme, that
requires custom change to pages or resources pages (see below).

1. Duplicate a site

It is sometime simpler to start from a clean site instead of to try to fix each
page. When you add a site, an option allow to duplicate another site with all
pages and settings. Furthermore, each page are related together, even if the
site has multiple translations. If the site exists already, you can copy all
pages and settings between sites via the site form in the page "Site info".

2. In site admin board

In the case you didn’t duplicate the site, you have to set the locale setting
for all sites you want to translate. It allows to set the language of all pages.
Furthermore, set the options you want for the display of the values of
internationalised properties of the resources in the section Internationalisation.

3. In main settings

Set the groups of all sites that will be translated. For example if you have a
main site and three exhibits, or four sites for different libraries:

```
my-site-fra, my-site-way, my-site-vie
my-exhibit-fra, my-exhibit-vie
other-exhibit-fra, other-exhibit-vie
fourth-site
```

Here, the first site is available in three languages, the second and third ones
in two languages and the last is not translated (and can be omitted).

4. In site pages

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

### Integration of the module

The language switcher is not added automatically to the theme. So use blocks or
the view helper.

#### Page block and resource block Language Switcher

Simply add the page block or the resource block to your pages and your theme to
display the language switcher.

#### Interface

The view helper can be use too, so put it somewhere in the file `layout.phtml`,
generally in the header:

```php
<?= $this->languageSwitcher() ?>
<?php // Or better, to make the theme more generic and resilient in case of an upgrade: ?>
<?= $this->getHelperPluginManager()->has('languageSwitcher') ? $this->languageSwitcher() : '' ?>
```

The partial `common/helper/language-switcher.phtml` view can be themed: simply
copy it in your theme and customize it. The helper supports options "template"
and "locale_as_code". Other options are passed to the template.

#### Properties

Before the module version 3.3 (Omeka < 3.0), some changes were required in the
core or in the theme. See older readme for them.

### API external requests

#### Translations

To get the list of all the translations of the interface, you can use the module
[Api Info] and go to https://example.org/api/infos/translations?locale=fr.

For a better output (or for people who don’t have a json viewer integrated to
browser), you can add "&pretty_print=1" to the url. For a still better output,
you can use the module [Next] that doesn’t escape unicode characters by default
([omeka/omeka-s#1493]).

Note that Omeka doesn’t separate admin and public strings.

#### Translations of api properties, resource class and template labels

To translate the property labels (for example "Title" for dcterms:title), the
resource class label ("Book" for bibo:Book) and the resource template label, the
client can add `&use_locale=xx_YY` and `&use_template_label=1` to the api
queries. In such way, the api will response for example French "Auteur" for the
property "dcterms:creator" on a template "Book").


TODO
----

- [ ] Return original page when it is not translated in a site, instead of an error (virtual instant mirror page).
- [ ] Add navigation link for the language switcher.
- [ ] Add links for easier browsing between translated pages.
- [x] Add a button to duplicate a site (item pool, pages and navigation, relations).
- [ ] Add a button to duplicate a page or to append blocks of a page to another one.
- [x] Add a button to apply settings of another site (except translatable content).
- [ ] Add automatic selection of the site with the browser language.
- [ ] Manage sites by group instead of sync manually.
- [ ] Add a view to display all the languages that are used.
- [ ] Add a bulk edit to normalize all languages, so fallbacks won't be necessary in  most of the cases.
- [ ] Add a view to manage fallbacks (site settings?).
- [ ] Sort by the translated value.
- [ ] Sort by the translated resource class and template labels.
- [x] Duplicate settings and pages for an existing site.
- [ ] Modify the internal urls with the target site one when copying blocks.
- [ ] Copy of pages: there is no mapping for collecting forms.
- [ ] Simplify the process to manage fallbacks when there is only one fallback (managed by default).


Warning
-------

Use it at your own risk.

It’s always recommended to backup your files and your databases and to check
your archives regularly so you can roll back if needed.


Troubleshooting
---------------

See online issues on the [module issues] page on GitLab.


License
-------

### Module

This module is published under the [CeCILL v2.1] license, compatible with
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

This module was built for [Watau].

* Copyright Daniel Berthereau, 2019-2025 (see [Daniel-KM] on GitLab)
* Copyright BibLibre, 2017 (see [BibLibre] on GitLab), for the switcher

This module provides the same features than the [Omeka Classic] plugins [MultiLanguage]
and [Locale Switcher], adapted for the multi-sites capabilities of Omeka S.


[Internationalisation]: https://gitlab.com/Daniel-KM/Omeka-S-module-Internationalisation
[Omeka S]: https://omeka.org/s
[Common]: https://gitlab.com/Daniel-KM/Omeka-S-module-COmmon
[Internationalisation.zip]: https://gitlab.com/Daniel-KM/Omeka-S-module-Internationalisation/-/releases
[installing a module]: https://omeka.org/s/docs/user-manual/modules/#installing-modules
[`application/src/Api/Representation/AbstractResourceEntityRepresentation.php`]: https://github.com/omeka/omeka-s/blob/v1.4.0/application/src/Api/Representation/AbstractResourceEntityRepresentation.php#L279
[`application/src/Api/Representation/AbstractResourceEntityRepresentation.php` ]: https://github.com/omeka/omeka-s/blob/v1.4.0/application/src/Api/Representation/AbstractResourceEntityRepresentation.php#L489
[#1506]: https://github.com/omeka/omeka-s/pull/1506/files
[omeka/omeka-s#1493]: https://github.com/omeka/omeka-s/pull/1493
[Api Info]: https://gitlab.com/Daniel-KM/Omeka-S-module-ApiInfo
[Next]: https://gitlab.com/Daniel-KM/Omeka-S-module-Next
[module issues]: https://gitlab.com/Daniel-KM/Omeka-S-module-Internationalisation/-/issues
[CeCILL v2.1]: https://www.cecill.info/licences/Licence_CeCILL_V2.1-en.html
[GNU/GPL]: https://www.gnu.org/licenses/gpl-3.0.html
[FSF]: https://www.fsf.org
[OSI]: http://opensource.org
[flag icons]: https://github.com/lipis/flag-icon-css
[Watau]: https://watau.fr
[BibLibre]: https://github.com/BibLibre
[MultiLanguage]: https://github.com/patrickmj/multilanguage
[Locale Switcher]: https://gitlab.com/Daniel-KM/Omeka-plugin-LocaleSwitcher
[Omeka Classic]: https://omeka.org/classic
[GitLab]: https://gitlab.com/Daniel-KM
[Daniel-KM]: https://gitlab.com/Daniel-KM "Daniel Berthereau"
