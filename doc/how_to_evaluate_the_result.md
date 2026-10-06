# Evaluating the Migration Result

Every migration is a compromise. Although this tool tries to be as faithful to
the original representation as possible, there are situations that need a
knowing eye and the attention of the users.

If ever possible the tool will always try to **port all information**, even if
the functionality cannot be restored.

> Due to some fundamental differences between the systems the tool needs to make
> decisions from time to time. [Read about the behavior](./behavior.md) in face
> of these to understand the subtleties of the migration.

## Cleanup Categories

In the case that the tool cannot migrate content or functionality it will create
a category, so you can manually fix issues after the import:

* `Broken_attachment_link`: An attachment was not found
* `Broken_emoticon`: An emoticon/emoji could not be migrated
* `Broken_image`: An image could not be found
* `Broken_image_external_link`: the link of a linked image could not be resolved
* `Broken_image_page_link`: the link to a page of a linked image could not be resolved
* `Broken_link`: a link could not be resolved
* `Broken_page_link`: a link to a page could not be resolved
* `Broken_user_link`: a link to a user profile could not be resolved

You can access a list of all categories on the special wiki page `Special:Categories`.

## Unsupported Macros

The tool converts Confluence macros into MediaWiki functionality. If it encounters an
unknown macro or if it cannot handle a macro for another reason, it does two things:

1. the macro source code is placed as an HTML comment on the page. You can inspect it
    in the editor and decide what to do.
2. the page receives the category `Broken_macro/<macro-name>`. Examining this
    category allows you to quickly find problematic pages.

## Standard MediaWiki Tools

MediaWiki itself comes equipped with tools for checking the consistency of your
content. You can use them to check the migrated result, too.

### Missing Content

Open the special page `Special:ShortPages`. The list on this page is ordered
by page size. Identify pages that seem too short.

You can check specifically for contentful Confluence pages that seem too short
in the wiki.

### Cross-Link Problems

Open the special page `Special:WantedPages`. This is a list of page titles
that are referenced on other pages, but that do not exist.

This view allows you to get a quick overview whether page links were migrated
succesfully.

### Missing Attachments

Open the special page `Special:WantedFiles`. This page lists all file names
that are referenced on wiki pages but are not present in the system.

Check here, if all files were transfered from the export. If an error is
reported, check also the Confluence source. Sometimes the file really does
not exist!

### Unreferenced Attachments

Open the special page `Special:UnusedFiles`. Here you will see a list of all
files uploaded into the wiki that are not referenced on any page.

### Missing Categories and Labels

Open the special page `Special:WantedCategories`. On this page you can check not
only the helper categories that were automatically created, but also any labels
that were migrated. Every Confluence label attached to a page, blog post or
attachment is transfered into a MediaWiki category.

## Not Migrated at the Moment

### User identities

The migration allows to [retain author info](./how_to_add_author_info.md), but the
user accounts themselves are not migrated. They need to be set up by the wiki
admin.

This will presumably never change. The security implications of automatically creating
a large amount of user accounts are out of scope for this tool. (Think user groups,
deleted users, external authentication, ...)

### Some Layouts

A set of page layouts is migrated (and editable with the PageLayouts extension). However,
if your users crafted delicate nested layouts, chances are high that they will not be
migrated fully.

> **Note:** If you haven’t got installed the PageLayouts extension you can mimic its
> effects by copying the [styles from the extension](https://github.com/BlueSpice-Wiki/mediawiki-extensions-PageLayouts/blob/main/resources/styles-view.css)
> into your [Common.css stylesheet](https://www.mediawiki.org/wiki/Manual:CSS).

### Files without Association to a Page or Space

Files in Confluence are always assigned to a page. If the export contains a file that does
not feature such a link, it is unclear, if it is a deleted file, a file from a different
space or something else altogether. If it cannot be associated to the currently migrated
space in other ways as well, the file cannot be meaningfully migrated.