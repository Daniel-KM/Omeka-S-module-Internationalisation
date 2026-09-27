Internationalisation (module for Omeka S)
=========================================

> __New versions of this module and support for Omeka S version 3.0 and above
> are available on [GitLab], which seems to respect users and privacy better
> than the previous repository.__

[Internationalisation] is a module for [Omeka S] to manage translations of the
public interface and any strings in themes. It allows to switch between sites
pages and resource pages directly when the sites are managed by language.

A language switcher is available as a page or resource block, so the user has
only one click to see the translated page. It can be added manually to the theme
too.

A quick and simple interface can be used to add new strings and translations, in
any language. The languages in themes are automatically included too.

This module is designed to translate short strings for sites and admin board
when the translations are in American English, the default language or Omeka.

To translate the records of the resources automatically, you can use the module
[Translate].


Installation
------------

It is recommended to install the php extension `intl` to localize some strings,
in particular numbers and dates. It may or may not be installed by default on
your server, so check the system information in the bottom of the admin board of
Omeka. A warning is added in the config of the module too.

See general end user documentation for [installing a module].

This module requires the module [Common], that should be installed first.

To manage the site settings and theme settings of grouped sites more easily, it
is recommended to install the module [Site Hub], that propagates settings
between the sites of a group, so they can be edited once for the whole group.

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

The module allows to:
- add translations not provided by omeka, modules or themes;
- switch between sites directly to the right page or resource;
- get translations from the api.

### Add translations

Ideally, the translations should be managed by each module. So if you translate
a module, it is recommended to do a pull request to the maintainer of the module
to integrate it directly in the module, so all users will have a translated
module.

#### Translations managed by the module

To add specific strings to the translator, you need to go to the main menu
"Translations", where you can add any needed language.

To add a new language, set its normalized name according to [BCP 47] and many
subsequents standards, for example "fr" for international French or "el-gr"
greek as spoken in Greece. Then fill the text area with translations from
American English, that is the default in Omeka S, to the locale, for example for
a site in international British English:

```ini
Color = Colour
Internationalization = Internationalisation
License = Licence
Movie = Film
```

Of course, only strings passed to the function `$translate()` will be translated,
so you need to check all hard coded strings in themes and to translate them to
American English inside the theme, then into a specific language in the module
part.

Note that the specific translations of the modules override the default
translations of Omeka, for example for vocabularies.

**Warning**: The translations stored by this module are a single
"string => translation" pair, so they cannot express plural forms nor contexts.
For these strings, add a gettext file in the directory "files/language/", named
with the locale, for example "fr.mo" (see below).

#### Translations via a gettext file

The table above cannot store the plural forms (`translatePlural()` / ngettext)
nor the contexts (`msgctxt`), that are the strings translated differently
according to where they are displayed. So the files "*.mo" of the directory
"files/language/" are loaded too. They are named with the locale, like "fr.mo"
or "fr_FR.mo", and they are prepared with a tool like poedit.

Unlike the files "*.php" of the same directory, that are automatically generated
from the table and replaced on each save, the files "*.mo" are never modified by
the module.

A string translated in the table wins over the same string translated in a file,
so a translation managed in the admin interface is always the one displayed.

The contexts are loaded, but laminas has no api for them, so use the view helper
of the module (see the conventions for the gender and the cases in the chapter
Development below):

```php
// Displays the translation of "Home" for the context "menu".
echo $this->translateContext('Home', 'menu');
```

When the pair message/context has no translation, the message alone is
translated, and when it has none either, the message is returned as is.

**Note**: Previous versions could delegate complex cases (a string translated
differently between sites) to the module [Table]. This support was removed: all
translations are now managed by this module. On upgrade, existing translation
tables (slug starting with "translation") are copied automatically into the
translations page.

#### Translations via the theme

Translations can be added in the directory "/language/" of the theme. The files
should be named with the locale. Two formats as supported: po/mo. For po/mo, use
the same tools than omeka to edit them. For php, use a simple text editor and
include an array to return, with strings as keys and the translations as values.

Since Omeka S v4, the directory language/ is automatically managed when the file
config/theme.ini contains "has_translations = true".

**Warning**: Theme translation files are loaded only during a site request (when
the public site and its theme are prepared). A string translated *only* in the
theme therefore falls back to the source language (usually English) whenever a
view is rendered outside that context: AJAX fragments, api endpoints (for
example the guest dialog), or any non-site request. This produces mixed pages
where the core and module strings (loaded globally) are translated while the
theme-only strings are not. For robustness, translate such strings in the
translation table (loaded globally for every request, see above) rather than
only in the theme.

### Translations by site

#### Preparation of linked sites

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

**Important**: set the locale for **every** site, including the one with the
default language of the install, that is English by default. So the locale of
the English site should be `en`. The source strings in Omeka, modules and themes
are written in American English, but when a site locale is left empty the
interface strings fall back to the **global default locale** (main settings).
So an English site with an empty locale may display strings translated into
another language (for example "Accueil" instead of "Home"). Setting the locale
to `en` also ensures that strings whose source is *not* English (some modules or
themes, or when coded strings are used) are translated to English when an
`en`/`en_US` translation exists.

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

When the page does not exist yet in the other sites, it can be created from the
list of the pages of the site: the action `Copy` opens a sidebar with a selector
of the site groups and of the sites. The page is then copied into the selected
sites with all its blocks and related to the original page as a translation.

The current site is the first choice of the selector, apart from the groups: the
page is then simply duplicated inside it, with a number appended to its slug. A
duplicate is not a translation, so it is not related to the original page.

For the other sites, a site that has already a translation of this page is
skipped, and the slug is suffixed too when it is used already in the target
site. Selecting a group copies the page into all the sites of the group at once,
and any site can be selected individually, so a single page can be translated
without preparing a group first.

It is the equivalent of the duplication of a site, but for a single page, so a
new page can be added to a group of sites that is already translated. When the
module [Translator] is installed and configured, the copies can be translated in
the background, following the pairs of languages set in its settings.

Note: the copy is not added to the navigation of the target sites, that stays
managed manually.

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

5. Translation of the pages

With the module [Translator], a second action `Translate` is available in the
list of the pages. It opens a sidebar with one checkbox by site, all checked:

- the site of the page itself translates it in place, into the locale of that
  site. It is used for a page that was never translated, or that is written in
  another language than the one of its site. The language of the page can be
  selected, or detected by the translation service.
- each other site updates the page it has related to this one, following the
  pairs of languages set in the settings of the module Translator.

A block is translated again only when the original text changed, so the
corrections made by a user are kept. A page written in the language of its own
site is not modified.

The copies are translated automatically when a page is created or saved, so
this action is mainly used to translate them again, or to translate a page that
is not a copy.

#### Integration of the language switcher

The language switcher is not added automatically to the theme. So use blocks or
the view helper.

##### Page block and resource block Language Switcher

Simply add the page block or the resource block to your pages and your theme to
display the language switcher.

##### Theme

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

##### Properties

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


Development
-----------

### Contexts for gender, case and other criteria

A gettext file has only two axes: the number, through the plural rule, and the
context, that is a free string. There is no native support of the gender, of the
grammatical cases, nor of the ordinals. In particular, the plural rule takes a
single integer, so it cannot select a form according to anything else: it fits
the complex systems, like the three forms of Polish or the six ones of Arabic,
but only as a function of the count.

So the other criteria are managed by convention, with the context. The
recommended convention is a criterion and a value, separated by ":", so the
contexts stay readable and easy to grep:

```po
msgctxt "gender:female"
msgid "%s left"
msgstr "%s est partie"

msgctxt "gender:male"
msgid "%s left"
msgstr "%s est parti"

msgctxt "case:genitive"
msgid "%d file"
msgid_plural "%d files"
msgstr[0] "%d pliku"
msgstr[1] "%d plików"
msgstr[2] "%d plików"
```

The caller builds the context itself:

```php
echo $this->translateContext('%s left', 'gender:' . $gender);
```

Three points to keep in mind:

- Nothing is automatic: unlike the plural forms, gettext never selects a
  context. It is the code that decides which one to ask.
- Use the same criteria and the same values across all the strings of a module
  or of a theme, else the translators cannot guess them.
- A missing context is not an error: `translateContext()` falls back to the
  message translated without context, then to the message itself, so a partial
  set of contexts still displays something.

The format that supports these criteria natively is ICU MessageFormat, available
in php through the extension `intl`, with its keywords `select` and
`selectordinal`. Neither Omeka nor laminas use it to translate the interface, so
it is not an option here.


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
- [ ] Add a bulk edit to normalize all languages, so fallbacks won't be necessary in  most of the cases. For now, use Bulk Edit.
- [ ] Support plural forms and contexts in the translation table (currently a single "string => translation" pair only; they are supported via a gettext file in "files/language/"). No loader has to be changed: the php array files support them already, with the key "" for the plural rule, an array of forms as a value, and the context prefixed to the string and separated by "\x04". So the work is to add a column for the context and a storage for the forms in the table, to manage them in the form, and to output them in the generated files.
- [ ] Add a view to manage fallbacks (site settings?).
- [ ] Sort by the translated value.
- [ ] Sort by the translated resource class and template labels.
- [x] Duplicate settings and pages for an existing site.
- [ ] Modify the internal urls with the target site one when copying blocks.
- [ ] Copy of pages: there is no mapping for collecting forms.
- [ ] Simplify the process to manage fallbacks when there is only one fallback (managed by default).
- [ ] A cache is probably useless, even simple to implement (via key in module.config.php translator/cache/adapter), because files are cached.


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

This module was built initialy for [Watau]. Next features were added for various
digital libraries, in particular the [Curiothèque] of the [Musée Curie].

* Copyright Daniel Berthereau, 2019-2026 (see [Daniel-KM] on GitLab)
* Copyright BibLibre, 2017 (see [BibLibre] on GitLab), for the switcher

This module provides the same features than the [Omeka Classic] plugins [MultiLanguage]
and [Locale Switcher], adapted for the multi-sites capabilities of Omeka S.


[Internationalisation]: https://gitlab.com/Daniel-KM/Omeka-S-module-Internationalisation
[Omeka S]: https://omeka.org/s
[Translator]: https://gitlab.com/Daniel-KM/Omeka-S-module-Translator
[Common]: https://gitlab.com/Daniel-KM/Omeka-S-module-Common
[Translate]: https://gitlab.com/Daniel-KM/Omeka-S-module-Translate
[Table]: https://gitlab.com/Daniel-KM/Omeka-S-module-Table
[Site Hub]: https://gitlab.com/Daniel-KM/Omeka-S-module-SiteHub
[Internationalisation.zip]: https://gitlab.com/Daniel-KM/Omeka-S-module-Internationalisation/-/releases
[BCP 47]: https://en.wikipedia.org/wiki/IETF_language_tag
[installing a module]: https://omeka.org/s/docs/user-manual/modules/#installing-modules
[`application/src/Api/Representation/AbstractResourceEntityRepresentation.php`]: https://github.com/omeka/omeka-s/blob/v1.4.0/application/src/Api/Representation/AbstractResourceEntityRepresentation.php#L279
[`application/src/Api/Representation/AbstractResourceEntityRepresentation.php` ]: https://github.com/omeka/omeka-s/blob/v1.4.0/application/src/Api/Representation/AbstractResourceEntityRepresentation.php#L489
[#1506]: https://github.com/omeka/omeka-s/pull/1506/files
[omeka/omeka-s#1493]: https://github.com/omeka/omeka-s/pull/1493
[Api Info]: https://gitlab.com/Daniel-KM/Omeka-S-module-ApiInfo
[Next]: https://gitlab.com/Daniel-KM/Omeka-S-module-Next
[module issues]: https://gitlab.com/Daniel-KM/Omeka-S-module-Internationalisation/-/work_items
[CeCILL v2.1]: https://www.cecill.info/licences/Licence_CeCILL_V2.1-en.html
[GNU/GPL]: https://www.gnu.org/licenses/gpl-3.0.html
[FSF]: https://www.fsf.org
[OSI]: http://opensource.org
[flag icons]: https://github.com/lipis/flag-icon-css
[Watau]: https://watau.fr
[Curiothèque]: https://curiotheque.musee.curie.fr
[Musée Curie]: https://musee.curie.fr
[BibLibre]: https://github.com/BibLibre
[MultiLanguage]: https://github.com/patrickmj/multilanguage
[Locale Switcher]: https://gitlab.com/Daniel-KM/Omeka-plugin-LocaleSwitcher
[Omeka Classic]: https://omeka.org/classic
[GitLab]: https://gitlab.com/Daniel-KM
[Daniel-KM]: https://gitlab.com/Daniel-KM "Daniel Berthereau"
